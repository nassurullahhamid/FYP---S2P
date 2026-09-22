<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SaveNetworkLkkRequest extends FormRequest
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
        $isDraft = $this->boolean('is_draft');

        $ticketId = (string) $this->route(
            'id_tiket',
            ''
        );

        $existingReport = $ticketId !== ''
            ? DB::table('laporan')
                ->where('id_tiket', $ticketId)
                ->first()
            : null;

        $requiresLogicalDiagram =
            ! $isDraft
            && empty($existingReport?->logical_diagram);

        $requiresPhysicalDiagram =
            ! $isDraft
            && empty($existingReport?->physical_diagram);

        return [
            'is_draft' => [
                'required',
                'boolean',
            ],
            'pendahuluan' => [
                Rule::requiredIf(! $isDraft),
                'nullable',
                'string',
                'max:10000',
            ],
            'objektif' => [
                Rule::requiredIf(! $isDraft),
                'nullable',
                'array',
                'min:1',
                'max:20',
            ],
            'objektif.*' => [
                'required',
                'array',
            ],
            'objektif.*.teks' => [
                Rule::requiredIf(! $isDraft),
                'nullable',
                'string',
                'max:2000',
            ],
            'cadangan_penambahbaikan' => [
                Rule::requiredIf(! $isDraft),
                'nullable',
                'array',
                'min:1',
                'max:20',
            ],
            'cadangan_penambahbaikan.*' => [
                'required',
                'array',
            ],
            'cadangan_penambahbaikan.*.teks' => [
                Rule::requiredIf(! $isDraft),
                'nullable',
                'string',
                'max:2000',
            ],
            'kos_items' => [
                Rule::requiredIf(! $isDraft),
                'nullable',
                'array',
                'min:1',
                'max:100',
            ],
            'kos_items.*' => [
                'required',
                'array',
            ],
            'kos_items.*.item' => [
                Rule::requiredIf(! $isDraft),
                'nullable',
                'string',
                'max:255',
            ],
            'kos_items.*.kuantiti' => [
                Rule::requiredIf(! $isDraft),
                'nullable',
                'integer',
                'min:1',
                'max:100000',
            ],
            'kos_items.*.anggaran' => [
                Rule::requiredIf(! $isDraft),
                'nullable',
                'numeric',
                'min:0',
                'max:999999999.99',
            ],
            'rumusan' => [
                Rule::requiredIf(! $isDraft),
                'nullable',
                'string',
                'max:10000',
            ],
            'disediakan_oleh' => [
                'nullable',
                'string',
                'max:255',
            ],
            'disemak_oleh' => [
                'nullable',
                'string',
                'max:255',
            ],
            'logical_diagram' => [
                Rule::requiredIf($requiresLogicalDiagram),
                'nullable',
                'file',
                'mimes:png,jpg,jpeg,pdf',
                'max:5120',
            ],
            'physical_diagram' => [
                Rule::requiredIf($requiresPhysicalDiagram),
                'nullable',
                'file',
                'mimes:png,jpg,jpeg,pdf',
                'max:5120',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'is_draft.required' => 'Mod penyimpanan LKK diperlukan.',
            'is_draft.boolean' => 'Mod penyimpanan LKK tidak sah.',
            'pendahuluan.required' => 'Pendahuluan LKK mesti dilengkapkan.',
            'objektif.required' => 'Sekurang-kurangnya satu objektif diperlukan.',
            'objektif.array' => 'Format objektif tidak sah.',
            'objektif.min' => 'Sekurang-kurangnya satu objektif diperlukan.',
            'objektif.*.teks.required' => 'Setiap objektif mesti mempunyai catatan.',
            'cadangan_penambahbaikan.required' => 'Sekurang-kurangnya satu cadangan diperlukan.',
            'cadangan_penambahbaikan.array' => 'Format cadangan tidak sah.',
            'cadangan_penambahbaikan.*.teks.required' => 'Setiap cadangan mesti mempunyai catatan.',
            'kos_items.required' => 'Sekurang-kurangnya satu item kos diperlukan.',
            'kos_items.array' => 'Format item kos tidak sah.',
            'kos_items.*.item.required' => 'Nama item kos mesti dinyatakan.',
            'kos_items.*.kuantiti.required' => 'Kuantiti item kos mesti dinyatakan.',
            'kos_items.*.kuantiti.min' => 'Kuantiti item kos mestilah sekurang-kurangnya satu.',
            'kos_items.*.anggaran.required' => 'Anggaran harga item mesti dinyatakan.',
            'kos_items.*.anggaran.numeric' => 'Anggaran harga item mesti berupa nombor.',
            'rumusan.required' => 'Rumusan LKK mesti dilengkapkan.',

            'logical_diagram.required' => 'Logical diagram mesti dimuat naik.',
            'logical_diagram.file' => 'Logical diagram mesti berupa fail.',
            'logical_diagram.mimes' => 'Logical diagram mestilah fail PNG, JPG, JPEG atau PDF.',
            'logical_diagram.max' => 'Logical diagram tidak boleh melebihi 5 MB.',
            'physical_diagram.required' => 'Physical diagram mesti dimuat naik.',
            'physical_diagram.file' => 'Physical diagram mesti berupa fail.',
            'physical_diagram.mimes' => 'Physical diagram mestilah fail PNG, JPG, JPEG atau PDF.',
            'physical_diagram.max' => 'Physical diagram tidak boleh melebihi 5 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_draft' => $this->boolean('is_draft'),
        ]);
    }
}
