<?php

declare(strict_types=1);

namespace Tests\Integration\API;

use App\InternalPhone\InternalPhone;
use App\ServiceCenter\AgentSession\Service\AgentSessionService;
use App\ServiceCenter\ServiceCenterCase\CaseType;
use App\ServiceCenter\ServiceCenterCase\Repository\ServiceCenterCaseRepository;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\ServiceCenter\WorkGroup\WorkGroup;
use Tests\Integration\IntegrationTestCase;

class AgentSessionControllerTest extends IntegrationTestCase
{
    /** @var InternalPhone */
    private $internalPhone;

    /** @var int[] */
    private $workGroupIdList;

    /** @var WorkGroup[] */
    private $workgroups;

    /** @var ServiceCenterCaseRepository */
    private $serviceCenterCaseRepository;

    /** @var AgentSessionService */
    private $agentSessionService;

    /** @var \App\Auth\User */
    private $adminUser;

    public function setUp(): void
    {
        parent::setUp();

        $this->adminUser = $this->loginAsAdmin();

        $this->serviceCenterCaseRepository = $this->app->make(ServiceCenterCaseRepository::class);
        $this->agentSessionService = $this->app->make(AgentSessionService::class);

        /** @var InternalPhone $internalPhone */
        $this->internalPhone = factory(InternalPhone::class)->create();

        $this->workgroups[] = factory(WorkGroup::class)->create();
        $this->workgroups[] = factory(WorkGroup::class)->create();

        $this->workGroupIdList = [];
        foreach ($this->workgroups as $workGroup) {
            $this->workGroupIdList[] = $workGroup->getId();
        }
    }

    public function testItCanStartAnAgentSession(): void
    {
        $automaticCall = true;
        $priority = 2;

        $response = $this->postJson(
            route('admin.sc.api.agentSession.startAgentSession'),
            [
                'internalPhoneId' => $this->internalPhone->getId(),
                'workGroupIdList' => $this->workGroupIdList,
                'automaticallyAssignCase' => $automaticCall,
                'priority' => $priority,
            ]
        );
        $response->assertOk();

        $response->assertJson(
            [
                'data' => [
                    'internalPhone' => ['id' => $this->internalPhone->getId()],
                    'automaticallyAssignCase' => $automaticCall,
                    'priority' => $priority,
                ],
            ],
            true
        );
    }

    public function testItCanEndAnAgentSession(): void
    {
        $this->post(
            route('admin.sc.api.agentSession.startAgentSession'),
            [
                'internalPhoneId' => $this->internalPhone->getId(),
                'workGroupIdList' => $this->workGroupIdList,
                'automaticallyAssignCase' => true,
                'priority' => 1,
            ]
        );

        $response = $this->post(route('admin.sc.api.agentSession.endCurrentAgentSession'));

        $response->assertOk();
    }

    public function testItCanChangeAssignCaseAutomatically(): void
    {
        $this->post(
            route('admin.sc.api.agentSession.startAgentSession'),
            [
                'internalPhoneId' => $this->internalPhone->getId(),
                'workGroupIdList' => $this->workGroupIdList,
                'automaticallyAssignCase' => true,
                'priority' => 1,
            ]
        );

        $response = $this->patchJson(
            route('admin.sc.api.agentSession.agentSessionAutomaticQueue'),
            [
                'automaticallyAssignCase' => false,
                'priority' => null,
            ]
        );

        $response->assertOk();
    }

    public function testItManagerCanChangeAssignCaseAutomatically(): void
    {
        $response = $this->postJson(
            route('admin.sc.api.agentSession.startAgentSession'),
            [
                'internalPhoneId' => $this->internalPhone->getId(),
                'workGroupIdList' => $this->workGroupIdList,
                'automaticallyAssignCase' => true,
                'priority' => 1,
            ]
        );
        $response->assertOk();

        $targetAgentSession = $this->adminUser->getActiveAgentSession();
        self::assertTrue($targetAgentSession->isAutomaticallyAssignCase());

        // login as manager
        $this->loginAsAdmin();

        $response = $this->postJson(
            route('admin.sc.api.agentSession.startAgentSession'),
            [
                'internalPhoneId' => $this->internalPhone->getId(),
                'workGroupIdList' => $this->workGroupIdList,
                'automaticallyAssignCase' => false,
            ]
        );
        $response->assertOk();

        $response = $this->patchJson(
            route('admin.sc.api.agentSession.managerChangeAgentSessionAutomaticAutomatically'),
            [
                'agentSessionId' => $targetAgentSession->getId(),
                'automaticallyAssignCase' => false,
            ]
        );
        $response->assertOk();

        $targetAgentSession->refresh();
        self::assertFalse($targetAgentSession->isAutomaticallyAssignCase());
    }

    public function testThatAManagerCanEndAnAgentSession(): void
    {
        $response = $this->postJson(
            route('admin.sc.api.agentSession.startAgentSession'),
            [
                'internalPhoneId' => $this->internalPhone->getId(),
                'workGroupIdList' => $this->workGroupIdList,
                'automaticallyAssignCase' => true,
                'priority' => 1,
            ]
        );
        $response->assertOk();

        $case = $this->getCase();

        $case = $this->serviceCenterCaseRepository->persist($case);

        $targetAgentSession = $this->adminUser->getActiveAgentSession();
        self::assertNull($targetAgentSession->deleted_at);

        self::assertTrue($targetAgentSession->hasActiveCase());

        $response = $this->postJson(
            route('admin.sc.api.agentSession.managerForceEndAgentSession'),
            [
                'agentSessionId' => $targetAgentSession->getId(),
            ]
        );

        $response->assertOk();

        $targetAgentSession->refresh();
        $case->refresh();

        self::assertNotNull($targetAgentSession->deleted_at);
        self::assertFalse($targetAgentSession->hasActiveCase());
        self::assertNull($case->getCaseEntry()->getAssignedAgent());
    }

    public function testThatItCanAssignToACase(): void
    {
        $response = $this->post(
            route('admin.sc.api.agentSession.startAgentSession'),
            [
                'internalPhoneId' => $this->internalPhone->getId(),
                'workGroupIdList' => $this->workGroupIdList,
                'automaticallyAssignCase' => false,
            ]
        );

        $response->assertOk();

        $case = $this->getCase();

        $case = $this->serviceCenterCaseRepository->persist($case);

        self::assertNull($case->getCurrentAgentSessionLogEntry());

        $response = $this->post(
            route('admin.sc.api.agentSession.assignCase', [$case->getId()])
        );

        $response->assertOk();

        $case = $this->serviceCenterCaseRepository->refresh($case);

        self::assertNotNull($case->getCurrentAgentSessionLogEntry());
    }

    public function testThatItCanUnassignAgentFromACase(): void
    {
        $response = $this->post(
            route('admin.sc.api.agentSession.startAgentSession'),
            [
                'internalPhoneId' => $this->internalPhone->getId(),
                'workGroupIdList' => $this->workGroupIdList,
                'automaticallyAssignCase' => false,
            ]
        );

        $response->assertOk();

        $case = $this->getCase();

        $case = $this->serviceCenterCaseRepository->persist($case);

        $response = $this->post(
            route('admin.sc.api.agentSession.assignCase', [$case->getId()])
        );

        $response->assertOk();

        $case = $this->serviceCenterCaseRepository->refresh($case);

        self::assertNotNull($case->getCurrentAgentSessionLogEntry());

        $response = $this->post(
            route('admin.sc.api.agentSession.unassignCase', [$case->getId()])
        );

        $response->assertOk();

        $case = $this->serviceCenterCaseRepository->refresh($case);

        self::assertNull($case->getCurrentAgentSessionLogEntry());
    }

    public function testThatItCanRejectCase(): void
    {
        $automaticallyAssignCase = true;
        $priority = 1;

        $agentSession = $this->agentSessionService->createAndStartSession(
            $this->adminUser,
            $this->internalPhone,
            $automaticallyAssignCase,
            $priority,
            ...$this->workgroups
        );

        $caseType = factory(CaseType::class)->create();

        $case = ServiceCenterCase::makeInstance(
            $caseType,
            $this->workgroups[0],
            null,
            null,
            null,
            null
        );

        $this->serviceCenterCaseRepository->persist($case);

        $case = $this->serviceCenterCaseRepository->refresh($case);

        self::assertNotNull($case->getCurrentAgentSessionLogEntry());

        self::assertEquals($agentSession, $case->getCurrentAgentSessionLogEntry()->getAgentSession());

        $response = $this->post(
            route('admin.sc.api.agentSession.rejectCase', [$case->getId()])
        );

        $response->assertOk();

        $case = $this->serviceCenterCaseRepository->refresh($case);

        self::assertNull($case->getCurrentAgentSessionLogEntry());
    }

    public function testThatItCanPauseCase(): void
    {
        $this->post(
            route('admin.sc.api.agentSession.startAgentSession'),
            [
                'internalPhoneId' => $this->internalPhone->getId(),
                'workGroupIdList' => $this->workGroupIdList,
                'automaticallyAssignCase' => false,
            ]
        );

        $case = $this->getCase();

        $this->serviceCenterCaseRepository->persist($case);

        $response = $this->post(
            route('admin.sc.api.agentSession.assignCase', [$case->getId()])
        );

        $response->assertOk();

        $response = $this->post(
            route('admin.sc.api.agentSession.pauseCase', [$case->getId()])
        );

        $response->assertOk();

        $case = $this->serviceCenterCaseRepository->refresh($case);

        self::assertNull($case->getCurrentAgentSessionLogEntry());
    }

    public function testThatItGetsCurrentAgentSession(): void
    {
        $response = $this->post(
            route('admin.sc.api.agentSession.startAgentSession'),
            [
                'internalPhoneId' => $this->internalPhone->getId(),
                'workGroupIdList' => $this->workGroupIdList,
                'automaticallyAssignCase' => true,
                'priority' => 1,
            ]
        );

        $response->assertOk();

        $result = json_decode($response->getContent(), true);

        $agentSessionId = $result['data']['agentSessionId'];

        $response = $this->get(
            route('admin.sc.api.agentSession.showCurrentAgentSession')
        );

        $response->assertOk();

        $response->assertJson(
            [
                'data' => [
                    'agentSessionId' => $agentSessionId,
                ],
            ],
            true
        );
    }

    public function testThatShowsCorrectAgentSession(): void
    {
        $response = $this->post(
            route('admin.sc.api.agentSession.startAgentSession'),
            [
                'internalPhoneId' => $this->internalPhone->getId(),
                'workGroupIdList' => $this->workGroupIdList,
                'automaticallyAssignCase' => true,
                'priority' => 1,
            ]
        );

        $response->assertOk();

        $result = json_decode($response->getContent(), true);

        $agentSessionId = $result['data']['agentSessionId'];

        $response = $this->get(
            route('admin.sc.api.agentSession.show', [$agentSessionId])
        );

        $response->assertOk();

        $response->assertJson(
            [
                'data' => [
                    'agentSessionId' => $agentSessionId,
                ],
            ],
            true
        );
    }

    public function testThatShowsActiveAgentSessions(): void
    {
        $response = $this->post(
            route('admin.sc.api.agentSession.startAgentSession'),
            [
                'internalPhoneId' => $this->internalPhone->getId(),
                'workGroupIdList' => $this->workGroupIdList,
                'automaticallyAssignCase' => true,
                'priority' => 1,
            ]
        );

        $response->assertOk();

        $result = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $agentSessionId = $result['data']['agentSessionId'];

        $response = $this->get(
            route('admin.sc.api.agentSession.activeAgentSessions'),
            []
        );

        $response->assertOk();

        $response->assertJson(
            [
                'data' => [
                    [
                        'agentSessionId' => $agentSessionId,
                    ],
                ],
            ],
            true
        );
    }

    public function testThatShowUniqueAgents(): void
    {
        $response = $this->post(
            route('admin.sc.api.agentSession.startAgentSession'),
            [
                'internalPhoneId' => $this->internalPhone->getId(),
                'workGroupIdList' => $this->workGroupIdList,
                'automaticallyAssignCase' => true,
                'priority' => 1,
            ]
        );

        $response->assertOk();

        $result = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $userId = $result['data']['user']['userId'];

        $response = $this->getJson(
            route('admin.sc.api.agentSession.uniqueAgents')
        );

        $response->assertOk();

        $response->assertJson([
            'data' => [
                [
                    'userId' => $userId,
                ],
            ],
        ]);
    }

    private function getCase(): ServiceCenterCase
    {
        return ServiceCenterCase::makeInstance(
            factory(CaseType::class)->create(),
            $this->workgroups[0],
            null,
            null,
            null,
            null
        );
    }
}
