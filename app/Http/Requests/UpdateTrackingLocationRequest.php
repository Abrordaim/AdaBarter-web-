<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTrackingLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'status'    => 'nullable|string|in:idle,otw,arrived',
            'heading'   => 'nullable|numeric|between:0,360',
            'speed'     => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.required'  => 'Koordinat latitude wajib dikirim.',
            'latitude.between'   => 'Koordinat latitude tidak valid (antara -90 s/d 90).',
            'longitude.required' => 'Koordinat longitude wajib dikirim.',
            'longitude.between'  => 'Koordinat longitude tidak valid (antara -180 s/d 180).',
            'status.in'          => 'Status perjalanan harus salah satu dari: idle, otw, arrived.',
        ];
    }
}
