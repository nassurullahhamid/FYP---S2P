<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class SaveProcurementLkkRequest extends FormRequest
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
            'kos_items.*.jenis_peralatan' => [
                'required',
                'string',
                'max:1000',
            ],
            'kos_items.*.kuantiti' => [
                'required',
                'integer',
                'min:1',
            ],
            'kos_items.*.anggaran_kos' => [
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
            'kos_items.min' => 'Sekurang-kurangnya satu jenis peralatan diperlukan.',
            'kos_items.max' => 'Anggaran kos tidak boleh melebihi 100 item.',

            'kos_items.*.jenis_peralatan.required' => 'Jenis peralatan mesti dinyatakan.',
            'kos_items.*.jenis_peralatan.string' => 'Jenis peralatan mesti berupa teks.',
            'kos_items.*.jenis_peralatan.max' => 'Jenis peralatan tidak boleh melebihi 1000 aksara.',

            'kos_items.*.kuantiti.required' => 'Kuantiti peralatan mesti dinyatakan.',
            'kos_items.*.kuantiti.integer' => 'Kuantiti peralatan mesti berupa nombor bulat.',
            'kos_items.*.kuantiti.min' => 'Kuantiti peralatan mestilah sekurang-kurangnya satu.',

            'kos_items.*.anggaran_kos.required' => 'Anggaran kos seunit mesti dinyatakan.',
            'kos_items.*.anggaran_kos.numeric' => 'Anggaran kos mesti berupa nombor.',
            'kos_items.*.anggaran_kos.min' => 'Anggaran kos tidak boleh bernilai negatif.',

            'rumusan.required' => 'Rumusan laporan mesti dilengkapkan.',
            'rumusan.string' => 'Rumusan laporan mesti berupa teks.',
            'rumusan.max' => 'Rumusan tidak boleh melebihi 10000 aksara.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $costItems = $this->input(
            'kos_items',
            []
        );

        if (! is_array($costItems)) {
            $costItems = [];
        }

        $this->merge([
            'rumusan' => trim(
                (string) $this->input(
                    'rumusan'
                )
            ),
            'kos_items' => array_values(
                array_filter(
                    array_map(
                        static function (mixed $row): array {
                            if (! is_array($row)) {
                                return [
                                    'jenis_peralatan' => '',
                                    'kuantiti' => null,
                                    'anggaran_kos' => null,
                                    'jumlah' => 0,
                                ];
                            }

                            $quantity =
                                $row['kuantiti']
                                ?? null;

                            $estimatedCost =
                                $row['anggaran_kos']
                                ?? null;

                            return [
                                'jenis_peralatan' => trim(
                                    (string) (
                                        $row['jenis_peralatan']
                                        ?? $row['item']
                                        ?? ''
                                    )
                                ),
                                'kuantiti' => $quantity,
                                'anggaran_kos' => $estimatedCost,
                                'jumlah' => (float) $quantity
                                    * (float) $estimatedCost,
                            ];
                        },
                        $costItems
                    ),
                    static fn (array $row): bool => $row['jenis_peralatan'] !== ''
                        || $row['kuantiti'] !== null
                        || $row['anggaran_kos'] !== null
                )
            ),
        ]);
    }
}
