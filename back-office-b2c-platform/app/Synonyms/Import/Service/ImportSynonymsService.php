<?php

declare(strict_types=1);

namespace App\Synonyms\Import\Service;

use App\Models\Office\ServicetypeSynonym;
use App\Repositories\Office\ServiceTypeRepository;
use App\Synonyms\Import\ImportSynonymsResult;
use App\Synonyms\Import\SynonymEntry;
use App\Synonyms\Repository\ServiceTypeSynonymRepository;
use App\Utils\Database\Contract\TransactionHandler;
use App\Utils\Excel\ExcelUtil;

class ImportSynonymsService
{
    private const ROW_SERVICE_TYPE_ID = 'id';
    private const ROW_SERVICE_TYPE_SYNONYMS = 'servicetype_synonyms';

    private TransactionHandler $transactionHandler;
    private ServiceTypeRepository $serviceTypeRepository;
    private ServiceTypeSynonymRepository $serviceTypeSynonymRepository;

    public function __construct(
        TransactionHandler $transactionHandler,
        ServiceTypeRepository $serviceTypeRepository,
        ServiceTypeSynonymRepository $serviceTypeSynonymRepository
    ) {
        $this->transactionHandler = $transactionHandler;
        $this->serviceTypeRepository = $serviceTypeRepository;
        $this->serviceTypeSynonymRepository = $serviceTypeSynonymRepository;
    }

    public function import(): ImportSynonymsResult
    {
        $fileLocation = resource_path('data' . DIRECTORY_SEPARATOR . 'professions_servicetypes_categories_synonyms.xlsx');

        $pageRows = ExcelUtil::loadFile($fileLocation);

        return $this->transactionHandler->transactional(function () use ($pageRows): ImportSynonymsResult {
            $insertCount = 0;
            $existCount = 0;

            foreach ($pageRows as $pageIndex => $rows) {
                if (!array_key_exists(self::ROW_SERVICE_TYPE_SYNONYMS, $rows[1])) {
                    continue;
                }

                foreach ($rows as $row) {
                    $synonymEntry = $this->getSynonymEntryFromRow($row);

                    $serviceType = $this->serviceTypeRepository->tryGetById($synonymEntry->getServiceTypeId());

                    if ($serviceType === null) {
                        continue;
                    }

                    foreach ($synonymEntry->getSynonyms() as $synonym) {
                        $serviceTypeSynonym = ServicetypeSynonym::makeInstance($serviceType, $synonym);

                        if (!$this->serviceTypeSynonymRepository->exists($serviceTypeSynonym)) {
                            $this->serviceTypeSynonymRepository->insert($serviceTypeSynonym);

                            $insertCount++;
                        } else {
                            $existCount++;
                        }
                    }
                }
            }

            return new ImportSynonymsResult(
                $insertCount,
                $existCount
            );
        });
    }

    private function getSynonymEntryFromRow(array $row): SynonymEntry
    {
        $serviceTypeId = $row[self::ROW_SERVICE_TYPE_ID];

        $serviceTypeSynonyms = $row[self::ROW_SERVICE_TYPE_SYNONYMS];

        $synonyms = [];
        if ($serviceTypeSynonyms !== null) {
            $synonyms = explode(',', $serviceTypeSynonyms);
            $synonyms = array_map('trim', $synonyms);
            $synonyms = array_filter($synonyms, fn ($synonym) => $synonym !== '');
        }

        return new SynonymEntry(
            $serviceTypeId,
            $synonyms
        );
    }
}
