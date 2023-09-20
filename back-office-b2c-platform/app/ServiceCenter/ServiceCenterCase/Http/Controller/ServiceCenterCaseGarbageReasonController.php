<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Http\Controller;

use App\Http\Controllers\Controller;
use App\ServiceCenter\ServiceCenterCase\Http\Resource\ServiceCenterCaseGarbageReasonResource;
use App\ServiceCenter\ServiceCenterCase\Repository\ServiceCenterCaseGarbageReasonRepository;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceCenterCaseGarbageReasonController extends Controller
{
    public function userGarbageReasons(ServiceCenterCaseGarbageReasonRepository $garbageReasonRepository): JsonResource
    {
        return ServiceCenterCaseGarbageReasonResource::collection(
            $garbageReasonRepository->getAllReasonsForUsers()
        );
    }
}
