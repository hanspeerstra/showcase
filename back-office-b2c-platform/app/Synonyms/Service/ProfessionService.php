<?php

declare(strict_types=1);

namespace App\Synonyms\Service;

use App\Matchmaker\Search\Job\IndexProfessionJob;
use App\Matchmaker\Search\Service\MatchmakerSearchDocumentValidator;
use App\Models\Office\Profession;
use App\Models\Office\ProfessionSynonym;
use App\Synonyms\Repository\ProfessionSynonymRepository;
use App\Utils\Database\Contract\TransactionHandler;
use Illuminate\Contracts\Bus\Dispatcher;

class ProfessionService
{
    private TransactionHandler $transactionHandler;
    private Dispatcher $dispatcher;
    private ProfessionSynonymRepository $professionSynonymRepository;

    public function __construct(
        TransactionHandler $transactionHandler,
        Dispatcher $dispatcher,
        ProfessionSynonymRepository $professionSynonymRepository
    ) {
        $this->transactionHandler = $transactionHandler;
        $this->dispatcher = $dispatcher;
        $this->professionSynonymRepository = $professionSynonymRepository;
    }

    public function update(Profession $profession, array $synonyms = null): Profession
    {
        /** @var Profession $profession */
        $profession = $this->transactionHandler->transactional(function () use (
            $profession,
            $synonyms
        ): Profession {
            if ($synonyms === null) {
                // Synonyms are not given, leave them as is.
                return $profession;
            }

            foreach ($synonyms as $synonym) {
                $professionSynonym = ProfessionSynonym::makeInstance(
                    $profession,
                    $synonym
                );

                if (!$this->professionSynonymRepository->exists($professionSynonym)) {
                    $this->professionSynonymRepository->insert($professionSynonym);
                }
            }

            $profession->professionSynonyms()
                ->whereNotIn('synonym', $synonyms)
                ->delete();

            return $profession;
        });

        if (MatchmakerSearchDocumentValidator::indexProfessionIsAllowed($profession)) {
            $this->dispatcher->dispatch(new IndexProfessionJob($profession));
        }

        return $profession;
    }
}
