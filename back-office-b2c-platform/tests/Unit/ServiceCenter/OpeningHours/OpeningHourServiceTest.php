<?php

declare(strict_types=1);

namespace Tests\Unit\ServiceCenter\OpeningHours;

use App\ServiceCenter\OpeningHours\OpeningHourService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use PHPUnit\Framework\TestCase;

class OpeningHourServiceTest extends TestCase
{
    /** @var OpeningHourService */
    private $SUT;

    protected function setUp(): void
    {
        $this->SUT = new OpeningHourService(
            '08:00',
            '18:00',
            '09:00',
            '17:00',
            '10:00',
            '16:00'
        );
    }

    /**
     * @dataProvider isOpenedProvider
     */
    public function testIsOpened(CarbonInterface $atDate, bool $expected)
    {
        self::assertEquals($expected, $this->SUT->isOpened($atDate));
    }

    public function isOpenedProvider(): iterable
    {
        // Tuesday
        yield [CarbonImmutable::parse('2020-10-20T00:00'), false];
        yield [CarbonImmutable::parse('2020-10-20T07:59:59'), false];
        yield [CarbonImmutable::parse('2020-10-20T08:00'), true];
        yield [CarbonImmutable::parse('2020-10-20T17:59:59'), true];
        yield [CarbonImmutable::parse('2020-10-20T18:00'), false];
        yield [CarbonImmutable::parse('2020-10-20T23:59:59'), false];

        // Saturday
        yield [CarbonImmutable::parse('2020-10-24T00:00'), false];
        yield [CarbonImmutable::parse('2020-10-24T08:59:59'), false];
        yield [CarbonImmutable::parse('2020-10-24T09:00'), true];
        yield [CarbonImmutable::parse('2020-10-24T16:59:59'), true];
        yield [CarbonImmutable::parse('2020-10-24T17:00'), false];
        yield [CarbonImmutable::parse('2020-10-24T23:59:59'), false];

        // Sunday
        yield [CarbonImmutable::parse('2020-10-25T00:00'), false];
        yield [CarbonImmutable::parse('2020-10-25T09:59:59'), false];
        yield [CarbonImmutable::parse('2020-10-25T10:00'), true];
        yield [CarbonImmutable::parse('2020-10-25T15:59:59'), true];
        yield [CarbonImmutable::parse('2020-10-25T16:00'), false];
        yield [CarbonImmutable::parse('2020-10-25T23:59:59'), false];
    }

    /**
     * @dataProvider nextOpeningTimeProvider
     */
    public function testNextOpeningTime(CarbonInterface $atDate, CarbonImmutable $expected)
    {
        self::assertEquals($expected, $this->SUT->getNextOpeningTime($atDate));
    }

    public function nextOpeningTimeProvider()
    {
        // Friday
        yield [CarbonImmutable::parse('2020-10-23T00:00'), CarbonImmutable::parse('2020-10-23T08:00')];
        yield [CarbonImmutable::parse('2020-10-23T07:59:59'), CarbonImmutable::parse('2020-10-23T08:00')];
        yield [CarbonImmutable::parse('2020-10-23T08:00'), CarbonImmutable::parse('2020-10-24T09:00')];
    }
}
