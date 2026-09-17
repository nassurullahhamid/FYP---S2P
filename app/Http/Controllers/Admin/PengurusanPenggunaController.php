<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PengurusanPenggunaController extends Controller
{
    // Display a listing of the system personnel records
    public function index()
    {
        // Fetch all personnel records ordered by their creation timeline
        $users = Pengguna::latest()->get();

        return Inertia::render('Admin/PengurusanPengguna', [
            'users' => $users
        ]);
    }

    // Store a newly created user record in the database
    public function store(Request $request)
    {
        // Validate form fields using standard input keys
        $validated = $request->validate([
            'no_ic'           => ['required', 'string', 'digits:12', 'unique:pengguna,no_ic'],
            'nama'            => ['required', 'string', 'max:255'],
            'emel'            => ['required', 'string', 'email', 'max:255', 'unique:pengguna,emel'],
            'no_telefon'      => ['required', 'string', 'max:20'],
            'jawatan'         => ['required', 'string', 'max:255'],
            'gred'            => ['required', 'string', 'max:10'],
            'peranan'         => ['required', 'string', Rule::in(['admin', 'ketua_upp', 'ketua_utd', 'juruteknik', 'ketua_wilayah'])],
            'status_pengguna' => ['required', 'string', Rule::in(['Aktif', 'Tidak Aktif'])],
            'password'        => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Encrypt the plain-text credentials using safe bcrypt hashing mechanisms
        $hashedPassword = Hash::make($request->password);

        $validated['kata_laluan'] = $hashedPassword;

        unset($validated['password']);

        Pengguna::create($validated);

        return Redirect::route('users.index')->with('success', 'Pengguna baharu berjaya didaftarkan.');
    }

    // Update an existing user's details
    public function update(Request $request, $no_ic)
    {
        $user = Pengguna::where('no_ic', $no_ic)->firstOrFail();

        $validated = $request->validate([
            'nama'            => ['required', 'string', 'max:255'],
            'emel'            => ['required', 'string', 'email', 'max:255', Rule::unique('pengguna', 'emel')->ignore($user->no_ic, 'no_ic')],
            'no_telefon'      => ['required', 'string', 'max:20'],
            'jawatan'         => ['required', 'string', 'max:255'],
            'gred'            => ['required', 'string', 'max:10'],
            'peranan'         => ['required', 'string', Rule::in(['admin', 'ketua_upp', 'ketua_utd', 'juruteknik', 'ketua_wilayah'])],
            'status_pengguna' => ['required', 'string', Rule::in(['Aktif', 'Tidak Aktif'])],
            'password'        => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        // Evaluate whether to re-encrypt and update password rows dynamically
        if ($request->filled('password')) {
            $validated['kata_laluan'] = Hash::make($request->password);
        }

        unset($validated['password']);

        $user->update($validated);

        return Redirect::route('users.index')->with('success', 'Maklumat pengguna berjaya dikemaskini.');
    }

    // Remove the specified user record from storage
    public function destroy(Request $request, $no_ic)
    {
        // Search the personnel database index
        $user = Pengguna::where('no_ic', $no_ic)->firstOrFail();

        // Pull down explicit class typing definitions directly from request pipeline wrappers
        if ($request->user()->no_ic === $user->no_ic) {
            return back()->withErrors([
                'sistem' => 'Anda tidak dibenarkan memadam akaun anda sendiri yang sedang digunakan.'
            ]);
        }

        $mempunyaiSejarah = DB::table('tiket')
            ->where('pengguna_ic', $user->no_ic)
            ->orWhere('disahkan_oleh_ic', $user->no_ic)
            ->orWhere('disemak_oleh_ic', $user->no_ic)
            ->exists()
            || DB::table('laporan')->where('pengguna_ic', $user->no_ic)->exists()
            || DB::table('tugasan_tiket')->where('no_ic', $user->no_ic)->exists()
            || DB::table('aset')->where('pengguna_ic', $user->no_ic)->exists()
            || DB::table('pemulangan_aset')->where('diterima_oleh_ic', $user->no_ic)->exists();

        if ($mempunyaiSejarah) {
            return back()->withErrors([
                'sistem' => 'Akaun ini mempunyai sejarah operasi dan tidak boleh dipadamkan. Tukar status pengguna kepada Tidak Aktif untuk mengekalkan rekod audit sistem.'
            ]);
        }

        $user->delete();

        return Redirect::route('users.index')->with('success', 'Akaun pengguna berjaya dipadamkan.');
    }
}
