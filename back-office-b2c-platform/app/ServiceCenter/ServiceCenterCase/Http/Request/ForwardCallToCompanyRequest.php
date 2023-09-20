<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Http\Request;

use App\Auth\User;
use App\Models\Office\Region;
use App\Models\Office\Servicetype;
use App\Models\Office\Site;
use App\Models\Office\Subscription;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\Utils\Bool\BoolUtil;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @property ServiceCenterCase $case
 */
class ForwardCallToCompanyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'subscriptionId' => ['required', 'integer'],
            'regionId' => ['required', 'integer'],
            'serviceTypeId' => ['required', 'integer'],
            'noCharge' => ['sometimes', 'boolean'],
        ];
    }

    public function getSubscription(): Subscription
    {
        $subscriptionId = (int) $this->input('subscriptionId');

        return Subscription::findOrFail($subscriptionId);
    }

    public function getRegion(): Region
    {
        $regionId = (int) $this->input('regionId');

        return Region::findOrFail($regionId);
    }

    public function getServiceType(): Servicetype
    {
        $serviceTypeId = (int) $this->input('serviceTypeId');

        return Servicetype::findOrFail($serviceTypeId);
    }

    public function isNoCharge(): bool
    {
        return BoolUtil::filterFromData(
            $this->input('noCharge', false)
        );
    }

    public function getAgent(): User
    {
        return $this->user();
    }

    public function getCase(): ServiceCenterCase
    {
        return $this->case;
    }

    public function getGclid(): ?string
    {
        return $this->getCase()->getSourceGclid();
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
