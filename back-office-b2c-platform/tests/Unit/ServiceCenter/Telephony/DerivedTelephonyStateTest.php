<?php

declare(strict_types=1);

namespace Tests\Unit\ServiceCenter\Telephony;

use App\ServiceCenter\Telephony\ChannelReferences;
use App\ServiceCenter\Telephony\DerivedChannelState;
use App\ServiceCenter\Telephony\DerivedTelephonyState;
use App\ServiceCenter\Telephony\Exception\NoSuchChannelException;
use PHPUnit\Framework\TestCase;
use Propaganistas\LaravelPhone\PhoneNumber;

class DerivedTelephonyStateTest extends TestCase
{
    public function testItHasCorrectInactiveState(): void
    {
        $derivedState = DerivedTelephonyState::inactive();

        self::assertSame(0, $derivedState->getActiveChannelCount());
        self::assertCount(0, $derivedState->getChannels());
        self::assertFalse($derivedState->hasChannel('some-id'));
        self::assertFalse($derivedState->hasChannelByReference('some-reference'));
        self::assertFalse($derivedState->agentParticipatesInCall());
        self::assertFalse($derivedState->isForwarded());
        self::assertFalse($derivedState->isOnHold());
        self::assertFalse($derivedState->hasAgentAnswered());
    }

    public function testItCreatesCorrectlyFromChannels(): void
    {
        $channels = [
            new DerivedChannelState(
                'channel-1',
                0,
                null,
                null,
                null,
                DerivedChannelState::STATE_ANSWERED,
                true,
                null
            ),
            new DerivedChannelState(
                'channel-2',
                1,
                ChannelReferences::COMPANY,
                null,
                PhoneNumber::make('+31612345678'),
                DerivedChannelState::STATE_ANSWERED,
                false,
                null
            ),
        ];

        $derivedState = new DerivedTelephonyState($channels, false, false, false);

        self::assertSame(2, $derivedState->getActiveChannelCount());
        self::assertCount(2, $derivedState->getChannels());
        self::assertTrue($derivedState->hasChannel('channel-1'));
        self::assertTrue($derivedState->hasChannel('channel-2'));
        self::assertFalse($derivedState->hasChannel('non-existing'));
        self::assertTrue($derivedState->hasChannelByReference(ChannelReferences::COMPANY));
        self::assertFalse($derivedState->hasChannelByReference('non-existing'));
        self::assertSame($channels[1], $derivedState->getChannelByReference(ChannelReferences::COMPANY));
        self::assertTrue($derivedState->agentParticipatesInCall());
    }

    public function testAgentIsInCallIfLinesAreOnHold(): void
    {
        $channels = [
            new DerivedChannelState(
                'channel-1',
                0,
                null,
                null,
                null,
                DerivedChannelState::STATE_ANSWERED,
                false,
                null
            ),
            new DerivedChannelState(
                'channel-2',
                1,
                ChannelReferences::COMPANY,
                null,
                PhoneNumber::make('+31612345678'),
                DerivedChannelState::STATE_ANSWERED,
                false,
                null
            ),
        ];

        $derivedState = new DerivedTelephonyState($channels, true, false, false);

        self::assertTrue($derivedState->agentParticipatesInCall());
    }

    public function testAgentIsNotInCallIfCallIsForwarded(): void
    {
        $channels = [
            new DerivedChannelState(
                'channel-1',
                0,
                null,
                null,
                null,
                DerivedChannelState::STATE_ANSWERED,
                false,
                null
            ),
            new DerivedChannelState(
                'channel-2',
                1,
                ChannelReferences::COMPANY,
                null,
                PhoneNumber::make('+31612345678'),
                DerivedChannelState::STATE_ANSWERED,
                false,
                null
            ),
        ];

        $derivedState = new DerivedTelephonyState($channels, false, true, false);

        self::assertFalse($derivedState->agentParticipatesInCall());
    }

    public function testItThrowsExceptionIfChannelIdDoesNotExist(): void
    {
        $this->expectException(NoSuchChannelException::class);

        $derivedState = new DerivedTelephonyState([], false, false, false);

        $derivedState->getChannel('some-id');
    }

    public function testItThrowsExceptionIfChannelReferenceDoesNotExist(): void
    {
        $this->expectException(NoSuchChannelException::class);

        $derivedState = new DerivedTelephonyState([], false, false, false);

        $derivedState->getChannelByReference('some-reference');
    }

    public function testItThrowsExceptionIfInvalidStateIsGiven(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DerivedChannelState(
            'channel-id',
            0,
            null,
            null,
            null,
            'invalid-state',
            false,
            null
        );
    }
}
