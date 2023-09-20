<?php

declare(strict_types=1);

namespace App\ServiceCenter\CaseQueue\Http\Resource;

use App\ServiceCenter\CaseQueue\CaseQueueEntry;
use App\ServiceCenter\ServiceCenterCase\Http\Resource\ServiceCenterCaseResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CaseQueueEntry
 */
class CaseQueueEntryResource extends JsonResource
{
    /**
     * @param Request $request
     */
    public function toArray($request): array
    {
        $workGroup = $this->getWorkGroup();

        return [
            'id' => $this->getId(),
            'automaticallyAssign' => $this->isAutomaticallyAssign(),
            'createdAt' => $this->getCreatedAt(),
            'workGroup' => [
                'id' => $workGroup->getId(),
                'name' => $workGroup->getName(),
                'label' => $workGroup->getLabel(),
            ],
            'case' => new ServiceCenterCaseResource($this->getCase()),
        ];
    }
}
