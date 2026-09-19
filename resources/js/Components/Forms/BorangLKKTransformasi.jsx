import React, { useState, useRef } from 'react';
import { useForm, router } from '@inertiajs/react';
import { Plus, Trash2, Send, FileText, Image, DollarSign, ListPlus, Target, Search, LayoutTemplate, Save, Calendar, UserCheck, CheckCircle, AlertTriangle } from 'lucide-react';
import PaparanRingkasanLKK from './PaparanRingkasanLKK';

export default function BorangLKKTransformasi({ ticket, senaraiPegawai = [], auth }) {
    const fileInputRef = useRef(null);

    // Dynamic state & user role variables
    const statusFormat = String(ticket.status_tiket || '').trim().toLowerCase();
    const laporanSemasa = ticket.laporan || {};
    const currentUser = auth?.user || {};
    const currentRole = String(currentUser?.peranan || currentUser?.role || '').trim().toLowerCase();

    // Helper function for parsing JSON array data from DB
    const parseArrayData = (dbValue, defaultArray) => {
        if (!dbValue) return defaultArray;
        if (typeof dbValue === 'string') {
            try {
                return JSON.parse(dbValue);
            } catch (e) {
                return defaultArray;
            }
        }
        if (Array.isArray(dbValue)) return dbValue;
        return defaultArray;
    };

    // Helper function to guarantee array structure
    const ensureArray = (value) => {
        if (!value) return [];
        if (Array.isArray(value)) return value;
        try {
            const parsed = typeof value === 'string' ? JSON.parse(value) : value;
            return Array.isArray(parsed) ? parsed : [parsed];
        } catch (e) {
            return [value];
        }
    };

    // Workflow phase determination logic
    const isKUPP_Fasa1 = statusFormat === 'menunggu semakan dokumen' && ['ketua_upp', 'ketua upp', 'kupp'].includes(currentRole);
    const isKUPP_SahkanTiket = statusFormat === 'menunggu semakan dokumen' && ['ketua_upp', 'ketua upp', 'kupp'].includes(currentRole);
    const isKUPP_TindakanUTD = statusFormat === 'tugasan upp' && ['ketua_upp', 'ketua upp', 'kupp'].includes(currentRole);
    const isKUTD_Fasa2 = (statusFormat === 'tugasan utd' || statusFormat === 'tindakan kutd (agihan)') && ['ketua_utd', 'ketua utd', 'kutd'].includes(currentRole);

    const petugasICList = ticket?.petugas?.map(p => p.no_ic) || [];
    const assignedPics = Array.isArray(ticket?.pic_ic) ? ticket.pic_ic : (ticket?.pic_ic ? [ticket.pic_ic] : []);
    const allPicList = [...petugasICList, ...assignedPics].filter(Boolean);
    const isPICUser = currentRole === 'pic' || currentRole === 'juruteknik' || allPicList.includes(currentUser?.no_ic);

    const isPIC_Fasa3 = ['dalam tindakan pegawai', 'tindakan pic', 'lkk perlu pembetulan'].includes(statusFormat) && isPICUser;
    const isPembetulan = statusFormat === 'lkk perlu pembetulan';

    const isKUTD_Fasa4 = ['menunggu semakan', 'semakan kutd', 'semakan laporan teknikal'].includes(statusFormat) &&
        ['ketua_utd', 'ketua utd', 'kutd'].includes(currentRole);

    const isKUPP_Fasa5 = ['menunggu pengesahan', 'menunggu pengesahan lkk'].includes(statusFormat) && ['ketua_upp', 'ketua upp', 'kupp'].includes(currentRole);

    const isKW_Fasa6 = ['menunggu validasi', 'validasi kw', 'menunggu validasi kw'].includes(statusFormat) &&
        ['ketua_wilayah', 'ketua wilayah', 'kw'].includes(currentRole);

    const statusAksesPenuhPIC = [
        'menunggu semakan',
        'semakan kutd',
        'semakan laporan teknikal',
        'menunggu pengesahan',
        'menunggu pengesahan lkk',
        'menunggu validasi',
        'menunggu validasi kw',
        'validasi kw',
        'lkk perlu pembetulan',
        'selesai'
    ];

    const tunjukFasa2KUTD = isKUTD_Fasa2;
    const tunjukFasa3PIC = isPIC_Fasa3 || isPembetulan || statusAksesPenuhPIC.includes(statusFormat);
    const tunjukFasa5KUPP = ['menunggu pengesahan', 'menunggu pengesahan lkk', 'menunggu validasi', 'menunggu validasi kw', 'validasi kw', 'selesai'].includes(statusFormat);

    // Filter officers based on roles
    const senaraiDisediakanOleh = senaraiPegawai.filter(p => {
        const role = String(p.peranan || '').toLowerCase().trim();
        return ['ketua_upp', 'ketua upp', 'kupp', 'ketua_utd', 'ketua utd', 'kutd', 'juruteknik', 'pic'].includes(role);
    });

    const senaraiDisemakOleh = senaraiPegawai.filter(p => {
        const role = String(p.peranan || '').toLowerCase().trim();
        return ['ketua_upp', 'ketua upp', 'kupp', 'ketua_utd', 'ketua utd', 'kutd', 'ketua_wilayah', 'kw'].includes(role);
    });

    // Inertia form state initialization
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        pendahuluan: laporanSemasa.pendahuluan || '',
        objektif: parseArrayData(laporanSemasa.objektif, [{ teks: '' }]),
        skop_kajian: parseArrayData(laporanSemasa.skop_kajian, [{ teks: '' }]),

        tarikh_lawatan: ticket.transformasiDigital?.tarikh_lawatan || ticket.tarikh_lawatan || '',
        masa_lawatan: ticket.transformasiDigital?.masa_lawatan || ticket.masa_lawatan || '',
        pic_ic: ticket.pic_ic || '',

        keadaan_semasa: parseArrayData(laporanSemasa.keadaan_semasa, [{ aspek: '', ulasan: '' }]),
        gambar_tapak: [],
        gambar_cadangan: null,
        cadangan_penambahbaikan: laporanSemasa.cadangan_penambahbaikan || '',
        kos_items: parseArrayData(laporanSemasa.kos_items, [{ item: '', kuantiti: '', harga_seunit: '', jumlah: 0 }]),

        rumusan: laporanSemasa.rumusan || '',
        disediakan_oleh: laporanSemasa.disediakan_oleh || '',
        disemak_oleh: laporanSemasa.disemak_oleh || '',

        ulasan_ketua: ''
    });

    const gambarTapakLama = ensureArray(laporanSemasa.gambar_tapak);
    const gambarCadanganLama = ensureArray(laporanSemasa.gambar_cadangan);

    // Dynamic field array handlers (Objektif)
    const tambahObjektif = () => setData('objektif', [...data.objektif, { teks: '' }]);
    const buangObjektif = (idx) => setData('objektif', data.objektif.filter((_, i) => i !== idx));
    const kemaskiniObjektif = (idx, nilai) => {
        const updated = [...data.objektif];
        updated[idx].teks = nilai;
        setData('objektif', updated);
    };

    // Dynamic field array handlers (Skop Kajian)
    const tambahSkop = () => setData('skop_kajian', [...data.skop_kajian, { teks: '' }]);
    const buangSkop = (idx) => setData('skop_kajian', data.skop_kajian.filter((_, i) => i !== idx));
    const kemaskiniSkop = (idx, nilai) => {
        const updated = [...data.skop_kajian];
        updated[idx].teks = nilai;
        setData('skop_kajian', updated);
    };

    // Dynamic field array handlers (Keadaan Semasa)
    const tambahKeadaan = () => setData('keadaan_semasa', [...data.keadaan_semasa, { aspek: '', ulasan: '' }]);
    const buangKeadaan = (idx) => setData('keadaan_semasa', data.keadaan_semasa.filter((_, i) => i !== idx));
    const kemaskiniKeadaan = (idx, medan, nilai) => {
        const updated = [...data.keadaan_semasa];
        updated[idx][medan] = nilai;
        setData('keadaan_semasa', updated);
    };

    // Officer assignment (PIC) handlers
    const handleAddPic = () => {
        const currentPics = Array.isArray(data.pic_ic) ? data.pic_ic : (data.pic_ic ? [data.pic_ic] : []);
        setData('pic_ic', [...currentPics, '']);
    };

    const handleRemovePic = (index) => {
        const currentPics = Array.isArray(data.pic_ic) ? data.pic_ic : [];
        const updated = currentPics.filter((_, i) => i !== index);
        setData('pic_ic', updated);
    };

    const handleUpdatePic = (index, value) => {
        const currentPics = Array.isArray(data.pic_ic) ? data.pic_ic : (data.pic_ic ? [data.pic_ic] : ['']);
        const updated = [...currentPics];
        updated[index] = value;
        setData('pic_ic', updated);
    };

    // Site photo upload & management handlers
    const handleAddGambarTapak = (e) => {
        const newFiles = Array.from(e.target.files || []);
        if (newFiles.length === 0) return;

        const existingFiles = Array.isArray(data.gambar_tapak) ? data.gambar_tapak : [];

        const combined = [...existingFiles];
        newFiles.forEach(nf => {
            if (!combined.some(f => f.name === nf.name && f.size === nf.size)) {
                combined.push(nf);
            }
        });

        setData('gambar_tapak', combined);

        if (e.target) {
            e.target.value = '';
        }
    };

    const handleRemoveGambarTapak = (indexToRemove) => {
        const existingFiles = Array.isArray(data.gambar_tapak) ? data.gambar_tapak : [];
        const updated = existingFiles.filter((_, idx) => idx !== indexToRemove);
        setData('gambar_tapak', updated);
    };

    // Cost items table handlers
    const tambahBarisKos = () => {
        setData('kos_items', [...data.kos_items, { item: '', kuantiti: '', harga_seunit: '', jumlah: 0 }]);
    };
    const buangBarisKos = (index) => {
        setData('kos_items', data.kos_items.filter((_, i) => i !== index));
    };
    const kemaskiniKos = (index, medan, nilai) => {
        const salinan = [...data.kos_items];
        salinan[index][medan] = nilai;
        if (medan === 'kuantiti' || medan === 'harga_seunit') {
            const ktt = parseFloat(salinan[index]['kuantiti']) || 0;
            const harga = parseFloat(salinan[index]['harga_seunit']) || 0;
            salinan[index]['jumlah'] = ktt * harga;
        }
        setData('kos_items', salinan);
    };

    const senaraiKosSelamat = Array.isArray(data.kos_items) ? data.kos_items : [];
    const jumlahBesar = senaraiKosSelamat.reduce((sum, item) => sum + (Number(item?.jumlah) || 0), 0);

    // Form submission handlers
    const handleSubmitWorkflow = (e, statusTindakan) => {
        e.preventDefault();
        post(route('tickets.lkk.prosesAliranPemodenan', { id_tiket: ticket.id_tiket, tindakan: statusTindakan }), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => alert("Tindakan telah berjaya diproses!"),
            onError: () => alert("Sila periksa kelayakan peranan atau kesempurnaan input data anda.")
        });
    };

    const handleSaveDraft = (e) => {
        e.preventDefault();
        post(route('tickets.lkk.storeTD', { id_tiket: ticket.id_tiket, is_draft: true }), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => alert("Draf laporan LKK telah berjaya disimpan!"),
        });
    };

    // Early return summary view for completed or final validation stages
    if (statusFormat === 'selesai' || isKW_Fasa6) {
        return <PaparanRingkasanLKK ticket={ticket} auth={auth} senaraiPegawai={senaraiPegawai} />;
    }

    return (
        <form className="space-y-6 w-full text-xs font-bold text-gray-700 animate-in fade-in duration-200">

            {/* Tajuk Utama Laporan */}
            <div className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-sm w-full">
                <div className="flex justify-between items-center flex-wrap gap-2">
                    <div className="space-y-2">
                        <h2 className="text-sm md:text-base font-black text-slate-900 uppercase tracking-tight leading-snug">
                            Laporan Kajian Kesauran Pemodenan Bilik Mesyuarat
                        </h2>
                    </div>
                </div>
            </div>

            {/* Nota / Ulasan Semakan (Jika ada pembetulan) */}
            {isPembetulan && (ticket.ulasan_semakan || ticket.catatan_penutupan) && (
                <div className="bg-amber-50 border-2 border-amber-300 p-4 md:p-5 rounded-2xl shadow-sm space-y-2 animate-in fade-in duration-200">
                    <div className="flex items-center gap-2 text-amber-900">
                        <h4 className="text-xs uppercase font-black tracking-wider">
                            Nota / Ulasan Semakan
                        </h4>
                    </div>
                    <div className="text-xs font-semibold text-amber-950 bg-white/90 p-3 rounded-xl border border-amber-200/80 leading-relaxed whitespace-pre-wrap">
                        {ticket.ulasan_semakan || ticket.catatan_penutupan}
                    </div>
                </div>
            )}

            {/* Notifikasi Ralat */}
            {Object.keys(errors).length > 0 && (
                <div className="p-4 bg-red-50 border border-red-200 rounded-xl text-xs text-red-700 space-y-1.5">
                    <div className="flex items-center gap-2 font-black uppercase text-red-800 mb-1">
                        <AlertCircle size={16} />
                        <span>Sistem Menolak Penghantaran (Sila Baiki Ruangan Berikut):</span>
                    </div>
                    <ul className="list-disc pl-5 font-bold space-y-0.5">
                        {Object.entries(errors).map(([key, message]) => (
                            <li key={key} className="capitalize">
                                <span className="text-red-950 font-black">{key.replace('_', ' ')}:</span> {message}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {/* 1. Pendahuluan/Latar Belakang */}
            <div className={`bg-white p-6 rounded-2xl border shadow-sm space-y-4 ${isKUPP_Fasa1 ? 'border-blue-400 ring-2 ring-blue-500/5' : 'border-gray-200/80'}`}>
                <div className="flex items-center gap-2 text-blue-900 border-b pb-2">
                    <h4 className="text-xs uppercase tracking-wider font-black">1. Pendahuluan/Latar Belakang <span className="text-red-500">*</span></h4>
                </div>
                <textarea
                    value={data.pendahuluan}
                    disabled={!isKUPP_Fasa1}
                    onChange={e => setData('pendahuluan', e.target.value)}
                    className="w-full min-h-[80px] border-gray-200 rounded-xl p-3 font-medium text-xs disabled:bg-gray-50 disabled:text-gray-400"
                    required
                />
            </div>

            {/* 2. Objektif */}
            <div className={`bg-white p-6 rounded-2xl border shadow-sm space-y-4 ${isKUPP_Fasa1 ? 'border-blue-400 ring-2 ring-blue-500/5' : 'border-gray-200/80'}`}>
                <div className="flex items-center gap-2 text-blue-900 border-b pb-2">
                    <h4 className="text-xs uppercase tracking-wider font-black">2. Objektif <span className="text-red-500">*</span></h4>
                </div>
                <div className="space-y-2">
                {(Array.isArray(data.objektif) ? data.objektif : []).map((item, idx) => (
                    <div key={idx} className="flex items-center gap-2">
                        <span className="text-gray-400 w-5">
                            {String.fromCharCode(97 + idx)}.
                        </span>
                        <input
                            type="text"
                            value={item.teks}
                            disabled={!isKUPP_Fasa1}
                            onChange={e => kemaskiniObjektif(idx, e.target.value)}
                            className="flex-1 h-10 border-gray-200 rounded-lg text-xs font-medium disabled:bg-gray-50 disabled:text-gray-400"
                            required
                        />
                        {isKUPP_Fasa1 && (
                            <button type="button" onClick={() => buangObjektif(idx)} disabled={data.objektif.length === 1} className="text-red-500 hover:text-red-700 p-2 disabled:opacity-30">
                                <Trash2 size={16} />
                            </button>
                        )}
                    </div>
                ))}
                {isKUPP_Fasa1 && (
                    <button type="button" onClick={tambahObjektif} className="inline-flex items-center gap-1.5 mt-2 px-3 py-1.5 text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors text-[10px] uppercase font-black">
                        <Plus size={12} /> Tambah Objektif
                    </button>
                )}
                </div>
            </div>

            {/* 3. Skop Kajian */}
            <div className={`bg-white p-6 rounded-2xl border shadow-sm space-y-4 ${isKUPP_Fasa1 ? 'border-blue-400 ring-2 ring-blue-500/5' : 'border-gray-200/80'}`}>
                <div className="flex items-center gap-2 text-blue-900 border-b pb-2">
                    <h4 className="text-xs uppercase tracking-wider font-black">3. Skop Kajian <span className="text-red-500">*</span></h4>
                </div>
                <div className="space-y-2">
                {(Array.isArray(data.skop_kajian) ? data.skop_kajian : []).map((item, idx) => (
                    <div key={idx} className="flex items-center gap-2">
                        <span className="text-gray-400 w-5">
                            {String.fromCharCode(97 + idx)}.
                        </span>
                        <input
                            type="text"
                            value={item.teks}
                            disabled={!isKUPP_Fasa1}
                            onChange={e => kemaskiniSkop(idx, e.target.value)}
                            className="flex-1 h-10 border-gray-200 rounded-lg text-xs font-medium disabled:bg-gray-50 disabled:text-gray-400"
                            required
                        />
                        {isKUPP_Fasa1 && (
                            <button type="button" onClick={() => buangSkop(idx)} disabled={data.skop_kajian.length === 1} className="text-red-500 hover:text-red-700 p-2 disabled:opacity-30">
                                <Trash2 size={16} />
                            </button>
                        )}
                    </div>
                ))}
                {isKUPP_Fasa1 && (
                    <button type="button" onClick={tambahSkop} className="inline-flex items-center gap-1.5 mt-2 px-3 py-1.5 text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors text-[10px] uppercase font-black">
                        <Plus size={12} /> Tambah Skop
                    </button>
                )}
                </div>
            </div>

            {/* Agihan Tugasan (Fasa 2 - KUTD) */}
            {tunjukFasa2KUTD && (
                <div className={`bg-white p-6 rounded-2xl border shadow-sm space-y-4 ${isKUTD_Fasa2 ? 'border-blue-400 ring-2 ring-orange-500/5' : 'border-gray-200/80'}`}>
                    <div className="flex items-center gap-2 text-blue-900 border-b pb-2">
                        <Calendar size={15} />
                        <h4 className="text-xs uppercase tracking-wider font-black">Agihan Tugasan</h4>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label className="block text-[10px] text-gray-500 uppercase mb-1">Tarikh Lawatan Tapak</label>
                            <input
                                type="date"
                                value={data.tarikh_lawatan}
                                disabled={!isKUTD_Fasa2}
                                required={isKUTD_Fasa2}
                                onChange={e => setData('tarikh_lawatan', e.target.value)}
                                className="w-full h-10 border-gray-200 rounded-lg text-xs disabled:bg-gray-50 text-gray-700 font-bold"
                            />
                        </div>
                        <div>
                            <label className="block text-[10px] text-gray-500 uppercase mb-1">Masa Lawatan Tapak</label>
                            <input
                                type="time"
                                value={data.masa_lawatan}
                                disabled={!isKUTD_Fasa2}
                                required={isKUTD_Fasa2}
                                onChange={e => setData('masa_lawatan', e.target.value)}
                                className="w-full h-10 border-gray-200 rounded-lg text-xs disabled:bg-gray-50 text-gray-700 font-bold"
                            />
                        </div>
                        <div className="space-y-2">
                            <label className="block text-[10px] text-gray-500 uppercase mb-1">
                                Pegawai Pelaksana <span className="text-red-500">*</span>
                            </label>

                            <div className="space-y-2">
                                {(Array.isArray(data.pic_ic) ? data.pic_ic : (data.pic_ic ? [data.pic_ic] : [''])).map((selectedIc, idx) => (
                                    <div key={idx} className="flex items-center gap-2">
                                        <select
                                            value={selectedIc}
                                            disabled={!isKUTD_Fasa2}
                                            onChange={e => handleUpdatePic(idx, e.target.value)}
                                            className="w-full h-10 border-gray-200 rounded-lg text-xs disabled:bg-gray-50 text-gray-700 font-bold"
                                            required
                                        >
                                            <option value="">Pilih Pegawai Teknikal</option>
                                            {senaraiPegawai.map((pegawai) => {
                                                const senaraiPilihan = Array.isArray(data.pic_ic) ? data.pic_ic : [data.pic_ic];
                                                const sudahDipilih = senaraiPilihan.includes(pegawai.no_ic) && pegawai.no_ic !== selectedIc;
                                                return (
                                                    <option key={pegawai.no_ic} value={pegawai.no_ic} disabled={sudahDipilih}>
                                                        {pegawai.nama} {sudahDipilih ? '(Telah Dipilih)' : ''}
                                                    </option>
                                                );
                                            })}
                                        </select>

                                        {isKUTD_Fasa2 && (Array.isArray(data.pic_ic) ? data.pic_ic.length : 1) > 1 && (
                                            <button
                                                type="button"
                                                onClick={() => handleRemovePic(idx)}
                                                className="text-red-500 hover:text-red-700 p-2 hover:bg-red-50 rounded-lg transition-colors"
                                            >
                                                <Trash2 size={16} />
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </div>

                            {isKUTD_Fasa2 && (
                                <button
                                    type="button"
                                    onClick={handleAddPic}
                                    className="inline-flex items-center gap-1.5 mt-1 px-3 py-1.5 text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors text-[10px] uppercase font-black"
                                >
                                    <Plus size={12} /> Tambah Pegawai Pelaksana
                                </button>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {/* Fasa 3: Pemerhatian, Lakaran & Kos (PIC) */}
            {tunjukFasa3PIC && (
                <>
                    {/* 4. Pemerhatian Keadaan Semasa */}
                    <div className={`bg-white p-6 rounded-2xl border shadow-sm space-y-4 ${isPIC_Fasa3 ? 'border-emerald-400 ring-2 ring-emerald-500/5' : 'border-gray-200/80'}`}>
                        <div className="flex items-center gap-2 text-blue-900 border-b pb-2">
                            <h4 className="text-xs uppercase tracking-wider font-black">4. Pemerhatian Keadaan Semasa <span className="text-red-500">*</span></h4>
                        </div>

                        {/* Paparan Gambar Tapak Sedia Ada */}
                        {gambarTapakLama.length > 0 && (
                            <div className="space-y-1.5">
                                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 border p-3 rounded-xl bg-gray-50/50">
                                    {gambarTapakLama.map((path, index) => (
                                        <div key={index} className="relative rounded-lg overflow-hidden border bg-white h-24">
                                            <img src={`/storage/${String(path).replace(/["']/g, '').replace(/\\/g, '')}`} alt="Gambar Tapak" className="h-full w-full object-cover" />
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Ruangan Muat Naik Gambar Tapak Baru */}
                        {isPIC_Fasa3 && (
                            <div className="space-y-3 bg-gray-50/50 p-4 rounded-xl border border-gray-100">
                                <div className="flex items-center justify-between">
                                    <label className="text-[10px] uppercase text-gray-500 font-black block">
                                        Muat Naik Gambar
                                    </label>
                                </div>

                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    accept="image/*"
                                    multiple
                                    onChange={handleAddGambarTapak}
                                    className="w-full text-xs file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-black file:bg-blue-600 file:text-white hover:file:bg-blue-700 cursor-pointer"
                                />

                                {Array.isArray(data.gambar_tapak) && data.gambar_tapak.length > 0 && (
                                    <div className="mt-3 space-y-2 border-t border-gray-200/60 pt-2.5">
                                        <span className="text-[10px] text-gray-500 font-black uppercase block">
                                            Senarai Fail Dipilih ({data.gambar_tapak.length} Fail):
                                        </span>
                                        <div className="space-y-1.5">
                                            {data.gambar_tapak.map((file, idx) => (
                                                <div
                                                    key={idx}
                                                    className="flex items-center justify-between bg-white px-3 py-2 rounded-xl border border-gray-200 text-xs font-bold text-gray-700 shadow-sm hover:border-gray-300 transition-all"
                                                >
                                                    <div className="flex items-center gap-2 min-w-0 flex-1 mr-2">
                                                        <span className="inline-flex items-center gap-1 text-[10px] text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded font-black shrink-0">
                                                            <FileText size={11} className="shrink-0" />
                                                            <span>{idx + 1}</span>
                                                        </span>
                                                        <span className="truncate">{file.name}</span>
                                                        <span className="text-[10px] text-gray-400 font-semibold shrink-0">
                                                            ({(file.size / 1024).toFixed(1)} KB)
                                                        </span>
                                                    </div>

                                                    <button
                                                        type="button"
                                                        onClick={() => handleRemoveGambarTapak(idx)}
                                                        className="text-red-500 hover:text-red-700 p-1 hover:bg-red-50 rounded-lg transition-colors shrink-0 cursor-pointer"
                                                        title="Padam fail ini"
                                                    >
                                                        <Trash2 size={15} />
                                                    </button>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Jadual Pemerhatian Aspek & Ulasan */}
                        <div className="border border-gray-200 rounded-xl overflow-hidden mt-3">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="bg-gray-100 text-[10px] uppercase text-gray-500 tracking-wider">
                                        <th className="p-3 w-1/3">Pemerhatian</th>
                                        <th className="p-3 w-7/12">Ulasan</th>
                                        {isPIC_Fasa3 && <th className="p-3 w-1/12 text-center">Tindakan</th>}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {(Array.isArray(data.keadaan_semasa) ? data.keadaan_semasa : []).map((item, idx) => (
                                        <tr key={idx} className="bg-white hover:bg-gray-50/50 transition-colors">
                                            <td className="p-2">
                                                <input
                                                    type="text"
                                                    value={item.aspek}
                                                    disabled={!isPIC_Fasa3}
                                                    onChange={e => kemaskiniKeadaan(idx, 'aspek', e.target.value)}
                                                    placeholder="Sila nyatakan aspek..."
                                                    className="w-full h-10 border-gray-200 rounded-lg text-xs font-bold disabled:bg-gray-50"
                                                    required
                                                />
                                            </td>
                                            <td className="p-2">
                                                <input
                                                    type="text"
                                                    value={item.ulasan}
                                                    disabled={!isPIC_Fasa3}
                                                    onChange={e => kemaskiniKeadaan(idx, 'ulasan', e.target.value)}
                                                    placeholder="Nyatakan ulasan..."
                                                    className="w-full h-10 border-gray-200 rounded-lg text-xs font-medium disabled:bg-gray-50"
                                                    required
                                                />
                                            </td>
                                            {isPIC_Fasa3 && (
                                                <td className="p-2 text-center">
                                                    <button type="button" onClick={() => buangKeadaan(idx)} disabled={data.keadaan_semasa.length === 1} className="text-red-500 hover:text-red-700 p-1.5 disabled:opacity-30">
                                                        <Trash2 size={16} />
                                                    </button>
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {isPIC_Fasa3 && (
                                <div className="p-2 bg-gray-50 border-t border-gray-200">
                                    <button type="button" onClick={tambahKeadaan} className="inline-flex items-center gap-1.5 px-3 py-1.5 text-blue-600 bg-white border border-blue-200 hover:bg-blue-50 rounded-lg transition-colors text-[10px] uppercase font-black shadow-sm">
                                        <Plus size={12} /> Tambah Aspek
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* 5. Cadangan Susun Atur Pemasangan Pemodenan */}
                    <div className={`bg-white p-6 rounded-2xl border shadow-sm space-y-4 ${isPIC_Fasa3 ? 'border-emerald-400 ring-2 ring-emerald-500/5' : 'border-gray-200/80'}`}>
                        <div className="flex items-center gap-2 text-blue-900 border-b pb-2">
                            <h4 className="text-xs uppercase tracking-wider font-black">5. Cadangan Susun Atur Pemasangan Pemodenan <span className="text-red-500">*</span></h4>
                        </div>

                        <div className="space-y-4">
                            {gambarCadanganLama.length > 0 && (
                                <div className="space-y-1.5">
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 border p-3 rounded-xl bg-gray-50/50">
                                        {gambarCadanganLama.map((path, index) => (
                                            <div key={index} className="rounded-lg overflow-hidden border bg-white p-2">
                                                {String(path).toLowerCase().endsWith('.pdf') ? (
                                                    <span className="text-[11px] text-red-600 font-bold block">Dokumen Fail PDF Semasa</span>
                                                ) : (
                                                    <img src={`/storage/${String(path).replace(/["']/g, '').replace(/\\/g, '')}`} alt="Lakaran Cadangan" className="max-h-32 object-contain w-full" />
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {isPIC_Fasa3 && (
                                <div className="space-y-2 bg-blue-50/50 p-4 rounded-xl border border-blue-100">
                                    <label className="text-[10px] uppercase text-blue-800 font-black block">Muat Naik Lakaran</label>
                                    <input
                                        type="file"
                                        accept="image/*,application/pdf"
                                        onChange={e => setData('gambar_cadangan', e.target.files[0])}
                                        className="w-full text-xs file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-black file:bg-blue-600 file:text-white hover:file:bg-blue-700 cursor-pointer"
                                    />
                                </div>
                            )}
                        </div>
                    </div>

                    {/* 6. Anggaran Kos */}
                    <div className={`bg-white p-6 rounded-2xl border shadow-sm space-y-4 ${isPIC_Fasa3 ? 'border-emerald-400 ring-2 ring-emerald-500/5' : 'border-gray-200/80'}`}>
                        <div className="flex items-center gap-2 text-blue-900 border-b pb-2">
                            <h4 className="text-xs uppercase tracking-wider font-black">6. Anggaran Kos <span className="text-red-500">*</span></h4>
                        </div>

                        <div className="border border-gray-200 rounded-xl overflow-hidden mt-2">
                            <table className="w-full text-left border-collapse">
                                <thead className="bg-gray-900 text-white text-[9px] uppercase tracking-wider">
                                    <tr>
                                        <th className="p-4 w-1/2">Item</th>
                                        <th className="p-4 w-20 text-center">Kuantiti</th>
                                        <th className="p-3 w-2/12 text-right">Kos Unit (RM)</th>
                                        <th className="p-3 w-2/12 text-right">Jumlah (RM)</th>
                                        {isPIC_Fasa3 && <th className="p-3 w-1/12 text-center">Tindakan</th>}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 text-xs font-semibold">
                                    {senaraiKosSelamat.map((baris, idx) => (
                                        <tr key={idx} className="hover:bg-gray-50/50">
                                            <td className="p-2">
                                                <input type="text" value={baris.item} disabled={!isPIC_Fasa3} onChange={e => kemaskiniKos(idx, 'item', e.target.value)} placeholder="Sila nyatakan item..." className="w-full h-9 border-gray-200 rounded-lg text-xs disabled:bg-gray-50" required />
                                            </td>
                                            <td className="p-2 text-center">
                                                <input type="number" min="1" value={baris.kuantiti} disabled={!isPIC_Fasa3} onChange={e => kemaskiniKos(idx, 'kuantiti', e.target.value)} placeholder="0" className="w-full h-9 border-gray-200 rounded-lg text-xs text-center disabled:bg-gray-50" required />
                                            </td>
                                            <td className="p-2">
                                                <input type="number" min="0" step="0.01" value={baris.harga_seunit} disabled={!isPIC_Fasa3} onChange={e => kemaskiniKos(idx, 'harga_seunit', e.target.value)} placeholder="0.00" className="w-full h-9 border-gray-200 rounded-lg text-xs text-right disabled:bg-gray-50" required />
                                            </td>
                                            <td className="p-3 text-right font-black text-gray-900">
                                                {baris.jumlah.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                            </td>
                                            {isPIC_Fasa3 && (
                                                <td className="p-2 text-center">
                                                    <button type="button" onClick={() => buangBarisKos(idx)} className="text-red-500 hover:text-red-700 p-1.5" disabled={data.kos_items.length === 1}>
                                                        <Trash2 size={14} />
                                                    </button>
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                    <tr className="bg-blue-50/20 font-black border-t border-gray-200">
                                        <td colSpan="3" className="p-3 text-right uppercase tracking-wider text-[10px] text-blue-900 border-r border-gray-200">Jumlah Keseluruhan:</td>
                                        <td className="p-3 text-right text-sm font-black">
                                            RM {jumlahBesar.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                        </td>
                                        {isPIC_Fasa3 && <td></td>}
                                    </tr>
                                </tbody>
                            </table>
                            {isPIC_Fasa3 && (
                                <div className="p-2 bg-gray-50 border-t border-gray-200">
                                    <button type="button" onClick={tambahBarisKos} className="inline-flex items-center gap-1.5 px-3 py-1.5 text-blue-600 bg-white border border-blue-200 hover:bg-blue-50 rounded-lg transition-colors text-[10px] uppercase font-black shadow-sm">
                                        <Plus size={12} /> Tambah Item Kos
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </>
            )}

            {/* Fasa 5: Rumusan & Pengesahan Pegawai (KUPP) */}
            {tunjukFasa5KUPP && (
                <>
                    {/* 7. Rumusan */}
                    <div className={`bg-white p-6 rounded-2xl border shadow-sm space-y-4 ${isKUPP_Fasa5 ? 'border-indigo-400 ring-2 ring-indigo-500/5' : 'border-gray-200/80'}`}>
                        <div className="flex items-center gap-2 text-blue-900 border-b pb-2">
                            <h4 className="text-xs uppercase tracking-wider font-black">7. Rumusan <span className="text-red-500">*</span></h4>
                        </div>

                        <textarea
                            value={data.rumusan}
                            disabled={!isKUPP_Fasa5}
                            onChange={e => setData('rumusan', e.target.value)}
                            className="w-full min-h-[100px] border-gray-200 rounded-xl p-3 font-medium text-xs disabled:bg-gray-50 text-gray-700"
                            placeholder="Sila nyatakan rumusan..."
                            required
                        />
                    </div>

                    {/* Pengesahan Pegawai (Disediakan Oleh & Disemak Oleh) */}
                    {(isKUPP_Fasa5 || data.disediakan_oleh || laporanSemasa.disediakan_oleh) && (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                            {/* Disediakan Oleh */}
                            <div className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-sm space-y-2">
                                <label className="block text-xs font-black text-slate-800 uppercase tracking-wider">
                                    Disediakan Oleh {isKUPP_Fasa5 && <span className="text-red-500">*</span>}
                                </label>

                                {isKUPP_Fasa5 ? (
                                    <select
                                        value={data.disediakan_oleh}
                                        onChange={e => setData('disediakan_oleh', e.target.value)}
                                        className="w-full h-11 px-3 text-xs font-bold text-gray-700 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                                        required
                                    >
                                        <option value="">Pilih pegawai</option>
                                        {(senaraiDisediakanOleh || senaraiPegawai)
                                            ?.filter(u => {
                                                const role = String(u.peranan || u.role || '').trim().toLowerCase();
                                                return ['juruteknik', 'ketua_utd', 'kutd', 'ketua_upp', 'kupp'].includes(role);
                                            })
                                            .map((u) => <option key={u.no_ic || u.nama} value={u.nama}>{u.nama}</option>)
                                        }
                                    </select>
                                ) : (
                                    <div className="pt-2 space-y-2">
                                        <div className="flex items-start">
                                            <span className="w-20 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Nama</span>
                                            <span className="w-3 text-slate-400">:</span>
                                            <span className="flex-1 text-xs font-black text-slate-900">{data.disediakan_oleh || laporanSemasa.disediakan_oleh}</span>
                                        </div>
                                        <div className="flex items-start">
                                            <span className="w-20 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Jawatan</span>
                                            <span className="w-3 text-slate-400">:</span>
                                            <span className="flex-1 text-xs font-bold text-slate-700">Penolong Pegawai Teknologi Maklumat</span>
                                        </div>
                                        <div className="flex items-start">
                                            <span className="w-20 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Tarikh</span>
                                            <span className="w-3 text-slate-400">:</span>
                                            <span className="flex-1 text-xs font-bold text-slate-700">
                                                {laporanSemasa.updated_at ? new Date(laporanSemasa.updated_at).toLocaleDateString('ms-MY', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '-'}
                                            </span>
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* Disemak Oleh */}
                            <div className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-sm space-y-2">
                                <label className="block text-xs font-black text-slate-800 uppercase tracking-wider">
                                    Disemak Oleh {isKUPP_Fasa5 && <span className="text-red-500">*</span>}
                                </label>

                                {isKUPP_Fasa5 ? (
                                    <select
                                        value={data.disemak_oleh}
                                        onChange={e => setData('disemak_oleh', e.target.value)}
                                        className="w-full h-11 px-3 text-xs font-bold text-gray-700 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                                        required
                                    >
                                        <option value="">Pilih pegawai</option>
                                        {(senaraiDisemakOleh || senaraiPegawai)
                                            ?.filter(u => {
                                                const role = String(u.peranan || u.role || '').trim().toLowerCase();
                                                return ['ketua_upp', 'kupp', 'ketua_utd', 'kutd', 'ketua_wilayah', 'kw'].includes(role);
                                            })
                                            .map((u) => <option key={u.no_ic || u.nama} value={u.nama}>{u.nama}</option>)
                                        }
                                    </select>
                                ) : (
                                    <div className="pt-2 space-y-2">
                                        <div className="flex items-start">
                                            <span className="w-20 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Nama</span>
                                            <span className="w-3 text-slate-400">:</span>
                                            <span className="flex-1 text-xs font-black text-slate-900">{data.disemak_oleh || laporanSemasa.disemak_oleh}</span>
                                        </div>
                                        <div className="flex items-start">
                                            <span className="w-20 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Jawatan</span>
                                            <span className="w-3 text-slate-400">:</span>
                                            <span className="flex-1 text-xs font-bold text-slate-700">Pegawai Teknologi Maklumat</span>
                                        </div>
                                        <div className="flex items-start">
                                            <span className="w-20 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Tarikh</span>
                                            <span className="w-3 text-slate-400">:</span>
                                            <span className="flex-1 text-xs font-bold text-slate-700">
                                                {laporanSemasa.updated_at ? new Date(laporanSemasa.updated_at).toLocaleDateString('ms-MY', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '-'}
                                            </span>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                </>
            )}

            {/* Fasa 4: Nota / Ulasan Semakan (KUTD) */}
            {isKUTD_Fasa4 && (
                <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-sm space-y-3">
                    <label className="block text-xs uppercase font-black text-gray-700">
                        Nota / Ulasan Semakan
                    </label>
                    <textarea
                        value={data.ulasan_ketua}
                        onChange={e => setData('ulasan_ketua', e.target.value)}
                        placeholder="Tulis ulasan semakan kelulusan atau sebab pemulangan di sini..."
                        className="w-full min-h-[90px] text-xs p-3 border border-gray-200 rounded-xl focus:ring-blue-500 font-medium"
                    />
                </div>
            )}

            {/* Butang Aksi / Workflow Action Buttons */}
            <div className="flex justify-end pt-2 pb-6 gap-3">
                {/* Simpan Draf */}
                {(isKUPP_Fasa1 || isPIC_Fasa3 || isKUPP_Fasa5) && (
                    <button
                        type="button"
                        disabled={processing}
                        onClick={handleSaveDraft}
                        className="flex items-center justify-center gap-2 bg-white hover:bg-gray-50 text-slate-700 font-extrabold text-xs uppercase tracking-wider px-5 h-11 border border-gray-200 rounded-xl shadow-sm transition-all cursor-pointer"
                    >
                        <Save size={15} /> Simpan Draf
                    </button>
                )}

                {/* Butang Sahkan Tiket KUPP (Fasa 1) */}
                {isKUPP_SahkanTiket && (
                    <button
                        type="button"
                        disabled={processing}
                        onClick={(e) => handleSubmitWorkflow(e, 'SAHKAN_TIKET')}
                        className="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl shadow-md uppercase tracking-wider text-[11px] font-black cursor-pointer"
                    >
                        <CheckCircle size={14} /> Sahkan Tiket
                    </button>
                )}

                {/* Butang Tindakan UTD selepas tiket disokong KUPP */}
                {isKUPP_TindakanUTD && (
                    <button
                        type="button"
                        disabled={processing}
                        onClick={(e) => handleSubmitWorkflow(e, 'HANTAR_KE_KUTD')}
                        className="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl shadow-md uppercase tracking-wider text-[11px] font-black cursor-pointer"
                    >
                        <Send size={14} /> Tindakan UTD
                    </button>
                )}

                {/* Butang Agih KUTD (Fasa 2) */}
                {isKUTD_Fasa2 && (
                    <button
                        type="button"
                        onClick={(e) => handleSubmitWorkflow(e, 'AGIH_KE_PIC')}
                        className="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl shadow-md uppercase tracking-wider text-[11px] font-black cursor-pointer"
                    >
                        <UserCheck size={14} /> Sahkan &amp; Agih Tugasan
                    </button>
                )}

                {/* Butang Hantar PIC (Fasa 3) */}
                {isPIC_Fasa3 && (
                    <button
                        type="button"
                        onClick={(e) => handleSubmitWorkflow(e, 'PIC_HANTAR_SEMAKAN')}
                        className="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl shadow-md uppercase tracking-wider text-[11px] font-black cursor-pointer"
                    >
                        <Send size={14} /> Hantar Laporan Teknikal
                    </button>
                )}

                {/* Butang Semakan KUTD (Fasa 4) */}
                {isKUTD_Fasa4 && (
                    <div className="flex justify-end gap-3 w-full">
                        <button
                            type="button"
                            disabled={processing}
                            onClick={(e) => handleSubmitWorkflow(e, 'KUTD_PEMBETULAN')}
                            className="inline-flex items-center gap-2 px-5 py-3 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded-xl shadow-sm uppercase tracking-wider text-[11px] font-black cursor-pointer transition-all"
                        >
                            <AlertTriangle size={14} /> Perlu Pembetulan
                        </button>

                        <button
                            type="button"
                            disabled={processing}
                            onClick={(e) => handleSubmitWorkflow(e, 'KUTD_SAH_SEMAKAN')}
                            className="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl shadow-md uppercase tracking-wider text-[11px] font-black cursor-pointer transition-all"
                        >
                            <CheckCircle size={14} /> Sahkan Laporan Teknikal
                        </button>
                    </div>
                )}

                {/* Butang Sahkan KUPP (Fasa 5) */}
                {isKUPP_Fasa5 && (
                    <button
                        type="button"
                        onClick={(e) => handleSubmitWorkflow(e, 'KUPP_HANTAR_VALIDASI')}
                        className="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl shadow-md uppercase tracking-wider text-[11px] font-black cursor-pointer"
                    >
                        <Send size={14} /> Sahkan Laporan LKK
                    </button>
                )}
            </div>
        </form>
    );
}
