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

        $summary = Aset::select('nama_aset',
            DB::raw('count(*) as jumlah'),
            DB::raw('sum(case when status = "Dipinjam" then 1 else 0 end) as dipinjam'),
            DB::raw('sum(case when status = "Tersedia" then 1 else 0 end) as baki')
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

    // Remove the asset from inventory
    public function destroy($serial_no)
    {
        $asset = Aset::where('serial_no', $serial_no)->firstOrFail();
        $asset->delete();

        return Redirect::back()->with('success', 'Aset berjaya dipadamkan daripada rekod inventori.');
    }
}
