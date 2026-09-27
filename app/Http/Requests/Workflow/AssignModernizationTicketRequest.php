<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignModernizationTicketRequest extends FormRequest
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
        ];
    }

    public function messages(): array
    {
        return [
            'tarikh_lawatan.required' => 'Tarikh lawatan mesti ditetapkan.',
            'tarikh_lawatan.date' => 'Tarikh lawatan tidak sah.',
            'tarikh_lawatan.after_or_equal' => 'Tarikh lawatan tidak boleh lebih awal daripada hari ini.',

            'masa_lawatan.required' => 'Masa lawatan mesti ditetapkan.',
            'masa_lawatan.date_format' => 'Format masa lawatan tidak sah.',

            'catatan_lawatan.string' => 'Catatan lawatan mesti berupa teks.',
            'catatan_lawatan.max' => 'Catatan lawatan tidak boleh melebihi 2000 aksara.',

            'senarai_pic_ic.required' => 'Sekurang-kurangnya seorang Juruteknik mesti dipilih.',
            'senarai_pic_ic.array' => 'Senarai Juruteknik tidak sah.',
            'senarai_pic_ic.min' => 'Sekurang-kurangnya seorang Juruteknik mesti dipilih.',
            'senarai_pic_ic.*.required' => 'Maklumat Juruteknik diperlukan.',
            'senarai_pic_ic.*.string' => 'Maklumat Juruteknik tidak sah.',
            'senarai_pic_ic.*.distinct' => 'Juruteknik yang sama tidak boleh dipilih lebih daripada sekali.',
            'senarai_pic_ic.*.exists' => 'Juruteknik yang dipilih tidak sah atau tidak aktif.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $technicians = $this->input(
            'senarai_pic_ic',
            $this->input('pic_ic', [])
        );

        if (! is_array($technicians)) {
            $technicians = [$technicians];
        }

        $this->merge([
            'senarai_pic_ic' => array_values(
                array_filter(
                    array_map(
                        fn (mixed $value): string => trim((string) $value),
                        $technicians
                    ),
                    fn (string $value): bool => $value !== ''
                )
            ),
            'catatan_lawatan' => $this->filled('catatan_lawatan')
                    ? trim(
                        (string) $this->input(
                            'catatan_lawatan'
                        )
                    )
                    : null,
        ]);
    }
}
