<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Service;

use App\ServiceCenter\ServiceCenterCase\Http\Request\StartOutboundCallToCustomerRequest;

class StartOutboundCallToCustomerAction
{
    /** @var ServiceCenterCaseTelephonyService */
    private $caseTelephonyService;

    public function __construct(ServiceCenterCaseTelephonyService $caseTelephonyService)
    {
        $this->caseTelephonyService = $caseTelephonyService;
    }

    public function handle(StartOutboundCallToCustomerRequest $request): void
    {
        $this->caseTelephonyService->callCustomer(
            $request->getCase(),
            $request->getPhoneNumber()
        );
    }
}
