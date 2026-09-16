import React, { useState, useEffect } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

import {
    User,
    Lock,
    Eye,
    EyeOff,
    ArrowRight
} from 'lucide-react';

export default function Login({ status, canResetPassword }) {

    const [showPassword, setShowPassword] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        no_ic: '',
        password: '',
        remember: false,
    });

    useEffect(() => {
        return () => {
            reset('password');
        };
    }, []);

    const submit = (e) => {
        e.preventDefault();
        post(route('login'));
    };

    return (
        <GuestLayout>
            <Head title="Log Masuk" />

            {status && (
                <div className="mb-5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 px-4 py-3 text-xs text-emerald-300 font-medium text-center">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-4 w-full max-w-sm mx-auto">

                <div className="space-y-1">
                    <div className="relative">
                        <User
                            className="absolute left-4 top-1/2 -translate-y-1/2 text-white/40"
                            size={18}
                        />

                        <input
                            type="text"
                            name="no_ic"
                            maxLength="12"
                            value={data.no_ic}
                            onChange={(e) => setData('no_ic', e.target.value)}
                            placeholder="No. Kad Pengenalan"
                            className={`w-full h-12 rounded-xl border bg-white/5 pl-12 pr-4 text-white placeholder-white/30 text-sm font-medium focus:border-blue-500 focus:ring-1 focus:ring-blue-500/50 transition-all outline-none transition-colors duration-[50000s] ease-in-out autofill:shadow-[inset_0_0_0_1000px_#001c54] [-webkit-text-fill-color:white] ${
                                errors.no_ic ? 'border-red-500/50 focus:ring-red-500/30' : 'border-white/10'
                            }`}
                            required
                        />
                    </div>

                    {errors.no_ic && (
                        <p className="text-left pl-1 text-[11px] text-red-400 font-medium animate-in fade-in duration-200">
                            {errors.no_ic}
                        </p>
                    )}
                </div>

                <div className="space-y-1">
                    <div className="relative">
                        <Lock
                            className="absolute left-4 top-1/2 -translate-y-1/2 text-white/40"
                            size={18}
                        />

                        <input
                            type={showPassword ? 'text' : 'password'}
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            placeholder="Kata Laluan"
                            className={`w-full h-12 rounded-xl border bg-white/5 pl-12 pr-4 text-white placeholder-white/30 text-sm font-medium focus:border-blue-500 focus:ring-1 focus:ring-blue-500/50 transition-all outline-none transition-colors duration-[50000s] ease-in-out autofill:shadow-[inset_0_0_0_1000px_#001c54] [-webkit-text-fill-color:white] ${
                                errors.password ? 'border-red-500/50 focus:ring-red-500/30' : 'border-white/10'
                            }`}
                            required
                        />

                        <button
                            type="button"
                            onClick={() => setShowPassword(!showPassword)}
                            className="absolute right-4 top-1/2 -translate-y-1/2 text-white/40 hover:text-white transition-colors"
                        >
                            {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                        </button>
                    </div>

                    {errors.password && (
                        <p className="text-left pl-1 text-[11px] text-red-400 font-medium animate-in fade-in duration-200">
                            {errors.password}
                        </p>
                    )}
                </div>

                <div className="flex items-center justify-between pt-1 px-1">
                    <label className="flex items-center gap-2 cursor-pointer group select-none">
                        <input
                            type="checkbox"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                            className="rounded border-white/20 bg-white/5 text-blue-600 focus:ring-0 focus:ring-offset-0 h-4 w-4 transition-all"
                        />
                        <span className="text-xs text-white/70 font-medium group-hover:text-white transition-colors">
                            Ingat Saya
                        </span>
                    </label>

                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="text-xs font-semibold text-blue-400 hover:text-blue-300 transition-colors"
                        >
                            Lupa Kata Laluan?
                        </Link>
                    )}
                </div>

                <div className="pt-2">
                    <button
                        disabled={processing}
                        className="w-full h-12 rounded-xl bg-gradient-to-r from-blue-600 to-blue-500 hover:from-blue-500 hover:to-blue-400 text-white font-bold uppercase tracking-wider text-xs shadow-lg shadow-blue-600/10 transition-all active:scale-[0.99] flex items-center justify-center gap-2 disabled:opacity-50 disabled:pointer-events-none"
                    >
                        <Lock size={13} className="-mt-0.5" />
                        <span>{processing ? 'Menghubung...' : 'Log Masuk'}</span>
                        {!processing && <ArrowRight size={14} className="ml-0.5" />}
                    </button>
                </div>

                <div className="flex items-center pt-4 select-none">
                    <div className="flex-1 border-t border-white/5"></div>
                    <p className="mx-4 text-xs text-white/40 font-medium tracking-wide">
                        Belum ada akaun?{' '}
                        <Link
                            href={route('register')}
                            className="font-bold text-blue-400 hover:text-blue-300 hover:underline transition-colors ml-0.5"
                        >
                            Daftar Sekarang
                        </Link>
                    </p>
                    <div className="flex-1 border-t border-white/5"></div>
                </div>

            </form>
        </GuestLayout>
    );
}
