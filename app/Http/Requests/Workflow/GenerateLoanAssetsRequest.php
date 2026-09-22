<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateLoanAssetsRequest extends FormRequest
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
                Rule::exists('aset', 'nama_aset')
                    ->where(
                        fn ($query) => $query
                            ->where('status', 'Tersedia')
                    ),
            ],
            'kuantiti_lulus' => [
                'required',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'id_aset.required' => 'Jenis peralatan mesti dipilih.',
            'id_aset.exists' => 'Jenis peralatan tidak wujud atau tiada stok tersedia.',
            'kuantiti_lulus.required' => 'Kuantiti diluluskan mesti dinyatakan.',
            'kuantiti_lulus.integer' => 'Kuantiti mesti berupa nombor.',
            'kuantiti_lulus.min' => 'Kuantiti mestilah sekurang-kurangnya satu.',
            'kuantiti_lulus.max' => 'Kuantiti tidak boleh melebihi 100.',
        ];
    }
}
