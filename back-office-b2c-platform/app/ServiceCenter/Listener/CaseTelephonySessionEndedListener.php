<?php

declare(strict_types=1);

namespace App\ServiceCenter\Listener;

use App\ServiceCenter\ServiceCenterCase\Repository\ServiceCenterCaseRepository;
use App\ServiceCenter\ServiceCenterCase\Service\ServiceCenterCaseGarbageService;
use App\ServiceCenter\ServiceCenterCase\Service\ServiceCenterCaseService;
use App\Telephony\Session\State\Concerns\HasChannels;
use App\Telephony\Session\State\StateInterface;
use App\Telephony\Session\StateTransitionedEvent;
use App\Telephony\Session\Util\TelephonySessionStateMachineUtil;

class CaseTelephonySessionEndedListener
{
    private const MIN_IVR_DURATION_FOR_MISSED_CALL = 10;

    private ServiceCenterCaseRepository $serviceCenterCaseRepository;
    private ServiceCenterCaseService $serviceCenterCaseService;
    private ServiceCenterCaseGarbageService $serviceCenterCaseGarbageService;
    private TelephonySessionStateMachineUtil $telephonySessionStateMachineUtil;

    public function __construct(
        ServiceCenterCaseRepository $serviceCenterCaseRepository,
        ServiceCenterCaseService $serviceCenterCaseService,
        ServiceCenterCaseGarbageService $serviceCenterCaseGarbageService,
        TelephonySessionStateMachineUtil $telephonySessionStateMachineUtil
    ) {
        $this->serviceCenterCaseRepository = $serviceCenterCaseRepository;
        $this->serviceCenterCaseService = $serviceCenterCaseService;
        $this->serviceCenterCaseGarbageService = $serviceCenterCaseGarbageService;
        $this->telephonySessionStateMachineUtil = $telephonySessionStateMachineUtil;
    }

    public function onTelephonyStateChanged(StateTransitionedEvent $event): void
    {
        $telephonySessionEnded = $event->hasTransitionedTo(static function (StateInterface $state): bool {
            return $state->isFinal();
        });

        if (!$telephonySessionEnded) {
            // Telephony session still active
            return;
        }

        $case = $this->serviceCenterCaseRepository->tryGetByTelephonySession($event->getTelephonySession());
        if ($case === null || $case->isClosed()) {
            // There is no active case associated with the ended telephony session
            return;
        }

        // Regardless of the type of call (inbound or outbound), since the telephony session ended, it should be
        // detached from the case.
        $this->serviceCenterCaseService->detachTelephonySession($case);

        // Depending on the situation, we may want to immediately close the case as well.
        $sourceTelephonySession = $case->getSourceTelephonySession();
        if ($sourceTelephonySession === null) {
            // The case was not created by an incoming call, so a telephony session ending should not close it
            return;
        }

        if (!$event->getTelephonySession()->is($sourceTelephonySession)) {
            // The telephony session that just ended is not the inbound call that created this case, so the case
            // should not be closed by this event
            return;
        }

        // At this point we know that the incoming call that created a case has just termindated
        if ($this->telephonySessionStateMachineUtil->hasBeenAnsweredByAgent($event->getSessionStateMachine())) {
            // The incoming call had also been answered by an agent. In this case we should not automatically
            // close the case, since the agent should manually input the case result information.
            return;
        }

        $ivrDuration = $event->getTelephonySession()->getCreatedAt()->diffInSeconds($event->getEventDate());
        if ($ivrDuration >= self::MIN_IVR_DURATION_FOR_MISSED_CALL) {
            // The caller has waited long enough to consider this a 'missed call' that we want an agent to look at and
            // maybe call back in order to still convert it to a lead. However, a source phone number is required to do
            // so.
            $state = $event->getCurrentState();
            $inboundCaller = $state instanceof HasChannels ? $state->tryFindInboundChannel() : null;
            $callerNumber = $inboundCaller === null ? null : $inboundCaller->getRemotePhoneNumber();
            if ($callerNumber !== null) {
                // Caller number is known, so leave the case open as missed call
                return;
            }
        }

        // IVR duration was too short, or caller was anonymous, going to auto-close this case.
        // First we should unassign the currently assigned agent, if any.
        $agentSessionEntry = $case->getCurrentAgentSessionLogEntry();
        if ($agentSessionEntry !== null) {
            $this->serviceCenterCaseService->unassignAgentFromCaseForAgentSession(
                $case,
                $agentSessionEntry->getAgentSession()
            );
        }

        // Finally, close the case
        $this->serviceCenterCaseGarbageService->markAsGarbageBySystemUser($case);
    }
}
