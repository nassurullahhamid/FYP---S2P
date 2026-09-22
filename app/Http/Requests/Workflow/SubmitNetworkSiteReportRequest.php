<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SubmitNetworkSiteReportRequest extends FormRequest
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
            empty($existingReport?->logical_diagram);

        $requiresPhysicalDiagram =
            empty($existingReport?->physical_diagram);

        return [
            'nama_lokasi_bangunan' => [
                'nullable',
                'string',
                'max:255',
            ],
            'jenis_premis' => [
                'required',
                'string',
                'max:255',
            ],
            'bilik_server' => [
                'required',
                'string',
                'max:255',
            ],
            'rack_server' => [
                'required',
                'string',
                'max:255',
            ],
            'sumber_kuasa' => [
                'required',
                'string',
                'max:255',
            ],
            'persekitaran_fizikal' => [
                'required',
                'string',
                'max:255',
            ],
            'liputan' => [
                'required',
                'string',
                'max:255',
            ],
            'jenis_capaian' => [
                'required',
                'string',
                'max:255',
            ],
            'kelajuan' => [
                'required',
                'string',
                'max:255',
            ],
            'lan' => [
                'required',
                'string',
                'max:255',
            ],
            'ap' => [
                'required',
                'string',
                'max:255',
            ],
            'firewall' => [
                'required',
                'string',
                'max:255',
            ],
            'rumusan' => [
                'required',
                'string',
                'max:5000',
            ],
            'ulasan_teknikal' => [
                'required',
                'array',
                'min:1',
                'max:20',
            ],
            'ulasan_teknikal.*' => [
                'required',
                'array',
            ],
            'ulasan_teknikal.*.teks' => [
                'required',
                'string',
                'max:2000',
            ],
            'cadangan_penambahbaikan' => [
                'required',
                'array',
                'min:1',
                'max:20',
            ],
            'cadangan_penambahbaikan.*' => [
                'required',
                'array',
            ],
            'cadangan_penambahbaikan.*.teks' => [
                'required',
                'string',
                'max:2000',
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
            'jenis_premis.required' => 'Jenis premis mesti dinyatakan.',
            'bilik_server.required' => 'Maklumat bilik server mesti dinyatakan.',
            'rack_server.required' => 'Maklumat rack server mesti dinyatakan.',
            'sumber_kuasa.required' => 'Maklumat sumber kuasa mesti dinyatakan.',
            'persekitaran_fizikal.required' => 'Maklumat persekitaran fizikal mesti dinyatakan.',
            'liputan.required' => 'Maklumat liputan mesti dinyatakan.',
            'jenis_capaian.required' => 'Jenis capaian mesti dinyatakan.',
            'kelajuan.required' => 'Kelajuan capaian mesti dinyatakan.',
            'lan.required' => 'Maklumat LAN mesti dinyatakan.',
            'ap.required' => 'Maklumat access point mesti dinyatakan.',
            'firewall.required' => 'Maklumat firewall mesti dinyatakan.',
            'rumusan.required' => 'Rumusan lawatan mesti dinyatakan.',
            'rumusan.max' => 'Rumusan tidak boleh melebihi 5000 aksara.',
            'ulasan_teknikal.required' => 'Sekurang-kurangnya satu ulasan teknikal diperlukan.',
            'ulasan_teknikal.array' => 'Format ulasan teknikal tidak sah.',
            'ulasan_teknikal.min' => 'Sekurang-kurangnya satu ulasan teknikal diperlukan.',
            'ulasan_teknikal.max' => 'Ulasan teknikal tidak boleh melebihi 20 catatan.',
            'ulasan_teknikal.*.teks.required' => 'Setiap ulasan teknikal mesti mempunyai catatan.',
            'ulasan_teknikal.*.teks.string' => 'Ulasan teknikal mesti berupa teks.',
            'ulasan_teknikal.*.teks.max' => 'Setiap ulasan teknikal tidak boleh melebihi 2000 aksara.',
            'cadangan_penambahbaikan.required' => 'Sekurang-kurangnya satu cadangan diperlukan.',
            'cadangan_penambahbaikan.array' => 'Format cadangan tidak sah.',
            'cadangan_penambahbaikan.min' => 'Sekurang-kurangnya satu cadangan diperlukan.',
            'cadangan_penambahbaikan.max' => 'Cadangan tidak boleh melebihi 20 catatan.',
            'cadangan_penambahbaikan.*.required' => 'Maklumat cadangan diperlukan.',
            'cadangan_penambahbaikan.*.array' => 'Format cadangan tidak sah.',
            'cadangan_penambahbaikan.*.teks.required' => 'Setiap cadangan mesti mempunyai catatan.',
            'cadangan_penambahbaikan.*.teks.string' => 'Cadangan mesti berupa teks.',
            'cadangan_penambahbaikan.*.teks.max' => 'Setiap cadangan tidak boleh melebihi 2000 aksara.',
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
}
