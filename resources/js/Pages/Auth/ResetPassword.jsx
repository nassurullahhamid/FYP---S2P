import React, { useState, useEffect } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm, Link } from '@inertiajs/react';

import {
    Mail,
    Lock,
    Eye,
    EyeOff,
    RefreshCw,
    ArrowRight
} from 'lucide-react';

export default function ResetPassword({ token, emel }) {
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        token: token,
        emel: emel || '',
        kata_laluan: '',
        kata_laluan_confirmation: '',
    });

    useEffect(() => {
        return () => {
            reset('kata_laluan', 'kata_laluan_confirmation');
        };
    }, []);

    const submit = (e) => {
        e.preventDefault();
        post(route('password.store'));
    };

    return (
        <GuestLayout>
            <Head title="Set Semula Kata Laluan" />

            <form onSubmit={submit} className="space-y-4 w-full max-w-sm mx-auto animate-in fade-in zoom-in-95 duration-400">

                {/* HEADER*/}
                <div className="flex items-center gap-3.5 pb-2 select-none border-b border-white/5 mb-2">
                    <div className="w-10 h-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center text-blue-400 shrink-0 shadow-inner">
                        <RefreshCw size={18} />
                    </div>
                    <div>
                        <h2 className="text-lg font-black tracking-tight text-white uppercase leading-none">
                            Kata Laluan Baru
                        </h2>
                        <p className="mt-1 text-[11px] text-blue-200/50 font-medium leading-tight">
                            Sila tetapkan kata laluan baru akaun anda.
                        </p>
                    </div>
                </div>

                {/* ALAMAT E-MEL */}
                <div className="space-y-1">
                    <label className="block text-[10px] font-bold uppercase tracking-widest text-white/40 pl-0.5">
                        Alamat E-Mel
                    </label>
                    <div className="relative">
                        <Mail className="absolute left-4 top-1/2 -translate-y-1/2 text-white/40 z-10" size={16} />
                        <input
                            type="email"
                            name="emel"
                            value={data.emel}
                            onChange={(e) => setData('emel', e.target.value)}
                            className={`w-full h-12 rounded-xl border bg-white/5 pl-11 pr-4 text-white text-sm font-medium focus:border-blue-500 focus:ring-1 focus:ring-blue-500/50 transition-all outline-none duration-[50000s] ease-in-out autofill:shadow-[inset_0_0_0_1000px_#001c54] [-webkit-text-fill-color:white] ${
                                errors.emel ? 'border-red-500/50 focus:ring-red-500/30' : 'border-white/10'
                            }`}
                            required
                        />
                    </div>
                    {errors.emel && (
                        <p className="pl-1 text-[11px] text-red-400 font-medium">{errors.emel}</p>
                    )}
                </div>

                {/* KATA LALUAN BARU */}
                <div className="space-y-1">
                    <label className="block text-[10px] font-bold uppercase tracking-widest text-white/40 pl-0.5">
                        Kata Laluan Baru
                    </label>
                    <div className="relative">
                        <Lock className="absolute left-4 top-1/2 -translate-y-1/2 text-white/40 z-10" size={16} />
                        <input
                            type={showPassword ? 'text' : 'password'}
                            name="kata_laluan"
                            value={data.kata_laluan}
                            onChange={(e) => setData('kata_laluan', e.target.value)}
                            className={`w-full h-12 rounded-xl border bg-white/5 pl-11 pr-11 text-white text-sm font-medium focus:border-blue-500 focus:ring-1 focus:ring-blue-500/50 transition-all outline-none duration-[50000s] ease-in-out autofill:shadow-[inset_0_0_0_1000px_#001c54] [-webkit-text-fill-color:white] ${
                                errors.kata_laluan ? 'border-red-500/50 focus:ring-red-500/30' : 'border-white/10'
                            }`}
                            required
                        />
                        <button
                            type="button"
                            onClick={() => setShowPassword(!showPassword)}
                            className="absolute right-4 top-1/2 -translate-y-1/2 text-white/40 hover:text-blue-400 transition-colors z-20"
                        >
                            {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                        </button>
                    </div>
                    {errors.kata_laluan && (
                        <p className="pl-1 text-[11px] text-red-400 font-medium">{errors.kata_laluan}</p>
                    )}
                </div>

                {/* SAHKAN KATA LALUAN BARU */}
                <div className="space-y-1">
                    <label className="block text-[10px] font-bold uppercase tracking-widest text-white/40 pl-0.5">
                        Sahkan Kata Laluan Baru
                    </label>
                    <div className="relative">
                        <Lock className="absolute left-4 top-1/2 -translate-y-1/2 text-white/40 z-10" size={16} />
                        <input
                            type={showConfirmPassword ? 'text' : 'password'}
                            name="kata_laluan_confirmation"
                            value={data.kata_laluan_confirmation}
                            onChange={(e) => setData('kata_laluan_confirmation', e.target.value)}
                            className={`w-full h-12 rounded-xl border bg-white/5 pl-11 pr-11 text-white text-sm font-medium focus:border-blue-500 focus:ring-1 focus:ring-blue-500/50 transition-all outline-none duration-[50000s] ease-in-out autofill:shadow-[inset_0_0_0_1000px_#001c54] [-webkit-text-fill-color:white] ${
                                errors.kata_laluan_confirmation ? 'border-red-500/50 focus:ring-red-500/30' : 'border-white/10'
                            }`}
                            required
                        />
                        <button
                            type="button"
                            onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                            className="absolute right-4 top-1/2 -translate-y-1/2 text-white/40 hover:text-blue-400 transition-colors z-20"
                        >
                            {showConfirmPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                        </button>
                    </div>
                    {errors.kata_laluan_confirmation && (
                        <p className="pl-1 text-[11px] text-red-400 font-medium">{errors.kata_laluan_confirmation}</p>
                    )}
                </div>

                {/*  BUTANG HANTAR */}
                <div className="pt-3">
                    <button
                        disabled={processing}
                        className="w-full h-12 rounded-xl bg-gradient-to-r from-blue-600 to-blue-500 hover:from-blue-500 hover:to-blue-400 text-white font-bold uppercase tracking-widest text-[11px] shadow-lg shadow-blue-600/10 transition-all active:scale-[0.99] flex items-center justify-center gap-2 disabled:opacity-50 disabled:pointer-events-none"
                    >
                        <RefreshCw size={14} className="-mt-0.5" />
                        <span>{processing ? 'MENGEMASKINI...' : 'KEMASKINI KATA LALUAN'}</span>
                        {!processing && <ArrowRight size={14} className="ml-0.5" />}
                    </button>
                </div>

                {/* PAUTAN BALIK */}
                <div className="flex items-center pt-3 select-none">
                    <div className="flex-1 border-t border-white/5"></div>
                    <Link
                        href={route('login')}
                        className="mx-4 text-xs font-bold text-blue-400 hover:text-blue-300 hover:underline transition-colors tracking-wide"
                    >
                        Kembali ke Log Masuk
                    </Link>
                    <div className="flex-1 border-t border-white/5"></div>
                </div>

            </form>
        </GuestLayout>
    );
}
