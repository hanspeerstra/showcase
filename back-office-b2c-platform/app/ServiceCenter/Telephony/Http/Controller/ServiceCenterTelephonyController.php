<?php

declare(strict_types=1);

namespace App\ServiceCenter\Telephony\Http\Controller;

use App\Http\Controllers\Controller;
use App\ServiceCenter\AgentSession\Assertion\AgentSessionAssertions;
use App\ServiceCenter\AgentSession\Repository\AgentSessionRepository;
use App\ServiceCenter\Telephony\Http\Request\HangupChannelRequest;
use App\ServiceCenter\Telephony\Http\Request\HangupRequest;
use App\ServiceCenter\Telephony\Http\Request\MuteAgentRequest;
use App\ServiceCenter\Telephony\Http\Request\SwitchToChannelRequest;
use App\ServiceCenter\Telephony\Http\TelephonyResponseFactory;
use App\ServiceCenter\Telephony\Service\TelephonyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;

class ServiceCenterTelephonyController extends Controller
{
    public function getStateForCurrentAgentSession(
        Request $request,
        AgentSessionRepository $agentSessionRepository,
        TelephonyResponseFactory $telephonyResponseFactory
    ): JsonResource {
        $agentSession = $agentSessionRepository->getByAgent($request->user());

        $telephonySession = $agentSession->getAgentSessionLogEntry()->getTelephonySession();

        return $telephonyResponseFactory->create($telephonySession);
    }

    public function hangup(HangupRequest $request, TelephonyService $telephonyService): Response
    {
        AgentSessionAssertions::assertAgentSessionHasTelephonySession($request->getAgentSession());

        $telephonyService->hangupAgentChannel(
            $request->getAgentSession()->getAgentSessionLogEntry()->getTelephonySession()
        );

        return new Response();
    }

    public function hangupChannel(HangupChannelRequest $request, TelephonyService $telephonyService): Response
    {
        AgentSessionAssertions::assertAgentSessionHasTelephonySession($request->getAgentSession());

        $telephonyService->hangupChannel(
            $request->getAgentSession()->getAgentSessionLogEntry()->getTelephonySession(),
            $request->getChannelId()
        );

        return new Response();
    }

    public function switchToChannel(SwitchToChannelRequest $request, TelephonyService $telephonyService): Response
    {
        AgentSessionAssertions::assertAgentSessionHasTelephonySession($request->getAgentSession());

        $telephonyService->switchToChannel(
            $request->getAgentSession()->getAgentSessionLogEntry()->getTelephonySession(),
            $request->getChannelId()
        );

        return new Response();
    }

    public function muteAgent(MuteAgentRequest $request, TelephonyService $telephonyService): Response
    {
        AgentSessionAssertions::assertAgentSessionHasTelephonySession($request->getAgentSession());

        $telephonyService->holdAllChannels(
            $request->getAgentSession()->getAgentSessionLogEntry()->getTelephonySession()
        );

        return new Response();
    }
}
