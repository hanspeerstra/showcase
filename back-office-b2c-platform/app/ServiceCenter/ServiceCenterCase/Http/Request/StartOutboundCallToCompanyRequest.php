<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Http\Request;

use App\Auth\User;
use App\Models\Office\Company;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\Telephony\Number\PhoneNumbers;
use Illuminate\Foundation\Http\FormRequest;
use Propaganistas\LaravelPhone\PhoneNumber;

/**
 * @property ServiceCenterCase $case
 */
class StartOutboundCallToCompanyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'companyId' => ['required', 'integer'],
            'phoneNumber' => ['sometimes', 'phone:NL'],
        ];
    }

    public function getAgent(): User
    {
        return $this->user();
    }

    public function getCase(): ServiceCenterCase
    {
        return $this->case;
    }

    public function getCompany(): Company
    {
        $companyId = (int) $this->input('companyId');

        return Company::findOrFail($companyId);
    }

    public function getPhoneNumber(): ?PhoneNumber
    {
        if ($this->has('phoneNumber')) {
            return PhoneNumbers::parseInternational($this->input('phoneNumber'));
        }

        return null;
    }
}
