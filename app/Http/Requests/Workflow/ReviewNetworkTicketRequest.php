<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewNetworkTicketRequest extends FormRequest
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
            'pendahuluan' => [
                'required',
                'string',
                'max:10000',
            ],
            'objektif' => [
                'required',
                'array',
                'min:1',
                'max:20',
            ],
            'objektif.*' => [
                'required',
                'array',
            ],
            'objektif.*.teks' => [
                'required',
                'string',
                'max:2000',
            ],
            'senarai_pic_ic' => [
                'required',
                'array',
                'min:1',
            ],
            'senarai_pic_ic.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists('pengguna', 'no_ic')
                    ->where(
                        fn ($query) => $query
                            ->where('peranan', 'juruteknik')
                            ->where('status_pengguna', 'Aktif')
                    ),
            ],
            'tarikh_lawatan' => [
                'required',
                'date',
                'after_or_equal:today',
            ],
            'masa_lawatan' => [
                'required',
                'date_format:H:i',
            ],
            'catatan_lawatan' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'pendahuluan.required' => 'Pendahuluan mesti dilengkapkan.',
            'pendahuluan.string' => 'Pendahuluan mesti berupa teks.',
            'pendahuluan.max' => 'Pendahuluan tidak boleh melebihi 10000 aksara.',
            'objektif.required' => 'Sekurang-kurangnya satu objektif diperlukan.',
            'objektif.array' => 'Format objektif tidak sah.',
            'objektif.min' => 'Sekurang-kurangnya satu objektif diperlukan.',
            'objektif.max' => 'Objektif tidak boleh melebihi 20 catatan.',
            'objektif.*.required' => 'Maklumat objektif diperlukan.',
            'objektif.*.array' => 'Format objektif tidak sah.',
            'objektif.*.teks.required' => 'Setiap objektif mesti mempunyai catatan.',
            'objektif.*.teks.string' => 'Catatan objektif mesti berupa teks.',
            'objektif.*.teks.max' => 'Setiap objektif tidak boleh melebihi 2000 aksara.',
            'senarai_pic_ic.required' => 'Sekurang-kurangnya seorang Juruteknik mesti dipilih.',
            'senarai_pic_ic.array' => 'Senarai Juruteknik tidak sah.',
            'senarai_pic_ic.min' => 'Sekurang-kurangnya seorang Juruteknik mesti dipilih.',
            'senarai_pic_ic.*.required' => 'Maklumat Juruteknik diperlukan.',
            'senarai_pic_ic.*.string' => 'Maklumat Juruteknik tidak sah.',
            'senarai_pic_ic.*.distinct' => 'Juruteknik yang sama tidak boleh dipilih lebih daripada sekali.',
            'senarai_pic_ic.*.exists' => 'Juruteknik yang dipilih tidak sah atau tidak aktif.',
            'tarikh_lawatan.required' => 'Tarikh lawatan mesti ditetapkan.',
            'tarikh_lawatan.date' => 'Tarikh lawatan tidak sah.',
            'tarikh_lawatan.after_or_equal' => 'Tarikh lawatan tidak boleh lebih awal daripada hari ini.',
            'masa_lawatan.required' => 'Masa lawatan mesti ditetapkan.',
            'masa_lawatan.date_format' => 'Format masa lawatan tidak sah.',
            'catatan_lawatan.string' => 'Catatan lawatan mesti berupa teks.',
            'catatan_lawatan.max' => 'Catatan lawatan tidak boleh melebihi 2000 aksara.',
        ];
    }
}
