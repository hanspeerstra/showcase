<?php

declare(strict_types=1);

namespace Tests\Integration\ServiceCenter\ServiceCenterCase;

use App\Auth\User;
use App\Exceptions\Eloquent\CouldNotUpdateModelException;
use App\ServiceCenter\ServiceCenterCase\CaseType;
use App\ServiceCenter\ServiceCenterCase\Repository\ServiceCenterCaseRepository;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCaseEntry;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCaseGarbageReason;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCaseNote;
use App\ServiceCenter\WorkGroup\WorkGroup;
use Carbon\CarbonImmutable;
use Tests\Integration\Concerns\InteractsWithTelephonySession;
use Tests\Integration\IntegrationTestCase;

class ServiceCenterCaseRepositoryTest extends IntegrationTestCase
{
    use InteractsWithTelephonySession;

    /** @var ServiceCenterCaseRepository */
    private $SUT;

    /** @var CaseType */
    private $caseType;

    /** @var WorkGroup */
    private $workGroup;

    /** @var User */
    private $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->caseType = factory(CaseType::class)->create();
        $this->workGroup = factory(WorkGroup::class)->create();
        $this->agent = factory(User::class)->create();

        $this->SUT = $this->app->make(ServiceCenterCaseRepository::class);
    }

    public function testItCanPersistNewlyCreatedCase(): void
    {
        $case = $this->getCase();

        $case = $this->SUT->persist($case);

        $this->assertDatabaseHas('sc_cases', [
            'id' => $case->getId(),
        ]);

        $this->assertDatabaseHas('sc_case_entries', [
            'case_id' => $case->getId(),
            'case_type_id' => $case->getCaseEntry()->getCaseType()->getId(),
            'work_group_id' => $case->getCaseEntry()->getWorkGroup()->getId(),
            'telephony_session_id' => $case->getActiveTelephonySession()->getId(),
            'assigned_agent_id' => null,
            'deleted_at' => null,
        ]);
    }

    public function testItPersistsCaseEntryWhenItIsUpdated(): void
    {
        $case = $this->getCase();

        $case = $this->SUT->persist($case);
        $caseEntry = $case->getCaseEntry();

        $caseType2 = factory(CaseType::class)->create();

        $updatedCaseEntry = ServiceCenterCaseEntry::makeInstance(
            $caseType2,
            $case->getCaseEntry()->getWorkGroup(),
            $case->getActiveTelephonySession(),
            $case->getCaseEntry()->getAssignedAgent()
        );

        /** @var ServiceCenterCase $updatedCase */
        $updatedCase = ServiceCenterCase::find($case->getId());
        $updatedCase->setCaseEntry($updatedCaseEntry);

        $this->SUT->persist($updatedCase);

        $this->assertDatabaseHas('sc_case_entries', [
            'case_id' => $case->getId(),
            'case_type_id' => $caseEntry->getCaseType()->getId(),
            'work_group_id' => $caseEntry->getWorkGroup()->getId(),
            'telephony_session_id' => $case->getActiveTelephonySession()->getId(),
            'assigned_agent_id' => null,
        ]);

        $this->assertDatabaseHas('sc_case_entries', [
            'case_id' => $case->getId(),
            'case_type_id' => $updatedCaseEntry->getCaseType()->getId(),
            'work_group_id' => $updatedCaseEntry->getWorkGroup()->getId(),
            'telephony_session_id' => $case->getActiveTelephonySession()->getId(),
            'assigned_agent_id' => null,
            'deleted_at' => null,
        ]);
    }

    public function testItDoesNotPersistCaseEntryWhenItHasNotChanged(): void
    {
        $case = $this->getCase();

        $case = $this->SUT->persist($case);
        $caseEntry = $case->getCaseEntry();

        $updatedCaseEntry = ServiceCenterCaseEntry::makeInstance(
            $caseEntry->getCaseType(),
            $caseEntry->getWorkGroup(),
            $case->getActiveTelephonySession(),
            $caseEntry->getAssignedAgent()
        );

        /** @var ServiceCenterCase $updatedCase */
        $updatedCase = ServiceCenterCase::find($case->getId());
        $updatedCase->setCaseEntry($updatedCaseEntry);

        $this->SUT->persist($updatedCase);

        $caseEntryCount = ServiceCenterCaseEntry::withTrashed()
            ->where('case_id', '=', $case->getId())
            ->count();

        self::assertSame(1, $caseEntryCount);
    }

    public function testItPersistsUpdatedCase(): void
    {
        $case = $this->getCase();

        $case = $this->SUT->persist($case);

        $startedAt = new CarbonImmutable('2020-08-19 15:00:00');
        $closedAt = new CarbonImmutable('2020-08-19 15:30:00');
        $garbageReason = ServiceCenterCaseGarbageReason::query()
            ->belongsToLabel(ServiceCenterCaseGarbageReason::LABEL_CLOSED_BY_SYSTEM_USER)
            ->firstOrFail();
        $case
            ->setGarbageReason($garbageReason)
            ->setStartedAt($startedAt)
            ->setClosedAt($closedAt);

        $this->SUT->persist($case);

        $this->assertDatabaseHas('sc_cases', [
            'id' => $case->getId(),
            'garbage_reason_id' => $garbageReason->getId(),
            'started_at' => $startedAt,
            'closed_at' => $closedAt,
        ]);
    }

    public function testThatItCanCreateAndUpdateACaseNote(): void
    {
        $case = $this->getCase();

        $case = $this->SUT->persist($case);

        $testNote = 'testNote';
        $caseNote = ServiceCenterCaseNote::makeInstance($case, $this->agent, $testNote);
        $this->SUT->insertCaseNote($caseNote);

        $this->assertDatabaseHas('sc_case_notes', [
            'id' => $caseNote->getId(),
            'case_id' => $case->getId(),
            'agent_id' => $this->agent->getId(),
            'note' => $testNote,
        ]);

        $caseNoteTwo = ServiceCenterCaseNote::makeInstance($case, $this->agent, $testNote);
        $this->SUT->insertCaseNote($caseNoteTwo);

        $this->assertDatabaseHas('sc_case_notes', [
            'id' => $caseNoteTwo->getId(),
            'case_id' => $case->getId(),
            'agent_id' => $this->agent->getId(),
            'note' => $testNote,
        ]);

        self::assertNotEquals($caseNote->getId(), $caseNoteTwo->getId());

        $updatedNoteText = 'updatedNoteText';
        $caseNote->setNote($updatedNoteText);

        $this->SUT->updateCaseNote($caseNote);

        $this->assertDatabaseHas('sc_case_notes', [
            'id' => $caseNote->getId(),
            'case_id' => $case->getId(),
            'agent_id' => $this->agent->getId(),
            'note' => $updatedNoteText,
        ]);
    }

    public function testThatItDoesNotInsertACaseNoteOnUpdate(): void
    {
        $case = $this->getCase();

        $case = $this->SUT->persist($case);

        $caseNote = ServiceCenterCaseNote::makeInstance($case, $this->agent, 'something');

        $this->expectException(CouldNotUpdateModelException::class);
        $this->expectErrorMessage('Cannot update non-existing model');

        $this->SUT->updateCaseNote($caseNote);
    }

    private function getCase(): ServiceCenterCase
    {
        return ServiceCenterCase::makeInstance(
            $this->caseType,
            $this->workGroup,
            null,
            null,
            $this->givenTelephonySession(true, false),
            null
        );
    }
}
