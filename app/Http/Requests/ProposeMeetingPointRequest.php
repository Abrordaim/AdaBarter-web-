<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProposeMeetingPointRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Divalidasi lebih spesifik lewat Policy di Controller/Service
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'latitude'   => 'required|numeric|between:-90,90',
            'longitude'  => 'required|numeric|between:-180,180',
            'place_name' => 'required|string|min:3|max:255',
            'address'    => 'nullable|string|max:500',
            'notes'      => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.required'   => 'Koordinat latitude wajib diisi.',
            'latitude.between'    => 'Koordinat latitude tidak valid (harus antara -90 s/d 90 derajat).',
            'longitude.required'  => 'Koordinat longitude wajib diisi.',
            'longitude.between'   => 'Koordinat longitude tidak valid (harus antara -180 s/d 180 derajat).',
            'place_name.required' => 'Nama tempat/lokasi titik temu wajib diisi.',
            'place_name.min'      => 'Nama tempat minimal 3 karakter.',
        ];
    }
}
