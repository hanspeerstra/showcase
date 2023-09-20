<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Http\Controller;

use App\Http\Controllers\Controller;
use App\Questionnaire\AnswerBag\Service\AnswerBagService;
use App\Questionnaire\Http\Resource\Factory\QuestionnaireDataResourceFactory;
use App\Questionnaire\Http\Resource\QuestionnaireDataResource;
use App\ServiceCenter\LeadScreening\Service\LeadScreeningService;
use App\ServiceCenter\PhoneGreeting\Service\PhoneGreetingService;
use App\ServiceCenter\QuoteFollowUp\QuoteFollowUp;
use App\ServiceCenter\Search\Http\Resource\SearchCaseCallerHistoryResultResource;
use App\ServiceCenter\Search\Service\ServiceCenterSearchService;
use App\ServiceCenter\ServiceCenterCase\Http\Request\CasePhoneGreetingRequest;
use App\ServiceCenter\ServiceCenterCase\Http\Request\CaseShowCallerHistoryRequest;
use App\ServiceCenter\ServiceCenterCase\Http\Request\CaseShowQuestionnaireDataByLeadScreeningRequest;
use App\ServiceCenter\ServiceCenterCase\Http\Request\CaseShowQuestionnaireDataByServiceTypeRequest;
use App\ServiceCenter\ServiceCenterCase\Http\Request\CaseShowQuestionnaireDataRequest;
use App\ServiceCenter\ServiceCenterCase\Http\Request\CaseStoreQuestionnaireDataRequest;
use App\ServiceCenter\ServiceCenterCase\Http\Request\CloseCaseRequest;
use App\ServiceCenter\ServiceCenterCase\Http\Request\CreateFollowUpQuoteCaseRequest;
use App\ServiceCenter\ServiceCenterCase\Http\Request\ForwardCallToCompanyHangupRequest;
use App\ServiceCenter\ServiceCenterCase\Http\Request\ForwardCallToCompanyRequest;
use App\ServiceCenter\ServiceCenterCase\Http\Request\ForwardCallToUnknownCompanyRequest;
use App\ServiceCenter\ServiceCenterCase\Http\Request\MakeCaseGarbageRequest;
use App\ServiceCenter\ServiceCenterCase\Http\Request\MakeCaseSalesOpportunityRequest;
use App\ServiceCenter\ServiceCenterCase\Http\Request\RescheduleSalesOpportunityRequest;
use App\ServiceCenter\ServiceCenterCase\Http\Request\ResumeCaseRequest;
use App\ServiceCenter\ServiceCenterCase\Http\Request\StartNewTelephonySessionRequest;
use App\ServiceCenter\ServiceCenterCase\Http\Request\StartOutboundCallToCompanyRequest;
use App\ServiceCenter\ServiceCenterCase\Http\Request\StartOutboundCallToCustomerRequest;
use App\ServiceCenter\ServiceCenterCase\Http\Request\StartOutboundCallToForwardCallToCompanyRequest;
use App\ServiceCenter\ServiceCenterCase\Http\Request\StartOutboundCallToUnknownCompanyRequest;
use App\ServiceCenter\ServiceCenterCase\Http\Request\StoreAppointmentRequest;
use App\ServiceCenter\ServiceCenterCase\Http\Request\StoreCallbackRequestRequest;
use App\ServiceCenter\ServiceCenterCase\Http\Request\StoreQuoteForServiceTypeRequest;
use App\ServiceCenter\ServiceCenterCase\Http\Request\StoreZoofyAppointmentRequest;
use App\ServiceCenter\ServiceCenterCase\Http\Resource\ServiceCenterCaseResource;
use App\ServiceCenter\ServiceCenterCase\Repository\ServiceCenterCaseRepository;
use App\ServiceCenter\ServiceCenterCase\Service\ForwardCallToCompanyAction;
use App\ServiceCenter\ServiceCenterCase\Service\ForwardCallToCompanyHangupAction;
use App\ServiceCenter\ServiceCenterCase\Service\ForwardCallToUnknownCompanyAction;
use App\ServiceCenter\ServiceCenterCase\Service\ServiceCenterCaseGarbageService;
use App\ServiceCenter\ServiceCenterCase\Service\ServiceCenterCaseService;
use App\ServiceCenter\ServiceCenterCase\Service\StartNewTelephonySessionAction;
use App\ServiceCenter\ServiceCenterCase\Service\StartOutboundCallToCompanyAction;
use App\ServiceCenter\ServiceCenterCase\Service\StartOutboundCallToCustomerAction;
use App\ServiceCenter\ServiceCenterCase\Service\StartOutboundCallToForwardCallToCompanyAction;
use App\ServiceCenter\ServiceCenterCase\Service\StartOutboundCallToUnknownCompanyAction;
use App\ServiceCenter\ServiceCenterCase\Service\StoreAppointmentAction;
use App\ServiceCenter\ServiceCenterCase\Service\StoreCallbackRequestAction;
use App\ServiceCenter\ServiceCenterCase\Service\StoreQuoteAction;
use App\ServiceCenter\ServiceCenterCase\Service\StoreZoofyAppointmentAction;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCaseNote;
use App\Utils\Database\Contract\TransactionHandler;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ServiceCenterCaseController extends Controller
{
    public function show(ServiceCenterCase $case): JsonResource
    {
        return new ServiceCenterCaseResource($case);
    }

    public function createQuoteFollowUpCase(
        CreateFollowUpQuoteCaseRequest $request,
        ServiceCenterCaseService $caseService
    ): JsonResource {
        $agentSessionToAssign = null;
        if ($request->shouldAssignAndStartCase()) {
            $agentSessionToAssign = $request->getAgentSession();
        }

        $quoteFollowUp = QuoteFollowUp::makeInstance(
            $request->getSite(),
            $request->getQuoteFollowUpSource()
        );

        $case = $caseService->createQuoteFollowUpCase(
            $quoteFollowUp,
            $agentSessionToAssign
        );

        return new ServiceCenterCaseResource($case);
    }

    public function makeCaseSalesOpportunity(
        MakeCaseSalesOpportunityRequest $makeCaseSalesOpportunityRequest,
        ServiceCenterCaseService $serviceCenterCaseService
    ): JsonResource {
        $case = $makeCaseSalesOpportunityRequest->getCase();

        $note = ServiceCenterCaseNote::makeInstance(
            $case,
            $makeCaseSalesOpportunityRequest->getAgent(),
            $makeCaseSalesOpportunityRequest->getNote()
        );

        $serviceCenterCaseService->makeCaseSalesOpportunity(
            $case,
            $makeCaseSalesOpportunityRequest->getAgentSession(),
            $note,
            $makeCaseSalesOpportunityRequest->getScheduleDate()
        );

        return new ServiceCenterCaseResource($case);
    }

    public function rescheduleSalesOpportunity(
        RescheduleSalesOpportunityRequest $request,
        ServiceCenterCaseService $serviceCenterCaseService
    ): JsonResource {
        $case = $request->getCase();

        $note = null;
        if (null !== $request->getNote()) {
            $note = ServiceCenterCaseNote::makeInstance(
                $case,
                $request->getAgent(),
                $request->getNote()
            );
        }

        $serviceCenterCaseService->rescheduleSalesOpportunity(
            $case,
            $request->getAgentSession(),
            $request->getScheduleDate(),
            $note
        );

        return new ServiceCenterCaseResource($case);
    }

    public function makeCaseGarbage(
        MakeCaseGarbageRequest $makeCaseGarbageRequest,
        ServiceCenterCaseGarbageService $serviceCenterCaseGarbageService
    ): ServiceCenterCaseResource {
        $case = $serviceCenterCaseGarbageService->markAsGarbage(
            $makeCaseGarbageRequest->getCase(),
            $makeCaseGarbageRequest->getGarbageReason(),
            $makeCaseGarbageRequest->getNote()
        );

        return new ServiceCenterCaseResource($case);
    }

    public function resumeCase(
        ResumeCaseRequest $resumeCaseRequest,
        ServiceCenterCaseService $serviceCenterCaseService
    ): ServiceCenterCaseResource {
        $case = $serviceCenterCaseService->resumeCaseForAgentSession(
            $resumeCaseRequest->getCase(),
            $resumeCaseRequest->getAgentSession()
        );

        return new ServiceCenterCaseResource($case);
    }

    public function phoneGreeting(CasePhoneGreetingRequest $request, PhoneGreetingService $phoneGreetingService): JsonResource
    {
        $phoneGreeting = $phoneGreetingService->getScIncomingCallPhoneGreeting(
            $request->getCase()->getCalledNumberInfo(),
            $request->getAgent()
        );

        return new JsonResource(
            [
                'greeting' => $phoneGreeting,
            ]
        );
    }

    public function closeCase(
        CloseCaseRequest $closeCaseRequest,
        ServiceCenterCaseService $serviceCenterCaseService,
        TransactionHandler $transactionHandler
    ): ServiceCenterCaseResource {
        $case = $transactionHandler->transactional(
            static function () use ($closeCaseRequest, $serviceCenterCaseService) {
                $case = $closeCaseRequest->getCase();

                if ($closeCaseRequest->getNote() !== null) {
                    $note = ServiceCenterCaseNote::makeInstance(
                        $case,
                        $closeCaseRequest->getAgent(),
                        $closeCaseRequest->getNote()
                    );

                    $serviceCenterCaseService->addCaseNote($note);
                }

                return $serviceCenterCaseService->closeCase($case);
            }
        );

        return new ServiceCenterCaseResource($case);
    }

    /**
     * Return the assigned cases (includes paused cases).
     */
    public function assignedCases(ServiceCenterCaseRepository $caseRepository): JsonResource
    {
        return ServiceCenterCaseResource::collection(
            $caseRepository->findAssignedCases()
        );
    }

    public function storeCallbackRequest(
        StoreCallbackRequestRequest $request,
        StoreCallbackRequestAction $storeCallbackRequestAction
    ): Response {
        $storeCallbackRequestAction->handle($request);

        return new Response();
    }

    public function storeAppointment(
        StoreAppointmentRequest $request,
        StoreAppointmentAction $storeAppointmentAction
    ): Response {
        $storeAppointmentAction->handle($request);

        return new Response();
    }

    public function storeQuote(StoreQuoteForServiceTypeRequest $request, StoreQuoteAction $storeQuoteAction): Response
    {
        $storeQuoteAction->handle($request);

        return new Response();
    }

    public function storeZoofyAppointment(
        StoreZoofyAppointmentRequest $request,
        StoreZoofyAppointmentAction $storeZoofyAppointmentAction
    ): Response {
        $storeZoofyAppointmentAction->handle($request);

        return new Response();
    }

    public function startOutboundCallToForwardToCompany(
        StartOutboundCallToForwardCallToCompanyRequest $request,
        StartOutboundCallToForwardCallToCompanyAction $startOutboundCallToForwardCallToCompanyAction
    ): Response {
        $startOutboundCallToForwardCallToCompanyAction->handle($request);

        return new Response();
    }

    public function forwardCallToCompany(
        ForwardCallToCompanyRequest $request,
        ForwardCallToCompanyAction $forwardCallToCompanyAction
    ): Response {
        $forwardCallToCompanyAction->handle($request);

        return new Response();
    }

    public function forwardCallToCompanyHangup(
        ForwardCallToCompanyHangupRequest $request,
        ForwardCallToCompanyHangupAction $forwardCallToCompanyHangupAction
    ): Response {
        $forwardCallToCompanyHangupAction->handle($request);

        return new Response();
    }

    public function startTelephonySession(
        StartNewTelephonySessionRequest $request,
        StartNewTelephonySessionAction $startNewTelephonySessionAction
    ): Response {
        $startNewTelephonySessionAction->handle($request);

        return new Response();
    }

    public function startOutboundCallToCompany(
        StartOutboundCallToCompanyRequest $request,
        StartOutboundCallToCompanyAction $startOutboundCallToCompanyAction
    ): Response {
        $startOutboundCallToCompanyAction->handle($request);

        return new Response();
    }

    public function startOutboundCallToCustomer(
        StartOutboundCallToCustomerRequest $request,
        StartOutboundCallToCustomerAction $startOutboundCallToCustomerAction
    ): Response {
        $startOutboundCallToCustomerAction->handle($request);

        return new Response();
    }

    public function startOutboundCallToUnknownCompany(
        StartOutboundCallToUnknownCompanyRequest $request,
        StartOutboundCallToUnknownCompanyAction $startOutboundCallToUnknownCompanyAction
    ): Response {
        $startOutboundCallToUnknownCompanyAction->handle($request);

        return new Response();
    }

    public function forwardCallToUnknownCompany(
        ForwardCallToUnknownCompanyRequest $request,
        ForwardCallToUnknownCompanyAction $forwardCallToUnknownCompanyAction
    ): Response {
        $forwardCallToUnknownCompanyAction->handle($request);

        return new Response();
    }

    public function showQuestionnaireDataByLeadScreening(
        CaseShowQuestionnaireDataByLeadScreeningRequest $request,
        QuestionnaireDataResourceFactory $questionnaireDataResourceFactory,
        LeadScreeningService $leadScreeningService
    ): QuestionnaireDataResource {
        $questionList = $leadScreeningService->getQuestionListForSite($request->getSite());

        if (null === $questionList) {
            throw new NotFoundHttpException();
        }

        return $questionnaireDataResourceFactory->create(
            $questionList,
            $request->getCase()->getAnswerBag()
        );
    }

    public function showQuestionnaireData(
        CaseShowQuestionnaireDataRequest $request,
        QuestionnaireDataResourceFactory $questionnaireDataResourceFactory
    ): QuestionnaireDataResource {
        return $questionnaireDataResourceFactory->create(
            $request->getQuestionList(),
            $request->getCase()->getAnswerBag()
        );
    }

    public function showQuestionnaireDataByServiceType(
        CaseShowQuestionnaireDataByServiceTypeRequest $request,
        QuestionnaireDataResourceFactory $questionnaireDataResourceFactory
    ): QuestionnaireDataResource {
        return $questionnaireDataResourceFactory->create(
            $request->getQuestionList(),
            $request->getCase()->getAnswerBag()
        );
    }

    public function storeQuestionnaireData(
        CaseStoreQuestionnaireDataRequest $request,
        ServiceCenterCaseService $serviceCenterCaseService,
        AnswerBagService $answerBagService,
        QuestionnaireDataResourceFactory $questionnaireDataResourceFactory,
        TransactionHandler $transactionHandler
    ): QuestionnaireDataResource {
        return $transactionHandler->transactional(function () use (
            $request,
            $serviceCenterCaseService,
            $answerBagService,
            $questionnaireDataResourceFactory
        ): QuestionnaireDataResource {
            $serviceCenterCaseService->ensureAnswerBag($request->getCase());

            $questionList = $request->getQuestionList();

            $answerBag = $answerBagService->updateByQuestionList(
                $questionList,
                $request->getAnswerBag(),
                ...$request->getAnswers()
            );

            return $questionnaireDataResourceFactory->create(
                $questionList,
                $answerBag
            );
        });
    }

    public function showCallerHistory(
        CaseShowCallerHistoryRequest $request,
        ServiceCenterSearchService $serviceCenterSearchService
    ): SearchCaseCallerHistoryResultResource {
        $searchCaseCallerHistoryResult = $serviceCenterSearchService->searchForCallerHistory(
            $request->getCase()
        );

        return new SearchCaseCallerHistoryResultResource($searchCaseCallerHistoryResult);
    }
}
