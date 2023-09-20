<?php

declare(strict_types=1);

namespace App\ServiceCenter\CaseQueue\Job;

use App\ServiceCenter\ServiceCenterCase\Service\AssignCasesToAgentsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCaseQueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('case_queue');
    }

    public function handle(AssignCasesToAgentsService $assignCasesToAgentsService): void
    {
        $assignCasesToAgentsService->assignCasesToAgents();
    }
}
