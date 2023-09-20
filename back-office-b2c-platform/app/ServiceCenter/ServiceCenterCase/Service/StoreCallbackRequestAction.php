<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Service;

use App\Actions\CreateCallbackRequestAction;
use App\ServiceCenter\ServiceCenterCase\Http\Request\StoreCallbackRequestRequest;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use Illuminate\Support\Facades\DB;

class StoreCallbackRequestAction
{
    /** @var ServiceCenterCaseService */
    private $caseService;
    /** @var CreateCallbackRequestAction */
    private $createCallbackRequestAction;

    public function __construct(
        ServiceCenterCaseService $caseService,
        CreateCallbackRequestAction $createCallbackRequestAction
    ) {
        $this->caseService = $caseService;
        $this->createCallbackRequestAction = $createCallbackRequestAction;
    }

    public function handle(StoreCallbackRequestRequest $request): ServiceCenterCase
    {
        return DB::transaction(function () use ($request) {
            $case = $request->getCase();

            $lead = $this->createCallbackRequestAction->execute($request);

            return $this->caseService->setCaseResult(
                $case,
                $lead,
                null,
                null
            );
        });
    }
}
