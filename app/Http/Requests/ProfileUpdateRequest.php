<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    // Get the validation rules that apply to the request
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'emel' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('pengguna', 'emel')->ignore($this->user()->no_ic, 'no_ic'),
            ],
        ];
    }
}
