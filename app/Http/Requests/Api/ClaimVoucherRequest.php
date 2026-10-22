<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ClaimVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:50', 'required_without:voucher_id'],
            'voucher_id' => ['nullable', 'integer', 'exists:vouchers,id', 'required_without:code'],
        ];
    }
}
