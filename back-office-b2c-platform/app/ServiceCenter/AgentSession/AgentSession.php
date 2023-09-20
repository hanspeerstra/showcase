<?php

declare(strict_types=1);

namespace App\ServiceCenter\AgentSession;

use App\Auth\Permission;
use App\Auth\User;
use App\InternalPhone\InternalPhone;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\ServiceCenter\WorkGroup\WorkGroup;
use App\Telephony\Session\Model\TelephonySession;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use UnexpectedValueException;

/**
 * @property int $id
 * @property InternalPhone $internalPhone
 * @property User $user
 * @property bool $automatically_assign_case
 * @property int $priority
 * @property WorkGroup[]|Collection $workGroups
 * @property AgentSessionLogEntry $agentSessionLog
 *
 * @method static static|Builder|\Illuminate\Database\Query\Builder availableForInteractiveCase()
 * @see AgentSession::scopeAvailableForInteractiveCase()
 * @method static static|Builder|\Illuminate\Database\Query\Builder availableForPassiveCase()
 * @see AgentSession::scopeAvailableForPassiveCase()
 * @method static static|Builder|\Illuminate\Database\Query\Builder orderByPriority()
 * @see AgentSession::scopeOrderByPriority()
 * @method static static|Builder|\Illuminate\Database\Query\Builder hasStatus(AgentSessionStatus $status)
 * @see AgentSession::scopeHasStatus()
 * @method static static|Builder|\Illuminate\Database\Query\Builder whereUser(User $user)
 * @see AgentSession::scopeWhereUser()
 * @method AgentSession|Builder|\Illuminate\Database\Query\Builder whereTelephonySession(TelephonySession $telephonySession)
 * @see AgentSession::scopeWhereTelephonySession()
 * @method AgentSession|Builder|\Illuminate\Database\Query\Builder whereUserInactiveSince(DateTimeInterface $since)
 * @see AgentSession::scopeWhereUserInactiveSince()
 *
 * @method static static|Builder|\Illuminate\Database\Query\Builder query()
 */
class AgentSession extends Model
{
    use SoftDeletes;

    protected $table = 'sc_agent_sessions';

    protected $casts = [
        'automatically_assign_case' => 'boolean',
    ];

    public static function makeInstance(User $user, InternalPhone $internalPhone, bool $automaticallyAssignCase, ?int $priority): self
    {
        if ($automaticallyAssignCase === true && $priority === null) {
            throw new UnexpectedValueException('Priority must be given when automaticallyAssign cases is on');
        }

        if ($automaticallyAssignCase === false && $priority !== null) {
            throw new UnexpectedValueException('Priority must be null when automaticallyAssign cases is off');
        }

        return (new static())
            ->setUser($user)
            ->setInternalPhone($internalPhone)
            ->setAutomaticallyAssignCase($automaticallyAssignCase)
            ->setPriority($priority);
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @internal
     */
    public function workGroups(): BelongsToMany
    {
        return $this->belongsToMany(
            WorkGroup::class,
            'sc_agent_session_work_groups',
            'agent_session_id',
            'work_group_id',
            'id'
        );
    }

    /**
     * @return WorkGroup[]
     */
    public function getWorkGroups(): iterable
    {
        return iterator_to_array($this->workGroups);
    }

    public function hasWorkGroup(WorkGroup $workGroup): bool
    {
        foreach ($this->getWorkGroups() as $agentWorkGroup) {
            if ($agentWorkGroup->getId() === $workGroup->getId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @internal
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getUser(): User
    {
        return $this->user;
    }

    private function setUser(User $user): self
    {
        $this->user()->associate($user);

        return $this;
    }

    /**
     * @internal
     */
    public function internalPhone(): BelongsTo
    {
        return $this->belongsTo(InternalPhone::class);
    }

    public function getInternalPhone(): InternalPhone
    {
        return $this->internalPhone;
    }

    private function setInternalPhone(InternalPhone $internalPhone): self
    {
        $this->internalPhone()->associate($internalPhone);

        return $this;
    }

    public function isAutomaticallyAssignCase(): bool
    {
        return $this->automatically_assign_case;
    }

    public function setAutomaticallyAssignCase(bool $automaticCall): self
    {
        $this->automatically_assign_case = $automaticCall;

        return $this;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function setPriority(?int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * @internal
     */
    public function agentSessionLog(): HasOne
    {
        return $this->hasOne(AgentSessionLogEntry::class);
    }

    public function getAgentSessionLogEntry(): AgentSessionLogEntry
    {
        return $this->agentSessionLog;
    }

    public function hasActiveTelephonySession(): bool
    {
        $telephonySession = $this->getAgentSessionLogEntry()->getTelephonySession();

        if ($telephonySession !== null) {
            return $telephonySession->isActive();
        }

        return false;
    }

    public function hasActiveCase(): bool
    {
        $handleCase = new AgentSessionStatus(AgentSessionStatus::HANDLE_CASE);

        return $handleCase->equals($this->getAgentSessionLogEntry()->getStatus());
    }

    public function getActiveCase(): ?ServiceCenterCase
    {
        if ($this->hasActiveCase()) {
            return $this->getAgentSessionLogEntry()->getServiceCenterCase();
        }

        return null;
    }

    public function isManager(): bool
    {
        return $this->getUser()->can(Permission::SERVICE_CENTER_MANAGER);
    }

    public function scopeAvailableForPassiveCase(Builder $queryBuilder): Builder
    {
        $queryBuilder->orWhereHas(
            'agentSessionLog',
            /**
             * @param AgentSessionLogEntry|Builder $queryBuilder
             */
            static function (Builder $queryBuilder) {
                $queryBuilder->hasStatus(new AgentSessionStatus(AgentSessionStatus::AWAITING_CASE));
            }
        );

        return $queryBuilder;
    }

    public function scopeAvailableForInteractiveCase(Builder $queryBuilder): Builder
    {
        $queryBuilder->orWhereHas(
            'agentSessionLog',
            /**
             * @param AgentSessionLogEntry|Builder $queryBuilder
             */
            static function (Builder $queryBuilder) {
                $queryBuilder->hasStatus(new AgentSessionStatus(AgentSessionStatus::AWAITING_CASE));
            }
        );

        $queryBuilder->orWhereHas(
            'agentSessionLog',
            /**
             * @param AgentSessionLogEntry|Builder $queryBuilder
             */
            static function (Builder $queryBuilder) {
                $queryBuilder->whereHas(
                    'serviceCenterCase',
                    /**
                     * @param ServiceCenterCase|Builder $queryBuilder
                     */
                    static function (Builder $queryBuilder) {
                        $queryBuilder->isContactMethodElectronic();
                    }
                );
            }
        );

        return $queryBuilder;
    }

    /**
     * @param static|Builder $queryBuilder
     *
     * @return static|Builder
     */
    public function scopeOrderByPriority(Builder $queryBuilder): Builder
    {
        $queryBuilder
            ->orderByRaw('priority IS NULL')
            ->orderBy('priority');

        return $queryBuilder;
    }

    public function scopeHasStatus(Builder $queryBuilder, AgentSessionStatus $status): Builder
    {
        $queryBuilder->whereHas(
            'agentSessionLog',
            /**
             * @param AgentSessionLogEntry|Builder $queryBuilder
             */
            static function (Builder $queryBuilder) use ($status) {
                $queryBuilder->hasStatus($status);
            }
        );

        return $queryBuilder;
    }

    public function scopeWhereUser(Builder $queryBuilder, User $user): Builder
    {
        $queryBuilder
            ->where('user_id', '=', $user->getId());

        return $queryBuilder;
    }

    public function scopeWhereTelephonySession(Builder $queryBuilder, TelephonySession $telephonySession): Builder
    {
        $queryBuilder->whereHas(
            'agentSessionLog',
            /**
             * @param AgentSessionLogEntry|Builder $queryBuilder
             */
            static function (Builder $queryBuilder) use ($telephonySession) {
                $queryBuilder->whereTelephonySession($telephonySession);
            }
        );

        return $queryBuilder;
    }

    public function scopeWhereUserInactiveSince(Builder $queryBuilder, DateTimeInterface $since): Builder
    {
        $queryBuilder->whereHas(
            'user',
            /**
             * @param User|Builder $queryBuilder
             */
            static function (Builder $queryBuilder) use ($since) {
                $queryBuilder->whereNoActivitySince($since);
            }
        );

        return $queryBuilder;
    }
}
