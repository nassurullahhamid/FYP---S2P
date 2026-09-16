import React from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

import {
    Mail,
    KeyRound,
    ArrowRight
} from 'lucide-react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        emel: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Lupa Kata Laluan" />


            {status && (
                <div className="mb-5 mx-auto max-w-sm rounded-xl bg-emerald-500/10 border border-emerald-500/20 px-4 py-3 text-xs text-emerald-300 font-medium text-center animate-in fade-in duration-300">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-4 w-full max-w-sm mx-auto animate-in fade-in zoom-in-95 duration-400">

                {/* HEADER */}
                <div className="flex items-center gap-3.5 pb-2 select-none border-b border-white/5 mb-2">
                    <div className="w-10 h-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center text-blue-400 shrink-0 shadow-inner">
                        <KeyRound size={18} />
                    </div>
                    <div>
                        <h2 className="text-lg font-black tracking-tight text-white uppercase leading-none">
                            Lupa Kata Laluan
                        </h2>
                        <p className="mt-1 text-[11px] text-blue-200/50 font-medium leading-tight">
                            Masukkan alamat e-mel anda untuk menerima pautan set semula.
                        </p>
                    </div>
                </div>

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
                            autoFocus
                        />
                    </div>
                    {errors.emel && (
                        <p className="pl-1 text-[11px] text-red-400 font-medium">{errors.emel}</p>
                    )}
                </div>

                <div className="pt-3">
                    <button
                        disabled={processing}
                        className="w-full h-12 rounded-xl bg-gradient-to-r from-blue-600 to-blue-500 hover:from-blue-500 hover:to-blue-400 text-white font-bold uppercase tracking-widest text-[11px] shadow-lg shadow-blue-600/10 transition-all active:scale-[0.99] flex items-center justify-center gap-2 disabled:opacity-50 disabled:pointer-events-none"
                    >
                        <KeyRound size={14} className="-mt-0.5" />
                        <span>{processing ? 'MENGHANTAR...' : 'HANTAR PAUTAN SET SEMULA'}</span>
                        {!processing && <ArrowRight size={14} className="ml-0.5" />}
                    </button>
                </div>

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
