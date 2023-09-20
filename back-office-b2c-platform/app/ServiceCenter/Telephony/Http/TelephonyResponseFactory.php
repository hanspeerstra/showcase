<?php

declare(strict_types=1);

namespace App\ServiceCenter\Telephony\Http;

use App\ServiceCenter\Telephony\DerivedTelephonyState;
use App\ServiceCenter\Telephony\Factory\DerivedTelephonyStateFactory;
use App\ServiceCenter\Telephony\Http\Resource\DerivedTelephonyStateResource;
use App\Telephony\Session\Http\Resource\TelephonySessionResource;
use App\Telephony\Session\Http\Resource\TelephonySessionStateResource;
use App\Telephony\Session\Model\TelephonySession;
use App\Telephony\Session\StateMachineFactory;
use Illuminate\Http\Resources\Json\JsonResource;

class TelephonyResponseFactory
{
    /** @var StateMachineFactory */
    private $telephonySessionStateMachineFactory;
    /** @var DerivedTelephonyStateFactory */
    private $derivedTelephonyStateFactory;

    public function __construct(
        StateMachineFactory $telephonySessionStateMachineFactory,
        DerivedTelephonyStateFactory $serviceCenterTelephonyService
    ) {
        $this->telephonySessionStateMachineFactory = $telephonySessionStateMachineFactory;
        $this->derivedTelephonyStateFactory = $serviceCenterTelephonyService;
    }

    public function create(?TelephonySession $telephonySession): JsonResource
    {
        if (null === $telephonySession) {
            return new JsonResource([
                'derivedTelephonyState' => new DerivedTelephonyStateResource(DerivedTelephonyState::inactive()),
                'telephonySession' => null,
                'telephonySessionState' => null,
            ]);
        }

        $telephonySessionState = $this->telephonySessionStateMachineFactory
            ->fromSession($telephonySession)
            ->getState();

        $derivedTelephonyState = $this->derivedTelephonyStateFactory
            ->createFromTelephonySession($telephonySession);

        return new JsonResource([
            'derivedTelephonyState' => new DerivedTelephonyStateResource($derivedTelephonyState),
            'telephonySession' => new TelephonySessionResource($telephonySession),
            'telephonySessionState' => new TelephonySessionStateResource($telephonySessionState),
        ]);
    }
}
