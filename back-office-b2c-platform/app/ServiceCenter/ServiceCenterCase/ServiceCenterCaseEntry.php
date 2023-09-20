<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase;

use App\Auth\User;
use App\ServiceCenter\WorkGroup\WorkGroup;
use App\Telephony\Session\Model\TelephonySession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property ServiceCenterCase $case
 * @property CaseType $caseType
 * @property WorkGroup $workGroup
 * @property TelephonySession|null $telephonySession
 * @property User|null $assignedAgent
 *
 * @method static static|Builder|\Illuminate\Database\Query\Builder hasTelephonySession(TelephonySession $sessionRecord)
 * @see ServiceCenterCaseEntry::scopeHasTelephonySession()
 * @method static static|Builder|\Illuminate\Database\Query\Builder hasAnyTelephonySession()
 * @see ServiceCenterCaseEntry::scopeHasAnyTelephonySession()
 * @method static static|Builder|\Illuminate\Database\Query\Builder hasNoTelephonySession()
 * @see ServiceCenterCaseEntry::scopeHasNoTelephonySession()
 */
class ServiceCenterCaseEntry extends Model
{
    use SoftDeletes;

    protected $table = 'sc_case_entries';

    public static function makeInstance(
        CaseType $caseType,
        WorkGroup $workGroup,
        ?TelephonySession $telephonySession,
        ?User $assignedAgent
    ): self {
        return (new static())
            ->setCaseType($caseType)
            ->setWorkGroup($workGroup)
            ->setTelephonySession($telephonySession)
            ->setAssignedAgent($assignedAgent);
    }

    public function getCaseType(): CaseType
    {
        return $this->caseType;
    }

    private function setCaseType(CaseType $caseType): self
    {
        $this->caseType()->associate($caseType);

        return $this;
    }

    public function getTelephonySession(): ?TelephonySession
    {
        return $this->telephonySession;
    }

    private function setTelephonySession(?TelephonySession $telephonySession): self
    {
        if ($telephonySession === null) {
            $this->telephonySession()->dissociate();
        } else {
            $this->telephonySession()->associate($telephonySession);
        }

        return $this;
    }

    public function getAssignedAgent(): ?User
    {
        return $this->assignedAgent;
    }

    private function setAssignedAgent(?User $user): self
    {
        if (null === $user) {
            $this->assignedAgent()->dissociate();
        } else {
            $this->assignedAgent()->associate($user);
        }

        return $this;
    }

    public function getWorkGroup(): WorkGroup
    {
        return $this->workGroup;
    }

    private function setWorkGroup(WorkGroup $workGroup): self
    {
        $this->workGroup()->associate($workGroup);

        return $this;
    }

    /**
     * @internal
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(ServiceCenterCase::class, 'case_id');
    }

    /**
     * @internal
     */
    public function workGroup(): BelongsTo
    {
        return $this->belongsTo(WorkGroup::class);
    }

    /**
     * @internal
     */
    public function caseType(): BelongsTo
    {
        return $this->belongsTo(CaseType::class);
    }

    /**
     * @internal
     */
    public function telephonySession(): BelongsTo
    {
        return $this->belongsTo(TelephonySession::class);
    }

    /**
     * @internal
     */
    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param static|Builder $queryBuilder
     *
     * @return static|Builder
     */
    public function scopeHasTelephonySession(Builder $queryBuilder, TelephonySession $telephonySession): Builder
    {
        return $queryBuilder->where('telephony_session_id', $telephonySession->getId());
    }

    /**
     * @param static|Builder $queryBuilder
     *
     * @return static|Builder
     */
    public function scopeHasAnyTelephonySession(Builder $queryBuilder): Builder
    {
        return $queryBuilder->whereNotNull('telephony_session_id');
    }

    /**
     * @param static|Builder $queryBuilder
     *
     * @return static|Builder
     */
    public function scopeHasNoTelephonySession(Builder $queryBuilder): Builder
    {
        return $queryBuilder->whereNull('telephony_session_id');
    }

    public function equals(self $other): bool
    {
        return $this->caseType->getId() === $other->caseType->getId()
            && $this->workGroup->getId() === $other->workGroup->getId()
            && (null !== $this->assignedAgent) === (null !== $other->assignedAgent)
            && (
                null === $this->assignedAgent
                || $this->assignedAgent->getId() === $other->assignedAgent->getId()
            )
            && (null === $this->telephonySession) === (null === $other->telephonySession)
            && (
                null === $this->telephonySession
                || $this->telephonySession->is($other->telephonySession)
            );
    }
}
