<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewNetworkSiteReportRequest extends FormRequest
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
            'tindakan' => [
                'required',
                'string',
                Rule::in([
                    'TERIMA',
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
            'tindakan.required' => 'Tindakan semakan laporan mesti dipilih.',
            'tindakan.string' => 'Tindakan semakan laporan tidak sah.',
            'tindakan.in' => 'Tindakan semakan laporan tidak dibenarkan.',
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
