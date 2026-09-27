<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class SubmitModernizationReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->status_pengguna === 'Aktif'
            && in_array(
                $user->peranan,
                [
                    'juruteknik',
                    'pic',
                ],
                true
            );
    }

    public function rules(): array
    {
        return [
            'keadaan_semasa' => [
                'required',
                'array',
                'min:1',
                'max:30',
            ],
            'keadaan_semasa.*' => [
                'required',
                'array',
            ],
            'keadaan_semasa.*.aspek' => [
                'required',
                'string',
                'max:1000',
            ],

            'keadaan_semasa.*.ulasan' => [
                'required',
                'string',
                'max:3000',
            ],
            'gambar_tapak' => [
                'nullable',
                'array',
                'max:20',
            ],
            'gambar_tapak.*' => [
                'file',
                'image',
                'max:5120',
            ],
            'gambar_cadangan' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
            'cadangan_penambahbaikan' => [
                'nullable',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'keadaan_semasa.required' => 'Pemerhatian keadaan semasa mesti dilengkapkan.',
            'keadaan_semasa.array' => 'Format pemerhatian keadaan semasa tidak sah.',
            'keadaan_semasa.min' => 'Sekurang-kurangnya satu pemerhatian diperlukan.',
            'keadaan_semasa.max' => 'Pemerhatian tidak boleh melebihi 30 catatan.',
            'keadaan_semasa.*.aspek.required' => 'Aspek pemerhatian mesti dinyatakan.',
            'keadaan_semasa.*.aspek.string' => 'Aspek pemerhatian mesti berupa teks.',
            'keadaan_semasa.*.aspek.max' => 'Aspek pemerhatian tidak boleh melebihi 1000 aksara.',

            'keadaan_semasa.*.ulasan.required' => 'Ulasan pemerhatian mesti dinyatakan.',
            'keadaan_semasa.*.ulasan.string' => 'Ulasan pemerhatian mesti berupa teks.',
            'keadaan_semasa.*.ulasan.max' => 'Ulasan pemerhatian tidak boleh melebihi 3000 aksara.',
            'gambar_tapak.array' => 'Format gambar tapak tidak sah.',
            'gambar_tapak.max' => 'Gambar tapak tidak boleh melebihi 20 fail.',
            'gambar_tapak.*.image' => 'Setiap gambar tapak mestilah fail imej.',
            'gambar_tapak.*.max' => 'Setiap gambar tapak tidak boleh melebihi 5 MB.',
            'gambar_cadangan.mimes' => 'Lakaran cadangan mestilah JPG, JPEG, PNG atau PDF.',
            'gambar_cadangan.max' => 'Lakaran cadangan tidak boleh melebihi 5 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $rows = $this->input('keadaan_semasa', []);

        if (! is_array($rows)) {
            $rows = [];
        }

        $this->merge([
            'keadaan_semasa' => array_values(
                array_filter(
                    array_map(
                        fn (mixed $row): array => [
                            'aspek' => trim(
                                (string) (
                                    is_array($row)
                                        ? ($row['aspek'] ?? '')
                                        : ''
                                )
                            ),
                            'ulasan' => trim(
                                (string) (
                                    is_array($row)
                                        ? ($row['ulasan'] ?? '')
                                        : ''
                                )
                            ),
                        ],
                        $rows
                    ),
                    fn (array $row): bool => $row['aspek'] !== ''
                        || $row['ulasan'] !== ''
                )
            ),
        ]);
    }
}
