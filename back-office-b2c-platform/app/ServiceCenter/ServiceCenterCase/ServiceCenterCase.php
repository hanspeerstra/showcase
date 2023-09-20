<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase;

use App\Affiliate\PartnerMatchmaker;
use App\Auth\User;
use App\ExternalQuoteRequest\ExternalQuoteRequest;
use App\Leads\LeadSource;
use App\Leads\LeadSourceUtils;
use App\Models\Office\Appointment;
use App\Models\Office\Company;
use App\Models\Office\Contactmethod;
use App\Models\Office\Customer;
use App\Models\Office\Lead;
use App\Models\Office\Profession;
use App\Models\Office\Quote;
use App\Models\Office\Region;
use App\Models\Office\Servicetype;
use App\Models\Office\Site;
use App\Models\Office\Subscription;
use App\Questionnaire\AnswerBag\AnswerBag;
use App\ServiceCenter\AgentSession\AgentSessionLogEntry;
use App\ServiceCenter\CaseQueue\CaseQueueEntry;
use App\ServiceCenter\QuoteFollowUp\QuoteFollowUp;
use App\ServiceCenter\WorkGroup\WorkGroup;
use App\Telephony\Number\CalledNumberInfo;
use App\Telephony\Number\ServicenumberLink;
use App\Telephony\Session\Model\TelephonySession;
use App\Telephony\Tracking\Models\CallTrackingSegment;
use Carbon\CarbonInterface;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * @property int|null $id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $started_at
 * @property CarbonInterface|null $closed_at
 * @property Lead|null $sourceLead
 * @property Appointment|null $sourceAppointment
 * @property TelephonySession|null $sourceTelephonySession
 * @property ServicenumberLink|null $servicenumberLink
 * @property AnswerBag|null $answerBag
 * @property QuoteFollowUp|null $quoteFollowUp
 * @property ExternalQuoteRequest|null $externalQuoteRequest
 * @property ServiceCenterCaseNote[]|Collection $notes
 * @property ServiceCenterCaseGarbageReason|null $garbageReason
 * @property Lead|null $lead
 * @property Appointment|null $appointment
 * @property ServiceCenterCaseEntry $currentCaseEntry
 * @property CaseQueueEntry|null $caseQueueEntry
 * @property AgentSessionLogEntry|null $currentAgentSessionLogEntry
 * @property AgentSessionLogEntry|null $lastAgentSessionLogEntry
 * @property ServiceCenterCaseMatchmakerSearch|null $caseMatchmakerSearch
 *
 * @method static static|Builder|\Illuminate\Database\Query\Builder pausedCases()
 * @see ServiceCenterCase::scopePausedCases()
 * @method static static|Builder|\Illuminate\Database\Query\Builder notClosed()
 * @see ServiceCenterCase::scopeNotClosed()
 * @method static static|Builder|\Illuminate\Database\Query\Builder hasSourceTelephonySession(TelephonySession $sessionRecord)
 * @see ServiceCenterCase::scopeHasSourceTelephonySession()
 * @method static static|Builder|\Illuminate\Database\Query\Builder hasTelephonySession(TelephonySession $telephonySession)
 * @see ServiceCenterCase::scopeHasTelephonySession()
 * @method static static|Builder|\Illuminate\Database\Query\Builder hasAnyTelephonySession()
 * @see ServiceCenterCase::scopeHasAnyTelephonySession()
 * @method static static|Builder|\Illuminate\Database\Query\Builder hasNoTelephonySession()
 * @see ServiceCenterCase::scopeHasNoTelephonySession()
 * @method static static|Builder|\Illuminate\Database\Query\Builder belongsToLead(Lead $lead)
 * @see ServiceCenterCase::scopeBelongsToLead()
 * @method static static|Builder|\Illuminate\Database\Query\Builder isContactMethodTelephony()
 * @see ServiceCenterCase::scopeIsContactMethodTelephony()
 * @method static static|Builder|\Illuminate\Database\Query\Builder isContactMethodElectronic()
 * @see ServiceCenterCase::scopeIsContactMethodElectronic()
 * @method static static|Builder|\Illuminate\Database\Query\Builder assignedCases()
 * @see ServiceCenterCase::scopeAssignedCases()
 * @method static static|Builder|\Illuminate\Database\Query\Builder byStartedAt()
 * @see ServiceCenterCase::scopeByStartedAt()
 * @method static static|Builder|\Illuminate\Database\Query\Builder byCreatedAt()
 * @see ServiceCenterCase::scopeByCreatedAt()
 * @method static static|Builder|\Illuminate\Database\Query\Builder hasAgentAssigned(User $agent)
 * @see ServiceCenterCase::scopeHasAgentAssigned()
 *
 * @method static static|Builder|\Illuminate\Database\Query\Builder query()
 *
 * @mixin Eloquent
 */
class ServiceCenterCase extends Model
{
    use SoftDeletes;

    private const COL_SOURCE_TEL_SESSION_ID = 'source_telephony_session_id';
    public const CONTACT_METHOD_ELECTRONIC = 'electronic';
    public const CONTACT_METHOD_TELEPHONY = 'telephony';

    protected $table = 'sc_cases';

    protected $casts = [
        'is_garbage' => 'boolean',
    ];

    protected $dates = ['started_at', 'closed_at'];

    /** @var ServiceCenterCaseEntry|null */
    private $overrideCurrentCaseEntry;

    public static function makeInstance(
        CaseType $caseType,
        WorkGroup $workGroup,
        ?Lead $sourceLead = null,
        ?Appointment $sourceAppointment = null,
        ?TelephonySession $sourceTelephonySession = null,
        ?ServicenumberLink $servicenumberLink = null,
        ?QuoteFollowUp $quoteFollowUp = null,
        ?ExternalQuoteRequest $externalQuoteRequest = null
    ): self {
        // TODO needs to be enabled when closing case with telephony session works again
//        if (null === ($sourceLead ?? $sourceAppointment ?? $sourceTelephonySession ?? $servicenumberLink ?? $quoteFollowUp)) {
//            throw new InvalidArgumentException('Case must have a source');
//        }

        $case = (new static())
            ->setSourceLead($sourceLead)
            ->setSourceAppointment($sourceAppointment)
            ->setSourceTelephonySession($sourceTelephonySession)
            ->setServicenumberLink($servicenumberLink)
            ->setCaseEntry(
                ServiceCenterCaseEntry::makeInstance($caseType, $workGroup, $sourceTelephonySession, null)
            )
            ->setExternalQuoteRequest($externalQuoteRequest);

        if (null !== $quoteFollowUp) {
            $case = $case->setQuoteFollowUp($quoteFollowUp);
        }

        return $case;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): CarbonInterface
    {
        return $this->created_at;
    }

    public function getStartedAt(): ?CarbonInterface
    {
        return $this->started_at;
    }

    public function setStartedAt(CarbonInterface $startedAt): self
    {
        $this->started_at = $startedAt;

        return $this;
    }

    public function getClosedAt(): ?CarbonInterface
    {
        return $this->closed_at;
    }

    public function setClosedAt(?CarbonInterface $closedAt): self
    {
        $this->closed_at = $closedAt;

        return $this;
    }

    public function getSourceLead(): ?Lead
    {
        return $this->sourceLead;
    }

    private function setSourceLead(?Lead $sourceLead): self
    {
        if (null === $sourceLead) {
            $this->sourceLead()->dissociate();
        } else {
            $this->sourceLead()->associate($sourceLead);
        }

        return $this;
    }

    public function getSourceAppointment(): ?Appointment
    {
        return $this->sourceAppointment;
    }

    public function setSourceAppointment(?Appointment $sourceAppointment): self
    {
        if (null === $sourceAppointment) {
            $this->sourceAppointment()->associate($sourceAppointment);
        } else {
            $this->sourceAppointment()->associate($sourceAppointment);
        }

        return $this;
    }

    private function setSourceTelephonySession(?TelephonySession $sourceTelephonySession): self
    {
        if (null === $sourceTelephonySession) {
            $this->sourceTelephonySession()->dissociate();
        } else {
            $this->sourceTelephonySession()->associate($sourceTelephonySession);
        }

        return $this;
    }

    private function setServicenumberLink(?ServicenumberLink $servicenumberLink): self
    {
        if (null === $servicenumberLink) {
            $this->servicenumberLink()->dissociate();
        } else {
            $this->servicenumberLink()->associate($servicenumberLink);
        }

        return $this;
    }

    public function getQuoteFollowUp(): ?QuoteFollowUp
    {
        return $this->quoteFollowUp;
    }

    private function setQuoteFollowUp(QuoteFollowUp $quoteFollowUp): self
    {
        $this->quoteFollowUp()->associate($quoteFollowUp);

        return $this;
    }

    public function quoteFollowUp(): BelongsTo
    {
        return $this->belongsTo(QuoteFollowUp::class);
    }

    public function getExternalQuoteRequest(): ?ExternalQuoteRequest
    {
        return $this->externalQuoteRequest;
    }

    private function setExternalQuoteRequest(?ExternalQuoteRequest $externalQuoteRequest): self
    {
        if ($externalQuoteRequest !== null) {
            $this->externalQuoteRequest()->associate($externalQuoteRequest);
        } else {
            $this->externalQuoteRequest()->dissociate();
        }

        return $this;
    }

    public function hasExternalQuoteRequest(): bool
    {
        return $this->externalQuoteRequest !== null;
    }

    public function externalQuoteRequest(): BelongsTo
    {
        return $this->belongsTo(ExternalQuoteRequest::class);
    }

    public function getCaseEntry(): ServiceCenterCaseEntry
    {
        return $this->overrideCurrentCaseEntry ?? $this->currentCaseEntry;
    }

    public function setCaseEntry(ServiceCenterCaseEntry $currentCaseEntry): self
    {
        $this->overrideCurrentCaseEntry = $currentCaseEntry;

        return $this;
    }

    /**
     * @internal
     */
    public function caseMatchmakerSearch(): HasOne
    {
        return $this->hasOne(ServiceCenterCaseMatchmakerSearch::class, 'case_id');
    }

    public function getCaseMatchmakerSearch(): ?ServiceCenterCaseMatchmakerSearch
    {
        return $this->caseMatchmakerSearch;
    }

    /**
     * @return ServiceCenterCaseNote[]
     */
    public function getCaseNotes(): iterable
    {
        return iterator_to_array($this->notes);
    }

    public function getGarbageReason(): ?ServiceCenterCaseGarbageReason
    {
        return $this->garbageReason;
    }

    public function setGarbageReason(?ServiceCenterCaseGarbageReason $garbageReason): self
    {
        if (null === $garbageReason) {
            $this->garbageReason()->dissociate();
        } else {
            $this->garbageReason()->associate($garbageReason);
        }

        return $this;
    }

    public function getLead(): ?Lead
    {
        return $this->lead;
    }

    public function setLead(Lead $lead): self
    {
        $this->lead()->associate($lead);

        return $this;
    }

    public function getAppointment(): ?Appointment
    {
        return $this->appointment;
    }

    public function setAppointment(Appointment $appointment): self
    {
        $this->appointment()->associate($appointment);

        return $this;
    }

    public function isClosed(): bool
    {
        return null !== $this->getClosedAt();
    }

    public function isPaused(): bool
    {
        return !$this->isClosed()
            && $this->isAssigned()
            && null === $this->currentAgentSessionLogEntry;
    }

    public function isQueued(): bool
    {
        return !$this->isClosed()
            && !$this->isAssigned()
            && null !== $this->caseQueueEntry;
    }

    public function isAssigned(): bool
    {
        return null !== $this->getCaseEntry()->getAssignedAgent();
    }

    public function whereAssignedAgent(User $agent): bool
    {
        $assignedAgent = $this->currentCaseEntry->getAssignedAgent();

        return $assignedAgent !== null
            && $assignedAgent->getId() === $agent->getId();
    }

    /**
     * @internal
     */
    public function sourceLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @internal
     */
    public function sourceAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * @internal
     */
    public function sourceTelephonySession(): BelongsTo
    {
        return $this->belongsTo(TelephonySession::class, self::COL_SOURCE_TEL_SESSION_ID);
    }

    public function getSourceTelephonySession(): ?TelephonySession
    {
        return $this->sourceTelephonySession;
    }

    public function hasActiveSourceTelephonySession(): bool
    {
        $sourceTelephonySession = $this->getSourceTelephonySession();

        if ($sourceTelephonySession !== null) {
            return $sourceTelephonySession->isActive();
        }

        return false;
    }

    public function getSourceCompany(): ?Company
    {
        $sourceCompany = null;
        if ($this->getCalledNumberInfo() !== null && $this->getCalledNumberInfo()->getEffectiveCompany() !== null) {
            $sourceCompany = $this->getCalledNumberInfo()->getEffectiveCompany();
        } elseif ($this->getSourceSite() !== null && $this->getSourceSite()->getContract() !== null) {
            $sourceCompany = $this->getSourceSite()->getContract()->getCompany();
        } elseif ($this->getSourceLead() !== null) {
            $sourceLead = $this->getSourceLead();

            if ($sourceLead->getCompany() !== null) {
                return $sourceLead->getCompany();
            } elseif ($sourceLead->getSubscription() !== null) {
                return $sourceLead->getSubscription()->getCompany();
            }
        }

        return $sourceCompany;
    }

    public function getSourceSubscription(): ?Subscription
    {
        $site = $this->getSourceSite();

        if ($site === null) {
            return null;
        }

        $company = $this->getSourceCompany();
        if ($site->isMygo() && $company !== null) {
            return $company->getActiveMatchmakerSubscription();
        }
        $contract = $this->getSourceSite()->getContract();
        return $contract === null ? null : $contract->getSubscription();
    }

    /**
     * @internal
     */
    public function servicenumberLink(): BelongsTo
    {
        return $this->belongsTo(ServicenumberLink::class)->withTrashed();
    }

    public function getServicenumberLink(): ?ServicenumberLink
    {
        return $this->servicenumberLink;
    }

    public function getCalledNumberInfo(): ?CalledNumberInfo
    {
        if ($this->servicenumberLink === null || $this->sourceTelephonySession === null) {
            return null;
        }
        return new CalledNumberInfo($this->servicenumberLink, $this->sourceTelephonySession->getCreatedAt());
    }

    public function getPartnerMatchmaker(): ?PartnerMatchmaker
    {
        if ($this->getServicenumberLink() !== null) {
            return $this->getServicenumberLink()->getAffiliatePartnerMatchmaker();
        }

        return null;
    }

    /**
     * @internal
     */
    public function answerBag(): BelongsTo
    {
        return $this->belongsTo(AnswerBag::class);
    }

    public function getAnswerBag(): ?AnswerBag
    {
        return $this->answerBag;
    }

    public function setAnswerBag(AnswerBag $answerBag): self
    {
        $this->answerBag()->associate($answerBag);

        return $this;
    }

    /**
     * @internal
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function garbageReason(): BelongsTo
    {
        return $this->belongsTo(ServiceCenterCaseGarbageReason::class, 'garbage_reason_id');
    }

    /**
     * @internal
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * @internal
     */
    public function currentCaseEntry(): HasOne
    {
        return $this->hasOne(ServiceCenterCaseEntry::class, 'case_id');
    }

    /**
     * @internal
     */
    public function caseQueueEntry(): HasOne
    {
        return $this->hasOne(CaseQueueEntry::class, 'case_id');
    }

    public function agentSessionLogEntry(): HasOne
    {
        return $this->hasOne(AgentSessionLogEntry::class, 'case_id');
    }

    /**
     * Contains soft deleted records; withTrashed()
     *
     * @internal
     */
    public function lastAgentSessionLogEntry()
    {
        return $this->agentSessionLogEntry()
            ->orderByDesc('created_at')
            ->withTrashed();
    }

    public function getCurrentAgentSessionLogEntry(): ?AgentSessionLogEntry
    {
        return $this->currentAgentSessionLogEntry;
    }

    public function getLatestAgentSessionLogEntry(): ?AgentSessionLogEntry
    {
        return $this->lastAgentSessionLogEntry;
    }

    public function getActiveTelephonySession(): ?TelephonySession
    {
        if ($this->getCaseEntry() !== null
            && $this->getCaseEntry()->getTelephonySession() !== null
            && $this->getCaseEntry()->getTelephonySession()->isActive()
        ) {
            return $this->getCaseEntry()->getTelephonySession();
        }

        return null;
    }

    public function hasResult(): bool
    {
        return $this->getLead() !== null
            || $this->getGarbageReason() !== null
            || $this->getAppointment() !== null;
    }

    /**
     * @internal
     */
    public function currentAgentSessionLogEntry(): HasOne
    {
        return $this->hasOne(AgentSessionLogEntry::class, 'case_id');
    }

    /**
     * @internal
     */
    public function notes(): HasMany
    {
        return $this->hasMany(ServiceCenterCaseNote::class, 'case_id')
            ->orderBy('created_at', 'DESC');
    }

    public function getSourceLeadContactMethod(): ?Contactmethod
    {
        if ($this->getFirstSourceLead() === null) {
            return null;
        }

        return $this->getFirstSourceLead()->getContactMethod();
    }

    public function getSourceLeadQuote(): ?Quote
    {
        if ($this->getFirstSourceLead() !== null) {
            return $this->getFirstSourceLead()->getQuote();
        }

        return null;
    }

    public function getSourceContactMethod(): string
    {
        if ($this->getSourceTelephonySession() !== null) {
            return self::CONTACT_METHOD_TELEPHONY;
        }

        return self::CONTACT_METHOD_ELECTRONIC;
    }

    public function isTelephonyCase(): bool
    {
        return $this->getSourceContactMethod() === self::CONTACT_METHOD_TELEPHONY;
    }

    public function isInteractiveCase(): bool
    {
        return $this->isTelephonyCase() && $this->getCaseEntry()->getTelephonySession() !== null;
    }

    public function getSourceRegion(): ?Region
    {
        if ($this->getCalledNumberInfo() !== null) {
            return $this->getCalledNumberInfo()->getEffectiveRegion();
        }

        if ($this->getFirstSourceLead() !== null && $this->getFirstSourceLead()->getRegion()) {
            return $this->getFirstSourceLead()->getRegion();
        }

        return null;
    }

    public function getSourceProfession(): ?Profession
    {
        if ($this->getSourceServiceType() !== null) {
            return $this->getSourceServiceType()->getProfession();
        }

        if ($this->getFirstSourceLead() !== null) {
            return $this->getFirstSourceLead()->getProfession();
        }

        if (null !== $this->getSourceSite() && null !== $this->getSourceSite()->getProfession()) {
            return $this->getSourceSite()->getProfession();
        }

        return null;
    }

    public function getSourceServiceType(): ?Servicetype
    {
        if ($this->getFirstSourceLead() !== null) {
            return $this->getFirstSourceLead()->getServiceType();
        }

        if ($this->getCalledNumberInfo() !== null) {
            return $this->getCalledNumberInfo()->getEffectiveServiceType();
        }

        return null;
    }

    public function getSourceSite(): ?Site
    {
        if ($this->getFirstSourceLead() !== null) {
            return $this->getFirstSourceLead()->getSite();
        }

        if ($this->getCalledNumberInfo() !== null) {
            return $this->getCalledNumberInfo()->getEffectiveSite();
        }

        if ($this->getQuoteFollowUp() !== null && $this->getQuoteFollowUp()->getSite() !== null) {
            return $this->getQuoteFollowUp()->getSite();
        }

        return null;
    }

    public function getSourceServicenumberLinkTypeLabel(): ?string
    {
        if ($this->getCalledNumberInfo() !== null) {
            return $this->getCalledNumberInfo()->getEffectiveLinkType();
        }

        return null;
    }

    public function getSourceServicenumberLinkSystemLabel(): ?string
    {
        if ($this->getCalledNumberInfo() !== null) {
            return $this->getServicenumberLink()->getLabel();
        }

        return null;
    }

    public function getSourceServicenumberLinkTrackingSegment(): ?CallTrackingSegment
    {
        if ($this->getCalledNumberInfo() !== null) {
            return $this->getCalledNumberInfo()->getServiceNumberLink()->getTrackingSegment();
        }

        return null;
    }

    public function getLeadSource(): ?string
    {
        if (null !== $this->getFirstSourceLead()) {
            return $this->getFirstSourceLead()->getSource();
        }

        if (null !== $this->getServicenumberLink() && null !== $this->getServicenumberLink()->getTrackingSegment()) {
            return $this->getServicenumberLink()->getTrackingSegment()->getLabel();
        }

        return null;
    }

    public function getLeadSourceInfo(): LeadSource
    {
        if ($this->getFirstSourceLead() !== null) {
            return $this->getFirstSourceLead()->getSourceInfo() ?? LeadSourceUtils::getUnknown();
        }

        $calledNumberInfo = $this->getCalledNumberInfo();
        if ($calledNumberInfo !== null) {
            return LeadSourceUtils::getForCalledServiceNumberInfo($calledNumberInfo);
        }

        $quoteFollowUp = $this->getQuoteFollowUp();
        if ($quoteFollowUp !== null) {
            $site = $quoteFollowUp->getSite();
            if ($site !== null) {
                // "Own site" quotes created by SC agent
                return LeadSourceUtils::getManualSc($site->getName());
            } else {
                // "External" partner quotes created by SC agent
                return LeadSourceUtils::getForQuoteFollowupSource($quoteFollowUp->getQuoteFollowUpSource());
            }
        }

        $externalQuoteRequest = $this->getExternalQuoteRequest();
        if ($externalQuoteRequest !== null) {
            // External quote requests are created by affiliates through our API
            return LeadSourceUtils::getForExternalQuoteRequest($externalQuoteRequest);
        }

        return LeadSourceUtils::getUnknown();
    }

    private function getFirstSourceLead(): ?Lead
    {
        if ($this->getSourceAppointment() !== null) {
            $leads = $this->getSourceAppointment()->getLeads();

            if ($leads !== []) {
                return $leads[0];
            }
        }

        return $this->getSourceLead();
    }

    public function getSourceCustomer(): ?Customer
    {
        if ($this->getFirstSourceLead() !== null) {
            return $this->getFirstSourceLead()->getCustomer();
        }

        return null;
    }

    public function getSourceGclid(): ?string
    {
        if (null !== $this->getFirstSourceLead()) {
            return $this->getFirstSourceLead()->getGclid();
        }

        return null;
    }

    public function hasLeadCall(): bool
    {
        $lead = $this->getLead();

        return $lead !== null && $lead->isCall();
    }

    /**
     * Filters cases which are currently assigned to an agent, but the agent has no session log entry for that case.
     *
     * @param static|Builder $queryBuilder
     *
     * @return static|Builder
     */
    public function scopePausedCases(Builder $queryBuilder): Builder
    {
        $queryBuilder->whereHas(
            'currentCaseEntry',
            static function (Builder $query) {
                $query->whereNotNull('assigned_agent_id');
            }
        );

        $queryBuilder->doesntHave('currentAgentSessionLogEntry');

        return $queryBuilder;
    }

    /**
     * @param static|Builder $queryBuilder
     *
     * @return static|Builder
     */
    public function scopeNotClosed(Builder $queryBuilder): Builder
    {
        return $queryBuilder->whereNull('closed_at');
    }

    /**
     * @param static|Builder $queryBuilder
     *
     * @return static|Builder
     */
    public function scopeHasSourceTelephonySession(Builder $queryBuilder, TelephonySession $telephonySession): Builder
    {
        return $queryBuilder->where(self::COL_SOURCE_TEL_SESSION_ID, $telephonySession->getId());
    }

    /**
     * @param static|Builder $queryBuilder
     *
     * @return static|Builder
     */
    public function scopeHasTelephonySession(Builder $queryBuilder, TelephonySession $telephonySession): Builder
    {
        return $queryBuilder->whereHas(
            'currentCaseEntry',
            static function (Builder $builder) use ($telephonySession): Builder {
                /** @var Builder|ServiceCenterCaseEntry $builder */
                return $builder->hasTelephonySession($telephonySession);
            }
        );
    }

    /**
     * @param static|Builder $queryBuilder
     *
     * @return static|Builder
     */
    public function scopeHasAnyTelephonySession(Builder $queryBuilder): Builder
    {
        return $queryBuilder->whereHas(
            'currentCaseEntry',
            /** @var Builder|ServiceCenterCaseEntry $builder */
            static fn (Builder $builder) => $builder->hasAnyTelephonySession()
        );
    }

    /**
     * @param static|Builder $queryBuilder
     *
     * @return static|Builder
     */
    public function scopeHasNoTelephonySession(Builder $queryBuilder): Builder
    {
        return $queryBuilder->whereHas(
            'currentCaseEntry',
            /** @var Builder|ServiceCenterCaseEntry $builder */
            static fn (Builder $builder) => $builder->hasNoTelephonySession()
        );
    }

    /**
     * @param static|Builder $queryBuilder
     *
     * @return static|Builder
     */
    public function scopeBelongsToLead(Builder $queryBuilder, Lead $lead): Builder
    {
        return $queryBuilder->where('lead_id', $lead->getId());
    }

    /**
     * @param static|Builder $queryBuilder
     *
     * @return static|Builder
     */
    public function scopeIsContactMethodTelephony(Builder $queryBuilder): Builder
    {
        return $queryBuilder->whereNotNull(self::COL_SOURCE_TEL_SESSION_ID);
    }

    /**
     * @param static|Builder $queryBuilder
     *
     * @return static|Builder
     */
    public function scopeIsContactMethodElectronic(Builder $queryBuilder): Builder
    {
        return $queryBuilder->whereNull(self::COL_SOURCE_TEL_SESSION_ID);
    }

    /**
     * @param static|Builder $queryBuilder
     *
     * @return static|Builder
     */
    public function scopeAssignedCases(Builder $queryBuilder): Builder
    {
        $queryBuilder->whereHas(
            'currentCaseEntry',
            static function (Builder $query) {
                $query->whereNotNull('assigned_agent_id');
            }
        );

        return $queryBuilder;
    }

    /**
     * @param static|Builder $queryBuilder
     *
     * @return static|Builder
     */
    public function scopeByStartedAt(Builder $queryBuilder): Builder
    {
        $queryBuilder->orderBy('started_at');

        return $queryBuilder;
    }

    /**
     * @param static|Builder $queryBuilder
     *
     * @return static|Builder
     */
    public function scopeByCreatedAt(Builder $queryBuilder): Builder
    {
        $queryBuilder->orderBy('created_at');

        return $queryBuilder;
    }

    /**
     * Filters on on cases that currently have the agent assigned
     *
     * @param static|Builder $queryBuilder
     *
     * @return static|Builder
     */
    public function scopeHasAgentAssigned(Builder $queryBuilder, User $agent): Builder
    {
        $queryBuilder->whereHas('currentCaseEntry', static function (Builder $query) use ($agent) {
            $query->where('assigned_agent_id', '=', $agent->getId());
        });

        return $queryBuilder;
    }

    /**
     * @inheritdoc
     */
    public function refresh()
    {
        $this->overrideCurrentCaseEntry = null;

        return parent::refresh();
    }
}
