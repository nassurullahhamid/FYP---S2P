<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class SubmitHelpdeskTicketRequest extends FormRequest
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
            'catatan_penutupan' => [
                'required',
                'string',
                'min:10',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'catatan_penutupan.required' => 'Catatan tindakan mesti dinyatakan.',
            'catatan_penutupan.string' => 'Catatan tindakan mesti berupa teks.',
            'catatan_penutupan.min' => 'Catatan tindakan mestilah sekurang-kurangnya 10 aksara.',
            'catatan_penutupan.max' => 'Catatan tindakan tidak boleh melebihi 2000 aksara.',
        ];
    }
}
