<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase;

use Eloquent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $label
 * @property string $name
 *
 * @mixin Eloquent
 */
class CaseType extends Model
{
    use SoftDeletes;

    public const COL_LABEL = 'label';

    public const TYPE_MATCHMAKER = 'matchmaker';
    public const TYPE_SALES_OPPORTUNITY = 'sales_opportunity';
    public const TYPE_QUOTE_FOLLOW_UP_CALL = 'quote_follow_up_call';
    public const TYPE_LEAD_SCREENING = 'lead_screening';
    public const TYPE_COMPANY_MATCHMAKER_LEAD_SCREENING = 'matchmaker_company_lead_screening';
    public const TYPE_UNFULFILLED_QUOTE = 'unfulfilled_quote';
    public const TYPE_UNFULFILLED_APPOINTMENT = 'unfulfilled_appointment';
    public const TYPE_CANCELLED_APPOINTMENT = 'cancelled_appointment';
    public const TYPE_REJECTED_APPOINTMENT = 'rejected_appointment';
    public const TYPE_EXTERNAL_QUOTE_REQUEST = 'external_quote_request';

    protected $table = 'sc_case_types';

    public function getId(): int
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    private function setLabel(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function isMatchmaker(): bool
    {
        return $this->getLabel() === self::TYPE_MATCHMAKER;
    }

    public function isSalesOpportunity(): bool
    {
        return $this->getLabel() === self::TYPE_SALES_OPPORTUNITY;
    }

    public function isQuoteFollowUpCall(): bool
    {
        return $this->getLabel() === self::TYPE_QUOTE_FOLLOW_UP_CALL;
    }

    public function isLeadScreening(): bool
    {
        return $this->getLabel() === self::TYPE_LEAD_SCREENING;
    }

    public function isCompanyMatchmakerLeadScreening(): bool
    {
        return $this->getLabel() === self::TYPE_COMPANY_MATCHMAKER_LEAD_SCREENING;
    }

    public function isAppointmentCase(): bool
    {
        $appointmentCaseTypes = [
            self::TYPE_UNFULFILLED_APPOINTMENT,
            self::TYPE_CANCELLED_APPOINTMENT,
            self::TYPE_REJECTED_APPOINTMENT,
        ];

        return in_array($this->getLabel(), $appointmentCaseTypes, true);
    }

    public function isExternalQuoteRequest(): bool
    {
        return $this->getLabel() === self::TYPE_EXTERNAL_QUOTE_REQUEST;
    }

    public static function new(string $label, string $name): self
    {
        return (new self())
            ->setLabel($label)
            ->setName($name);
    }
}
