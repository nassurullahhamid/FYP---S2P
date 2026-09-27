<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidateModernizationLkkRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->status_pengguna === 'Aktif'
            && $user->peranan === 'ketua_wilayah';
    }

    public function rules(): array
    {
        return [
            'tindakan' => [
                'required',
                'string',
                Rule::in([
                    'LULUS',
                    'PEMBETULAN',
                ]),
            ],
            'ulasan' => [
                Rule::requiredIf(
                    fn (): bool => $this->input('tindakan')
                        === 'PEMBETULAN'
                ),
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'tindakan.required' => 'Tindakan validasi mesti dipilih.',
            'tindakan.string' => 'Tindakan validasi tidak sah.',
            'tindakan.in' => 'Tindakan validasi tidak dibenarkan.',
            'ulasan.required' => 'Ulasan pembetulan mesti dinyatakan.',
            'ulasan.string' => 'Ulasan pembetulan mesti berupa teks.',
            'ulasan.max' => 'Ulasan pembetulan tidak boleh melebihi 2000 aksara.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tindakan' => strtoupper(
                trim((string) $this->input('tindakan'))
            ),
            'ulasan' => $this->filled('ulasan')
                ? trim((string) $this->input('ulasan'))
                : null,
        ]);
    }
}
