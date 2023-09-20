<?php

declare(strict_types=1);

namespace App\Synonyms\Service;

use App\Matchmaker\Search\Job\IndexServiceTypeJob;
use App\Matchmaker\Search\Service\MatchmakerSearchDocumentValidator;
use App\Models\Office\Servicetype;
use App\Models\Office\ServicetypeSynonym;
use App\Synonyms\Repository\ServiceTypeSynonymRepository;
use App\Utils\Database\Contract\TransactionHandler;
use Illuminate\Contracts\Bus\Dispatcher;

class ServiceTypeService
{
    private TransactionHandler $transactionHandler;
    private Dispatcher $dispatcher;
    private ServiceTypeSynonymRepository $serviceTypeSynonymRepository;

    public function __construct(
        TransactionHandler $transactionHandler,
        Dispatcher $dispatcher,
        ServiceTypeSynonymRepository $serviceTypeSynonymRepository
    ) {
        $this->transactionHandler = $transactionHandler;
        $this->dispatcher = $dispatcher;
        $this->serviceTypeSynonymRepository = $serviceTypeSynonymRepository;
    }

    public function update(Servicetype $serviceType, array $synonyms = null): Servicetype
    {
        /** @var Servicetype $serviceType */
        $serviceType = $this->transactionHandler->transactional(function () use (
            $serviceType,
            $synonyms
        ): Servicetype {
            if ($synonyms === null) {
                // Synonyms are not given, leave them as is.
                return $serviceType;
            }

            foreach ($synonyms as $synonym) {
                $serviceTypeSynonym = ServicetypeSynonym::makeInstance(
                    $serviceType,
                    $synonym
                );

                if (!$this->serviceTypeSynonymRepository->exists($serviceTypeSynonym)) {
                    $this->serviceTypeSynonymRepository->insert($serviceTypeSynonym);
                }
            }

            $serviceType->servicetypeSynonyms()
                ->whereNotIn('synonym', $synonyms)
                ->delete();

            return $serviceType;
        });

        if (MatchmakerSearchDocumentValidator::indexServiceTypeIsAllowed($serviceType)) {
            $this->dispatcher->dispatch(new IndexServiceTypeJob($serviceType));
        }

        return $serviceType;
    }
}
