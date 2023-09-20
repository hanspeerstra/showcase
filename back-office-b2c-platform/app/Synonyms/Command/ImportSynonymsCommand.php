<?php

declare(strict_types=1);

namespace App\Synonyms\Command;

use App\Synonyms\Import\Service\ImportSynonymsService;
use Illuminate\Console\Command;

class ImportSynonymsCommand extends Command
{
    protected $signature = 'synonyms:import';
    protected $description = 'Import synonyms from spreadsheet.';

    public function handle(ImportSynonymsService $importSynonymsService): int
    {
        $result = $importSynonymsService->import();

        $this->info(
            sprintf(
                '%d service type synonyms inserted. %d already existed.',
                $result->getInsertCount(),
                $result->getExistCount()
            )
        );

        return 0;
    }
}
