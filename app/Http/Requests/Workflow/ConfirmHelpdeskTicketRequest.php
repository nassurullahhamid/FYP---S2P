<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmHelpdeskTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->status_pengguna === 'Aktif'
            && $user->peranan === 'ketua_utd';
    }

    public function rules(): array
    {
        return [
            'ulasan' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'ulasan.string' => 'Ulasan pengesahan mesti berupa teks.',
            'ulasan.max' => 'Ulasan pengesahan tidak boleh melebihi 2000 aksara.',
        ];
    }
}
