<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReviewLoanTicketRequest extends FormRequest
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
            'id_aset' => [
                'required',
                'string',
                Rule::exists('aset', 'nama_aset'),
            ],
            'kuantiti_lulus' => [
                'required',
                'integer',
                'min:1',
                'max:100',
            ],
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
            'senarai_aset' => [
                'required',
                'array',
                'min:1',
            ],
            'senarai_aset.*.serial_no' => [
                'required',
                'string',
                'distinct',
                Rule::exists('aset', 'serial_no')
                    ->where(
                        fn ($query) => $query
                            ->where('status', 'Tersedia')
                    ),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $approvedQuantity = (int) $this->input(
                    'kuantiti_lulus',
                    0
                );

                $assets = $this->input('senarai_aset', []);

                if (! is_array($assets)) {
                    return;
                }

                if (count($assets) !== $approvedQuantity) {
                    $validator->errors()->add(
                        'senarai_aset',
                        'Bilangan aset mesti sama dengan kuantiti yang diluluskan.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'id_aset.required' => 'Jenis peralatan mesti dipilih.',
            'id_aset.exists' => 'Jenis peralatan tidak ditemui.',
            'kuantiti_lulus.required' => 'Kuantiti diluluskan mesti dinyatakan.',
            'kuantiti_lulus.integer' => 'Kuantiti mesti berupa nombor.',
            'kuantiti_lulus.min' => 'Kuantiti mestilah sekurang-kurangnya satu.',
            'pic_ic.required' => 'Petugas mesti dipilih.',
            'pic_ic.exists' => 'Petugas yang dipilih tidak sah atau tidak aktif.',
            'senarai_aset.required' => 'Senarai peralatan mesti dijana.',
            'senarai_aset.*.serial_no.distinct' => 'Nombor siri peralatan tidak boleh berulang.',
            'senarai_aset.*.serial_no.exists' => 'Peralatan tidak wujud atau tidak lagi tersedia.',
        ];
    }
}
