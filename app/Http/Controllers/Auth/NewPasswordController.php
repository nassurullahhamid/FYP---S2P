<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Pengguna;
use App\Rules\S2PPassword;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
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
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'emel' => ['required', 'string', 'email'],
            'kata_laluan' => [
                'required',
                'string',
                'confirmed',
                new S2PPassword,
            ],
        ], [
            'kata_laluan.confirmed' => 'Pengesahan kata laluan baharu tidak sepadan.',
        ]);

        $resetUser = null;

        $status = DB::transaction(function () use (
            $validated,
            &$resetUser
        ) {
            $user = Pengguna::where('emel', $validated['emel'])
                ->lockForUpdate()
                ->first();

            if (! $user) {
                return Password::INVALID_USER;
            }

            return Password::broker()->reset(
                [
                    'emel' => $validated['emel'],
                    'token' => $validated['token'],
                    'password' => $validated['kata_laluan'],
                ],
                function (Pengguna $user, string $password) use (&$resetUser) {
                    $user->forceFill([
                        'kata_laluan' => Hash::make($password),
                        'remember_token' => Str::random(60),
                    ])->save();

                    $resetUser = $user;
                }
            );
        }, 3);

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'kata_laluan' => 'Pautan reset tidak sah, telah luput atau telah digunakan. Sila minta pautan baharu.',
            ]);
        }

        event(new PasswordReset($resetUser));

        return redirect()->route('login')->with(
            'status',
            'Kata laluan berjaya dikemaskini. Sila log masuk.'
        );
    }
}
