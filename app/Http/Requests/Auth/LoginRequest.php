<?php

namespace App\Http\Requests\Auth;

use App\Models\Pengguna;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    // Determine if the user is authorized to make this request
    public function authorize(): bool
    {
        return true;
    }

    // Get the validation rules that apply to the request
    public function rules(): array
    {
        return [
            'no_ic' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    // Attempt to authenticate the request's credentials
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = [
            'no_ic' => $this->input('no_ic'),
            'password' => $this->input('password'),
            'status_pengguna' => 'Aktif',
        ];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), 60);

            throw ValidationException::withMessages([
                'no_ic' => (function () {
                    $pengguna = Pengguna::where(
                        'no_ic',
                        $this->input('no_ic')
                    )->first();

                    if (
                        $pengguna &&
                        $pengguna->status_pengguna !== 'Aktif' &&
                        Hash::check(
                            (string) $this->input('password'),
                            $pengguna->kata_laluan
                        )
                    ) {
                        return 'Akaun anda tidak aktif.';
                    }

                    return 'No. kad pengenalan atau kata laluan tidak sah.';
                })(),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    // Ensure the login request is not rate limited
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'no_ic' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    // Get the rate limiting throttle key for the request
    public function throttleKey(): string
    {
        return 'login:'.hash('sha256', trim((string) $this->input('no_ic')).'|'.$this->ip());
    }
}
