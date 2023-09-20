<?php

declare(strict_types=1);

namespace App\ServiceCenter\Telephony\Http\Resource;

use App\ServiceCenter\Telephony\DerivedTelephonyState;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DerivedTelephonyState
 */
class DerivedTelephonyStateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'channels' => DerivedChannelStateResource::collection($this->getChannels()),
            'numberOfChannels' => $this->getActiveChannelCount(),
            'agentParticipatesInCall' => $this->agentParticipatesInCall(),
            'agentAnswered' => $this->hasAgentAnswered(),
            'onHold' => $this->isOnHold(),
            'forwarded' => $this->isForwarded(),
        ];
    }
}
