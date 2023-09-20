<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Command;

use App\Leads\LeadRepository;
use App\ServiceCenter\ServiceCenterCase\Service\ServiceCenterCaseService;
use Exception;
use Illuminate\Console\Command;
use Psr\Log\LoggerInterface;

class CreateUnfulfilledQuoteCasesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'service-center:create-unfulfilled-quote-cases';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create service center cases for unfulfilled quotes';

    public function handle(
        LeadRepository $leadRepository,
        ServiceCenterCaseService $caseService,
        LoggerInterface $logger
    ): void {
        $unfulfilledQuoteLeads = $leadRepository->getUnfulfilledQuoteLeadsWithoutServiceCenterCase();

        if (0 !== count($unfulfilledQuoteLeads)) {
            $logger->info(
                sprintf('Found %d unfulfilled quotes, creating cases', count($unfulfilledQuoteLeads))
            );
        }

        foreach ($unfulfilledQuoteLeads as $lead) {
            try {
                $caseService->createUnfulfilledQuoteCase($lead);
            } catch (Exception $exception) {
                $logger->error(
                    sprintf(
                        'Could not create unfulfilled quote case for lead %d: %s',
                        $lead->getId(),
                        $exception->getMessage()
                    ),
                    [
                        'lead_id' => $lead->getId(),
                        'exception' => $exception,
                    ]
                );
            }
        }
    }
}
