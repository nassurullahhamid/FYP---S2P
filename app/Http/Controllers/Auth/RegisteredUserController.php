<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Pengguna;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'no_ic' => 'required|string|max:12|unique:pengguna,no_ic',
            'nama' => 'required|string|max:255',
            'emel' => 'required|string|email|max:255|unique:pengguna,emel',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'jawatan' => 'nullable|string|max:255',
            'gred' => 'nullable|string|max:50',
            'no_telefon' => 'nullable|string|max:20',
            'peranan' => 'required|string|in:admin,ketua_utd,kutd,ketua_upp,kupp,ketua_wilayah,kw,juruteknik,pic',
        ]);

        $user = Pengguna::create([
            'no_ic' => $request->no_ic,
            'nama' => $request->nama,
            'emel' => $request->emel,
            'kata_laluan' => Hash::make($request->password),
            'jawatan' => $request->jawatan,
            'gred' => $request->gred,
            'no_telefon' => $request->no_telefon,
            'peranan' => $request->peranan,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
