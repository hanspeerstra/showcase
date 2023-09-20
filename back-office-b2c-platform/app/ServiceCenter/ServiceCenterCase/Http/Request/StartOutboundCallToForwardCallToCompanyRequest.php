<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Http\Request;

use App\Auth\User;
use App\Models\Office\Contactmethod;
use App\Models\Office\Region;
use App\Models\Office\Servicetype;
use App\Models\Office\Subscription;
use App\Repositories\Office\ContactMethodRepository;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\Subscription\Service\SubscriptionValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * @property ServiceCenterCase $case
 */
class StartOutboundCallToForwardCallToCompanyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'subscriptionId' => ['required', 'integer'],
            'regionId' => ['required', 'integer'],
            'serviceTypeId' => ['required', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->setCustomMessages([
            'company_unavailable' => __('Dit bedrijf is niet beschikbaar voor je verzoek.'),
        ]);

        $validator->after(function (Validator $validator) {
            if ($validator->failed()) {
                // Maybe some model was not found, let's not continue validating.
                return;
            }

            $companyIsAvailableForLead = $this->subscriptionIsAvailableForLead(
                $this->getSubscription(),
                $this->getServiceType(),
                $this->getRegion(),
                $this->getContactMethod()
            );

            if (!$companyIsAvailableForLead) {
                $validator->addFailure('subscriptionId', 'company_unavailable');
            }

            // TODO check phone number + opening hours
        });
    }

    private function subscriptionIsAvailableForLead(
        Subscription $subscription,
        Servicetype $serviceType,
        Region $region,
        Contactmethod $contactMethod
    ): bool {
        /** @var SubscriptionValidator $subscriptionValidator */
        $subscriptionValidator = $this->container->make(SubscriptionValidator::class);

        return $subscriptionValidator
            ->subscriptionIsValidFor($subscription, $serviceType, $contactMethod, $region);
    }

    private function getContactMethod(): Contactmethod
    {
        return $this->container->make(ContactMethodRepository::class)
            ->getOnlineById(Contactmethod::CALL);
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

    public function getAgent(): User
    {
        return $this->user();
    }

    public function getCase(): ServiceCenterCase
    {
        return $this->case;
    }
}
