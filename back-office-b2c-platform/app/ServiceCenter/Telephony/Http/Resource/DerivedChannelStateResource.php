<?php

declare(strict_types=1);

namespace App\ServiceCenter\Telephony\Http\Resource;

use App\ServiceCenter\Telephony\DerivedChannelState;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DerivedChannelState
 */
class DerivedChannelStateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'channelId' => $this->getChannelId(),
            'reference' => $this->getReference(),
            'state' => $this->getState(),
            'lineNumber' => $this->getLineNumber() + 1,
            'phoneNumber' => $this->getRemotePhoneNumber() ? $this->getRemotePhoneNumber()->formatE164() : null,
            'audioConnectedToAgent' => $this->isAudioConnectedToAgent(),
            'companyId' => $this->getCompanyId(),
        ];
    }
}
