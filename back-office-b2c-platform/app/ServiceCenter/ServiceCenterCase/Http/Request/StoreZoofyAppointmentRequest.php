<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Http\Request;

use App\AddressCompletion\AddressLookupServiceInterface;
use App\Leads\LeadSource;
use App\Models\Office\Region;
use App\Models\Office\Servicetype;
use App\Models\Office\Site;
use App\ServiceCenter\ServiceCenterCase\Service\ServiceCenterCaseLeadService;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\Zoofy\DropdownValue;
use App\Zoofy\DynamicField;
use App\Zoofy\RateDayPart;
use App\Zoofy\Request\CreateAppointmentRequest;
use App\Zoofy\Request\Option;
use App\Zoofy\ZoofyClientInterface;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Propaganistas\LaravelPhone\PhoneNumber;
use UnexpectedValueException;

/**
 * @property ServiceCenterCase $case
 */
class StoreZoofyAppointmentRequest extends FormRequest implements CreateAppointmentRequest
{
    private const REGEX_POSTAL_CODE = '/^(?<digits>\\d{4})[\\s]*?(?<letters>[a-zA-Z]{2})$/';

    public function rules(): array
    {
        return [
            'serviceTypeId' => ['required', 'integer'],
            'taskId' => ['required', 'integer'],
            'rate' => ['required', 'integer'],
            'comment' => ['required'],
            'firstName' => ['required'],
            'lastName' => ['required'],
            'phone' => ['required', 'phone:NL,BE'],
            'email' => ['required', 'email'],
            'postalCode' => ['required', 'regex:' . self::REGEX_POSTAL_CODE],
            'houseNumber' => ['required', 'regex:/\\d+/'],
            'dynamicFields' => ['sometimes', 'nullable', 'array'],
            'dynamicFields.*.field' => ['required'],
            'dynamicFields.*.value' => ['sometimes', 'nullable'],
            'urgent' => ['boolean'],
            'options' => [
                Rule::requiredIf(function (): bool {
                    return !$this->isUrgent();
                }),
                'nullable',
                'array',
                'min:3',
            ],
            'options.*.date' => ['required_with:options', 'date_format:Y-m-d', 'after_or_equal:today'],
            'options.*.dayPart' => ['required_with:options', Rule::in(RateDayPart::MORNING, RateDayPart::AFTERNOON, RateDayPart::EVENING)],
            'insurance' => ['boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'urgent' => $this->boolean('urgent'),
        ]);
    }

    public function attributes(): array
    {
        return [
            'comment' => __('Omschrijving'),
            'firstName' => __('Voornaam'),
            'lastName' => __('Achternaam'),
            'phone' => __('Telefoonnummer'),
            'email' => __('E-mailadres'),
            'houseNumber' => __('Huisnummer'),
            'postalCode' => __('Postcode'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->setCustomMessages([
            'invalid_address' => __('Combinatie postcode en huisnummer is ongeldig.'),
            'dynamic_field.missing' => __('Vraag ":Attribute" is niet beantwoord.'),
            'dynamic_field.number.numeric' => __('":Attribute" moet een getal zijn.'),
            'dynamic_field.number.min' => __('":Attribute" moet minimaal :min zijn.'),
            'dynamic_field.number.max' => __('":Attribute" mag niet groter dan :max zijn.'),
            'dynamic_field.dropdown.unknown_value' => __('":Attribute" bevat een ongeldige waarde.'),
            'min' => __('Je moet minimaal :min afspraak momenten opgeven'),
            'after_or_equal' => __('Afspraak moment mag niet in het verleden zijn'),
            'options_not_allowed' => __('Je kunt geen afspraak momenten opgeven bij spoed'),
        ]);

        $validator->addReplacer(
            'dynamic_field.number.min',
            static function (string $message, string $attribute, string $rule, array $parameters) {
                return str_replace(
                    ':min',
                    $parameters[0],
                    $message
                );
            }
        );
        $validator->addReplacer(
            'dynamic_field.number.max',
            static function (string $message, string $attribute, string $rule, array $parameters) {
                return str_replace(
                    ':max',
                    $parameters[0],
                    $message
                );
            }
        );

        $validator->after(function (Validator $validator) {
            // Validate dynamic fields
            $dynamicFields = $this->getZoofyClient()->getTaskDynamicFields($this->getTaskId());

            $dynamicFieldValues = $this->getDynamicFieldValues();
            $dynamicFieldValuesInput = $this->getNormalizedDynamicFieldValuesInput();

            $attributeNames = [];
            foreach ($dynamicFields as $dynamicField) {
                foreach ($dynamicFieldValuesInput as $key => $dynamicFieldValue) {
                    if ($dynamicField->getName() === $dynamicFieldValue['field']) {
                        $attribute = "dynamicFields.{$key}.value";

                        $attributeNames[$attribute] = $dynamicField->getName();

                        break;
                    }
                }
            }

            $validator->setAttributeNames($attributeNames);

            foreach ($dynamicFields as $dynamicField) {
                if (!isset($dynamicFieldValues[$dynamicField->getName()])) {
                    $validator->addFailure($dynamicField->getName(), 'dynamic_field.missing');
                } else {
                    $errors = $this->validateField($dynamicField, $dynamicFieldValues[$dynamicField->getName()]);

                    if ([] !== $errors) {
                        $attribute = null;
                        foreach ($dynamicFieldValuesInput as $key => $dynamicFieldValue) {
                            if ($dynamicField->getName() === $dynamicFieldValue['field']) {
                                $attribute = "dynamicFields.{$key}.value";

                                break;
                            }
                        }

                        foreach ($errors as $rule => $parameters) {
                            $validator->addFailure($attribute, $rule, $parameters);
                        }
                    }
                }
            }

            if ($validator->failed()) {
                // Maybe some model was not found, let's not continue validating.
                return;
            }

            if (null === $this->tryGetRegion($this->getPostalCode())
                || !$this->isValidAddress($this->getPostalCode(), $this->getHouseNumber())
            ) {
                $validator->addFailure('postalCode', 'invalid_address');
            }

            if ($this->isUrgent()) {
                if ([] !== $this->getOptions()) {
                    $validator->addFailure('options', 'options_not_allowed');
                }
            } else {
                // Validate options not in the past
                foreach ($this->getOptions() as $option) {
                    if ($option->getStartTime() <= new DateTimeImmutable()) {
                        $validator->addFailure('options', 'after_or_equal');
                    }
                }
            }
        });
    }

    private function validateField(DynamicField $dynamicField, string $value): array
    {
        if ('' === $value) {
            return ['required' => []];
        }

        $errors = [];

        switch ($dynamicField->getType()) {
            case DynamicField::TYPE_TEXT:
                break;
            case DynamicField::TYPE_NUMBER:
                $value = str_replace(',', '.', $value);

                if (!is_numeric($value)) {
                    $errors['dynamic_field.number.numeric'] = [];

                    break;
                }

                if (null !== $dynamicField->getMin() && $value < $dynamicField->getMin()) {
                    $errors['dynamic_field.number.min'] = [(string) $dynamicField->getMin()];
                }

                if (null !== $dynamicField->getMax() && $value > $dynamicField->getMax()) {
                    $errors['dynamic_field.number.max'] = [(string) $dynamicField->getMax()];
                }

                break;
            case DynamicField::TYPE_SELECT:
                $allowedValues = array_map(
                    static function (DropdownValue $dropdownValue): string {
                        return $dropdownValue->getValue();
                    },
                    $dynamicField->getDropdownValues()
                );

                if (!in_array($value, $allowedValues)) {
                    $errors['dynamic_field.dropdown.unknown_value'] = [];
                }
                break;
        }

        return $errors;
    }

    public function getDynamicFieldValues(): array
    {
        $dynamicFieldValues = [];

        foreach ($this->getNormalizedDynamicFieldValuesInput() as $dynamicFieldValue) {
            $field = $dynamicFieldValue['field'];
            $value = $dynamicFieldValue['value'];

            $dynamicFieldValues[$field] = $value;
        }

        return $dynamicFieldValues;
    }

    private function getNormalizedDynamicFieldValuesInput(): array
    {
        $dynamicFieldValuesInput = [];

        $input = $this->input('dynamicFields', []) ?? [];

        if (is_array($input)) {
            foreach ($input as $key => $dynamicFieldValue) {
                if (!isset($dynamicFieldValue['field']) || !is_string($dynamicFieldValue['field'])) {
                    continue;
                }

                $field = $dynamicFieldValue['field'];
                $value = (string) ($dynamicFieldValue['value'] ?? '');

                $dynamicFieldValuesInput[$key] = [
                    'field' => $field,
                    'value' => $value,
                ];
            }
        }

        return $dynamicFieldValuesInput;
    }

    public function getServiceType(): Servicetype
    {
        return Servicetype::findOrFail((int) $this->input('serviceTypeId'));
    }

    public function getTaskId(): int
    {
        return (int) $this->input('taskId');
    }

    public function getRate(): int
    {
        return (int) $this->input('rate');
    }

    public function getComment(): string
    {
        return $this->input('comment');
    }

    public function getFirstName(): string
    {
        return $this->input('firstName');
    }

    public function getLastName(): string
    {
        return $this->input('lastName');
    }

    public function getPhoneNumber(): PhoneNumber
    {
        return PhoneNumber::make($this->input('phone'), 'NL');
    }

    public function getHouseNumber(): string
    {
        return $this->input('houseNumber');
    }

    public function getEmail(): string
    {
        return $this->input('email');
    }

    public function getPostalCode(): string
    {
        $postalCodeInput = $this->input('postalCode');

        if (!preg_match(self::REGEX_POSTAL_CODE, $postalCodeInput, $matches)) {
            throw new UnexpectedValueException('expected valid postal code');
        }

        return $matches['digits'] . strtoupper($matches['letters']);
    }

    public function getCase(): ServiceCenterCase
    {
        return $this->case;
    }

    public function getCaseNumber(): string
    {
        return sprintf('case-%s', $this->getCase()->getId());
    }

    public function getRegion(): Region
    {
        return $this->tryGetRegion($this->input('postalCode'));
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

    public function isCreatedByServiceCenter(): bool
    {
        return true;
    }

    private function isValidAddress(string $postalCode, string $houseNumber): bool
    {
        return 0 !== count($this->getAddressLookupService()->lookup($postalCode, $houseNumber));
    }

    private function tryGetRegion(string $postalCode): ?Region
    {
        return Region::query()
            ->wherePostcode($postalCode)
            ->first();
    }

    private function getZoofyClient(): ZoofyClientInterface
    {
        return $this->container->get(ZoofyClientInterface::class);
    }

    private function getAddressLookupService(): AddressLookupServiceInterface
    {
        return $this->container->get(AddressLookupServiceInterface::class);
    }

    /**
     * @return Option[]
     */
    public function getOptions(): array
    {
        $optionsInput = $this->input('options');

        if (null === $optionsInput) {
            return [];
        }

        $options = [];

        foreach ($optionsInput as $input) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $input['date']);

            if (RateDayPart::MORNING === $input['dayPart']) {
                $options[] = new Option($date->setTime(8, 0, 0), $date->setTime(10, 0, 0));
                $options[] = new Option($date->setTime(10, 0, 0), $date->setTime(12, 0, 0));
            }

            if (RateDayPart::AFTERNOON === $input['dayPart']) {
                $options[] = new Option($date->setTime(12, 0, 0), $date->setTime(14, 0, 0));
                $options[] = new Option($date->setTime(14, 0, 0), $date->setTime(16, 0, 0));
                $options[] = new Option($date->setTime(16, 0, 0), $date->setTime(18, 0, 0));
            }

            if (RateDayPart::EVENING === $input['dayPart']) {
                $options[] = new Option($date->setTime(18, 0, 0), $date->setTime(20, 0, 0));
                $options[] = new Option($date->setTime(20, 0, 0), $date->setTime(21, 0, 0));
            }
        }

        return $options;
    }

    public function isUrgent(): bool
    {
        return $this->boolean('urgent');
    }

    public function isInsuranceRequested(): bool
    {
        return $this->boolean('insurance');
    }

    public function getSourceInfo(): LeadSource
    {
        return $this->getCase()->getLeadSourceInfo();
    }
}
