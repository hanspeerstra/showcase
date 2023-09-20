<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Http\Request;

use App\Actions\Request\CreateCustomerRequest as CreateCustomerRequestInterface;
use App\Actions\Validation\QuoteAlreadyExistsValidator;
use App\Affiliate\PartnerMatchmaker;
use App\Http\Requests\Matchmaker\Concerns\RequiresCustomer;
use App\Http\Requests\Matchmaker\CreateCustomerRequest;
use App\Http\Requests\Matchmaker\StoreQuoteForServiceTypeRequest as StoreMatchmakerQuoteForServiceTypeRequest;
use App\Leads\LeadSource;
use App\Models\Office\Contactmethod;
use App\Models\Office\CustomFormField;
use App\Models\Office\Region;
use App\Models\Office\Servicetype;
use App\Models\Office\Site;
use App\Models\Office\Subscription;
use App\QuestionnaireV2\Model\QuestionnaireAnswerBag;
use App\Repositories\Office\ContactMethodRepository;
use App\ServiceCenter\ServiceCenterCase\Service\ServiceCenterCaseLeadService;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\Subscription\Service\SubscriptionValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;
use RuntimeException;
use Throwable;

/**
 * @property ServiceCenterCase $case
 */
class StoreQuoteForServiceTypeRequest extends FormRequest
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
                'description' => ['nullable'],

                'customfields' => ['array'],
                'customfields.*.*.id' => ['bail', 'exists:custom_form_fields,id'],
                'customfields.*.checkbox.values' => ['sometimes', 'required', 'min:1'],
                'customfields.*.checkbox.values.*.id' => ['exists:custom_form_field_options,id'],
                'customfields.*.select.value' => ['sometimes', 'required'],
                'customfields.*.select.value.id' => ['exists:custom_form_field_options,id'],
                'customfields.*.input.value' => ['sometimes', 'required'],
                'customfields.*.date.value' => ['sometimes', 'nullable', 'date'],
                'note_to_company' => ['nullable', 'string'],
            ]
        );
    }

    public function withValidator(Validator $validator): void
    {
        $validator->setCustomMessages([
            'company_unavailable' => __('Dit bedrijf is niet beschikbaar voor je verzoek.'),
            'quote_already_exists' => __('Mailaanvraag bestaat al'),
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

            if ($this->quoteAlreadyExists()) {
                $validator->addFailure('quote', 'quote_already_exists');
            }
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

    private function quoteAlreadyExists(): bool
    {
        return $this->container->make(QuoteAlreadyExistsValidator::class)
            ->quoteAlreadyExists(
                $this->getSubscription()->getCompany(),
                $this->getServiceType(),
                $this->getRegion(),
                $this->getCreateCustomerRequest()->getEmail(),
                $this->getDescription(),
                true
            );
    }

    private function getContactMethod(): Contactmethod
    {
        return $this->container->make(ContactMethodRepository::class)
            ->getOnlineById(Contactmethod::QUOTE);
    }

    public function getCase(): ServiceCenterCase
    {
        return $this->case;
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

    /**
     * Same format as matchmaker request.
     *
     * @see StoreMatchmakerQuoteForServiceTypeRequest::getCustomFieldsAnswers()
     */
    public function getCustomFieldsAnswers(): array
    {
        return collect($this->customfields)->map(function ($customfield) {
            return collect($customfield)->mapWithKeys(function ($field, $type) {
                $id = Arr::get($field, 'id');
                $label = CustomFormField::whereKey($id)->value('frm_label');

                switch ($type) {
                    case 'input':
                        $value = Arr::get($field, 'value');
                        break;
                    case 'date':
                        $value = Arr::get($field, 'value');
                        break;
                    case 'checkbox':
                        $value = Arr::pluck(Arr::get($field, 'values'), 'value');
                        break;
                    case 'select':
                        $value = Arr::get($field, 'value.value');
                        break;
                    default:
                        $value = '';
                }

                return [$label => $value];
            });
        })->toArray();
    }

    public function getQuestionnaireAnswerBag(): ?QuestionnaireAnswerBag
    {
        return null;
    }

    public function getQuestionnaireData(): array
    {
        return collect($this->questionnaireData)->toArray();
    }

    public function getDescription(): ?string
    {
        return $this->input('description');
    }

    public function getSite(): Site
    {
        /** @var Site $site */
        $site = $this->getCase()->getSourceSite();

        // Offertes nabellen heeft optioneel een Site
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
}
