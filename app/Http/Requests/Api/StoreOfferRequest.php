<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'offerer_item_id' => ['required', 'integer', 'exists:items,id'],
            'target_item_id' => ['required', 'integer', 'exists:items,id'],
            'cash_supplement' => ['nullable', 'numeric', 'min:0'],
            'cash_supplement_by' => ['nullable', 'string', 'in:offerer,target_owner'],
        ];
    }
}
