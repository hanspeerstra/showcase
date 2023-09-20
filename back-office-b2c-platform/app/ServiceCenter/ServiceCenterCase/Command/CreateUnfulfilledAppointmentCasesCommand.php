<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Command;

use App\Repositories\Office\AppointmentRepository;
use App\ServiceCenter\ServiceCenterCase\Service\ServiceCenterCaseService;
use Exception;
use Illuminate\Console\Command;
use Psr\Log\LoggerInterface;

class CreateUnfulfilledAppointmentCasesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'service-center:create-unfulfilled-appointment-cases';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create service center cases for unfulfilled appointments';

    public function handle(
        AppointmentRepository $appointmentRepository,
        ServiceCenterCaseService $caseService,
        LoggerInterface $logger
    ): void {
        $unfulfilledAppointments = $appointmentRepository->getUnfulfilledAppointmentsWithoutServiceCenterCase();

        if (0 !== count($unfulfilledAppointments)) {
            $logger->info(
                sprintf('Found %d unfulfilled appointments, creating cases', count($unfulfilledAppointments))
            );
        }

        foreach ($unfulfilledAppointments as $appointment) {
            try {
                $caseService->createUnfulfilledAppointmentCase($appointment);
            } catch (Exception $exception) {
                $logger->error(
                    sprintf(
                        'Could not create case for unfulfilled appointments %d: %s',
                        $appointment->getId(),
                        $exception->getMessage()
                    ),
                    [
                        'appointment_id' => $appointment->getId(),
                        'exception' => $exception,
                    ]
                );
            }
        }
    }
}
