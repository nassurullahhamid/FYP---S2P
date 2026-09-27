<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class ReviewProcurementTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->status_pengguna === 'Aktif'
            && $user->peranan === 'ketua_upp';
    }

    public function rules(): array
    {
        return [
            'pendahuluan' => [
                'required',
                'array',
            ],
            'pendahuluan.jabatan' => [
                'required',
                'string',
                'max:255',
            ],
            'pendahuluan.tarikh_terima' => [
                'required',
                'date',
            ],
            'pendahuluan.no_rujukan' => [
                'required',
                'string',
                'max:255',
            ],
            'pendahuluan.bilangan_kakitangan' => [
                'required',
                'integer',
                'min:1',
            ],
            'pendahuluan.tujuan' => [
                'required',
                'string',
                'max:5000',
            ],
            'pendahuluan.peruntukan' => [
                'required',
                'string',
                'max:255',
            ],
            'pendahuluan.tanggungjawab' => [
                'required',
                'string',
                'max:255',
            ],
            'pendahuluan.emel_ketua_cawangan' => [
                'required',
                'email',
                'max:255',
            ],
            'pendahuluan.pegawai_nama' => [
                'required',
                'string',
                'max:255',
            ],
            'pendahuluan.pegawai_notel' => [
                'required',
                'string',
                'max:30',
            ],
            'pendahuluan.pegawai_emel' => [
                'required',
                'email',
                'max:255',
            ],
            'pendahuluan.pegawai_jawatan' => [
                'required',
                'string',
                'max:255',
            ],
            'pendahuluan.cdo_nama' => [
                'nullable',
                'string',
                'max:255',
            ],
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
        ];
    }

    public function messages(): array
    {
        return [
            'pendahuluan.required' => 'Butiran permohonan mesti dilengkapkan.',
            'pendahuluan.array' => 'Format butiran permohonan tidak sah.',
            'pendahuluan.jabatan.required' => 'Kementerian atau jabatan mesti dinyatakan.',
            'pendahuluan.tarikh_terima.required' => 'Tarikh permohonan mesti dinyatakan.',
            'pendahuluan.tarikh_terima.date' => 'Tarikh permohonan tidak sah.',
            'pendahuluan.no_rujukan.required' => 'Nombor rujukan surat mesti dinyatakan.',
            'pendahuluan.bilangan_kakitangan.required' => 'Bilangan kakitangan mesti dinyatakan.',
            'pendahuluan.bilangan_kakitangan.integer' => 'Bilangan kakitangan mesti berupa nombor bulat.',
            'pendahuluan.bilangan_kakitangan.min' => 'Bilangan kakitangan mestilah sekurang-kurangnya satu.',
            'pendahuluan.tujuan.required' => 'Tujuan permohonan mesti dinyatakan.',
            'pendahuluan.peruntukan.required' => 'Sumber peruntukan mesti dinyatakan.',
            'pendahuluan.tanggungjawab.required' => 'Tanggungjawab mesti dinyatakan.',
            'pendahuluan.emel_ketua_cawangan.required' => 'E-mel Ketua Cawangan mesti dinyatakan.',
            'pendahuluan.emel_ketua_cawangan.email' => 'E-mel Ketua Cawangan tidak sah.',
            'pendahuluan.pegawai_nama.required' => 'Nama pegawai yang dihubungi mesti dinyatakan.',
            'pendahuluan.pegawai_notel.required' => 'Nombor telefon pegawai mesti dinyatakan.',
            'pendahuluan.pegawai_emel.required' => 'E-mel pegawai mesti dinyatakan.',
            'pendahuluan.pegawai_emel.email' => 'E-mel pegawai tidak sah.',
            'pendahuluan.pegawai_jawatan.required' => 'Jawatan pegawai mesti dinyatakan.',
            'hasil_kajian.required' => 'Sekurang-kurangnya seorang pemohon mesti direkodkan.',
            'hasil_kajian.array' => 'Format senarai pemohon tidak sah.',
            'hasil_kajian.min' => 'Sekurang-kurangnya seorang pemohon mesti direkodkan.',
            'hasil_kajian.max' => 'Senarai pemohon tidak boleh melebihi 100 orang.',
            'hasil_kajian.*.nama_pemohon.required' => 'Nama setiap pemohon mesti dinyatakan.',
            'hasil_kajian.*.jawatan_pemohon.required' => 'Jawatan setiap pemohon mesti dinyatakan.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $introduction = $this->input(
            'pendahuluan',
            []
        );

        if (! is_array($introduction)) {
            $introduction = [];
        }

        foreach ($introduction as $key => $value) {
            if (is_string($value)) {
                $introduction[$key] = trim($value);
            }
        }

        $rows = $this->input(
            'hasil_kajian',
            []
        );

        if (! is_array($rows)) {
            $rows = [];
        }

        $rows = array_values(
            array_filter(
                array_map(
                    static function (mixed $row): array {
                        if (! is_array($row)) {
                            return [
                                'nama_pemohon' => '',
                                'jawatan_pemohon' => '',
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
                        ];
                    },
                    $rows
                ),
                static fn (array $row): bool => $row['nama_pemohon'] !== ''
                    || $row['jawatan_pemohon'] !== ''
            )
        );

        $this->merge([
            'pendahuluan' => $introduction,
            'hasil_kajian' => $rows,
        ]);
    }
}
