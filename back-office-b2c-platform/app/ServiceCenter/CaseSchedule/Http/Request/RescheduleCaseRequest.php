<?php

declare(strict_types=1);

namespace App\ServiceCenter\CaseSchedule\Http\Request;

use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @property-read ServiceCenterCase $case
 */
class RescheduleCaseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'dueAt' => ['required', 'date', 'after_or_equal:now'],
        ];
    }

    public function getCase(): ServiceCenterCase
    {
        return $this->case;
    }

    public function getDueAt(): CarbonImmutable
    {
        return new CarbonImmutable(
            $this->input('dueAt')
        );
    }
}
