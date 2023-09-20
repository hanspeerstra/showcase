<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Http\Request;

use App\Actions\Request\CreateAppointmentRequest;
use App\Actions\Request\CreateCustomerRequest as CreateCustomerRequestInterface;
use App\Actions\Validation\AppointmentExistsValidator;
use App\Affiliate\PartnerMatchmaker;
use App\Http\Requests\Matchmaker\Concerns\RequiresCustomer;
use App\Http\Requests\Matchmaker\CreateCustomerRequest;
use App\Leads\LeadSource;
use App\Models\Office\Company;
use App\Models\Office\Contactmethod;
use App\Models\Office\Daypart;
use App\Models\Office\Region;
use App\Models\Office\Servicetype;
use App\Models\Office\Site;
use App\Models\Office\Subscription;
use App\Repositories\Office\ContactMethodRepository;
use App\Repositories\Office\SubscriptionRepository;
use App\ServiceCenter\ServiceCenterCase\Service\ServiceCenterCaseLeadService;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\Subscription\Service\SubscriptionValidator;
use App\Utils\Bool\BoolUtil;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;
use RuntimeException;
use Throwable;

/**
 * @property ServiceCenterCase $case
 */
class StoreAppointmentRequest extends FormRequest implements CreateAppointmentRequest
{
    use RequiresCustomer;

    public function rules(): array
    {
        return array_merge(
            $this->customerRules(),
            [
                'subscription_ids' => ['required', 'array', 'min:1'],
                'subscription_ids.*' => ['required', 'integer'],
                'region_id' => ['required', 'integer'],
                'servicetype_id' => ['required', 'integer'],
                'date' => ['required', 'date', 'after_or_equal:today'],
                'daypart_id' => ['required', 'integer'],
                'urgent' => ['nullable', 'boolean'],
                'description' => ['nullable'],
                'note_to_company' => ['nullable', 'string'],
            ],
        );
    }

    public function withValidator(Validator $validator): void
    {
        $validator->setCustomMessages([
            'date.after_or_equal' => __(':Attribute moet in de toekomst liggen.'),
            'day_part_unavailable' => __('Het gekozen dagdeel is niet mogelijk.'),
            'appointment_already_exists' => __('Afspraak bestaat al'),
            'company_unavailable' => __('Dit bedrijf is niet beschikbaar voor je verzoek.'),
        ]);

        $validator->addCustomAttributes([
            'date' => 'Datum',
        ]);

        $validator->after(function (Validator $validator) {
            if ($validator->failed()) {
                // Maybe some model was not found, let's not continue validating.
                return;
            }

            foreach ($this->getSubscriptions() as $key => $subscription) {
                $companyIsAvailableForLead = $this->subscriptionIsAvailableForLead(
                    $subscription,
                    $this->getServiceType(),
                    $this->getRegion(),
                    $this->getContactMethod()
                );

                if (!$companyIsAvailableForLead) {
                    $validator->addFailure('subscription_ids.' . $key, 'company_unavailable');
                }
            }

            if (!$this->isDayPartPossible()) {
                $validator->addFailure('daypart_id', 'day_part_unavailable');
            }

            if ($this->appointmentAlreadyExists()) {
                $validator->addFailure('appointment', 'appointment_already_exists');
            }
        });
    }

    private function isDayPartPossible(): bool
    {
        return $this->getDayPart()->isEnabled(
            $this->getDate(),
            $this->getServiceType()->getProfession()->getTimeTillStartOfDayPart()
        );
    }

    private function appointmentAlreadyExists(): bool
    {
        return $this->container->make(AppointmentExistsValidator::class)
            ->appointmentAlreadyExists(
                $this->getCompanies(),
                $this->getServiceType(),
                $this->getRegion(),
                $this->getDate(),
                $this->getDayPart(),
                $this->getCreateCustomerRequest()->getEmail(),
                $this->getDescription()
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

    private function getContactMethod(): Contactmethod
    {
        return $this->container->make(ContactMethodRepository::class)
            ->getOnlineById(Contactmethod::APPOINTMENT);
    }

    public function getCase(): ServiceCenterCase
    {
        return $this->case;
    }

    /**
     * @return Company[]
     */
    public function getSubscriptions(): iterable
    {
        $subscriptionIds = $this->input('subscription_ids');

        $idList = collect($subscriptionIds)
            ->map(static function ($subscriptionId): int {
                return (int) $subscriptionId;
            })
            ->all();

        return $this->container->make(SubscriptionRepository::class)->getByIdList(...$idList);
    }

    /**
     * @return Company[]
     */
    private function getCompanies(): iterable
    {
        return Collection::make($this->getSubscriptions())
            ->map(static function (Subscription $subscription): Company {
                return $subscription->getCompany();
            })
            ->unique(static function (Company $company): int {
                return $company->getId();
            });
    }

    public function getRegion(): Region
    {
        return Region::findOrFail($this->region_id);
    }

    public function getServiceType(): Servicetype
    {
        return Servicetype::findOrFail($this->servicetype_id);
    }

    public function getDate(): CarbonInterface
    {
        return Carbon::parse($this->date);
    }

    public function getDayPart(): Daypart
    {
        return Daypart::findOrFail($this->daypart_id);
    }

    public function isUrgent(): bool
    {
        return BoolUtil::isTruthy($this->urgent);
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCreateCustomerRequest(): CreateCustomerRequestInterface
    {
        $createCustomerRequest = CreateCustomerRequest::createFrom($this)
            ->setContainer($this->container);

        try {
            $createCustomerRequest->validateResolved();
        } catch (Throwable $e) {
            throw new RuntimeException('Could not create CreateCustomerRequest');
        }

        return $createCustomerRequest;
    }

    public function getSite(): Site
    {
        /** @var Site $site */
        $site = $this->getCase()->getSourceSite();

        // Offertes nabellen heeft optioneel site
        if ($site === null) {
            $site = Site::getDefaultSite();
        }

        return $site;
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

    public function isCreatedByServiceCenter(): bool
    {
        return true;
    }
}
