import React, { useState } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

import {
    Mail,
    Lock,
    User,
    Eye,
    EyeOff,
    ArrowRight,
    Fingerprint,
    Briefcase,
    Milestone,
    Phone,
    ShieldCheck,
    CheckCircle2
} from 'lucide-react';

export default function Register() {
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        no_ic: '',
        nama: '',
        emel: '',
        password: '',
        password_confirmation: '',
        jawatan: '',
        gred: '',
        no_telefon: '',
        peranan: 'juruteknik',
        bersetuju: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Daftar Akaun" />

            <div className="w-full max-w-4xl mx-auto flex flex-col items-center animate-in fade-in zoom-in-95 duration-500 pt-2 pb-8">

                <div className="w-full flex items-center gap-4 mb-4 px-2 sm:px-4 select-none self-start max-w-3xl mx-auto">
                    <div className="w-12 h-12 rounded-xl bg-blue-600/10 border border-blue-500/20 flex items-center justify-center text-blue-400 shrink-0 shadow-[inset_0_0_12px_rgba(59,130,246,0.1)]">
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                    </div>
                    <div>
                        <h2 className="text-base font-bold tracking-wide text-white uppercase leading-none">
                            Daftar Akaun
                        </h2>

                    </div>
                </div>

                <div className="w-full bg-[#0a1530]/60 border border-white/10 backdrop-blur-2xl rounded-[2rem] p-6 sm:p-8 lg:p-10 shadow-[0_30px_70px_-20px_rgba(0,0,0,0.6)]">

                    <form onSubmit={submit} className="space-y-6">

                        <div className="space-y-4">
                            <div className="flex items-center gap-3 text-white font-bold text-sm uppercase tracking-wider select-none border-b border-white/5 pb-2">
                                <span className="w-6 h-6 rounded-full bg-blue-600 flex items-center justify-center text-xs font-black shadow-[0_0_10px_rgba(37,99,235,0.5)]">1</span>
                                <span>Maklumat Pengenalan</span>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">

                                {/* No. Kad Pengenalan */}
                                <div className="space-y-1.5">
                                    <label className="block text-[11px] font-bold uppercase tracking-widest text-white/50 pl-0.5">No. Kad Pengenalan</label>
                                    <div className="relative">
                                        <Fingerprint className="absolute left-4 top-1/2 -translate-y-1/2 text-white/30" size={16} />
                                        <input
                                            type="text"
                                            maxLength="12"
                                            value={data.no_ic}
                                            onChange={(e) => setData('no_ic', e.target.value)}
                                            className="w-full h-12 rounded-xl border border-white/10 bg-[#122044]/40 pl-11 pr-4 text-white placeholder-white/20 text-sm font-medium focus:border-blue-500 focus:ring-1 focus:ring-blue-500/40 transition-all outline-none duration-[50000s] ease-in-out autofill:shadow-[inset_0_0_0_1000px_#001c54] [-webkit-text-fill-color:white]"
                                            required
                                        />
                                    </div>
                                    {errors.no_ic && <p className="text-[11px] text-red-400 pl-1">{errors.no_ic}</p>}
                                </div>

                                {/* Nama Penuh */}
                                <div className="space-y-1.5">
                                    <label className="block text-[11px] font-bold uppercase tracking-widest text-white/50 pl-0.5">Nama Penuh</label>
                                    <div className="relative">
                                        <User className="absolute left-4 top-1/2 -translate-y-1/2 text-white/30" size={16} />
                                        <input
                                            type="text"
                                            value={data.nama}
                                            onChange={(e) => setData('nama', e.target.value)}
                                            className="w-full h-12 rounded-xl border border-white/10 bg-[#122044]/40 pl-11 pr-4 text-white placeholder-white/20 text-sm font-medium focus:border-blue-500 focus:ring-1 focus:ring-blue-500/40 transition-all outline-none duration-[50000s] ease-in-out autofill:shadow-[inset_0_0_0_1000px_#001c54] [-webkit-text-fill-color:white]"
                                            required
                                        />
                                    </div>
                                    {errors.nama && <p className="text-[11px] text-red-400 pl-1">{errors.nama}</p>}
                                </div>

                                {/* E-mel */}
                                <div className="space-y-1.5">
                                    <label className="block text-[11px] font-bold uppercase tracking-widest text-white/50 pl-0.5">E-mel</label>
                                    <div className="relative">
                                        <Mail className="absolute left-4 top-1/2 -translate-y-1/2 text-white/30" size={16} />
                                        <input
                                            type="email"
                                            value={data.emel}
                                            onChange={(e) => setData('emel', e.target.value)}
                                            className="w-full h-12 rounded-xl border border-white/10 bg-[#122044]/40 pl-11 pr-4 text-white placeholder-white/20 text-sm font-medium focus:border-blue-500 focus:ring-1 focus:ring-blue-500/40 transition-all outline-none duration-[50000s] ease-in-out autofill:shadow-[inset_0_0_0_1000px_#001c54] [-webkit-text-fill-color:white]"
                                            required
                                        />
                                    </div>
                                    {errors.emel && <p className="text-[11px] text-red-400 pl-1">{errors.emel}</p>}
                                </div>

                                {/* No. Telefon */}
                                <div className="space-y-1.5">
                                    <label className="block text-[11px] font-bold uppercase tracking-widest text-white/50 pl-0.5">No. Telefon</label>
                                    <div className="relative">
                                        <Phone className="absolute left-4 top-1/2 -translate-y-1/2 text-white/30" size={16} />
                                        <input
                                            type="text"
                                            value={data.no_telefon}
                                            onChange={(e) => setData('no_telefon', e.target.value)}
                                            className="w-full h-12 rounded-xl border border-white/10 bg-[#122044]/40 pl-11 pr-4 text-white placeholder-white/20 text-sm font-medium focus:border-blue-500 focus:ring-1 focus:ring-blue-500/40 transition-all outline-none duration-[50000s] ease-in-out autofill:shadow-[inset_0_0_0_1000px_#001c54] [-webkit-text-fill-color:white]"
                                            required
                                        />
                                    </div>
                                    {errors.no_telefon && <p className="text-[11px] text-red-400 pl-1">{errors.no_telefon}</p>}
                                </div>

                                {/* Jawatan */}
                                <div className="space-y-1.5">
                                    <label className="block text-[11px] font-bold uppercase tracking-widest text-white/50 pl-0.5">Jawatan</label>
                                    <div className="relative">
                                        <Briefcase className="absolute left-4 top-1/2 -translate-y-1/2 text-white/30 pointer-events-none z-10" size={16} />

                                        <select
                                            value={data.jawatan}
                                            onChange={(e) => setData('jawatan', e.target.value)}
                                            className={`w-full h-12 rounded-xl border border-white/10 bg-[#122044]/40 pl-11 pr-10 text-white text-sm font-medium focus:border-blue-500 focus:ring-1 focus:ring-blue-500/40 transition-all outline-none appearance-none cursor-pointer ${
                                                data.jawatan === "" ? "text-white/20" : "text-white"
                                            }`}
                                            required
                                        >
                                            <option value="" className="bg-[#122044] text-white/40">Pilih Jawatan</option>
                                            <option value="Pegawai Teknologi Maklumat" className="bg-[#122044] text-white">Pegawai Teknologi Maklumat</option>
                                            <option value="Penolong Pegawai Teknologi Maklumat" className="bg-[#122044] text-white">Penolong Pegawai Teknologi Maklumat</option>
                                            <option value="Juruteknik Komputer" className="bg-[#122044] text-white">Juruteknik Komputer</option>
                                            <option value="Pembantu Khidmat Am" className="bg-[#122044] text-white">Pembantu Khidmat Am</option>
                                            <option value="Pembantu Tadbir" className="bg-[#122044] text-white">Pembantu Tadbir</option>
                                        </select>

                                        <div className="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-white/30">
                                            <svg className="w-4 h-4 fill-current" viewBox="0 0 20 20">
                                                <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                                            </svg>
                                        </div>
                                    </div>
                                    {errors.jawatan && <p className="text-[11px] text-red-400 pl-1">{errors.jawatan}</p>}
                                </div>

                               {/* Gred */}
                                <div className="space-y-1.5">
                                    <label className="block text-[11px] font-bold uppercase tracking-widest text-white/50 pl-0.5">Gred</label>
                                    <div className="relative">
                                        <Milestone className="absolute left-4 top-1/2 -translate-y-1/2 text-white/30 pointer-events-none" size={16} />

                                        <select
                                            value={data.gred}
                                            onChange={(e) => setData('gred', e.target.value)}
                                            className="w-full h-12 rounded-xl border border-white/10 bg-[#122044]/40 pl-11 pr-10 text-white text-sm font-medium focus:border-blue-500 focus:ring-1 focus:ring-blue-500/40 transition-all outline-none appearance-none cursor-pointer"
                                            required
                                        >
                                            <option value="" className="bg-[#122044] text-white/40">Pilih Gred</option>
                                            <option value="F10" className="bg-[#122044] text-white">F10</option>
                                            <option value="F9" className="bg-[#122044] text-white">F9</option>
                                            <option value="F6" className="bg-[#122044] text-white">F6</option>
                                            <option value="F5" className="bg-[#122044] text-white">F5</option>
                                            <option value="F2" className="bg-[#122044] text-white">F2</option>
                                            <option value="E1" className="bg-[#122044] text-white">FT1</option>
                                            <option value="N1" className="bg-[#122044] text-white">N1</option>
                                            <option value="H1" className="bg-[#122044] text-white">H1</option>
                                        </select>

                                        <div className="absolute right-4 top-1/2 -translate-y-1/2 text-white/30 pointer-events-none">
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </div>
                                    </div>
                                    {errors.gred && <p className="text-[11px] text-red-400 pl-1">{errors.gred}</p>}
                                </div>
                            </div>
                        </div>

                        <div className="space-y-4 pt-2">
                            <div className="flex items-center gap-3 text-white font-bold text-sm uppercase tracking-wider select-none border-b border-white/5 pb-2">
                                <span className="w-6 h-6 rounded-full bg-blue-600 flex items-center justify-center text-xs font-black shadow-[0_0_10px_rgba(37,99,235,0.5)]">2</span>
                                <span>Peranan</span>
                            </div>

                            <div className="space-y-1.5 w-full">
                                <label className="block text-[11px] font-bold uppercase tracking-widest text-white/50 pl-0.5">Peranan</label>
                                <div className="relative">
                                    <ShieldCheck className="absolute left-4 top-1/2 -translate-y-1/2 text-white/30 pointer-events-none z-10" size={16} />
                                    <select
                                        value={data.peranan}
                                        onChange={(e) => setData('peranan', e.target.value)}
                                        className="w-full h-12 rounded-xl border border-white/10 bg-[#122044]/40 pl-11 pr-10 text-white text-sm font-medium focus:border-blue-500 focus:ring-1 focus:ring-blue-500/40 transition-all outline-none appearance-none cursor-pointer"
                                        required
                                    >
                                        <option value="" className="bg-[#001c54] text-white/40">Pilih peranan</option>
                                        <option value="admin" className="bg-[#001c54] text-white">Admin</option>
                                        <option value="ketua_upp" className="bg-[#001c54] text-white">Ketua UPP</option>
                                        <option value="ketua_utd" className="bg-[#001c54] text-white">Ketua UTD</option>
                                        <option value="ketua_wilayah" className="bg-[#001c54] text-white">Ketua Wilayah</option>
                                        <option value="juruteknik" className="bg-[#001c54] text-white">Juruteknik</option>
                                    </select>
                                    <div className="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-white/30">
                                        <svg className="w-4 h-4 fill-current" viewBox="0 0 20 20"><path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/></svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="space-y-4 pt-2">
                            <div className="flex items-center gap-3 text-white font-bold text-sm uppercase tracking-wider select-none border-b border-white/5 pb-2">
                                <span className="w-6 h-6 rounded-full bg-blue-600 flex items-center justify-center text-xs font-black shadow-[0_0_10px_rgba(37,99,235,0.5)]">3</span>
                                <span>Kata Laluan</span>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                                {/* Kata Laluan */}
                                <div className="space-y-1.5">
                                    <label className="block text-[11px] font-bold uppercase tracking-widest text-white/50 pl-0.5">Kata Laluan</label>
                                    <div className="relative">
                                        <Lock className="absolute left-4 top-1/2 -translate-y-1/2 text-white/30 z-10" size={16} />
                                        <input
                                            type={showPassword ? 'text' : 'password'}
                                            placeholder="••••••••••••"
                                            value={data.password}
                                            onChange={(e) => setData('password', e.target.value)}
                                            className="w-full h-12 rounded-xl border border-white/10 bg-[#122044]/40 pl-11 pr-12 text-white placeholder-white/20 text-sm font-medium focus:border-blue-500 focus:ring-1 focus:ring-blue-500/40 transition-all outline-none duration-[50000s] ease-in-out autofill:shadow-[inset_0_0_0_1000px_#001c54] [-webkit-text-fill-color:white]"
                                            required
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword(!showPassword)}
                                            className="absolute right-4 top-1/2 -translate-y-1/2 text-white/30 hover:text-white transition-colors z-10"
                                        >
                                            {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                                        </button>
                                    </div>
                                    {errors.password && <p className="text-[11px] text-red-400 pl-1">{errors.password}</p>}
                                </div>

                                {/* Sahkan Kata Laluan */}
                                <div className="space-y-1.5">
                                    <label className="block text-[11px] font-bold uppercase tracking-widest text-white/50 pl-0.5">Sahkan Kata Laluan</label>
                                    <div className="relative">
                                        <Lock className="absolute left-4 top-1/2 -translate-y-1/2 text-white/30 z-10" size={16} />
                                        <input
                                            type={showConfirmPassword ? 'text' : 'password'}
                                            placeholder="••••••••••••"
                                            value={data.password_confirmation}
                                            onChange={(e) => setData('password_confirmation', e.target.value)}
                                            className="w-full h-12 rounded-xl border border-white/10 bg-[#122044]/40 pl-11 pr-12 text-white placeholder-white/20 text-sm font-medium focus:border-blue-500 focus:ring-1 focus:ring-blue-500/40 transition-all outline-none duration-[50000s] ease-in-out autofill:shadow-[inset_0_0_0_1000px_#001c54] [-webkit-text-fill-color:white]"
                                            required
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                                            className="absolute right-4 top-1/2 -translate-y-1/2 text-white/30 hover:text-white transition-colors z-10"
                                        >
                                            {showConfirmPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                                        </button>
                                    </div>
                                </div>
                            </div>

                        </div>


                        {/* BUTTON DAFTAR AKAUN */}
                        <div className="pt-1">
                            <button
                                disabled={processing}
                                className="w-full h-12 rounded-xl bg-gradient-to-r from-blue-600 to-blue-500 hover:from-blue-500 hover:to-blue-400 text-white font-bold uppercase tracking-widest text-sm shadow-lg shadow-blue-600/20 hover:shadow-blue-500/30 transition-all active:scale-[0.995] flex items-center justify-center gap-2 disabled:opacity-50 disabled:pointer-events-none"
                            >
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                </svg>
                                <span>{processing ? 'Mendaftar...' : 'DAFTAR AKAUN'}</span>
                                {!processing && <ArrowRight size={16} className="ml-1" />}
                            </button>
                        </div>

                        {/* Pautan Kembali Ke Login */}
                        <div className="text-center pt-3 select-none">
                            <p className="text-xs text-white/50 font-medium tracking-wide">
                                Sudah ada akaun?{' '}
                                <Link
                                    href={route('login')}
                                    className="font-bold text-blue-400 hover:text-blue-300 hover:underline transition-colors ml-1"
                                >
                                    Log Masuk
                                </Link>
                            </p>
                        </div>

                    </form>
                </div>
            </div>
        </GuestLayout>
    );
}
