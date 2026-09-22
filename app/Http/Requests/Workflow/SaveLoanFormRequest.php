<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveLoanFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->status_pengguna === 'Aktif'
            && $user->peranan === 'juruteknik';
    }

    public function rules(): array
    {
        return [
            'serial_no' => [
                'required',
                'string',
                Rule::exists('aset', 'serial_no'),
            ],
            'no_harta' => [
                'nullable',
                'string',
                'max:255',
            ],
            'status_perkakasan' => [
                'required',
                'string',
                Rule::in([
                    'Baru',
                    'Terpakai',
                ]),
            ],
            'mod_penggunaan' => [
                'required',
                'string',
                Rule::in([
                    'Dipinjamkan',
                    'Diserahkan',
                ]),
            ],
            'jawatan_penerima' => [
                'required',
                'string',
                'max:255',
            ],
            'catatan' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'serial_no.required' => 'Nombor siri peralatan diperlukan.',
            'serial_no.exists' => 'Peralatan tidak ditemui dalam inventori.',
            'status_perkakasan.required' => 'Status peralatan mesti dipilih.',
            'status_perkakasan.in' => 'Status peralatan tidak sah.',
            'mod_penggunaan.required' => 'Mod penggunaan mesti dipilih.',
            'mod_penggunaan.in' => 'Mod penggunaan tidak sah.',
            'jawatan_penerima.required' => 'Jawatan penerima mesti dinyatakan.',
            'jawatan_penerima.max' => 'Jawatan penerima terlalu panjang.',
            'catatan.max' => 'Catatan tidak boleh melebihi 2000 aksara.',
        ];
    }
}
