<?php

declare(strict_types=1);

namespace App\ServiceCenter\AgentSession\Http\Request;

use App\InternalPhone\InternalPhone;
use App\InternalPhone\Repository\InternalPhoneRepository;
use App\Auth\User;
use App\ServiceCenter\WorkGroup\WorkGroup;
use App\Utils\Bool\BoolUtil;
use Illuminate\Foundation\Http\FormRequest;

class StartAgentSessionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'internalPhoneId' => 'required|integer',
            'workGroupIdList' => 'required|array',
            'automaticallyAssignCase' => 'required|boolean',
            'priority' => 'nullable|integer',
        ];
    }

    /**
     * @return WorkGroup[]
     */
    public function getWorkGroups(): array
    {
        $result = WorkGroup::query()
            ->whereIn('id', $this->getWorkGroupIdList())
            ->findOrFail($this->getWorkGroupIdList());

        return iterator_to_array($result);
    }

    public function getInternalPhone(): InternalPhone
    {
        $internalPhoneRepository = $this->container->make(InternalPhoneRepository::class);

        return $internalPhoneRepository->getById(
            $this->get('internalPhoneId')
        );
    }

    public function getAutomaticCall(): bool
    {
        return BoolUtil::filterFromData($this->get('automaticallyAssignCase'));
    }

    public function getPriority(): ?int
    {
        return $this->get('priority');
    }

    public function getAgent(): User
    {
        return $this->user();
    }

    /**
     * @return int[]
     */
    private function getWorkGroupIdList(): array
    {
        $workgroupIdList = [];
        foreach ($this->get('workGroupIdList') as $workgroupId) {
            $workgroupIdList[] = $workgroupId;
        }

        return $workgroupIdList;
    }
}
