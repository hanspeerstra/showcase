<?php

declare(strict_types=1);

namespace App\Synonyms\Http\Controller;

use App\Http\Controllers\Controller;
use App\Models\Office\Servicetype;
use App\Synonyms\Http\Request\UpdateServiceTypeRequest;
use App\Synonyms\Overview\Http\Resource\ServiceTypeResource;
use App\Synonyms\Service\ServiceTypeService;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceTypeController extends Controller
{
    public function update(
        UpdateServiceTypeRequest $request,
        Servicetype $serviceType,
        ServiceTypeService $serviceTypeService
    ): JsonResource {
        $serviceType = $serviceTypeService->update(
            $serviceType,
            $request->getSynonyms()
        );

        return new ServiceTypeResource($serviceType);
    }
}
