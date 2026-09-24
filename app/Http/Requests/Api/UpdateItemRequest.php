<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'condition' => ['sometimes', 'string', 'in:baru,bekas_seperti_baru,bekas_baik,bekas_layak_pakai'],
            'desired_items' => ['nullable', 'string'],
            'estimated_price' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'new_images' => ['nullable', 'array', 'max:5'],
            'new_images.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:3072'],
        ];
    }
}
