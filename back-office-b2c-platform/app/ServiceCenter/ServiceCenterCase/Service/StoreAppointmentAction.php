<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Service;

use App\Actions\CreateAppointmentAction;
use App\ServiceCenter\ServiceCenterCase\Http\Request\StoreAppointmentRequest;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\Utils\Database\Contract\TransactionHandler;

class StoreAppointmentAction
{
    /** @var ServiceCenterCaseService */
    private $caseService;
    /** @var CreateAppointmentAction */
    private $createAppointmentAction;
    /** @var TransactionHandler */
    private $transactionHandler;

    public function __construct(
        ServiceCenterCaseService $caseService,
        CreateAppointmentAction $createAppointmentAction,
        TransactionHandler $transactionHandler
    ) {
        $this->caseService = $caseService;
        $this->createAppointmentAction = $createAppointmentAction;
        $this->transactionHandler = $transactionHandler;
    }

    public function handle(StoreAppointmentRequest $request): ServiceCenterCase
    {
        return $this->transactionHandler->transactional(function () use ($request) {
            $case = $request->getCase();

            $appointment = $this->createAppointmentAction->execute($request);

            return $this->caseService->setCaseResult(
                $case,
                null,
                $appointment,
                null
            );
        });
    }
}
