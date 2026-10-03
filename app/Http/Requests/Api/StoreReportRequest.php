<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reportable_type' => ['required', 'string', 'in:item,user'],
            'reportable_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:1000'],
            'evidence' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:3072'],
        ];
    }
}
