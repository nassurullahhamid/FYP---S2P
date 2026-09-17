<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aset;
use App\Models\Pengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class AsetController extends Controller
{
    // Display a listing of inventory assets

    public function index()
    {
        $assets = Aset::latest()->get();

        $summary = Aset::select(
        'nama_aset',
        DB::raw('count(*) as jumlah'),
        DB::raw('sum(case when status = "Dipinjam" then 1 else 0 end) as dipinjam'),
        DB::raw('sum(case when status = "Tersedia" then 1 else 0 end) as baki'),
        DB::raw('sum(case when status = "Rosak" then 1 else 0 end) as rosak'),
        DB::raw('sum(case when status = "Perlu Pemeriksaan" then 1 else 0 end) as perlu_pemeriksaan')
    )
        ->groupBy('nama_aset')
        ->get();

        $users = Pengguna::select('no_ic', 'nama')->orderBy('nama', 'asc')->get();

        return Inertia::render('Admin/PengurusanAset', [
            'assets' => $assets,
            'summary' => $summary,
            'users'  => $users
        ]);
    }

    // Store a newly created asset in inventory

    public function store(Request $request)
    {
        $validated = $request->validate([
            'serial_no'   => ['required', 'string', 'max:255', 'unique:aset,serial_no'],
            'nama_aset'   => ['required', 'string', Rule::in(['Komputer Riba','Komputer Meja', 'Printer',  'TV', 'Skrin Projektor', 'Projektor'])],
            'model'       => ['required', 'string', 'max:255'],
            'cpu'         => [Rule::requiredIf(in_array($request->nama_aset, ['Komputer Riba', 'Komputer Meja'])), 'nullable', 'string', 'max:255'],
            'ram'         => [Rule::requiredIf(in_array($request->nama_aset, ['Komputer Riba', 'Komputer Meja'])), 'nullable', 'string', 'max:255'],
            'hard_disk'   => [Rule::requiredIf(in_array($request->nama_aset, ['Komputer Riba', 'Komputer Meja'])), 'nullable', 'string', 'max:255'],
            'os'          => [Rule::requiredIf(in_array($request->nama_aset, ['Komputer Riba', 'Komputer Meja'])), 'nullable', 'string', 'max:255'],
        ]);

        if (!in_array($request->nama_aset, ['Komputer Riba', 'Komputer Meja'])) {
            $validated['cpu'] = null;
            $validated['ram'] = null;
            $validated['hard_disk'] = null;
            $validated['os'] = null;
        }

        $validated['pengguna_ic'] = $request->user()->no_ic;
        $validated['status']      = 'Tersedia';

        Aset::create($validated);

        return Redirect::route('assets.index')->with('success', 'Aset baharu berjaya didaftarkan.');
    }


    //  Update an existing asset's details
    public function update(Request $request, $serial_no)
    {
        $asset = Aset::where('serial_no', $serial_no)->firstOrFail();

        $validated = $request->validate([
            'nama_aset'   => ['required', 'string', Rule::in(['Komputer Riba','Komputer Meja', 'Printer',  'TV', 'Skrin Projektor', 'Projektor'])],
            'model'       => ['required', 'string', 'max:255'],
            'pengguna_ic' => ['nullable', 'string', 'exists:pengguna,no_ic'],
            'cpu'         => [Rule::requiredIf(in_array($request->nama_aset, ['Komputer Riba', 'Komputer Meja'])), 'nullable', 'string', 'max:255'],
            'ram'         => [Rule::requiredIf(in_array($request->nama_aset, ['Komputer Riba', 'Komputer Meja'])), 'nullable', 'string', 'max:255'],
            'hard_disk'   => [Rule::requiredIf(in_array($request->nama_aset, ['Komputer Riba', 'Komputer Meja'])), 'nullable', 'string', 'max:255'],
            'os'          => [Rule::requiredIf(in_array($request->nama_aset, ['Komputer Riba', 'Komputer Meja'])), 'nullable', 'string', 'max:255'],
        ]);

        if (!in_array($request->nama_aset, ['Komputer Riba', 'Komputer Meja'])) {
            $validated['cpu'] = null;
            $validated['ram'] = null;
            $validated['hard_disk'] = null;
            $validated['os'] = null;
        }

        $asset->update($validated);

        return Redirect::back()->with('success', 'Maklumat spesifikasi aset berjaya dikemaskini.');
    }

/**
 * Record the physical return of a borrowed asset.
 */
public function storePemulangan(Request $request, $serial_no)
{
    $validated = $request->validate([
        'keadaan_aset' => [
            'required',
            'string',
            Rule::in(['Baik', 'Rosak', 'Perlu Pemeriksaan']),
        ],
        'tarikh_pulang' => [
            'required',
            'date',
            'before_or_equal:now',
        ],
        'catatan' => [
            'nullable',
            'string',
            'max:2000',
        ],
    ]);

    DB::beginTransaction();

    try {
        // Kunci rekod aset sepanjang transaksi untuk mengelakkan
        // pemulangan serentak atau pemulangan aset yang sama dua kali.
        $asset = DB::table('aset')
            ->where('serial_no', $serial_no)
            ->lockForUpdate()
            ->first();

        if (!$asset) {
            DB::rollBack();

            return Redirect::back()->withErrors([
                'pemulangan' => 'Aset tidak dijumpai dalam rekod inventori.',
            ]);
        }

        if ($asset->status !== 'Dipinjam') {
            DB::rollBack();

            return Redirect::back()->withErrors([
                'pemulangan' => 'Hanya aset berstatus Dipinjam boleh direkodkan sebagai dipulangkan.',
            ]);
        }

/*
 * Cari peminjaman TERKINI bagi serial aset ini yang masih belum
 * mempunyai rekod pemulangan.
 *
 * Satu aset boleh melalui beberapa kitaran:
 * Pinjam A -> Pulang A -> Pinjam B -> Pulang B.
 *
 * Oleh itu rekod peminjaman lama yang sudah dipulangkan mesti
 * diabaikan.
 */
$laporanCalon = DB::table('laporan')
    ->join('tiket', 'tiket.id_tiket', '=', 'laporan.id_tiket')
    ->where('laporan.kos_items', 'like', '%' . $serial_no . '%')
    ->orderByDesc('laporan.updated_at')
    ->get([
        'laporan.id_tiket',
        'laporan.kos_items',
        'laporan.updated_at',
        'tiket.status_tiket',
    ]);

$rekodPeminjaman = null;

foreach ($laporanCalon as $laporan) {
    $kosItems = json_decode($laporan->kos_items, true);

    if (!is_array($kosItems)) {
        continue;
    }

    $senaraiSiri = $kosItems['senarai_siri'] ?? [];

    if (!is_array($senaraiSiri)) {
        continue;
    }

    foreach ($senaraiSiri as $item) {
        $serialSepadan =
            ($item['serial_no'] ?? null) === $serial_no;

        $adalahPinjaman =
            ($item['mod_penggunaan'] ?? null) === 'Dipinjamkan';

        if (!$serialSepadan || !$adalahPinjaman) {
            continue;
        }

        /*
         * Jika kombinasi tiket + serial ini sudah mempunyai rekod
         * pemulangan, ia ialah kitaran peminjaman lama.
         */
        $sudahDipulangkan = DB::table('pemulangan_aset')
            ->where('id_tiket', $laporan->id_tiket)
            ->where('serial_no', $serial_no)
            ->exists();

        if ($sudahDipulangkan) {
            continue;
        }

        $rekodPeminjaman = [
            'id_tiket' => $laporan->id_tiket,
            'item' => $item,
        ];

        break 2;
    }
}

if (!$rekodPeminjaman) {
    DB::rollBack();

    return Redirect::back()->withErrors([
        'pemulangan' => 'Tiada rekod peminjaman aktif yang sah dijumpai bagi aset ini.',
    ]);
}

        DB::table('pemulangan_aset')->insert([
            'id_tiket' => $rekodPeminjaman['id_tiket'],
            'serial_no' => $serial_no,
            'tarikh_pulang' => $validated['tarikh_pulang'],
            'diterima_oleh_ic' => $request->user()->no_ic,
            'keadaan_aset' => $validated['keadaan_aset'],
            'catatan' => $validated['catatan'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
         * Aset hanya kembali Tersedia jika keadaan fizikalnya Baik.
         * Aset rosak atau perlu diperiksa tidak boleh ditawarkan semula
         * untuk peminjaman.
         */
        $statusBaharu = match ($validated['keadaan_aset']) {
            'Baik' => 'Tersedia',
            'Rosak' => 'Rosak',
            'Perlu Pemeriksaan' => 'Perlu Pemeriksaan',
        };

        DB::table('aset')
            ->where('serial_no', $serial_no)
            ->update([
                'status' => $statusBaharu,
                'updated_at' => now(),
            ]);

        DB::commit();

        return Redirect::back()->with(
            'success',
            $statusBaharu === 'Tersedia'
                ? 'Pemulangan aset berjaya direkodkan dan aset kini tersedia untuk dipinjam semula.'
                : 'Pemulangan aset berjaya direkodkan. Aset tidak ditandakan tersedia kerana memerlukan tindakan lanjut.'
        );
    } catch (\Throwable $e) {
        DB::rollBack();

        report($e);

        return Redirect::back()->withErrors([
            'pemulangan' => 'Pemulangan aset gagal diproses. Sila cuba semula.',
        ]);
    }
}

    // Remove an asset only when it has no operational history
    public function destroy($serial_no)
    {
        $asset = Aset::where('serial_no', $serial_no)->firstOrFail();

        if ($asset->status === 'Dipinjam') {
            return Redirect::back()->withErrors([
                'aset' => 'Aset sedang dipinjam dan tidak boleh dipadamkan.',
            ]);
        }

        $mempunyaiSejarah =
            DB::table('meja_bantuan')
                ->where('serial_no', $serial_no)
                ->exists()
            || DB::table('pemulangan_aset')
                ->where('serial_no', $serial_no)
                ->exists()
            || DB::table('laporan')
                ->where('kos_items', 'like', '%' . $serial_no . '%')
                ->exists();

        if ($mempunyaiSejarah) {
            return Redirect::back()->withErrors([
                'aset' => 'Aset ini mempunyai sejarah operasi dan tidak boleh dipadamkan bagi mengekalkan rekod audit sistem.',
            ]);
        }

        $asset->delete();

        return Redirect::back()->with(
            'success',
            'Aset berjaya dipadamkan daripada rekod inventori.'
        );
    }
}
