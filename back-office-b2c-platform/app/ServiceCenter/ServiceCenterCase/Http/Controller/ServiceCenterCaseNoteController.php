<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Http\Controller;

use App\Http\Controllers\Controller;
use App\ServiceCenter\ServiceCenterCase\Http\Request\CreateCaseNoteRequest;
use App\ServiceCenter\ServiceCenterCase\Http\Request\EditCaseNoteRequest;
use App\ServiceCenter\ServiceCenterCase\Http\Resource\ServiceCenterCaseNoteResource;
use App\ServiceCenter\ServiceCenterCase\Service\ServiceCenterCaseService;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCaseNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceCenterCaseNoteController extends Controller
{
    public function store(
        CreateCaseNoteRequest $createNoteRequest,
        ServiceCenterCaseService $caseService
    ): JsonResource {
        $note = ServiceCenterCaseNote::makeInstance(
            $createNoteRequest->getCase(),
            $createNoteRequest->getAgent(),
            $createNoteRequest->getNote()
        );

        $caseNote = $caseService->addCaseNote($note);

        return new ServiceCenterCaseNoteResource($caseNote);
    }

    public function update(
        EditCaseNoteRequest $editCaseNoteRequest,
        ServiceCenterCaseService $caseService
    ): JsonResponse {
        $caseNote = $editCaseNoteRequest->getCaseNote()
            ->setNote($editCaseNoteRequest->getNote());

        $caseService->editCaseNote($caseNote, $editCaseNoteRequest->getAgent());

        return new JsonResponse();
    }
}
