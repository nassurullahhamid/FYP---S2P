<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'emel' => $request->emel ?? $request->email,
            'token' => $request->route('token'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'emel' => 'required|email',
            'kata_laluan' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $record = DB::table('password_reset_tokens')
                    ->where('email', $request->emel)
                    ->first();

        if (! $record || ! Hash::check($request->token, $record->token)) {
            throw ValidationException::withMessages([
                'kata_laluan' => ['Token ini tidak sah atau telah luput. Sila minta pautan baru.'],
            ]);
        }

        $user = \App\Models\Pengguna::where('emel', $request->emel)->first();

        if ($user) {
            $user->forceFill([
                'kata_laluan' => Hash::make($request->kata_laluan),
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));

            DB::table('password_reset_tokens')->where('email', $request->emel)->delete();

            return redirect()->route('login')->with('status', 'Kata laluan berjaya dikemaskini!');
        }

        throw ValidationException::withMessages([
            'emel' => ['Pengguna tidak dijumpai.'],
        ]);
    }
}
