<?php

declare(strict_types=1);

namespace ServiceCenter\Statistics\Http;

use Tests\Integration\Concerns\InteractsWithAgentSession;
use Tests\Integration\IntegrationTestCase;

class ServiceCenterStatisticsControllerTest extends IntegrationTestCase
{
    use InteractsWithAgentSession;

    public function setUp(): void
    {
        parent::setUp();

        $this->loginAsAdmin();
        $this->givenUserHavingAgentSession($this->getLoggedInUser());
    }

    public function testIfItCanExecuteShow(): void
    {
        $fromDate = '2014-02-01';
        $tillDate = '2021-02-01';

        $response = $this->getJson(
            route('admin.sc.api.statistics.show', [
                'fromDate' => $fromDate,
                'tillDate' => $tillDate,
            ])
        );

        $response->assertOk();

        $response->assertJson([
            'data' => [
                'fromDate' => $fromDate,
                'tillDate' => $tillDate,
            ],
        ]);
    }
}
