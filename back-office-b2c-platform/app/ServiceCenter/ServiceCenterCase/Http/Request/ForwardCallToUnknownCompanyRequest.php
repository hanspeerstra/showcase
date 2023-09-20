<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Http\Request;

use App\Auth\User;
use App\Models\Office\Region;
use App\Models\Office\Servicetype;
use App\Models\Office\Site;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @property ServiceCenterCase $case
 */
class ForwardCallToUnknownCompanyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'regionId' => ['sometimes', 'nullable', 'integer'],
            'serviceTypeId' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    public function getRegion(): ?Region
    {
        $regionId = $this->input('regionId');
        if ($regionId !== null) {
            return Region::findOrFail((int) $regionId);
        }

        return null;
    }

    public function getServiceType(): ?Servicetype
    {
        $serviceTypeId = $this->input('serviceTypeId');
        if ($serviceTypeId !== null) {
            return Servicetype::findOrFail((int) $serviceTypeId);
        }

        return null;
    }

    public function getAgent(): User
    {
        return $this->user();
    }

    public function getCase(): ServiceCenterCase
    {
        return $this->case;
    }

    public function getSource(): string
    {
        return 'service-center';
    }

    public function getSite(): ?Site
    {
        return $this->getCase()->getSourceSite();
    }
}
