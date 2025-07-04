<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActivateKraDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pin' => ['required', 'string'],
            'device_type' => ['required', 'in:OSCU,VSCU'],
            'data' => ['sometimes', 'array'],
        ];
    }
} 