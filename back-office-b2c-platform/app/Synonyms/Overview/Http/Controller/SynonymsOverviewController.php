<?php

declare(strict_types=1);

namespace App\Synonyms\Overview\Http\Controller;

use App\Synonyms\Overview\Http\Request\SynonymsOverviewRequest;
use App\Synonyms\Overview\Http\Resource\SynonymsOverviewItemResource;
use App\Synonyms\Overview\Service\SynonymsOverviewService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\JsonResource;

class SynonymsOverviewController
{
    public function index(
        SynonymsOverviewRequest $request,
        SynonymsOverviewService $synonymsOverviewService
    ): JsonResource {
        $result = $synonymsOverviewService->getSynonymsOverview(
            $request->getPage(),
            $request->getPerPage(),
            $request->getTypes(),
            $request->getSearch(),
            $request->getHasAnySynonymFilter(),
            $request->getMatchmakerFilterEnabled(),
            $request->getSorting()
        );

        $synonymsOverviewItem = (new Collection($result->getItems()))
            ->loadMissing(
                [
                    'serviceType.servicetypeSynonyms',
                    'serviceType.professions',
                    'profession.professionSynonyms',
                ]
            )
            ->all();

        return SynonymsOverviewItemResource::collection($synonymsOverviewItem)
            ->additional([
                'page' => $result->getPage(),
                'perPage' => $result->getPerPage(),
                'lastPage' => $result->getLastPage(),
                'count' => $result->getCount(),
            ]);
    }
}
