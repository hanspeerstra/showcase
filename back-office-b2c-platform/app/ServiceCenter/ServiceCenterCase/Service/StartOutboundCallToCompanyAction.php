<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Service;

use App\ServiceCenter\ServiceCenterCase\Http\Request\StartOutboundCallToCompanyRequest;

class StartOutboundCallToCompanyAction
{
    /** @var ServiceCenterCaseTelephonyService */
    private $caseTelephonyService;

    public function __construct(
        ServiceCenterCaseTelephonyService $caseTelephonyService
    ) {
        $this->caseTelephonyService = $caseTelephonyService;
    }

    public function handle(StartOutboundCallToCompanyRequest $request): void
    {
        $phoneNumber = $request->getPhoneNumber();
        if (null === $phoneNumber) {
            $phoneNumber = $this->caseTelephonyService->getCompanyPhoneNumber($request->getCompany());
        }

        $this->caseTelephonyService->callCompany(
            $request->getCase(),
            $request->getCompany(),
            $phoneNumber
        );
    }
}
