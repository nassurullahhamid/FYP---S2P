<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\S2PPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    // Update the user's password
    public function update(Request $request): RedirectResponse
    {

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', new S2PPassword, 'confirmed'],
        ], [
            'current_password.current_password' => 'Kata laluan semasa yang anda masukkan adalah salah.',
            'password.confirmed' => 'Pengesahan kata laluan baharu tidak sepadan.',
        ]);

        $user = $request->user();

        $user->kata_laluan = Hash::make($validated['password']);
        $user->save();

        return back()->with('status', 'password-updated');
    }
}
