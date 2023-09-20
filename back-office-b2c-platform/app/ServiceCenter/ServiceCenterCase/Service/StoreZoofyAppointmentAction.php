<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Service;

use App\ServiceCenter\ServiceCenterCase\Http\Request\StoreZoofyAppointmentRequest;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\Zoofy\Service\CreateZoofyAppointmentAction;

class StoreZoofyAppointmentAction
{
    /** @var CreateZoofyAppointmentAction */
    private $createZoofyAppointmentAction;

    /** @var ServiceCenterCaseService */
    private $caseService;

    public function __construct(
        CreateZoofyAppointmentAction $createZoofyAppointmentAction,
        ServiceCenterCaseService $caseService
    ) {
        $this->createZoofyAppointmentAction = $createZoofyAppointmentAction;
        $this->caseService = $caseService;
    }

    public function handle(StoreZoofyAppointmentRequest $request): ServiceCenterCase
    {
        $lead = $this->createZoofyAppointmentAction->execute($request);

        return $this->caseService->setCaseResult(
            $request->getCase(),
            $lead,
            null,
            null
        );
    }
}
