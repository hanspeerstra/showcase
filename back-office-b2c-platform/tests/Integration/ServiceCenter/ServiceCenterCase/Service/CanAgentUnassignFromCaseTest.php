<?php

declare(strict_types=1);

namespace Tests\Integration\ServiceCenter\ServiceCenterCase\Service;

use App\Auth\User;
use App\InternalPhone\InternalPhone;
use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\AgentSession\Service\AgentSessionService;
use App\ServiceCenter\ServiceCenterCase\CaseType;
use App\ServiceCenter\ServiceCenterCase\Repository\ServiceCenterCaseGarbageReasonRepository;
use App\ServiceCenter\ServiceCenterCase\Repository\ServiceCenterCaseRepository;
use App\ServiceCenter\ServiceCenterCase\Service\ServiceCenterCaseService;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\ServiceCenter\WorkGroup\WorkGroup;
use App\Telephony\Session\Model\TelephonySession;
use App\Telephony\Session\StateMachineFactory;
use App\Telephony\Session\Util\TelephonySessionStateMachineUtil;
use BaseTestSeeder;
use Carbon\Carbon;
use TestMygoSeeder;
use Tests\Integration\Concerns\InteractsWithTelephonySession;
use Tests\Integration\IntegrationTestCase;

class CanAgentUnassignFromCaseTest extends IntegrationTestCase
{
    use InteractsWithTelephonySession;

    /** @var ServiceCenterCaseService */
    private $serviceCenterCaseService;

    /** @var User */
    private $user;

    /** @var AgentSession */
    private $agentSession;

    /** @var TelephonySession */
    private $telephonySession;

    /** @var ServiceCenterCase */
    private $case;

    /** @var WorkGroup */
    private $workGroup;

    /** @var CaseType */
    private $caseType;

    /** @var TelephonySessionStateMachineUtil */
    private $telephonySessionStateMachineUtil;

    /** @var StateMachineFactory */
    private $stateMachineFactory;

    /** @var ServiceCenterCaseRepository */
    private $serviceCenterCaseRepository;

    /** @var ServiceCenterCaseGarbageReasonRepository */
    private $serviceCenterCaseGarbageReasonRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->seed([
            BaseTestSeeder::class,
            TestMygoSeeder::class,
        ]);
        $this->serviceCenterCaseService = $this->app->make(ServiceCenterCaseService::class);
        /** @var AgentSessionService $agentSessionService */
        $agentSessionService = $this->app->make(AgentSessionService::class);
        $this->serviceCenterCaseRepository = $this->app->make(ServiceCenterCaseRepository::class);
        $this->telephonySessionStateMachineUtil = $this->app->make(TelephonySessionStateMachineUtil::class);
        $this->stateMachineFactory = $this->app->make(StateMachineFactory::class);
        $this->serviceCenterCaseGarbageReasonRepository = $this->app->make(ServiceCenterCaseGarbageReasonRepository::class);

        $this->workGroup = factory(WorkGroup::class)->create();
        $this->user = factory(User::class)->create();
        $this->caseType = factory(CaseType::class)->create();

        $this->agentSession = $agentSessionService->createAndStartSession(
            $this->user,
            factory(InternalPhone::class)->create(),
            true,
            1,
            ...[$this->workGroup]
        );
    }

    /**
     * @dataProvider dataProvider
     */
    public function testCanAgentUnassignFromCase(?callable $callable, bool $caseHasResult, bool $caseIsClosed, bool $useCloseCaseMethod, ?string $expectedErrorMessagePart): void
    {
        $telephonySession = null;

        if ($callable !== null) {
            $agentSessionSetupMethod = $callable[1];
            /** @var TelephonySession $telephonySession */
            $telephonySession = $this->{$agentSessionSetupMethod}($this->agentSession);
        }

        $case = ServiceCenterCase::makeInstance(
            $this->caseType,
            $this->workGroup,
            null,
            null,
            $telephonySession,
            null
        );

        $this->serviceCenterCaseRepository->persist($case);

        if ($caseHasResult) {
            $garbageReason = $this->serviceCenterCaseGarbageReasonRepository->getByLabel(
                'beller-vraagt-advies'
            );

            $case = $this->serviceCenterCaseService->setCaseResult($case, null, null, $garbageReason);
        }

        if ($caseIsClosed && !$useCloseCaseMethod) {
            $case = $case->setClosedAt(Carbon::now());
        }

        $errorMessage = null;

        try {
            if ($useCloseCaseMethod) {
                $this->serviceCenterCaseService->closeCase($case);
            } else {
                $this->serviceCenterCaseService->unassignAgentFromCaseForAgentSession(
                    $case,
                    $this->agentSession
                );
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
        }

        if ($errorMessage === null) {
            self::assertNull($errorMessage);
        } else {
            self::assertStringContainsString($expectedErrorMessagePart, $errorMessage);
        }
    }

    public function dataProvider(): iterable
    {
        yield [
            [$this, 'givenIncomingCallWithoutActiveAgentChannelAnswered'],
            $caseHasResult = false,
            $caseIsClosed = false,
            $useCloseCaseMethod = false,
            '$hasBeenAnsweredByAgent (1) $hasAgentAnActiveChannel (0) $hasCaseResult (0) $isCaseClosed (0)',
        ];

        yield [
            [$this, 'givenIncomingCallWithActiveAgentChannelAnswered'],
            $caseHasResult = false,
            $caseIsClosed = false,
            $useCloseCaseMethod = false,
            '$hasBeenAnsweredByAgent (1) $hasAgentAnActiveChannel (1) $hasCaseResult (0) $isCaseClosed (0)',
        ];

        yield [
            [$this, 'givenIncomingCallWithoutActiveAgentChannelAnswered'],
            $caseHasResult = true,
            $caseIsClosed = false,
            $useCloseCaseMethod = false,
            '$hasBeenAnsweredByAgent (1) $hasAgentAnActiveChannel (0) $hasCaseResult (1) $isCaseClosed (0)',
        ];

        yield [
            [$this, 'givenIncomingCallWithoutActiveAgentChannelAnswered'],
            $caseHasResult = false,
            $caseIsClosed = true,
            $useCloseCaseMethod = true,
            'Case must have a result when closing a case', // error message from ServiceCenterCaseService::closeCase()
        ];

        yield [
            [$this, 'givenIncomingCallWithoutActiveAgentChannelAnswered'],
            $caseHasResult = false,
            $caseIsClosed = true,
            $useCloseCaseMethod = false,
            '$hasBeenAnsweredByAgent (1) $hasAgentAnActiveChannel (0) $hasCaseResult (0) $isCaseClosed (1)',
        ];

        yield [
            [$this, 'givenIncomingCallWithoutActiveAgentChannelAnswered'],
            $caseHasResult = true,
            $caseIsClosed = true,
            $useCloseCaseMethod = true,
            null,
        ];

        yield [
            [$this, 'givenIncomingCallWithActiveAgentChannelAnswered'],
            $caseHasResult = true,
            $caseIsClosed = false,
            $useCloseCaseMethod = false,
            '$hasBeenAnsweredByAgent (1) $hasAgentAnActiveChannel (1) $hasCaseResult (1) $isCaseClosed (0)',
        ];

        yield [
            [$this, 'givenIncomingCallWithActiveAgentChannelAnswered'],
            $caseHasResult = true,
            $caseIsClosed = false,
            $useCloseCaseMethod = true,
            'Cannot close a case when there is an active telephony channel', // error message from ServiceCenterCaseService::closeCase()
        ];

        yield [
            [$this, 'givenIncomingCallWithActiveAgentChannelAnswered'],
            $caseHasResult = false,
            $caseIsClosed = true,
            $useCloseCaseMethod = false,
            '$hasBeenAnsweredByAgent (1) $hasAgentAnActiveChannel (1) $hasCaseResult (0) $isCaseClosed (1)',
        ];

        yield [
            [$this, 'givenIncomingCallWithActiveAgentChannelAnswered'],
            $caseHasResult = false,
            $caseIsClosed = true,
            $useCloseCaseMethod = true,
            'Cannot close a case when there is an active telephony channel', // error message from ServiceCenterCaseService::closeCase()
        ];

        yield [
            [$this, 'givenIncomingCallWithActiveAgentChannelAnswered'],
            $caseHasResult = true,
            $caseIsClosed = true,
            $useCloseCaseMethod = false,
            '$hasBeenAnsweredByAgent (1) $hasAgentAnActiveChannel (1) $hasCaseResult (1) $isCaseClosed (1)',
        ];

        yield [
            [$this, 'givenIncomingCallWithActiveAgentChannelAnswered'],
            $caseHasResult = true,
            $caseIsClosed = true,
            $useCloseCaseMethod = true,
            'Cannot close a case when there is an active telephony channel', // error message from ServiceCenterCaseService::closeCase()
        ];

        yield [
            null,
            $caseHasResult = false,
            $caseIsClosed = false,
            $useCloseCaseMethod = false,
            null,
        ];

        yield [
            [$this, 'givenIncomingCallWithActiveAgentChannelNotAnswered'],
            $caseHasResult = false,
            $caseIsClosed = false,
            $useCloseCaseMethod = false,
            '$hasBeenAnsweredByAgent (0) $hasAgentAnActiveChannel (1) $hasCaseResult (0) $isCaseClosed (0)',
        ];

        yield [
            null,
            $caseHasResult = true,
            $caseIsClosed = false,
            $useCloseCaseMethod = false,
            '$hasBeenAnsweredByAgent (0) $hasAgentAnActiveChannel (0) $hasCaseResult (1) $isCaseClosed (0)',
        ];

        yield [
            null,
            $caseHasResult = false,
            $caseIsClosed = true,
            $useCloseCaseMethod = false,
            '$hasBeenAnsweredByAgent (0) $hasAgentAnActiveChannel (0) $hasCaseResult (0) $isCaseClosed (1)',
        ];

        yield [
            null,
            $caseHasResult = false,
            $caseIsClosed = true,
            $useCloseCaseMethod = true,
            'Case must have a result when closing a case', // error message from ServiceCenterCaseService::closeCase()
        ];

        yield [
            null,
            $caseHasResult = true,
            $caseIsClosed = true,
            $useCloseCaseMethod = true,
            null,
        ];

        yield [
            null,
            $caseHasResult = true,
            $caseIsClosed = true,
            $useCloseCaseMethod = false,
            null,
        ];

        yield [
            [$this, 'givenIncomingCallWithActiveAgentChannelNotAnswered'],
            $caseHasResult = true,
            $caseIsClosed = false,
            $useCloseCaseMethod = false,
            '$hasBeenAnsweredByAgent (0) $hasAgentAnActiveChannel (1) $hasCaseResult (1) $isCaseClosed (0)',
        ];

        yield [
            [$this, 'givenIncomingCallWithActiveAgentChannelNotAnswered'],
            $caseHasResult = true,
            $caseIsClosed = true,
            $useCloseCaseMethod = false,
            '$hasBeenAnsweredByAgent (0) $hasAgentAnActiveChannel (1) $hasCaseResult (1) $isCaseClosed (1)',
        ];

        yield [
            [$this, 'givenIncomingCallWithActiveAgentChannelNotAnswered'],
            $caseHasResult = true,
            $caseIsClosed = true,
            $useCloseCaseMethod = true,
            'Cannot close a case when there is an active telephony channel', // error message from ServiceCenterCaseService::closeCase()
        ];

        yield [
            [$this, 'givenIncomingCallWithActiveAgentChannelNotAnswered'],
            $caseHasResult = false,
            $caseIsClosed = true,
            $useCloseCaseMethod = false,
            '$hasBeenAnsweredByAgent (0) $hasAgentAnActiveChannel (1) $hasCaseResult (0) $isCaseClosed (1)',
        ];

        yield [
            [$this, 'givenIncomingCallWithActiveAgentChannelNotAnswered'],
            $caseHasResult = false,
            $caseIsClosed = true,
            $useCloseCaseMethod = true,
            'Cannot close a case when there is an active telephony channel', // error message from ServiceCenterCaseService::closeCase()
        ];

        yield [
            [$this, 'givenIncomingCallWithoutActiveAgentChannelAnswered'],
            $caseHasResult = false,
            $caseIsClosed = false,
            $useCloseCaseMethod = false,
            'Case result is missing',
        ];

        yield [
            [$this, 'givenIncomingCallWithoutActiveAgentChannelAnsweredEndedSession'],
            $caseHasResult = false,
            $caseIsClosed = false,
            $useCloseCaseMethod = false,
            null,
        ];
    }
}
