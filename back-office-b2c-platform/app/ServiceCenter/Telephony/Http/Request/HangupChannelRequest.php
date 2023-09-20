<?php

declare(strict_types=1);

namespace App\ServiceCenter\Telephony\Http\Request;

use App\Auth\User;
use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\Telephony\DerivedTelephonyState;
use App\ServiceCenter\Telephony\Factory\DerivedTelephonyStateFactory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Psr\Log\LoggerInterface;

class HangupChannelRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'channelId' => ['required'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->setCustomMessages([
            'telephony_channel_not_active' => __('Deze lijn is niet actief.'),
        ]);

        $validator->after(function (Validator $validator) {
            if ($this->has('channelId') && null !== $this->getAgent()->getActiveAgentSession()) {
                $derivedTelephonyState = $this->getDerivedTelephonyState();

                if (!$derivedTelephonyState->hasChannel($this->getChannelId())) {
                    $this->container->make(LoggerInterface::class)
                        ->error(sprintf(
                            'HangupChannelRequest telephony_channel_not_active (%s) AgentSession (ID: %s)',
                            $this->getChannelId(),
                            $this->getAgentSession()->getId()
                        ));

                    $validator->addFailure('channelId', 'telephony_channel_not_active');
                }
            }
        });
    }

    private function getDerivedTelephonyState(): DerivedTelephonyState
    {
        /** @var DerivedTelephonyStateFactory $derivedTelephonyStateFactory */
        $derivedTelephonyStateFactory = $this->container->make(DerivedTelephonyStateFactory::class);

        return $derivedTelephonyStateFactory->createFromTelephonySession(
            $this->getAgentSession()->getAgentSessionLogEntry()->getTelephonySession()
        );
    }

    public function getChannelId(): string
    {
        return $this->input('channelId');
    }

    public function getAgentSession(): AgentSession
    {
        return $this->getAgent()->getActiveAgentSession();
    }

    private function getAgent(): User
    {
        return $this->user();
    }
}
