<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Listener;

use App\Events\AppointmentRejected;
use App\ServiceCenter\ServiceCenterCase\Service\ServiceCenterCaseService;

class CreateCaseForRejectedAppointmentListener
{
    /** @var ServiceCenterCaseService */
    private $serviceCenterCaseService;

    public function __construct(ServiceCenterCaseService $serviceCenterCaseService)
    {
        $this->serviceCenterCaseService = $serviceCenterCaseService;
    }

    public function handle(AppointmentRejected $event): void
    {
        $appointment = $event->getAppointment();

        if ($appointment->isRejectedByAllCompanies() && !$appointment->hasServiceCenterCase()) {
            $this->serviceCenterCaseService->createUnfulfilledAppointmentCase($appointment);
        }
    }
}
