<?php

declare(strict_types=1);

namespace App\ServiceCenter\AgentSession;

use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\Telephony\Session\Model\TelephonySession;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property AgentSession $agentSession
 * @property AgentSession $agentSessionWithTrashed
 * @property string $status
 * @property ServiceCenterCase|null $serviceCenterCase
 * @property TelephonySession|null $telephonySession
 * @property CarbonInterface $created_at
 *
 * @method static static|Builder|\Illuminate\Database\Query\Builder hasStatus(AgentSessionStatus $status)
 * @see AgentSessionLogEntry::scopeHasStatus()
 * @method AgentSessionLogEntry|Builder|\Illuminate\Database\Query\Builder whereTelephonySession(TelephonySession $telephonySession)
 * @see AgentSessionLogEntry::scopeWhereTelephonySession()
 */
class AgentSessionLogEntry extends Model
{
    use SoftDeletes;

    protected $table = 'sc_agent_session_log';

    public $timestamps = false;

    protected $dates = ['created_at'];

    public static function new(
        AgentSession $agentSession,
        AgentSessionStatus $agentSessionStatus,
        ?ServiceCenterCase $serviceCenterCase,
        ?TelephonySession $telephonySession
    ): self {
        return (new static())
            ->setAgentSession($agentSession)
            ->setStatus($agentSessionStatus)
            ->setServiceCenterCase($serviceCenterCase)
            ->setTelephonySession($telephonySession)
            ->setCreatedAt(Carbon::now());
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @internal
     */
    public function agentSession(): BelongsTo
    {
        return $this->belongsTo(AgentSession::class);
    }

    /**
     * @internal
     */
    public function agentSessionWithTrashed(): BelongsTo
    {
        return $this->agentSession()->withTrashed();
    }

    public function getAgentSession(): AgentSession
    {
        return $this->agentSession;
    }

    public function getAgentSessionWithTrashed(): AgentSession
    {
        return $this->agentSessionWithTrashed;
    }

    private function setAgentSession(AgentSession $agentSession): self
    {
        $this->agentSession()->associate($agentSession);

        return $this;
    }

    public function getStatus(): AgentSessionStatus
    {
        return new AgentSessionStatus($this->status);
    }

    private function setStatus(AgentSessionStatus $agentSessionStatus): self
    {
        $this->status = $agentSessionStatus->getValue();

        return $this;
    }

    public function setCreatedAt($value): self
    {
        $this->created_at = $value;

        return $this;
    }

    /**
     * @internal
     */
    public function serviceCenterCase(): BelongsTo
    {
        return $this->belongsTo(ServiceCenterCase::class, 'case_id');
    }

    public function getServiceCenterCase(): ?ServiceCenterCase
    {
        return $this->serviceCenterCase;
    }

    private function setServiceCenterCase(?ServiceCenterCase $serviceCenterCase): self
    {
        $this->serviceCenterCase()->associate($serviceCenterCase);

        return $this;
    }

    /**
     * @internal
     */
    public function telephonySession(): BelongsTo
    {
        return $this->belongsTo(TelephonySession::class);
    }

    public function getTelephonySession(): ?TelephonySession
    {
        return $this->telephonySession;
    }

    private function setTelephonySession(?TelephonySession $telephonySession): self
    {
        $this->telephonySession()->associate($telephonySession);

        return $this;
    }

    public function scopeHasStatus(Builder $queryBuilder, AgentSessionStatus $status): Builder
    {
        $queryBuilder->where('status', '=', $status->getValue());

        return $queryBuilder;
    }

    public function scopeWhereTelephonySession(Builder $queryBuilder, TelephonySession $telephonySession): Builder
    {
        $queryBuilder->where('telephony_session_id', '=', $telephonySession->getId());

        return $queryBuilder;
    }
}
