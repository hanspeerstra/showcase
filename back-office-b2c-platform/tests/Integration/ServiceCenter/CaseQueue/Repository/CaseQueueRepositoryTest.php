<?php

declare(strict_types=1);

namespace Tests\Integration\ServiceCenter\CaseQueue\Repository;

use App\ServiceCenter\CaseQueue\CaseQueueEntry;
use App\ServiceCenter\CaseQueue\Repository\CaseQueueRepository;
use App\ServiceCenter\ServiceCenterCase\CaseType;
use App\ServiceCenter\ServiceCenterCase\Repository\ServiceCenterCaseRepository;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\ServiceCenter\WorkGroup\WorkGroup;
use App\Telephony\Session\Model\TelephonySession;
use Tests\Integration\IntegrationTestCase;

class CaseQueueRepositoryTest extends IntegrationTestCase
{
    /** @var CaseQueueRepository */
    private $SUT;

    /** @var WorkGroup */
    private $workGroup;

    /** @var CaseType */
    private $caseType;

    /** @var TelephonySession */
    private $telephonySession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->SUT = $this->app->make(CaseQueueRepository::class);

        $this->workGroup = factory(WorkGroup::class)->create();
        $this->caseType = factory(CaseType::class)->create();
        $this->telephonySession = factory(TelephonySession::class)->create();
    }

    public function testItCanInsert(): void
    {
        $case = $this->createCase();

        $this->assertDatabaseHas('sc_case_queue', [
            'case_id' => $case->getId(),
            'work_group_id' => $this->workGroup->getId(),
        ]);
    }

    public function testItCanDeleteCorrectly(): void
    {
        $case = $this->createCase();

        $caseQueueEntry = $this->SUT->findInitialQueueEntryByCase($case);

        $this->SUT->delete($caseQueueEntry);

        self::assertSame(0, CaseQueueEntry::query()->count());
    }

    public function testItCanInsertSameCaseAfterDelete(): void
    {
        $case = $this->createCase();

        $caseQueueEntry1 = $this->SUT->findInitialQueueEntryByCase($case);

        $this->SUT->delete($caseQueueEntry1);

        $caseQueueEntry2 = CaseQueueEntry::makeInstance($case, $this->workGroup);
        $this->SUT->insert($caseQueueEntry2);

        $this->assertDatabaseHas('sc_case_queue', [
            'case_id' => $case->getId(),
            'work_group_id' => $this->workGroup->getId(),
        ]);
    }

    public function testItFindsInitialQueueEntryForCase(): void
    {
        $case = $this->createCase();

        $expectedInitialCaseQueueEntry = $this->SUT->findInitialQueueEntryByCase($case);

        $this->SUT->delete($expectedInitialCaseQueueEntry);

        $caseQueueEntry = CaseQueueEntry::makeInstance($case, $this->workGroup);

        $this->SUT->insert($caseQueueEntry);
        $this->SUT->delete($caseQueueEntry);

        $initialCaseEntry = $this->SUT->findInitialQueueEntryByCase($case);

        self::assertNotNull($initialCaseEntry);
        self::assertEquals($expectedInitialCaseQueueEntry->getId(), $initialCaseEntry->getId());
    }

    private function createCase(): ServiceCenterCase
    {
        $case = ServiceCenterCase::makeInstance(
            $this->caseType,
            $this->workGroup,
            null,
            null,
            null,
            null
        );

        return $this->app->make(ServiceCenterCaseRepository::class)
            ->persist($case);
    }
}
