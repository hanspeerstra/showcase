<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Service;

use App\ServiceCenter\ServiceCenterCase\Http\Request\StartOutboundCallToUnknownCompanyRequest;

class StartOutboundCallToUnknownCompanyAction
{
    /** @var ServiceCenterCaseTelephonyService */
    private $caseTelephonyService;

    public function __construct(ServiceCenterCaseTelephonyService $caseTelephonyService)
    {
        $this->caseTelephonyService = $caseTelephonyService;
    }

    public function handle(StartOutboundCallToUnknownCompanyRequest $request): void
    {
        $this->caseTelephonyService->callCompany(
            $request->getCase(),
            null,
            $request->getPhoneNumber()
        );
    }
}
