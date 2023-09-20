<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Http\Request;

use App\Auth\User;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\Telephony\Number\PhoneNumbers;
use Illuminate\Foundation\Http\FormRequest;
use Propaganistas\LaravelPhone\PhoneNumber;

/**
 * @property ServiceCenterCase $case
 */
class StartOutboundCallToUnknownCompanyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'phoneNumber' => ['required', 'phone:NL'],
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

    public function getPhoneNumber(): ?PhoneNumber
    {
        return PhoneNumbers::parseInternational($this->input('phoneNumber'));
    }
}
