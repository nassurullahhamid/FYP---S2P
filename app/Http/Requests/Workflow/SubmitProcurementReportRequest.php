<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class SubmitProcurementReportRequest extends FormRequest
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
            'hasil_kajian' => [
                'required',
                'array',
                'min:1',
                'max:100',
            ],
            'hasil_kajian.*' => [
                'required',
                'array',
            ],
            'hasil_kajian.*.nama_pemohon' => [
                'required',
                'string',
                'max:255',
            ],
            'hasil_kajian.*.jawatan_pemohon' => [
                'required',
                'string',
                'max:255',
            ],
            'hasil_kajian.*.keadaan_semasa' => [
                'required',
                'string',
                'max:5000',
            ],
            'hasil_kajian.*.justifikasi_cadangan' => [
                'required',
                'string',
                'max:5000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'hasil_kajian.required' => 'Butiran hasil kajian mesti dilengkapkan.',
            'hasil_kajian.array' => 'Format butiran hasil kajian tidak sah.',
            'hasil_kajian.min' => 'Sekurang-kurangnya satu hasil kajian diperlukan.',
            'hasil_kajian.max' => 'Hasil kajian tidak boleh melebihi 100 rekod.',

            'hasil_kajian.*.nama_pemohon.required' => 'Nama pemohon mesti dinyatakan.',
            'hasil_kajian.*.nama_pemohon.string' => 'Nama pemohon mesti berupa teks.',
            'hasil_kajian.*.nama_pemohon.max' => 'Nama pemohon tidak boleh melebihi 255 aksara.',

            'hasil_kajian.*.jawatan_pemohon.required' => 'Jawatan pemohon mesti dinyatakan.',
            'hasil_kajian.*.jawatan_pemohon.string' => 'Jawatan pemohon mesti berupa teks.',
            'hasil_kajian.*.jawatan_pemohon.max' => 'Jawatan pemohon tidak boleh melebihi 255 aksara.',

            'hasil_kajian.*.keadaan_semasa.required' => 'Keadaan semasa bagi setiap pemohon mesti dinyatakan.',
            'hasil_kajian.*.keadaan_semasa.string' => 'Keadaan semasa mesti berupa teks.',
            'hasil_kajian.*.keadaan_semasa.max' => 'Keadaan semasa tidak boleh melebihi 5000 aksara.',

            'hasil_kajian.*.justifikasi_cadangan.required' => 'Justifikasi dan cadangan penyelesaian mesti dinyatakan.',
            'hasil_kajian.*.justifikasi_cadangan.string' => 'Justifikasi dan cadangan mesti berupa teks.',
            'hasil_kajian.*.justifikasi_cadangan.max' => 'Justifikasi dan cadangan tidak boleh melebihi 5000 aksara.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $rows = $this->input(
            'hasil_kajian',
            []
        );

        if (! is_array($rows)) {
            $rows = [];
        }

        $this->merge([
            'hasil_kajian' => array_values(
                array_filter(
                    array_map(
                        static function (mixed $row): array {
                            if (! is_array($row)) {
                                return [
                                    'nama_pemohon' => '',
                                    'jawatan_pemohon' => '',
                                    'keadaan_semasa' => '',
                                    'justifikasi_cadangan' => '',
                                ];
                            }

                            return [
                                'nama_pemohon' => trim(
                                    (string) (
                                        $row['nama_pemohon']
                                        ?? ''
                                    )
                                ),
                                'jawatan_pemohon' => trim(
                                    (string) (
                                        $row['jawatan_pemohon']
                                        ?? ''
                                    )
                                ),
                                'keadaan_semasa' => trim(
                                    (string) (
                                        $row['keadaan_semasa']
                                        ?? ''
                                    )
                                ),
                                'justifikasi_cadangan' => trim(
                                    (string) (
                                        $row['justifikasi_cadangan']
                                        ?? ''
                                    )
                                ),
                            ];
                        },
                        $rows
                    ),
                    static fn (array $row): bool => $row['nama_pemohon'] !== ''
                        || $row['jawatan_pemohon'] !== ''
                        || $row['keadaan_semasa'] !== ''
                        || $row['justifikasi_cadangan'] !== ''
                )
            ),
        ]);
    }
}
