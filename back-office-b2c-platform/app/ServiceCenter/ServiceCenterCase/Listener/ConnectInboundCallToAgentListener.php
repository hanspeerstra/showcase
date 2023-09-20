<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Listener;

use App\Models\Office\Site;
use App\ServiceCenter\ServiceCenterCase\Event\CaseAgentAssigned;
use App\Telephony\Commands\TelephonyCommandFactory;
use App\Telephony\Number\Repository\ServiceNumberLinkRepository;
use App\Telephony\Session\TelephonySessionService;
use App\Telephony\Tracking\Repository\CallTrackingSegmentRepository;
use Propaganistas\LaravelPhone\PhoneNumber;

class ConnectInboundCallToAgentListener
{
    /** @var TelephonyCommandFactory */
    private $commandFactory;
    /** @var TelephonySessionService */
    private $telephonySessionService;
    /** @var ServiceNumberLinkRepository */
    private $numberLinkRepository;
    /** @var CallTrackingSegmentRepository */
    private $trackingSegmentRepository;

    public function __construct(
        TelephonyCommandFactory $commandFactory,
        TelephonySessionService $telephonySessionService,
        ServiceNumberLinkRepository $numberLinkRepository,
        CallTrackingSegmentRepository $trackingSegmentRepository
    ) {
        $this->commandFactory = $commandFactory;
        $this->telephonySessionService = $telephonySessionService;
        $this->numberLinkRepository = $numberLinkRepository;
        $this->trackingSegmentRepository = $trackingSegmentRepository;
    }

    public function handle(CaseAgentAssigned $event): void
    {
        $telephonySession = $event->getCase()->getSourceTelephonySession();
        if ($telephonySession !== null && $telephonySession->isActive()) {
            // An agent got assigned to a telephony case. We need to connect the caller to the agent's device.
            $deviceExternalNumber = $event->getAgentSession()->getInternalPhone()->getExternalPhoneNumber();
            $command = $this->commandFactory->connectToAgent(
                $this->getSourcePhoneNumber(),
                $deviceExternalNumber,
                $event->getAgentSession()->getId()
            );
            $this->telephonySessionService->dispatchCommand($telephonySession, $command);
        }
    }

    private function getSourcePhoneNumber(): PhoneNumber
    {
        $serviceNumberLink = $this->numberLinkRepository->query()
            ->whereSite(Site::getDefaultSite())
            ->whereMainSiteType()
            ->whereTrackingSegment($this->trackingSegmentRepository->getStandardSegment())
            ->firstOrFail();

        return $serviceNumberLink->getServicenumber()->getPhoneNumber();
    }
}
