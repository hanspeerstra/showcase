<?php

declare(strict_types=1);

namespace App\ServiceCenter\Telephony\Service;

use App\Models\Office\Company;
use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\Telephony\ChannelReferences;
use App\ServiceCenter\Telephony\Exception\NoSuchChannelException;
use App\ServiceCenter\Telephony\Factory\DerivedTelephonyStateFactory;
use App\Telephony\ChannelMeta;
use App\Telephony\Commands\TelephonyCommandFactory;
use App\Telephony\Number\Repository\ServiceNumberLinkRepository;
use App\Telephony\Number\ServicenumberLink;
use App\Telephony\Session\Event\StartCustomSession;
use App\Telephony\Session\Model\TelephonySession;
use App\Telephony\Session\Repository\SessionRepositoryInterface;
use App\Telephony\Session\SessionStateMachineUpdater;
use App\Telephony\Session\State\Concerns\HasChannels;
use App\Telephony\Session\State\Sub\ChannelState;
use App\Telephony\Session\StateMachineFactory;
use App\Telephony\Session\TelephonySessionService;
use Propaganistas\LaravelPhone\PhoneNumber;
use UnexpectedValueException;

class TelephonyService
{
    private DerivedTelephonyStateFactory $derivedTelephonyStateFactory;
    private TelephonyCommandFactory $telephonyCommandFactory;
    private TelephonySessionService $telephonySessionService;
    private SessionStateMachineUpdater $telephonySessionUpdater;
    private StateMachineFactory $telephonySessionStateMachineFactory;
    private SessionRepositoryInterface $telephonySessionRepository;
    private ServiceNumberLinkRepository $serviceNumberLinkRepository;
    private TelephonyConfig $config;

    public function __construct(
        DerivedTelephonyStateFactory $derivedTelephonyStateFactory,
        TelephonyCommandFactory $telephonyCommandFactory,
        TelephonySessionService $telephonySessionService,
        SessionStateMachineUpdater $telephonySessionUpdater,
        StateMachineFactory $telephonySessionStateMachineFactory,
        SessionRepositoryInterface $telephonySessionRepository,
        ServiceNumberLinkRepository $serviceNumberLinkRepository,
        TelephonyConfig $config
    ) {
        $this->derivedTelephonyStateFactory = $derivedTelephonyStateFactory;
        $this->telephonyCommandFactory = $telephonyCommandFactory;
        $this->telephonySessionService = $telephonySessionService;
        $this->telephonySessionUpdater = $telephonySessionUpdater;
        $this->telephonySessionStateMachineFactory = $telephonySessionStateMachineFactory;
        $this->telephonySessionRepository = $telephonySessionRepository;
        $this->serviceNumberLinkRepository = $serviceNumberLinkRepository;
        $this->config = $config;
    }

    public function createOutboundTelephonySession(): TelephonySession
    {
        $sessionStateMachine = $this->telephonySessionService->createNewSessionStateMachine();
        $telephonySession = $this->telephonySessionRepository->getById($sessionStateMachine->getId());

        $this->telephonySessionUpdater->onEvent(
            new StartCustomSession($this->config->getOutboundSessionProvider()),
            $sessionStateMachine
        );

        return $telephonySession;
    }

    public function startOutboundTelephonySession(
        TelephonySession $telephonySession,
        PhoneNumber $agentNumber,
        AgentSession $agentSession
    ): void {
        $command = $this->telephonyCommandFactory->startOutboundScSession(
            $this->getSourceNumberForStartingOutboundSession(),
            $agentNumber,
            $agentSession->getId()
        );

        $this->telephonySessionService->dispatchCommand($telephonySession, $command);
    }

    public function callCompany(
        TelephonySession $telephonySession,
        PhoneNumber $sourcePhoneNumber,
        PhoneNumber $companyPhoneNumber,
        ?Company $company
    ): void {
        $metadata = [];
        if (null !== $company) {
            $metadata = [ChannelMeta::COMPANY_ID => $company->getId()];
        }

        $this->telephonySessionService->dispatchCommand(
            $telephonySession,
            $this->telephonyCommandFactory->startOutboundCall(
                $sourcePhoneNumber,
                $companyPhoneNumber,
                ChannelReferences::COMPANY,
                $metadata
            )
        );
    }

    public function callCustomer(
        TelephonySession $telephonySession,
        PhoneNumber $sourcePhoneNumber,
        PhoneNumber $customerPhoneNumber
    ): void {
        $this->telephonySessionService->dispatchCommand(
            $telephonySession,
            $this->telephonyCommandFactory->startOutboundCall(
                $sourcePhoneNumber,
                $customerPhoneNumber,
                ChannelReferences::CUSTOMER
            )
        );
    }

    private function getSourceNumberForStartingOutboundSession(): PhoneNumber
    {
        return $this->serviceNumberLinkRepository
            ->query()
            ->whereSystemType()
            ->whereLabel(ServicenumberLink::SYSTEM_LABEL_SC_OUTBOUND_CALL_SOURCE_AGENT)
            ->firstOrFail()
            ->getServicenumber()
            ->getPhoneNumber();
    }

    /**
     * @throws NoSuchChannelException if there is no (active) agent channel
     */
    public function hangupAgentChannel(TelephonySession $telephonySession): void
    {
        $derivedTelephonyState = $this->derivedTelephonyStateFactory->createFromTelephonySession($telephonySession);

        if ($derivedTelephonyState->isForwarded()) {
            throw new UnexpectedValueException(
                sprintf(
                    'Agent cannot close TelephonySession when call is forwarded (TelephonySession ID: %s)',
                    $telephonySession->getId()
                )
            );
        }

        $telephonyState = $this->telephonySessionStateMachineFactory
            ->fromSession($telephonySession)
            ->getState();

        if (!$telephonyState instanceof HasChannels) {
            throw new UnexpectedValueException(
                'Expected telephony state be of type HasChannels, got ' . get_class($telephonyState)
            );
        }

        $agentChannels = $telephonyState->getChannels(
            static function (ChannelState $channel): bool {
                return ChannelReferences::AGENT === $channel->getMetaField(ChannelMeta::REFERENCE);
            }
        );

        if (count($agentChannels) === 0) {
            throw new NoSuchChannelException('There is no agent channel');
        }

        $agentChannel = end($agentChannels);

        $hangupChannelCommand = $this->telephonyCommandFactory->hangupChannel($agentChannel->getChannelId());
        $this->telephonySessionService->dispatchCommand($telephonySession, $hangupChannelCommand);
    }

    /**
     * @throws NoSuchChannelException thrown if the given channel ID does not refer to an active channel.
     */
    public function hangupChannel(TelephonySession $telephonySession, string $channelId): void
    {
        $this->assertActiveChannel($telephonySession, $channelId);

        $hangupChannelCommand = $this->telephonyCommandFactory->hangupChannel($channelId);
        $this->telephonySessionService->dispatchCommand($telephonySession, $hangupChannelCommand);
    }

    /**
     * @throws NoSuchChannelException thrown if the given channel ID does not refer to an active channel.
     */
    public function switchToChannel(TelephonySession $telephonySession, string $channelId): void
    {
        $this->assertActiveChannel($telephonySession, $channelId);

        $switchToChannelCommand = $this->telephonyCommandFactory->switchToChannel($channelId);
        $this->telephonySessionService->dispatchCommand($telephonySession, $switchToChannelCommand);
    }

    public function holdAllChannels(TelephonySession $telephonySession): void
    {
        $switchToChannelCommand = $this->telephonyCommandFactory->switchToChannel('');
        $this->telephonySessionService->dispatchCommand($telephonySession, $switchToChannelCommand);
    }

    /**
     * @throws NoSuchChannelException thrown if the given channel ID does not refer to an active channel.
     */
    private function assertActiveChannel(TelephonySession $telephonySession, string $channelId): void
    {
        $derivedTelephonyState = $this->derivedTelephonyStateFactory->createFromTelephonySession($telephonySession);

        $derivedTelephonyState->getChannel($channelId);

        if (!$derivedTelephonyState->hasChannel($channelId)) {
            throw NoSuchChannelException::noActiveChannel($channelId);
        }
    }
}
