<?php

declare(strict_types=1);

namespace App\ServiceCenter\Listener;

use App\ServiceCenter\ServiceCenterCase\Repository\ServiceCenterCaseRepository;
use App\ServiceCenter\ServiceCenterCase\Service\ServiceCenterCaseService;
use App\Telephony\Session\State\Concerns\HasChannels;
use App\Telephony\Session\State\StateInterface;
use App\Telephony\Session\State\Sub\ChannelState;
use App\Telephony\Session\StateTransitionedEvent;

class CallerHangupTelephonyCaseListener
{
    private ServiceCenterCaseRepository $serviceCenterCaseRepository;
    private ServiceCenterCaseService $serviceCenterCaseService;

    public function __construct(
        ServiceCenterCaseRepository $serviceCenterCaseRepository,
        ServiceCenterCaseService $serviceCenterCaseService
    ) {
        $this->serviceCenterCaseRepository = $serviceCenterCaseRepository;
        $this->serviceCenterCaseService = $serviceCenterCaseService;
    }

    public function onTelephonyStateChanged(StateTransitionedEvent $event): void
    {
        $inboundCallerHungUp = $event->hasTransitionedTo(static function (StateInterface $state): bool {
            $inboundCaller = $state instanceof HasChannels ? $state->tryFindInboundChannel() : null;
            return null !== $inboundCaller
                && $inboundCaller->getState() === ChannelState::STATE_HANGUP
                && $inboundCaller->isHangupByRemote();
        });

        if ($inboundCallerHungUp) {
            $case = $this->serviceCenterCaseRepository->tryGetBySourceTelephonySession($event->getTelephonySession());
            if ($case === null) {
                return;
            }

            $ivrDuration = $event->getTelephonySession()->getCreatedAt()->diffInSeconds($event->getEventDate());
            $this->serviceCenterCaseService->addCaseNoteOnCallerHangup($case, $ivrDuration);
        }
    }
}
