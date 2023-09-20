<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Service;

use App\Auth\User;
use App\Events\Backend\AppointmentCancelledByServiceCenter;
use App\ExternalQuoteRequest\ExternalQuoteRequest;
use App\Leads\LeadHandlingService;
use App\Leads\LeadRepository;
use App\Leads\Service\LeadGclidService;
use App\Models\Office\Appointment;
use App\Models\Office\Lead;
use App\Models\Office\Quote;
use App\Questionnaire\AnswerBag\Service\AnswerBagService;
use App\Repositories\Backend\Auth\UserRepository;
use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\AgentSession\AgentSessionLogEntry;
use App\ServiceCenter\AgentSession\AgentSessionStatus;
use App\ServiceCenter\AgentSession\Repository\AgentSessionRepository;
use App\ServiceCenter\AgentSession\Service\AgentSessionService;
use App\ServiceCenter\CaseQueue\Event\CaseQueueChangedBroadcastEvent;
use App\ServiceCenter\CaseQueue\Service\CaseQueueService;
use App\ServiceCenter\CaseSchedule\Service\CaseScheduleService;
use App\ServiceCenter\QuoteFollowUp\QuoteFollowUp;
use App\ServiceCenter\ServiceCenterCase\Assertion\CanAgentUnassignFromCase;
use App\ServiceCenter\ServiceCenterCase\Assertion\ServiceCenterCaseAssertions;
use App\ServiceCenter\ServiceCenterCase\CaseType;
use App\ServiceCenter\ServiceCenterCase\Event\CaseAgentAssigned;
use App\ServiceCenter\ServiceCenterCase\Event\CaseAssignmentChangedBroadcastEvent;
use App\ServiceCenter\ServiceCenterCase\Event\CaseChangedBroadcastEvent;
use App\ServiceCenter\ServiceCenterCase\Event\CaseClosedEvent;
use App\ServiceCenter\ServiceCenterCase\Event\CaseNoteAddedEvent;
use App\ServiceCenter\ServiceCenterCase\Event\CaseNoteUpdatedEvent;
use App\ServiceCenter\ServiceCenterCase\Event\CaseTypeChangedEvent;
use App\ServiceCenter\ServiceCenterCase\Event\CaseWasUnassignedEvent;
use App\ServiceCenter\ServiceCenterCase\Repository\CaseTypeRepository;
use App\ServiceCenter\ServiceCenterCase\Repository\ServiceCenterCaseRepository;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCaseEntry;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCaseGarbageReason;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCaseNote;
use App\ServiceCenter\Telephony\Factory\DerivedTelephonyStateFactory;
use App\ServiceCenter\WorkGroup\Repository\WorkGroupRepository;
use App\ServiceCenter\WorkGroup\WorkGroup;
use App\Telephony\Commands\TelephonyCommandFactory;
use App\Telephony\Number\CalledNumberInfo;
use App\Telephony\Number\ServicenumberLink;
use App\Telephony\Session\Model\TelephonySession;
use App\Telephony\Session\TelephonySessionService;
use App\Utils\Database\Contract\TransactionHandler;
use Assert\Assert;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DB;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use InvalidArgumentException;
use LogicException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use UnexpectedValueException;

class ServiceCenterCaseService
{
    private AgentSessionService $agentSessionService;
    private ServiceCenterCaseRepository $serviceCenterCaseRepository;
    private AgentSessionRepository $agentSessionRepository;
    private WorkGroupRepository $workGroupRepository;
    private CaseTypeRepository $caseTypeRepository;
    private CaseQueueService $caseQueueService;
    private LeadHandlingService $leadHandlingService;
    private LeadGclidService $leadGclidService;
    private LeadRepository $leadRepository;
    private DerivedTelephonyStateFactory $derivedTelephonyStateFactory;
    private TelephonyCommandFactory $telephonyCommandFactory;
    private TelephonySessionService $telephonySessionService;
    private UserRepository $userRepository;
    private TransactionHandler $transactionHandler;
    private Dispatcher $dispatcher;
    private AnswerBagService $answerBagService;
    private Connection $connection;
    private CanAgentUnassignFromCase $canAgentUnassignFromCase;
    private LoggerInterface $logger;

    public function __construct(
        AgentSessionService $agentSessionService,
        ServiceCenterCaseRepository $serviceCenterCaseRepository,
        AgentSessionRepository $agentSessionRepository,
        WorkGroupRepository $workGroupRepository,
        CaseTypeRepository $caseTypeRepository,
        CaseQueueService $caseQueueService,
        LeadHandlingService $leadHandlingService,
        LeadGclidService $leadGclidService,
        LeadRepository $leadRepository,
        DerivedTelephonyStateFactory $derivedTelephonyStateFactory,
        TelephonyCommandFactory $telephonyCommandFactory,
        TelephonySessionService $telephonySessionService,
        UserRepository $userRepository,
        TransactionHandler $transactionHandler,
        Dispatcher $dispatcher,
        AnswerBagService $answerBagService,
        Connection $connection,
        CanAgentUnassignFromCase $canAgentUnassignFromCase,
        LoggerInterface $logger
    ) {
        $this->agentSessionService = $agentSessionService;
        $this->serviceCenterCaseRepository = $serviceCenterCaseRepository;
        $this->agentSessionRepository = $agentSessionRepository;
        $this->workGroupRepository = $workGroupRepository;
        $this->caseTypeRepository = $caseTypeRepository;
        $this->caseQueueService = $caseQueueService;
        $this->leadHandlingService = $leadHandlingService;
        $this->leadGclidService = $leadGclidService;
        $this->leadRepository = $leadRepository;
        $this->derivedTelephonyStateFactory = $derivedTelephonyStateFactory;
        $this->telephonyCommandFactory = $telephonyCommandFactory;
        $this->telephonySessionService = $telephonySessionService;
        $this->userRepository = $userRepository;
        $this->transactionHandler = $transactionHandler;
        $this->dispatcher = $dispatcher;
        $this->answerBagService = $answerBagService;
        $this->connection = $connection;
        $this->canAgentUnassignFromCase = $canAgentUnassignFromCase;
        $this->logger = $logger;
    }

    public function startCase(ServiceCenterCase $case, AgentSession $agentSession): ServiceCenterCase
    {
        $telephonySession = $case->getActiveTelephonySession();

        $this->assertThatAgentSessionDoesNotHaveAnActiveCase($agentSession);

        // Agent could make an outgoing call without having a Case
        $this->assertThatAgentSessionDoesNotHaveAnActiveTelephonySession($agentSession);

        // Lock the case to prevent multiple agents getting assigned to the same case at the same time
        $case = $this->transactionHandler->doInLockedTransaction(
            $case,
            function () use ($case, $agentSession, $telephonySession) {
                // Check that the case is currently not assigned to prevent double assignment
                ServiceCenterCaseAssertions::assertThatCaseIsUnassigned($case);

                $this->agentSessionService->updateAgentSessionLogEntry(
                    $agentSession,
                    new AgentSessionStatus(AgentSessionStatus::HANDLE_CASE),
                    $case,
                    $telephonySession
                );

                $agentSession->refresh();

                if ($case->getStartedAt() === null) {
                    $case->setStartedAt(new CarbonImmutable());
                }

                $serviceCenterCaseEntry = ServiceCenterCaseEntry::makeInstance(
                    $case->getCaseEntry()->getCaseType(),
                    $case->getCaseEntry()->getWorkGroup(),
                    $telephonySession,
                    $agentSession->getUser()
                );

                $case->setCaseEntry($serviceCenterCaseEntry);

                $case = $this->serviceCenterCaseRepository->persist($case);

                $this->caseQueueService->dequeueByCase($case);
                $this->dispatcher->dispatch(new CaseAgentAssigned($case, $agentSession));

                return $case;
            }
        );

        $this->dispatcher->dispatch(
            new CaseChangedBroadcastEvent($case)
        );

        $this->dispatcher->dispatch(
            new CaseAssignmentChangedBroadcastEvent()
        );

        return $case;
    }

    public function createTelephonyLeadScreeningCase(
        TelephonySession $telephonySession,
        ServicenumberLink $servicenumberLink
    ): ServiceCenterCase {
        $caseType = $this->caseTypeRepository->getByLabel(CaseType::TYPE_LEAD_SCREENING);
        $workGroup = $this->workGroupRepository->getByLabel(WorkGroup::GROUP_PHONE_SERVICE_DESK);

        return $this->serviceCenterCaseRepository->persist(
            ServiceCenterCase::makeInstance(
                $caseType,
                $workGroup,
                null,
                null,
                $telephonySession,
                $servicenumberLink
            )
        );
    }

    public function createQuoteLeadScreeningCase(Quote $quote): ServiceCenterCase
    {
        $caseType = $this->caseTypeRepository->getByLabel(CaseType::TYPE_LEAD_SCREENING);
        $workGroup = $this->workGroupRepository->getByLabel(WorkGroup::GROUP_QUOTE_FOLLOW_UP);

        if ($quote->getLead()->getSubscription() === null) {
            throw new InvalidArgumentException('Cannot make quote lead screening case for quote without destination');
        }

        return $this->serviceCenterCaseRepository->persist(
            ServiceCenterCase::makeInstance(
                $caseType,
                $workGroup,
                $quote->getLead()
            )
        );
    }

    public function createQuoteMatchmakerCompanyLeadScreeningCase(Quote $quote): ServiceCenterCase
    {
        $caseType = $this->caseTypeRepository->getByLabel(CaseType::TYPE_COMPANY_MATCHMAKER_LEAD_SCREENING);
        $workGroup = $this->workGroupRepository->getByLabel(WorkGroup::GROUP_QUOTE_FOLLOW_UP);

        if ($quote->getLead()->getSubscription() === null) {
            throw new InvalidArgumentException(
                'Cannot make quote matchmaker company lead screening case for quote without destination'
            );
        }

        return $this->serviceCenterCaseRepository->persist(
            ServiceCenterCase::makeInstance(
                $caseType,
                $workGroup,
                $quote->getLead()
            )
        );
    }

    public function createMatchmakerCompanyLeadScreeningCase(
        TelephonySession $telephonySession,
        ServicenumberLink $servicenumberLink
    ): ServiceCenterCase {
        $caseType = $this->caseTypeRepository->getByLabel(CaseType::TYPE_COMPANY_MATCHMAKER_LEAD_SCREENING);
        $workGroup = $this->workGroupRepository->getByLabel(WorkGroup::GROUP_PHONE_SERVICE_DESK);

        $case = $this->serviceCenterCaseRepository->persist(
            ServiceCenterCase::makeInstance(
                $caseType,
                $workGroup,
                null,
                null,
                $telephonySession,
                $servicenumberLink
            )
        );

        /*
         * Outdated ServiceNumberLink can be called still
         * In the ServiceCenter (some) of the source information will not be present like ServiceType and Region
         */
        $calledNumberInfo = new CalledNumberInfo($servicenumberLink, $telephonySession->getCreatedAt());
        if (!$calledNumberInfo->isLinkWasValid()) {
            $this->logger->error(
                sprintf(
                    'createMatchmakerCompanyLeadScreeningCase ServiceNumberLink (ID %s) is invalid (outdated) Case (ID: %s)',
                    $servicenumberLink->getId(),
                    $case->getId()
                )
            );
        }

        return $case;
    }

    public function createMatchmakerCase(
        TelephonySession $telephonySession,
        ServicenumberLink $servicenumberLink
    ): ServiceCenterCase {
        $caseType = $this->caseTypeRepository->getByLabel(CaseType::TYPE_MATCHMAKER);
        $workGroup = $this->workGroupRepository->getByLabel(WorkGroup::GROUP_PHONE_SERVICE_DESK);

        return $this->serviceCenterCaseRepository->persist(
            ServiceCenterCase::makeInstance(
                $caseType,
                $workGroup,
                null,
                null,
                $telephonySession,
                $servicenumberLink
            )
        );
    }

    /**
     * @param AgentSession|null $agentSessionToAssign if given will start and assign the newly created case to the agent
     */
    public function createQuoteFollowUpCase(
        QuoteFollowUp $quoteFollowUp,
        ?AgentSession $agentSessionToAssign = null
    ): ServiceCenterCase {
        $caseType = $this->caseTypeRepository->getByLabel(CaseType::TYPE_QUOTE_FOLLOW_UP_CALL);
        $workGroup = $this->workGroupRepository->getByLabel(WorkGroup::GROUP_QUOTE_FOLLOW_UP);

        return $this->transactionHandler->transactional(
            function () use ($caseType, $workGroup, $quoteFollowUp, $agentSessionToAssign): ServiceCenterCase {
                if (!$quoteFollowUp->exists) {
                    $quoteFollowUp->save();
                }

                $case = ServiceCenterCase::makeInstance(
                    $caseType,
                    $workGroup,
                    null,
                    null,
                    null,
                    null,
                    $quoteFollowUp
                );

                $case = $this->serviceCenterCaseRepository->persist($case);

                if (null !== $agentSessionToAssign) {
                    $case = $this->startCase($case, $agentSessionToAssign);
                }

                return $case;
            }
        );
    }

    public function createExternalQuoteRequestCase(ExternalQuoteRequest $externalQuoteRequest): ServiceCenterCase
    {
        $caseType = $this->caseTypeRepository->getByLabel(CaseType::TYPE_EXTERNAL_QUOTE_REQUEST);
        $workGroup = $this->workGroupRepository->getByLabel(WorkGroup::GROUP_QUOTE_FOLLOW_UP);

        $case = ServiceCenterCase::makeInstance(
            $caseType,
            $workGroup,
            null,
            null,
            null,
            null,
            null,
            $externalQuoteRequest
        );

        return $this->serviceCenterCaseRepository->persist($case);
    }

    public function createUnfulfilledQuoteCase(Lead $sourceLead): ServiceCenterCase
    {
        Assert::that($sourceLead->isQuote())->true();

        $caseType = $this->caseTypeRepository->getByLabel(CaseType::TYPE_UNFULFILLED_QUOTE);
        $workGroup = $this->workGroupRepository->getByLabel(WorkGroup::GROUP_QUOTE_FOLLOW_UP);

        $case = ServiceCenterCase::makeInstance(
            $caseType,
            $workGroup,
            $sourceLead,
            null,
            null,
            null
        );

        return $this->serviceCenterCaseRepository->persist($case);
    }

    public function createUnfulfilledAppointmentCase(Appointment $appointment): ServiceCenterCase
    {
        $caseType = $this->caseTypeRepository->getByLabel(CaseType::TYPE_UNFULFILLED_APPOINTMENT);
        $workGroup = $this->workGroupRepository->getByLabel(WorkGroup::GROUP_APPOINTMENT_FOLLOW_UP);

        $case = ServiceCenterCase::makeInstance(
            $caseType,
            $workGroup,
            null,
            $appointment,
            null,
            null,
            null
        );

        return $this->serviceCenterCaseRepository->persist($case);
    }

    public function pauseCase(ServiceCenterCase $case): ServiceCenterCase
    {
        ServiceCenterCaseAssertions::assertThatCaseHasAnActiveAgentSession($case);

        /** @var AgentSessionLogEntry $agentSessionLogEntry */
        $agentSessionLogEntry = $case->getCurrentAgentSessionLogEntry();

        $agentSession = $agentSessionLogEntry->getAgentSession();
        $this->assertThatAgentSessionDoesNotHaveAnActiveTelephonySession(
            $agentSession
        );

        $this->transactionHandler->transactional(function () use ($agentSession) {
            $this->agentSessionService->setInitialAgentSessionLogEntry($agentSession);
        });

        $case = $this->serviceCenterCaseRepository->refresh($case);

        $this->dispatcher->dispatch(
            new CaseChangedBroadcastEvent($case)
        );

        $this->dispatcher->dispatch(
            new CaseAssignmentChangedBroadcastEvent()
        );

        return $case;
    }

    public function pauseCaseFromAgentSession(ServiceCenterCase $case, AgentSession $agentSession): ServiceCenterCase
    {
        ServiceCenterCaseAssertions::assertThatCaseBelongsToAgentSession($case, $agentSession);

        return $this->pauseCase($case);
    }

    public function resumeCaseForAgentSession(ServiceCenterCase $case, AgentSession $agentSession): ServiceCenterCase
    {
        ServiceCenterCaseAssertions::assertThatCaseIsAssignedToAgent($case, $agentSession);

        return $this->resumeCase($case);
    }

    public function resumeCase(ServiceCenterCase $case): ServiceCenterCase
    {
        if ($case->getCurrentAgentSessionLogEntry() !== null) {
            throw new UnexpectedValueException(
                'Cannot resume an active case which is currently assigned to an agent'
            );
        }

        $agent = $case->getCaseEntry()->getAssignedAgent();

        if ($agent === null) {
            throw new UnexpectedValueException('Case must have an agent assigned');
        }

        $agentSession = $this->agentSessionRepository->getByAgent($agent);

        if ($agentSession === null) {
            throw new UnexpectedValueException('Agent does not have an AgentSession');
        }

        if ($agentSession->hasActiveTelephonySession()) {
            throw new LogicException('Agent has an active TelephonySession');
        }

        $this->agentSessionService->updateAgentSessionLogEntry(
            $agentSession,
            new AgentSessionStatus(AgentSessionStatus::HANDLE_CASE),
            $case,
            null
        );

        $case = $this->serviceCenterCaseRepository->refresh($case);

        $this->dispatcher->dispatch(
            new CaseChangedBroadcastEvent($case)
        );

        $this->dispatcher->dispatch(
            new CaseAssignmentChangedBroadcastEvent()
        );

        return $case;
    }

    public function setCaseResult(
        ServiceCenterCase $case,
        ?Lead $lead,
        ?Appointment $appointment,
        ?ServiceCenterCaseGarbageReason $garbageReason
    ): ServiceCenterCase {
        $case = $this->serviceCenterCaseRepository->refresh($case);
        if ($case->hasResult()) {
            throw new UnexpectedValueException('Case result can only be set once');
        }

        if (null !== $lead) {
            $case = $case->setLead($lead);
        }

        if (null !== $appointment) {
            $case = $case->setAppointment($appointment);
        }

        if (null !== $garbageReason) {
            $case = $case->setGarbageReason($garbageReason);
        }

        if (!$case->hasResult()) {
            throw new UnexpectedValueException('Result must be set, no result given');
        }

        $this->transactionHandler->transactional(function () use ($case, $lead, $appointment) {
            // Try and find Gclid for telephony cases, based on source call lead or source telephony session
            if ($case->isTelephonyCase()
                && (
                    (null !== $lead && !$lead->hasGclid())
                    || (null !== $appointment && !$appointment->getFirstLead()->hasGclid())
                )
            ) {
                $gclid = null;

                $sourceLead = $case->getSourceLead();
                if ($sourceLead !== null && $sourceLead->isCall()) {
                    $this->leadGclidService->tryFindAndAttachGclidToCallLead($sourceLead);
                    $gclid = $sourceLead->getGclid();
                }

                if (null === $gclid && null !== $case->getSourceTelephonySession()) {
                    $call = $case->getSourceTelephonySession()->getCalls()->first();

                    if (null !== $call) {
                        $gclid = $this->leadGclidService->findGclidForCall($call);
                    }
                }

                if (null !== $gclid) {
                    if (null !== $lead) {
                        $lead->setGclid($gclid);
                        $this->leadRepository->update($lead);
                    } elseif (null !== $appointment) {
                        foreach ($appointment->getLeads() as $appointmentLead) {
                            $appointmentLead->setGclid($gclid);
                            $this->leadRepository->update($appointmentLead);
                        }
                    }
                }
            }

            $case = $this->serviceCenterCaseRepository->persist($case);

            $sourceLead = $case->getSourceLead();
            if ($sourceLead !== null) {
                $this->leadHandlingService->reject($sourceLead);
                $this->leadHandlingService->handledByServiceCenter($sourceLead);
            }

            $sourceAppointment = $case->getSourceAppointment();
            if (null !== $sourceAppointment
                && !$sourceAppointment->isCancelled()
                && !$sourceAppointment->isAccepted()
                && !$sourceAppointment->isExpired()
            ) {
                // If appointment is cancelled/accepted/expired, then user is already notified and there is nothing to do.
                $sourceAppointment->cancel('Verwerkt door service center');
                $sourceAppointment->save();

                event(new AppointmentCancelledByServiceCenter($sourceAppointment));
            }
        });

        $this->dispatcher->dispatch(
            new CaseChangedBroadcastEvent($case)
        );

        return $case;
    }

    public function makeCaseSalesOpportunity(
        ServiceCenterCase $case,
        AgentSession $agentSession,
        ServiceCenterCaseNote $caseNote,
        CarbonInterface $scheduleDate
    ): void {
        ServiceCenterCaseAssertions::assertThatCaseBelongsToAgentSession($case, $agentSession);

        if ($agentSession->hasActiveTelephonySession()) {
            throw new RuntimeException(
                'Cannot mark case as sales opportunity while there is an active telephony session'
            );
        }

        $salesWorkGroup = $this->workGroupRepository->getByLabel(WorkGroup::GROUP_SALES);
        $salesOpportunityCaseType = $this->caseTypeRepository->getByLabel(CaseType::TYPE_SALES_OPPORTUNITY);

        DB::transaction(function () use ($caseNote, $case, $salesOpportunityCaseType, $salesWorkGroup, $scheduleDate) {
            $this->addCaseNote($caseNote);
            $this->updateCaseType($case, $salesOpportunityCaseType, $salesWorkGroup, false);
            app(CaseScheduleService::class)->scheduleCase($case, $scheduleDate);
        });

        $this->dispatcher->dispatch(
            new CaseTypeChangedEvent($case)
        );

        $this->dispatcher->dispatch(
            new CaseChangedBroadcastEvent($case)
        );
    }

    public function rescheduleSalesOpportunity(
        ServiceCenterCase $case,
        AgentSession $agentSession,
        CarbonInterface $scheduleDate,
        ?ServiceCenterCaseNote $caseNote
    ): void {
        ServiceCenterCaseAssertions::assertThatCaseBelongsToAgentSession($case, $agentSession);

        $salesOpportunityCaseType = $this->caseTypeRepository->getByLabel(CaseType::TYPE_SALES_OPPORTUNITY);

        if (!$case->getCaseEntry()->getCaseType()->is($salesOpportunityCaseType)) {
            throw new UnexpectedValueException(
                sprintf('Tried to reschedule case %d, but is not a sales opportunity', $case->getId())
            );
        }

        $this->transactionHandler->transactional(function () use ($case, $agentSession, $scheduleDate, $caseNote) {
            if (null !== $caseNote) {
                $this->addCaseNote($caseNote);
            }

            $this->unassignCase($case, $agentSession);
            app(CaseScheduleService::class)->scheduleCase($case, $scheduleDate);

            $this->dispatcher->dispatch(
                new CaseChangedBroadcastEvent($case)
            );
        });
    }

    public function changeCaseType(
        ServiceCenterCase $case,
        CaseType $caseType,
        WorkGroup $workGroup,
        bool $selfAssign
    ): void {
        $agentSessionLogEntry = $case->getCurrentAgentSessionLogEntry();

        $this->updateCaseType($case, $caseType, $workGroup, $selfAssign);

        $this->dispatcher->dispatch(
            new CaseTypeChangedEvent($case)
        );

        $this->dispatcher->dispatch(
            new CaseChangedBroadcastEvent($case)
        );
    }

    private function updateCaseType(
        ServiceCenterCase $case,
        CaseType $caseType,
        WorkGroup $workGroup,
        bool $selfAssign
    ): void {
        $this->transactionHandler->transactional(
            function () use ($case, $caseType, $workGroup, $selfAssign): void {
                if (!$selfAssign && null !== $case->getCurrentAgentSessionLogEntry()->getAgentSession()) {
                    $this->unassignCase($case, $case->getCurrentAgentSessionLogEntry()->getAgentSession());
                }

                $serviceCenterCaseEntry = ServiceCenterCaseEntry::makeInstance(
                    $caseType,
                    $workGroup,
                    $case->getActiveTelephonySession(),
                    $selfAssign ? $case->getCaseEntry()->getAssignedAgent() : null
                );

                $case->setCaseEntry($serviceCenterCaseEntry);

                $this->serviceCenterCaseRepository->persist($case);
            }
        );
    }

    public function agentRejectCase(ServiceCenterCase $case, AgentSession $agentSession): void
    {
        $this->unassignCase($case, $agentSession);
    }

    public function unassignAgentFromCase(ServiceCenterCase $case): void
    {
        $this->unassignCase($case, $case->getCurrentAgentSessionLogEntry()->getAgentSession());
    }

    public function unassignAgentFromCaseForAgentSession(ServiceCenterCase $case, AgentSession $agentSession): void
    {
        $this->unassignCase($case, $agentSession);
    }

    private function unassignCase(ServiceCenterCase $case, AgentSession $agentSession): void
    {
        $this->canAgentUnassignFromCase->assert($case, $agentSession);

        $serviceCenterCaseEntry = ServiceCenterCaseEntry::makeInstance(
            $case->getCaseEntry()->getCaseType(),
            $case->getCaseEntry()->getWorkGroup(),
            $case->getCaseEntry()->getTelephonySession(),
            null
        );

        $case->setCaseEntry($serviceCenterCaseEntry);

        /** @var ServiceCenterCase $case */
        $case = $this->transactionHandler->transactional(function () use ($case, $agentSession) {
            $case = $this->serviceCenterCaseRepository->persist($case);

            $this->agentSessionService->setInitialAgentSessionLogEntry(
                $agentSession
            );

            return $case;
        });

        $this->dispatcher->dispatch(
            new CaseWasUnassignedEvent(
                $case,
                $agentSession->getAgentSessionLogEntry()->getTelephonySession()
            )
        );

        $this->dispatcher->dispatch(
            new CaseChangedBroadcastEvent($case)
        );

        $this->dispatcher->dispatch(
            new CaseAssignmentChangedBroadcastEvent()
        );
    }

    public function attachTelephonySession(
        ServiceCenterCase $case,
        TelephonySession $telephonySession
    ): ServiceCenterCase {
        if (null !== $case->getActiveTelephonySession()) {
            throw new UnexpectedValueException(
                'Cannot attach telephony session to case which already has an active telephony session'
            );
        }

        $currentCaseEntry = $case->getCaseEntry();

        $serviceCenterCaseEntry = ServiceCenterCaseEntry::makeInstance(
            $currentCaseEntry->getCaseType(),
            $currentCaseEntry->getWorkGroup(),
            $telephonySession,
            $currentCaseEntry->getAssignedAgent()
        );

        $case->setCaseEntry($serviceCenterCaseEntry);

        $updatedCase = $this->serviceCenterCaseRepository->persist($case);

        $this->dispatcher->dispatch(
            new CaseChangedBroadcastEvent($updatedCase)
        );

        return $updatedCase;
    }

    public function detachTelephonySession(ServiceCenterCase $case): ServiceCenterCase
    {
        return $this->transactionHandler->doInLockedTransaction($case, function () use ($case) {
            if ($case->getCaseEntry()->getTelephonySession() === null) {
                throw new UnexpectedValueException(
                    'ServiceCenterCase does not have a TelephonySession'
                );
            }

            $currentCaseEntry = $case->getCaseEntry();

            $serviceCenterCaseEntry = ServiceCenterCaseEntry::makeInstance(
                $currentCaseEntry->getCaseType(),
                $currentCaseEntry->getWorkGroup(),
                null,
                $currentCaseEntry->getAssignedAgent()
            );

            $case->setCaseEntry($serviceCenterCaseEntry);

            $updatedCase = $this->serviceCenterCaseRepository->persist($case);
            $this->dispatcher->dispatch(new CaseChangedBroadcastEvent($updatedCase));

            // Detaching the telephony session can change the case from interactive to passive, and this influences the
            // order of the case in the case queue, so we need to broadcast this (potential) queue change
            $this->dispatcher->dispatch(new CaseQueueChangedBroadcastEvent());

            return $updatedCase;
        });
    }

    public function closeCase(ServiceCenterCase $case): ServiceCenterCase
    {
        $case = $this->serviceCenterCaseRepository->refresh($case);

        $case = $this->transactionHandler->transactional(function () use ($case) {
            if ($case->getActiveTelephonySession() !== null) {
                /** @var TelephonySession $telephonySession */
                $telephonySession = $case->getActiveTelephonySession();

                $derivedTelephonyState = $this->derivedTelephonyStateFactory
                    ->createFromTelephonySession($telephonySession);

                if ($derivedTelephonyState->agentParticipatesInCall()) {
                    if (0 !== $derivedTelephonyState->getActiveChannelCount()) {
                        throw new UnexpectedValueException(
                            'Cannot close a case when there is an active telephony channel'
                        );
                    }

                    $closeAgentCall = $this->telephonyCommandFactory->hangupAgentCall();

                    $this->telephonySessionService->dispatchCommand($telephonySession, $closeAgentCall);
                }
            }

            if (!$case->hasResult()) {
                throw new UnexpectedValueException('Case must have a result when closing a case');
            }

            $case->setClosedAt(new CarbonImmutable());

            $this->serviceCenterCaseRepository->persist($case);

            if ($case->isAssigned()) {
                $this->unassignAgentFromCase($case);
            }

            return $case;
        });

        $this->dispatcher->dispatch(new CaseClosedEvent($case));

        $this->dispatcher->dispatch(
            new CaseChangedBroadcastEvent($case)
        );

        return $case;
    }

    public function addCaseNote(ServiceCenterCaseNote $caseNote): ServiceCenterCaseNote
    {
        $caseNote = $this->serviceCenterCaseRepository->insertCaseNote($caseNote);

        $this->dispatcher->dispatch(new CaseNoteAddedEvent($caseNote));

        $this->dispatcher->dispatch(
            new CaseChangedBroadcastEvent($caseNote->getCase())
        );

        return $caseNote;
    }

    public function addCaseNoteOnCallerHangup(ServiceCenterCase $case, int $ivrDuration): void
    {
        // The caller hung up. When the case did not have a result yet, that means that it was not fully handled yet
        // and the agent should be informed about the hangup.
        if ($case->hasResult()) {
            return;
        }

        $systemUser = $this->userRepository->getSystemUser();

        $caseNote = ServiceCenterCaseNote::makeInstance(
            $case,
            $systemUser,
            sprintf('Beller heeft opgehangen na %d seconden wachttijd', $ivrDuration)
        );

        $this->addCaseNote($caseNote);
    }

    public function ensureAnswerBag(ServiceCenterCase $case): ServiceCenterCase
    {
        if ($case->getAnswerBag() === null) {
            $case = $this->transactionHandler->transactional(function () use ($case) {
                $case->setAnswerBag(
                    $this->answerBagService->createAnswerBag()
                );

                return $this->serviceCenterCaseRepository->persist($case);
            });
        }

        return $case;
    }

    public function editCaseNote(ServiceCenterCaseNote $caseNote, User $agent): void
    {
        if (!$agent->is($caseNote->getAgent())) {
            throw new HttpException(
                Response::HTTP_FORBIDDEN,
                sprintf(
                    'Agent %s cannot update case note %d because it does not belong to agent',
                    $agent->getId(),
                    $caseNote->getId()
                )
            );
        }

        $this->serviceCenterCaseRepository->updateCaseNote($caseNote);

        $this->dispatcher->dispatch(new CaseNoteUpdatedEvent($caseNote));

        $this->dispatcher->dispatch(
            new CaseChangedBroadcastEvent($caseNote->getCase())
        );
    }

    public function getAgentThatCreatedLead(ServiceCenterCase $case): ?User
    {
        $sql = <<<'SQL'
            SELECT sce.assigned_agent_id
            FROM sc_cases sc
                INNER JOIN leads l
                    ON sc.lead_id = l.id
                INNER JOIN sc_case_entries sce
                    ON sc.id = sce.case_id
                    AND sce.created_at <= l.created_at
                    AND (sce.deleted_at IS NULL OR sce.deleted_at >= l.created_at)
            WHERE
                sc.id = :case_id
SQL;

        $stmt = $this->connection->getPdo()->prepare($sql);

        $stmt->execute([
            'case_id' => $case->getId(),
        ]);

        $userId = $stmt->fetchColumn();

        if ($userId !== null && $userId !== false) {
            return $this->userRepository->getById($userId);
        }

        return null;
    }

    private function assertThatAgentSessionDoesNotHaveAnActiveCase(AgentSession $agentSession): void
    {
        if ($agentSession->hasActiveCase()) {
            throw new UnexpectedValueException(
                sprintf(
                    'AgentSession (id: %s) has an active ServiceCenterCase',
                    $agentSession->getId()
                )
            );
        }
    }

    public function assertThatAgentSessionDoesNotHaveAnActiveTelephonySession(AgentSession $agentSession): void
    {
        if ($agentSession->hasActiveTelephonySession()) {
            throw new UnexpectedValueException(
                sprintf(
                    'AgentSession (id: %s) has an active TelephonySession',
                    $agentSession->getId()
                )
            );
        }
    }

    public function assertThatCaseHasAnActiveAgentSession(ServiceCenterCase $case): void
    {
        if ($case->getCurrentAgentSessionLogEntry() === null) {
            throw new UnexpectedValueException(
                sprintf(
                    'Case (id: %s) does not have an active AgentSession',
                    $case->getId()
                )
            );
        }
    }
}
