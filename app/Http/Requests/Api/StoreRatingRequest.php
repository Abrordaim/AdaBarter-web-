<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreRatingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array 
    {
        return [
            'offer_id' => ['required', 'integer', 'exists:offers,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'offer_id.required' => 'ID transaksi barter wajib disertakan.',
            'offer_id.exists' => 'Transaksi barter tidak ditemukan.',
            'rating.required' => 'Rating bintang wajib diberikan.',
            'rating.min' => 'Rating minimal 1 bintang.',
            'rating.max' => 'Rating maksimal 5 bintang.',
            'comment.max' => 'Komentar ulasan maksimal 500 karakter.',
        ];
    }
}
