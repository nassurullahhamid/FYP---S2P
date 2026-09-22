<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pengguna;
use App\Rules\S2PPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PengurusanPenggunaController extends Controller
{
    // Display a listing of the system personnel records
    public function index()
    {
        // Fetch all personnel records ordered by their creation timeline
        $users = Pengguna::latest()->get();

        return Inertia::render('Admin/PengurusanPengguna', [
            'users' => $users,
        ]);
    }

    // Store a newly created user record in the database
    public function store(Request $request)
    {
        // Validate form fields using standard input keys
        $validated = $request->validate([
            'no_ic' => ['required', 'string', 'digits:12', 'unique:pengguna,no_ic'],
            'nama' => ['required', 'string', 'max:255'],
            'emel' => ['required', 'string', 'email', 'max:255', 'unique:pengguna,emel'],
            'no_telefon' => ['required', 'string', 'max:20'],
            'jawatan' => ['required', 'string', Rule::in([
                'Pegawai Teknologi Maklumat',
                'Penolong Pegawai Teknologi Maklumat',
                'Juruteknik Komputer',
                'Pembantu Tadbir',
                'Pembantu Khidmat Am',
            ])],
            'gred' => ['required', 'string', Rule::in([
                'F12', 'F10', 'F9', 'F7', 'F6', 'F5',
                'FT2', 'FT1', 'N2', 'N1', 'H1',
            ])],
            'peranan' => ['required', 'string', Rule::in(['admin', 'ketua_upp', 'ketua_utd', 'juruteknik', 'ketua_wilayah'])],
            'status_pengguna' => ['required', 'string', Rule::in(['Aktif', 'Tidak Aktif'])],
            'password' => ['required', 'string', new S2PPassword, 'confirmed'],
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
            'nama' => ['required', 'string', 'max:255'],
            'emel' => ['required', 'string', 'email', 'max:255', Rule::unique('pengguna', 'emel')->ignore($user->no_ic, 'no_ic')],
            'no_telefon' => ['required', 'string', 'max:20'],
            'jawatan' => ['required', 'string', 'max:255'],
            'gred' => ['required', 'string', 'max:10'],
            'peranan' => ['required', 'string', Rule::in(['admin', 'ketua_upp', 'ketua_utd', 'juruteknik', 'ketua_wilayah'])],
            'status_pengguna' => ['required', 'string', Rule::in(['Aktif', 'Tidak Aktif'])],
            'password' => ['nullable', 'string', new S2PPassword, 'confirmed'],
        ]);

        // Evaluate whether to re-encrypt and update password rows dynamically
        if ($request->filled('password')) {
            $validated['kata_laluan'] = Hash::make($request->password);
        }

        unset($validated['password']);

        // S2P: perlindungan Admin semasa kemas kini.
        DB::transaction(function () use ($request, $no_ic, $validated) {
            // Kunci rekod dalam urutan sama untuk perubahan serentak.
            $users = Pengguna::query()
                ->orderBy('no_ic')
                ->lockForUpdate()
                ->get();

            $actor = $users->first(
                fn ($item) => (string) $item->no_ic ===
                    (string) $request->user()->no_ic
            );

            abort_unless(
                $actor &&
                $actor->peranan === 'admin' &&
                $actor->status_pengguna === 'Aktif',
                403,
                'Hanya Admin aktif boleh mengemaskini pengguna.'
            );

            $target = $users->first(
                fn ($item) => (string) $item->no_ic === (string) $no_ic
            );

            abort_unless($target, 404);

            $isSelf = (string) $actor->no_ic === (string) $target->no_ic;

            if ($isSelf && $validated['peranan'] !== 'admin') {
                throw ValidationException::withMessages([
                    'peranan' => 'Anda tidak boleh menukar peranan Admin sendiri.',
                ]);
            }

            if ($isSelf && $validated['status_pengguna'] !== 'Aktif') {
                throw ValidationException::withMessages([
                    'status_pengguna' => 'Anda tidak boleh menyahaktifkan akaun sendiri.',
                ]);
            }

            $activeAdmins = $users->filter(
                fn ($item) => $item->peranan === 'admin' &&
                    $item->status_pengguna === 'Aktif'
            )->count();

            $wasActiveAdmin =
                $target->peranan === 'admin' &&
                $target->status_pengguna === 'Aktif';

            $willBeActiveAdmin =
                $validated['peranan'] === 'admin' &&
                $validated['status_pengguna'] === 'Aktif';

            if ($wasActiveAdmin && ! $willBeActiveAdmin && $activeAdmins <= 1) {
                throw ValidationException::withMessages([
                    'peranan' => 'Sistem mesti mempunyai sekurang-kurangnya seorang Admin aktif.',
                    'status_pengguna' => 'Admin aktif terakhir tidak boleh dinyahaktifkan.',
                ]);
            }

            $target->update($validated);
        }, 3);

        return Redirect::route('users.index')->with('success', 'Maklumat pengguna berjaya dikemaskini.');
    }

    // Remove the specified user record from storage
    public function destroy(Request $request, $no_ic)
    {
        // Search the personnel database index
        $user = Pengguna::where('no_ic', $no_ic)->firstOrFail();

        // S2P: akaun Admin tidak boleh dipadam terus.
        if ($user->peranan === 'admin') {
            return back()->withErrors([
                'sistem' => 'Akaun Admin tidak boleh dipadam terus. Minta Admin lain menukar peranan akaun ini terlebih dahulu.',
            ]);
        }

        // Pull down explicit class typing definitions directly from request pipeline wrappers
        if ($request->user()->no_ic === $user->no_ic) {
            return back()->withErrors([
                'sistem' => 'Anda tidak dibenarkan memadam akaun anda sendiri yang sedang digunakan.',
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
                'sistem' => 'Akaun ini mempunyai sejarah operasi dan tidak boleh dipadamkan. Tukar status pengguna kepada Tidak Aktif untuk mengekalkan rekod audit sistem.',
            ]);
        }

        DB::transaction(fn () => $user->delete());

        return Redirect::route('users.index')->with('success', 'Akaun pengguna berjaya dipadamkan.');
    }
}
