<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class SaveModernizationLkkRequest extends FormRequest
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
            'kos_items' => [
                'required',
                'array',
                'min:1',
                'max:100',
            ],
            'kos_items.*' => [
                'required',
                'array',
            ],
            'kos_items.*.item' => [
                'required',
                'string',
                'max:1000',
            ],
            'kos_items.*.kuantiti' => [
                'required',
                'numeric',
                'min:1',
            ],
            'kos_items.*.harga_seunit' => [
                'required',
                'numeric',
                'min:0',
            ],
            'rumusan' => [
                'required',
                'string',
                'max:10000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'kos_items.required' => 'Anggaran kos mesti dilengkapkan.',
            'kos_items.array' => 'Format anggaran kos tidak sah.',
            'kos_items.min' => 'Sekurang-kurangnya satu item kos diperlukan.',
            'kos_items.max' => 'Anggaran kos tidak boleh melebihi 100 item.',
            'kos_items.*.item.required' => 'Nama item kos mesti dinyatakan.',
            'kos_items.*.item.string' => 'Nama item kos mesti berupa teks.',
            'kos_items.*.item.max' => 'Nama item kos tidak boleh melebihi 1000 aksara.',
            'kos_items.*.kuantiti.required' => 'Kuantiti item mesti dinyatakan.',
            'kos_items.*.kuantiti.numeric' => 'Kuantiti item mesti berupa nombor.',
            'kos_items.*.kuantiti.min' => 'Kuantiti item mestilah sekurang-kurangnya satu.',
            'kos_items.*.harga_seunit.required' => 'Harga seunit mesti dinyatakan.',
            'kos_items.*.harga_seunit.numeric' => 'Harga seunit mesti berupa nombor.',
            'kos_items.*.harga_seunit.min' => 'Harga seunit tidak boleh bernilai negatif.',
            'rumusan.required' => 'Rumusan laporan mesti dilengkapkan.',
            'rumusan.string' => 'Rumusan laporan mesti berupa teks.',
            'rumusan.max' => 'Rumusan laporan tidak boleh melebihi 10000 aksara.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $costItems = $this->input('kos_items', []);

        if (! is_array($costItems)) {
            $costItems = [];
        }

        $this->merge([
            'rumusan' => trim(
                (string) $this->input('rumusan')
            ),
            'kos_items' => array_values(
                array_filter(
                    array_map(
                        function (mixed $row): array {
                            if (! is_array($row)) {
                                return [
                                    'item' => '',
                                    'kuantiti' => null,
                                    'harga_seunit' => null,
                                    'jumlah' => 0,
                                ];
                            }

                            $quantity =
                                $row['kuantiti'] ?? null;

                            $unitPrice =
                                $row['harga_seunit'] ?? null;

                            return [
                                'item' => trim(
                                    (string) (
                                        $row['item'] ?? ''
                                    )
                                ),
                                'kuantiti' => $quantity,
                                'harga_seunit' => $unitPrice,
                                'jumlah' => (float) $quantity
                                    * (float) $unitPrice,
                            ];
                        },
                        $costItems
                    ),
                    fn (array $row): bool => $row['item'] !== ''
                        || $row['kuantiti'] !== null
                        || $row['harga_seunit'] !== null
                )
            ),
        ]);
    }
}
