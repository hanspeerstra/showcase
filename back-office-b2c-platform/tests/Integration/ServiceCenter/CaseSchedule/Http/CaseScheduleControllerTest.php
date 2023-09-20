<?php

declare(strict_types=1);

namespace Tests\Integration\ServiceCenter\CaseSchedule\Http;

use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\CaseQueue\Service\CaseQueueService;
use App\ServiceCenter\CaseSchedule\Repository\CaseScheduleRepository;
use App\ServiceCenter\CaseSchedule\Service\CaseScheduleService;
use Carbon\CarbonImmutable;
use Tests\Integration\Concerns\InteractsWithAgentSession;
use Tests\Integration\Concerns\InteractsWithCase;
use Tests\Integration\IntegrationTestCase;

class CaseScheduleControllerTest extends IntegrationTestCase
{
    use InteractsWithCase, InteractsWithAgentSession;

    /** @var AgentSession */
    private $agentSession;

    protected function setUp(): void
    {
        parent::setUp();

        $user = $this->loginAsAdmin();
        $this->agentSession = $this->givenUserHavingAgentSession($user);
    }

    public function testItCanRescheduleCase(): void
    {
        $case = $this->givenCase();

        app(CaseScheduleService::class)
            ->scheduleCase($case, CarbonImmutable::tomorrow());

        $newDueAt = CarbonImmutable::now()->addDays(3);

        $response = $this->postJson(
            route('admin.sc.api.cases.schedule.reschedule', [$case->getId()]),
            [
                'dueAt' => $newDueAt->format('Y-m-d H:i:s'),
            ]
        );

        $response->assertSuccessful();

        $scheduleEntry = app(CaseScheduleRepository::class)->findByCase($case);

        self::assertNotNull($scheduleEntry);
        self::assertEquals($newDueAt->setMicroseconds(0), $scheduleEntry->getDueAt());
    }

    public function testItCannotRescheduleCaseWhichIsNotScheduled(): void
    {
        $case = $this->givenCase();

        $newDueAt = CarbonImmutable::now()->addDays(3);

        $response = $this->postJson(
            route('admin.sc.api.cases.schedule.reschedule', [$case->getId()]),
            [
                'dueAt' => $newDueAt->format('Y-m-d H:i:s'),
            ]
        );

        $response->assertNotFound();
    }

    public function testItCanQueueScheduledCase(): void
    {
        $case = $this->givenCase();

        app(CaseScheduleService::class)
            ->scheduleCase($case, CarbonImmutable::tomorrow());

        app(CaseQueueService::class)->dequeueByCase($case);

        $response = $this->postJson(
            route('admin.sc.api.cases.schedule.queue', [$case->getId()])
        );

        $response->assertSuccessful();

        self::assertTrue(app(CaseQueueService::class)->isQueued($case));
    }

    public function testItCannotQueueCaseWhichIsNotScheduled(): void
    {
        $case = $this->givenCase();

        app(CaseQueueService::class)->dequeueByCase($case);

        $response = $this->postJson(
            route('admin.sc.api.cases.schedule.queue', [$case->getId()])
        );

        $response->assertNotFound();
    }

    public function testItCanAssignScheduledCase(): void
    {
        $case = $this->givenCase();

        app(CaseQueueService::class)->dequeueByCase($case);

        app(CaseScheduleService::class)->scheduleCase($case, CarbonImmutable::tomorrow());

        $response = $this->postJson(
            route('admin.sc.api.cases.schedule.assign', [$case->getId()])
        );

        $response->assertSuccessful();

        $case->refresh();

        self::assertFalse(app(CaseScheduleService::class)->isScheduled($case));
        self::assertFalse(app(CaseQueueService::class)->isQueued($case));
        self::assertNotNull($case->getCaseEntry()->getAssignedAgent());
        self::assertSame($this->agentSession->getUser()->getId(), $case->getCaseEntry()->getAssignedAgent()->getId());
        self::assertNotNull($this->agentSession->getActiveCase());
        self::assertSame($case->getId(), $this->agentSession->getActiveCase()->getId());
    }

    public function testItCannotAssignScheduledCaseWhichIsNotScheduled(): void
    {
        $case = $this->givenCase();

        app(CaseQueueService::class)->dequeueByCase($case);

        $response = $this->postJson(
            route('admin.sc.api.cases.schedule.assign', [$case->getId()])
        );

        $response->assertNotFound();
    }
}
