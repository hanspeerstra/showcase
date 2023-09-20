<?php

declare(strict_types=1);

namespace App\ServiceCenter\Telephony;

class ChannelReferences
{
    public const CALLER = 'caller'; // In case of an inbound call, the reference of the inbound channel
    public const COMPANY = 'company'; // When calling outbound to a company
    public const AGENT = 'agent'; // The outbound channel to the agent's device
    public const CUSTOMER = 'customer'; // When calling outbound to a customer
}
