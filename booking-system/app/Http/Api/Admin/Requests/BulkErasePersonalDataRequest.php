<?php

namespace App\Http\Api\Admin\Requests;

use App\Http\RequestUtils;
use Illuminate\Foundation\Http\FormRequest;

class BulkErasePersonalDataRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'idList' => ['required', 'string'],
        ];
    }

    /**
     * @return int[]
     */
    public function getBookingIdList(): array
    {
        return array_map(
            'intval',
            RequestUtils::readCommaSeperatedList($this->input('idList'))
        );
    }
}
