<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Service;

use App\Actions\CreateQuoteForServiceTypeAction;
use App\ServiceCenter\Matchmaker\Factory\CreateQuoteForServiceTypeParametersFactory;
use App\ServiceCenter\ServiceCenterCase\Http\Request\StoreQuoteForServiceTypeRequest;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\Utils\Database\Contract\TransactionHandler;

class StoreQuoteAction
{
    private TransactionHandler $transactionHandler;
    private ServiceCenterCaseService $caseService;
    private CreateQuoteForServiceTypeParametersFactory $createQuoteParametersFactory;
    private CreateQuoteForServiceTypeAction $createQuoteAction;

    public function __construct(
        TransactionHandler $transactionHandler,
        ServiceCenterCaseService $caseService,
        CreateQuoteForServiceTypeParametersFactory $createQuoteParametersFactory,
        CreateQuoteForServiceTypeAction $createQuoteAction
    ) {
        $this->transactionHandler = $transactionHandler;
        $this->caseService = $caseService;
        $this->createQuoteParametersFactory = $createQuoteParametersFactory;
        $this->createQuoteAction = $createQuoteAction;
    }

    public function handle(StoreQuoteForServiceTypeRequest $request): ServiceCenterCase
    {
        return $this->transactionHandler->transactional(function () use ($request) {
            $case = $request->getCase();

            $createQuoteParameters = $this->createQuoteParametersFactory->make($request);

            $lead = $this->createQuoteAction->execute($createQuoteParameters);

            return $this->caseService->setCaseResult(
                $case,
                $lead,
                null,
                null
            );
        });
    }
}
