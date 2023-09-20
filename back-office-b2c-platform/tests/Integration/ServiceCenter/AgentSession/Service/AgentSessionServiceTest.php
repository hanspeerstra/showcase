<?php

namespace Tests\Integration\ServiceCenter\AgentSession\Service;

use App\InternalPhone\InternalPhone;
use App\InternalPhone\Repository\InternalPhoneRepository;
use App\Auth\User;
use App\Repositories\Backend\Auth\UserRepository;
use App\ServiceCenter\AgentSession\AgentSessionStatus;
use App\ServiceCenter\AgentSession\Repository\AgentSessionRepository;
use App\ServiceCenter\AgentSession\Service\AgentSessionService;
use App\ServiceCenter\WorkGroup\WorkGroup;
use InternalPhoneSeeder;
use Tests\Integration\IntegrationTestCase;
use UserTableSeeder;

/**
 * @covers \App\ServiceCenter\AgentSession\Service\AgentSessionService
 */
class AgentSessionServiceTest extends IntegrationTestCase
{
    /** @var AgentSessionService */
    private $SUT;

    /** @var UserRepository */
    private $userRepository;

    /** @var AgentSessionRepository */
    private $agentSessionRepository;

    /** @var InternalPhoneRepository */
    private $internalPhoneRepository;

    /** @var WorkGroup[] */
    private $workGroups = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->seed([
            UserTableSeeder::class,
            InternalPhoneSeeder::class,
        ]);

        $this->SUT = $this->app->make(AgentSessionService::class);

        $this->userRepository = $this->app->make(UserRepository::class);

        $this->agentSessionRepository = $this->app->make(AgentSessionRepository::class);

        $this->internalPhoneRepository = $this->app->make(InternalPhoneRepository::class);

        $this->workGroups[] = factory(WorkGroup::class)->create();
        $this->workGroups[] = factory(WorkGroup::class)->create();
    }

    public function testItCanOnlyStartOneAgentSession(): void
    {
        /** @var User $user */
        $user = $this->userRepository->getById(10);

        $internalPhone = $this->getInternalPhone();

        $automaticCall = true;
        $priority = 1;

        $this->SUT->createAndStartSession(
            $user,
            $internalPhone,
            $automaticCall,
            $priority,
            ...$this->workGroups
        );

        $this->expectExceptionMessageMatches('/Integrity constraint violation/');

        $this->SUT->createAndStartSession(
            $user,
            $internalPhone,
            $automaticCall,
            $priority,
            ...$this->workGroups
        );
    }

    public function testItCanCreateSecondAgentSessionAfterDelete(): void
    {
        /** @var User $user */
        $user = $this->userRepository->getById(10);

        $internalPhone = $this->getInternalPhone();

        $automaticCall = true;
        $priority = 1;

        $agentSession = $this->SUT->createAndStartSession(
            $user,
            $internalPhone,
            $automaticCall,
            $priority,
            ...$this->workGroups
        );

        $this->agentSessionRepository->delete($agentSession);

        $agentSession = $this->SUT->createAndStartSession(
            $user,
            $internalPhone,
            $automaticCall,
            $priority,
            ...$this->workGroups
        );

        $this->assertDatabaseHas('sc_agent_sessions', [
            'id' => $agentSession->getId(),
            'user_id' => $agentSession->getUser()->getId(),
        ]);

        $this->assertDatabaseHas('sc_agent_session_log', [
            'id' => $agentSession->getAgentSessionLogEntry()->getId(),
            'agent_session_id' => $agentSession->getId(),
        ]);

        foreach ($this->workGroups as $workGroup) {
            $this->assertDatabaseHas('sc_agent_session_work_groups', [
                'agent_session_id' => $agentSession->getId(),
                'work_group_id' => $workGroup->getId(),
            ]);
        }
    }

    public function testThatAnUpdateInAgentSessionLogEntryResultsInANewRecord(): void
    {
        /** @var User $user */
        $user = $this->userRepository->getById(10);

        $internalPhone = $this->getInternalPhone();

        $automaticCall = false;
        $priority = null;

        $agentSession = $this->SUT->createAndStartSession(
            $user,
            $internalPhone,
            $automaticCall,
            $priority,
            ...$this->workGroups
        );

        $agentSessionLogEntry =  $agentSession->getAgentSessionLogEntry();
        self::assertNotNull($agentSessionLogEntry);

        $secondAgentSessionLogEntry = $this->SUT->updateAgentSessionLogEntry(
            $agentSession,
            new AgentSessionStatus(AgentSessionStatus::AWAITING_CASE),
            $agentSessionLogEntry->getServiceCenterCase(),
            $agentSessionLogEntry->getTelephonySession()
        );

        $this->assertDatabaseHas('sc_agent_session_log', [
            'id' => $secondAgentSessionLogEntry->getId(),
            'agent_session_id' => $secondAgentSessionLogEntry->getAgentSession()->getId(),
        ]);

        self::assertNotEquals($secondAgentSessionLogEntry->getId(), $agentSessionLogEntry->getId());
    }

    public function testThatSetsTheInitialAgentSessionLogEntry(): void
    {
        /** @var User $user */
        $user = $this->userRepository->getById(10);

        $internalPhone = $this->getInternalPhone();

        $automaticCall = false;
        $priority = null;

        $agentSession = $this->SUT->createAndStartSession(
            $user,
            $internalPhone,
            $automaticCall,
            $priority,
            ...$this->workGroups
        );

        $firstAgentSessionLogEntry = $agentSession->getAgentSessionLogEntry();

        $secondAgentSessionLogEntry = $this->SUT->updateAgentSessionLogEntry(
            $agentSession,
            new AgentSessionStatus(AgentSessionStatus::AWAITING_CASE),
            null,
            null
        );
        self::assertNotEquals($firstAgentSessionLogEntry, $secondAgentSessionLogEntry);

        $initialAgentSessionLogEntryValues = $this->SUT->setInitialAgentSessionLogEntry($agentSession)->getAgentSessionLogEntry();

        self::assertEquals($firstAgentSessionLogEntry, $initialAgentSessionLogEntryValues);
    }

    private function getInternalPhone(): InternalPhone
    {
        return $this->internalPhoneRepository->getByInternalPhoneNumber('100');
    }
}
