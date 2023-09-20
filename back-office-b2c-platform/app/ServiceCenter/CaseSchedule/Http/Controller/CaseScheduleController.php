<?php

declare(strict_types=1);

namespace App\ServiceCenter\CaseSchedule\Http\Controller;

use App\ServiceCenter\CaseSchedule\Exception\CaseNotScheduledException;
use App\ServiceCenter\CaseSchedule\Http\Request\AssignScheduledCaseRequest;
use App\ServiceCenter\CaseSchedule\Http\Request\CaseScheduleOverviewRequest;
use App\ServiceCenter\CaseSchedule\Http\Request\RescheduleCaseRequest;
use App\ServiceCenter\CaseSchedule\Http\Resource\CaseScheduleOverviewItemResource;
use App\ServiceCenter\CaseSchedule\Service\CaseScheduleOverviewService;
use App\ServiceCenter\CaseSchedule\Service\CaseScheduleService;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CaseScheduleController
{
    public function index(
        CaseScheduleOverviewRequest $request,
        CaseScheduleOverviewService $caseScheduleOverviewService
    ): JsonResource {
        $result = $caseScheduleOverviewService->getOverviewItems(
            $request->getPage(),
            $request->getPerPage(),
            $request->getPostalCode(),
            $request->getCaseNumber(),
            $request->getSorting()
        );

        return CaseScheduleOverviewItemResource::collection($result->getItems())
            ->additional([
                'page' => $result->getPage(),
                'perPage' => $result->getPerPage(),
                'lastPage' => $result->getLastPage(),
                'count' => $result->getCount(),
            ]);
    }

    public function assignScheduledCase(
        AssignScheduledCaseRequest $request,
        CaseScheduleService $caseScheduleService
    ): Response {
        try {
            $caseScheduleService->assignScheduledCase($request->getCase(), $request->getAgentSession());
        } catch (CaseNotScheduledException $caseNotScheduledException) {
            throw new NotFoundHttpException(null, $caseNotScheduledException);
        }

        return new Response();
    }

    public function queueScheduledCase(ServiceCenterCase $case, CaseScheduleService $caseScheduleService): Response
    {
        try {
            $caseScheduleService->queueScheduledCase($case);
        } catch (CaseNotScheduledException $caseNotScheduledException) {
            throw new NotFoundHttpException(null, $caseNotScheduledException);
        }

        return new Response();
    }

    public function rescheduleCase(RescheduleCaseRequest $request, CaseScheduleService $caseScheduleService): Response
    {
        try {
            $caseScheduleService->rescheduleCase(
                $request->getCase(),
                $request->getDueAt()
            );
        } catch (CaseNotScheduledException $caseNotScheduledException) {
            throw new NotFoundHttpException(null, $caseNotScheduledException);
        }

        return new Response();
    }
}
