<?php

declare(strict_types=1);

namespace Tests\Integration\API;

use App\ServiceCenter\CaseQueue\CaseQueueEntry;
use App\ServiceCenter\CaseQueue\Repository\CaseQueueRepository;
use App\ServiceCenter\ServiceCenterCase\CaseType;
use App\ServiceCenter\ServiceCenterCase\Repository\ServiceCenterCaseRepository;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\ServiceCenter\WorkGroup\WorkGroup;
use Tests\Integration\Concerns\InteractsWithAgentSession;
use Tests\Integration\IntegrationTestCase;

class CaseQueueControllerTest extends IntegrationTestCase
{
    use InteractsWithAgentSession;

    private $agentSession;

    public function setUp(): void
    {
        parent::setUp();

        $this->loginAsAdmin();
        $this->agentSession = $this->givenUserHavingAgentSession($this->getLoggedInUser());
    }

    public function testItShowsCaseQueue(): void
    {
        $cases = $this->getCaseQueue();

        $response = $this->get(route('admin.sc.api.cases.queue.index'));

        $response->assertJson(
            [
                'data' => [
                    [
                        'id' => $cases[0]->getId(),
                        'workGroup' => [
                            'id' => $cases[0]->getWorkGroup()->getId(),
                        ],
                        'case' => [
                            'id' => $cases[0]->getCase()->getId(),
                        ],
                    ],
                ],
            ],
            true
        );
    }

    /**
     * @return CaseQueueEntry[]
     */
    public function getCaseQueue(): iterable
    {
        $workgroup = $this->agentSession->getWorkGroups()[0];
        $this->createCase($workgroup);
        $this->createCase($workgroup);

        return $this->app->make(CaseQueueRepository::class)->getAllByWorkGroups($workgroup);
    }

    public function createCase(WorkGroup $workGroup): ServiceCenterCase
    {
        $case = ServiceCenterCase::makeInstance(
            factory(CaseType::class)->create(),
            $workGroup,
            null,
            null,
            null,
            null
        );

        return $this->app->make(ServiceCenterCaseRepository::class)
            ->persist($case);
    }
}
