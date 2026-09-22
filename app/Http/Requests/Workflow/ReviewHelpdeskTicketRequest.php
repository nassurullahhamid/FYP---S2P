<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewHelpdeskTicketRequest extends FormRequest
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
            'pic_ic' => [
                'required',
                'string',
                Rule::exists('pengguna', 'no_ic')
                    ->where(
                        fn ($query) => $query
                            ->where('peranan', 'juruteknik')
                            ->where('status_pengguna', 'Aktif')
                    ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'pic_ic.required' => 'Petugas mesti dipilih.',
            'pic_ic.string' => 'Maklumat petugas tidak sah.',
            'pic_ic.exists' => 'Petugas yang dipilih tidak sah atau tidak aktif.',
        ];
    }
}
