<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Http\Request;

use App\Actions\Request\CreateCallbackRequestRequest;
use App\Actions\Request\CreateCustomerRequest as CreateCustomerRequestInterface;
use App\Actions\Validation\CallbackRequestExistsValidator;
use App\Affiliate\PartnerMatchmaker;
use App\Company\OpeningHours\OpeningTimesService;
use App\Http\Requests\Matchmaker\Concerns\RequiresCustomer;
use App\Leads\LeadSource;
use App\Models\Office\Company;
use App\Models\Office\Contactmethod;
use App\Models\Office\Customer;
use App\Models\Office\Daypart;
use App\Models\Office\Region;
use App\Models\Office\Servicetype;
use App\Models\Office\Site;
use App\Models\Office\Subscription;
use App\Repositories\Office\ContactMethodRepository;
use App\ServiceCenter\ServiceCenterCase\Service\ServiceCenterCaseLeadService;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\Subscription\Service\SubscriptionValidator;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * @property ServiceCenterCase $case
 */
class StoreCallbackRequestRequest extends FormRequest implements CreateCallbackRequestRequest, CreateCustomerRequestInterface
{
    use RequiresCustomer;

    public function rules(): array
    {
        return array_merge(
            $this->customerRules(),
            [
                'subscription_id' => ['required', 'integer'],
                'region_id' => ['required', 'integer'],
                'servicetype_id' => ['required', 'integer'],
                'date' => ['sometimes', 'date'],
                'daypart_id' => ['sometimes', 'integer'],
                'description' => ['nullable'],
                'note_to_company' => ['nullable', 'string'],
            ],
        );
    }

    public function withValidator(Validator $validator): void
    {
        $validator->setCustomMessages([
            'callback_request_already_exists' => __('Terugbelverzoek bestaat al'),
            'company_unavailable' => __('Dit bedrijf is niet beschikbaar voor je verzoek.'),
            'daypart_id.day_part_unavailable' => __('Het gekozen dagdeel is niet mogelijk.'),
        ]);

        $validator->addCustomAttributes([
            'date' => 'Datum',
            'daypart' => 'Dagdeel',
        ]);

        $validator->after(function (Validator $validator) {
            if ($validator->failed()) {
                // Maybe some model was not found, let's not continue validating.
                return;
            }

            $subscriptionIsAvailableForLead = $this->subscriptionIsAvailableForLead(
                $this->getSubscription(),
                $this->getServiceType(),
                $this->getRegion(),
                $this->getContactMethod()
            );

            if (!$subscriptionIsAvailableForLead) {
                $validator->addFailure('subscription_id', 'company_unavailable');
            }

            if ($this->getDayPart() !== null && !$this->isDaypartAvailable()) {
                $validator->addFailure('daypart_id', 'day_part_unavailable');
            }

            if ($this->callbackRequestAlreadyExists()) {
                $validator->addFailure('callback_request', 'callback_request_already_exists');
            }
        });
    }

    private function getContactMethod(): Contactmethod
    {
        /** @var ContactMethodRepository $contactMethodRepository */
        $contactMethodRepository = $this->container->make(ContactMethodRepository::class);

        return $contactMethodRepository->getOnlineById(Contactmethod::CALLBACK_REQUEST);
    }

    private function isDaypartAvailable(): bool
    {
        /** @var OpeningTimesService $openingTimesService */
        $openingTimesService = $this->container->make(OpeningTimesService::class);

        return $openingTimesService->isCallbackRequestCompanyDaypartAvailable(
            $this->getCompany(),
            $this->getDate(),
            $this->getDayPart()
        );
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

    private function callbackRequestAlreadyExists(): bool
    {
        /** @var CallbackRequestExistsValidator $callbackRequestExistsValidator */
        $callbackRequestExistsValidator = $this->container->make(CallbackRequestExistsValidator::class);

        return $callbackRequestExistsValidator->callbackRequestAlreadyExists(
            $this->getCompany(),
            $this->getServiceType(),
            $this->getRegion(),
            $this->getDate(),
            $this->getDayPart(),
            $this->getEmail(),
            $this->getZipcode(),
            $this->getHouseNumber(),
            $this->getDescription()
        );
    }

    public function getCase(): ServiceCenterCase
    {
        return $this->case;
    }

    private function getCompany(): Company
    {
        return $this->getSubscription()->getCompany();
    }

    public function getSubscription(): Subscription
    {
        $subscriptionId = (int) $this->input('subscription_id');

        return Subscription::findOrFail($subscriptionId);
    }

    public function getRegion(): Region
    {
        $regionId = (int) $this->input('region_id');

        return Region::findOrFail($regionId);
    }

    public function getServiceType(): Servicetype
    {
        $serviceTypeId = (int) $this->input('servicetype_id');

        return Servicetype::findOrFail($serviceTypeId);
    }

    public function getDate(): ?CarbonInterface
    {
        $dateStr = $this->input('date');

        if ($dateStr !== null) {
            return Carbon::parse($dateStr);
        }

        return null;
    }

    public function getDayPart(): ?Daypart
    {
        $daypartId = $this->input('daypart_id');

        if ($daypartId !== null) {
            return Daypart::findOrFail($daypartId);
        }

        return null;
    }

    public function getDescription(): ?string
    {
        return $this->input('description');
    }

    public function getSource(): string
    {
        return $this->container->make(ServiceCenterCaseLeadService::class)
            ->determineLeadSource($this->getCase());
    }

    public function getSourceInfo(): LeadSource
    {
        return $this->getCase()->getLeadSourceInfo();
    }

    public function getGclid(): ?string
    {
        return $this->getCase()->getSourceGclid();
    }

    public function getPartnerMatchmaker(): ?PartnerMatchmaker
    {
        return $this->getCase()->getPartnerMatchmaker();
    }

    public function getNoteToCompany(): ?string
    {
        return $this->input('note_to_company');
    }

    public function getSite(): Site
    {
        $site = $this->getCase()->getSourceSite();

        // Offertes nabellen heeft optioneel een Site (QuoteFollowUp)
        if ($site === null) {
            $site = Site::getDefaultSite();
        }

        return $site;
    }

    public function isCreatedByServiceCenter(): bool
    {
        return true;
    }

    protected function isCustomerEmailRequired(): bool
    {
        return false;
    }

    public function getName(): string
    {
        return $this->input('name');
    }

    public function getEmail(): ?string
    {
        return $this->input('email');
    }

    public function getGender(): ?string
    {
        return $this->input('gender');
    }

    public function getCompanyName(): ?string
    {
        return $this->input('company');
    }

    public function getPhone(): string
    {
        return $this->input('phone');
    }

    public function getStreet(): string
    {
        return $this->input('street');
    }

    public function getHouseNumber(): string
    {
        return $this->input('housenumber');
    }

    public function getHouseNumberSuffix(): ?string
    {
        return $this->input('housenumber_suffix');
    }

    public function getZipcode(): string
    {
        return $this->input('zipcode');
    }

    public function getCity(): string
    {
        return $this->input('city');
    }

    public function getCountry(): string
    {
        return $this->input('country', Customer::COUNTY_NETHERLANDS);
    }

    public function getCreateCustomerRequest(): CreateCustomerRequestInterface
    {
        return $this;
    }
}
