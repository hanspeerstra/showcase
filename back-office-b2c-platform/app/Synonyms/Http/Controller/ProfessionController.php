<?php

declare(strict_types=1);

namespace App\Synonyms\Http\Controller;

use App\Http\Controllers\Controller;
use App\Models\Office\Profession;
use App\Synonyms\Http\Request\UpdateProfessionRequest;
use App\Synonyms\Overview\Http\Resource\ProfessionResource;
use App\Synonyms\Service\ProfessionService;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfessionController extends Controller
{
    public function update(
        UpdateProfessionRequest $request,
        Profession $profession,
        ProfessionService $professionService
    ): JsonResource {
        $profession = $professionService->update(
            $profession,
            $request->getSynonyms()
        );

        return new ProfessionResource($profession);
    }
}
