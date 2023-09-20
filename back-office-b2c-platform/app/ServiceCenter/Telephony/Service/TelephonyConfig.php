<?php

declare(strict_types=1);

namespace App\ServiceCenter\Telephony\Service;

/**
 * Service center telephony configuration
 */
class TelephonyConfig
{
    /** @var string */
    private $outboundSessionProvider;

    /**
     * @param string $outboundSessionProvider which telephony provider to use for outbound SC telephony sessions
     */
    public function __construct(string $outboundSessionProvider)
    {
        $this->outboundSessionProvider = $outboundSessionProvider;
    }

    public function getOutboundSessionProvider(): string
    {
        return $this->outboundSessionProvider;
    }
}
