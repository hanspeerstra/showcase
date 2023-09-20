<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Service;

use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\Telephony\Tracking\Models\CallTrackingSegment;

class ServiceCenterCaseLeadService
{
    public function determineLeadSource(ServiceCenterCase $case): string
    {
        return $case->getLeadSource() ?? CallTrackingSegment::LABEL_STANDARD;
    }
}
