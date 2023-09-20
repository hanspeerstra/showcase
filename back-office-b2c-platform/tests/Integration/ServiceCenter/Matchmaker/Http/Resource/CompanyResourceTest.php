<?php

declare(strict_types=1);

namespace Tests\Integration\ServiceCenter\Matchmaker\Http\Resource;

use App\Models\Office\Company;
use App\Models\Office\OpeningHours;
use App\ServiceCenter\Matchmaker\Http\Resource\CompanyResource;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Tests\Integration\IntegrationTestCase;

class CompanyResourceTest extends IntegrationTestCase
{
    public function testOpeningStatusWhenNoOpeningHoursSpecified(): void
    {
        $company = $this->givenCompany();

        $resource = (new CompanyResource($company))
            ->response()
            ->getData(true);

        $this->assertNull($resource['data']['openingStatus']);
    }

    /**
     * @dataProvider openingStatusProvider
     */
    public function testOpeningStatusWhenOpeningHoursSpecified(CarbonInterface $now, array $expected): void
    {
        $company = $this->givenCompany();

        OpeningHours::makeInstance($company, 'monday', '08:15', '19:00')
            ->save();

        Carbon::setTestNow($now);

        $resource = (new CompanyResource($company))
            ->response()
            ->getData(true);

        $this->assertEqualsCanonicalizing($expected, $resource['data']['openingStatus']);
    }

    public function openingStatusProvider(): array
    {
        $tz = 'Europe/Amsterdam';

        return [
            'Company is about to open' => [
                Carbon::parse('Monday 9 Aug 2022 08:00', $tz),
                [
                    'isClosed' => true,
                    'isClosedToday' => false,
                    'hasYetToOpenToday' => true,
                    'hasYetToCloseToday' => true,
                    'opensTodayAt' => '08:15',
                    'closesTodayAt' => '19:00',
                ],
            ],
            'Company is open' => [
                Carbon::parse('Monday 9 Aug 2022 12:00', $tz),
                [
                    'isClosed' => false,
                    'isClosedToday' => false,
                    'hasYetToOpenToday' => false,
                    'hasYetToCloseToday' => true,
                    'opensTodayAt' => '08:15',
                    'closesTodayAt' => '19:00',
                ],
            ],
            'Company is closed' => [
                Carbon::parse('Monday 9 Aug 2022 20:00', $tz),
                [
                    'isClosed' => true,
                    'isClosedToday' => false,
                    'hasYetToOpenToday' => false,
                    'hasYetToCloseToday' => false,
                    'opensTodayAt' => '08:15',
                    'closesTodayAt' => '19:00',
                ],
            ],
            'Closed all day' => [
                Carbon::parse('Tuesday 10 Aug 2022 11:00', $tz),
                [
                    'isClosed' => true,
                    'isClosedToday' => true,
                    'hasYetToOpenToday' => false,
                    'hasYetToCloseToday' => false,
                    'opensTodayAt' => null,
                    'closesTodayAt' => null,
                ],
            ],
        ];
    }

    private function givenCompany(): Company
    {
        return factory(Company::class)
            ->create(
                [
                    'imported' => false,
                ]
            );
    }
}
