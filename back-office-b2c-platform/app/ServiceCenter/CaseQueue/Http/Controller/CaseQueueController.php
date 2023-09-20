<?php

declare(strict_types=1);

namespace App\ServiceCenter\CaseQueue\Http\Controller;

use App\Http\Controllers\Controller;
use App\ServiceCenter\CaseQueue\Http\Resource\CaseQueueEntryResource;
use App\ServiceCenter\CaseQueue\Repository\CaseQueueRepository;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseQueueController extends Controller
{
    public function index(CaseQueueRepository $caseQueueRepository): JsonResource
    {
        return CaseQueueEntryResource::collection(
            $caseQueueRepository->getAll()
        );
    }
}
