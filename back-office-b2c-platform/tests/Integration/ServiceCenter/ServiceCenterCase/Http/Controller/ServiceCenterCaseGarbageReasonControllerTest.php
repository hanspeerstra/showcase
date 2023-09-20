<?php

declare(strict_types=1);

namespace ServiceCenter\ServiceCenterCase\Http\Controller;

use Tests\Integration\Concerns\InteractsWithAgentSession;
use Tests\Integration\IntegrationTestCase;

class ServiceCenterCaseGarbageReasonControllerTest extends IntegrationTestCase
{
    use InteractsWithAgentSession;

    private $adminUser;

    public function setUp(): void
    {
        parent::setUp();

        $this->adminUser = $this->loginAsAdmin();
    }

    public function testItAllUserGarbageEndpoint(): void
    {
        $this->givenUserHavingAgentSession($this->adminUser);

        $response = $this->get(
            route('admin.sc.api.cases.garbageReason.userGarbageReasons')
        );

        $response->assertOk();
    }
}
