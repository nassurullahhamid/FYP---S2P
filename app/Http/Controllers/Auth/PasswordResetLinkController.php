<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'emel' => ['required', 'string', 'email'],
        ], [
            'emel.required' => 'Sila masukkan alamat e-mel.',
            'emel.email' => 'Sila masukkan alamat e-mel yang sah.',
        ]);

        $status = Password::broker()->sendResetLink([
            'emel' => $validated['emel'],
        ]);

        if (in_array($status, [
            Password::RESET_LINK_SENT,
            Password::INVALID_USER,
            Password::RESET_THROTTLED,
        ], true)) {
            return back()->with(
                'status',
                'Jika e-mel berdaftar, pautan reset akan dihantar. Semak peti masuk atau folder spam. Jika baru meminta pautan, sila tunggu sebelum mencuba lagi.'
            );
        }

        throw ValidationException::withMessages([
            'emel' => 'Permintaan reset tidak dapat diproses. Sila cuba lagi.',
        ]);
    }
}
