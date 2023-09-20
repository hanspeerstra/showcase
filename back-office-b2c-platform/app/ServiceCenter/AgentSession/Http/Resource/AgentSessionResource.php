<?php

namespace App\ServiceCenter\AgentSession\Http\Resource;

use App\Auth\Http\Resource\UserResource;
use App\InternalPhone\Http\Resource\InternalPhoneResource;
use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\WorkGroup\Http\Resource\WorkGroupResource;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AgentSession
 */
class AgentSessionResource extends JsonResource
{
    public function __construct(AgentSession $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @inheritdoc
     */
    public function toArray($request): array
    {
        $activeCase = $this->getActiveCase();

        return [
            'agentSessionId' => $this->getId(),
            'automaticallyAssignCase' => $this->isAutomaticallyAssignCase(),
            'priority' => $this->getPriority(),
            'internalPhone' => new InternalPhoneResource($this->getInternalPhone()),
            'user' => new UserResource($this->getUser()),
            'activeCase' => [
                'id' => null !== $activeCase ? $activeCase->getId() : null,
                'contactMethod' => null !== $activeCase ? $activeCase->getSourceContactMethod() : null,
            ],
            'workGroups' => WorkGroupResource::collection($this->getWorkGroups()),
            'isManager' => $this->isManager(),
        ];
    }
}
