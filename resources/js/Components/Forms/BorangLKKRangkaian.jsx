import React, { useState } from 'react';
import { useForm, router } from '@inertiajs/react';
import { Upload, FileText, X, Plus, Trash2, Save, AlertCircle, Send, Lock, AlertTriangle, Users } from 'lucide-react';
import PaparanRingkasanLKK from './PaparanRingkasanLKK';

export default function BorangLKKRangkaian({ ticket, senaraiKosSelamat, existingObjektif, senaraiPegawai = [], auth }) {

    const laporanSemasa = ticket.laporan || {};
    const currentUser = auth?.user || {};

    const isCurrentUserPIC = currentUser?.no_ic && ticket?.petugas?.some(p => p.no_ic === currentUser.no_ic);

    const perananSemasa = String(currentUser.peranan || '').trim().toLowerCase();
    const isKUTD = ['ketua_utd', 'kutd'].includes(perananSemasa);
    const isKW = perananSemasa === 'ketua_wilayah' || perananSemasa === 'kw';

    const statusFormat = String(ticket.status_tiket || '').trim().toLowerCase();
    const isSelesai = statusFormat === 'selesai';
    const isMenungguKW = ['menunggu validasi kw', 'menunggu validasi', 'validasi kw'].includes(statusFormat);
    const isDalamTindakan = statusFormat === 'dalam tindakan pegawai';
    const isPembetulan = statusFormat === 'lkk perlu pembetulan';

    // Form read-only lock state (Only editable by KUTD before it hits KW)
    const isBorangLocked = isSelesai || isMenungguKW || !isKUTD;

    // Manual processing state for router.post
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Initial data filter functions
    const dapatkanObjektifAsal = () => {
        if (ticket.laporan?.objektif) {
            try {
                const parsed = typeof ticket.laporan.objektif === 'string'
                    ? JSON.parse(ticket.laporan.objektif)
                    : ticket.laporan.objektif;
                if (Array.isArray(parsed) && parsed.length > 0) return parsed;
            } catch (e) {
                console.error("Gagal membaca struktur JSON objektif sedia ada:", e);
            }
        }
        return [{ teks: '' }];
    };

    const dapatkanCadanganAwal = () => {
        const rawCadangan = ticket.laporan?.cadangan_penambahbaikan;

        if (!rawCadangan) return [{ teks: '' }];
        if (Array.isArray(rawCadangan)) return rawCadangan.length > 0 ? rawCadangan : [{ teks: '' }];

        if (typeof rawCadangan === 'string') {
            try {
                const parsed = JSON.parse(rawCadangan);
                if (Array.isArray(parsed)) return parsed;
            } catch (e) {
                return [{ teks: rawCadangan }];
            }
        }
        return [{ teks: '' }];
    };

    // Inertia form initialization
    const { data, setData, errors } = useForm({
        objektif: dapatkanObjektifAsal(),
        pendahuluan: ticket.laporan?.pendahuluan || '',
        ulasan_teknikal: ticket.laporan?.ulasan_teknikal || ticket.konsultasi_rangkaian?.ulasan_teknikal || '',
        cadangan_penambahbaikan: dapatkanCadanganAwal(),
        logical_diagram: null,
        physical_diagram: null,
        kos_items: ticket.laporan?.kos_items
            ? (Array.isArray(ticket.laporan.kos_items)
                ? ticket.laporan.kos_items
                : JSON.parse(ticket.laporan.kos_items))
            : [{ item: '', kuantiti: 1, anggaran: 0 }],
        rumusan: ticket.laporan?.rumusan || '',
        disediakan_oleh: ticket.laporan?.disediakan_oleh || '',
        disemak_oleh: ticket.laporan?.disemak_oleh || '',
    });

    // Action logic and state handlers
    const tambahObjektif = () => {
        if (isBorangLocked) return;
        setData('objektif', [...data.objektif, { teks: '' }]);
    };

    const buangObjektif = (idx) => {
        if (isBorangLocked) return;
        setData('objektif', data.objektif.filter((_, i) => i !== idx));
    };

    const kemaskiniObjektif = (idx, nilai) => {
        if (isBorangLocked) return;
        const updated = [...data.objektif];
        updated[idx].teks = nilai;
        setData('objektif', updated);
    };

    const tambahCadangan = () => {
        setData('cadangan_penambahbaikan', [...data.cadangan_penambahbaikan, { teks: '' }]);
    };

    const buangCadangan = (indexAkanDibuang) => {
        const arrayBaru = data.cadangan_penambahbaikan.filter((_, idx) => idx !== indexAkanDibuang);
        setData('cadangan_penambahbaikan', arrayBaru);
    };

    const kemaskiniCadangan = (indexAkanDiedit, nilaiBaru) => {
        const arrayBaru = data.cadangan_penambahbaikan.map((item, idx) =>
            idx === indexAkanDiedit ? { ...item, teks: nilaiBaru } : item
        );
        setData('cadangan_penambahbaikan', arrayBaru);
    };

    // Diagram preview logic
    const logicalPreview = data.logical_diagram
        ? URL.createObjectURL(data.logical_diagram)
        : (ticket.laporan?.logical_diagram ? `/storage/${ticket.laporan.logical_diagram}` : null);

    const isLogicalPdf = data.logical_diagram
        ? data.logical_diagram.name?.toLowerCase().endsWith('.pdf')
        : ticket.laporan?.logical_diagram?.toLowerCase().endsWith('.pdf');

    const logicalFileName = data.logical_diagram
        ? data.logical_diagram.name
        : ticket.laporan?.logical_diagram?.split('/').pop();

    const physicalPreview = data.physical_diagram
        ? URL.createObjectURL(data.physical_diagram)
        : (ticket.laporan?.physical_diagram ? `/storage/${ticket.laporan.physical_diagram}` : null);

    const isPhysicalPdf = data.physical_diagram
        ? data.physical_diagram.name?.toLowerCase().endsWith('.pdf')
        : ticket.laporan?.physical_diagram?.toLowerCase().endsWith('.pdf');

    const physicalFileName = data.physical_diagram
        ? data.physical_diagram.name
        : ticket.laporan?.physical_diagram?.split('/').pop();

    // Cost table handlers
    const handleAddRow = () => {
        if (isBorangLocked) return;
        setData('kos_items', [...data.kos_items, { item: '', kuantiti: 1, anggaran: 0 }]);
    };

    const handleRemoveRow = (index) => {
        if (isBorangLocked) return;
        if (data.kos_items.length > 1) {
            setData('kos_items', data.kos_items.filter((_, i) => i !== index));
        }
    };

    const handleRowChange = (index, field, value) => {
        if (isBorangLocked) return;
        const updatedRows = data.kos_items.map((row, i) => {
            if (i === index) {
                let formattedValue = value;
                if (field === 'kuantiti') formattedValue = parseInt(value) || 0;
                if (field === 'anggaran') formattedValue = parseFloat(value) || 0;
                return { ...row, [field]: formattedValue };
            }
            return row;
        });
        setData('kos_items', updatedRows);
    };

    const hitungGrandTotal = () => {
        return (data.kos_items || []).reduce((total, row) => {
            const ktt = parseInt(row.kuantiti) || 0;
            const harga = parseFloat(row.anggaran) || 0;
            return total + (ktt * harga);
        }, 0);
    };

    // Form submission handlers via Router Post
    const handleSubmitLKK = (e) => {
        e.preventDefault();
        if (isBorangLocked) return;

        if (!data.disediakan_oleh || !data.disemak_oleh) {
            alert("Sila pilih nama pegawai untuk ruangan 'Disediakan Oleh' dan 'Disemak Oleh' di bahagian pengesahan terlebih dahulu.");
            return;
        }

        setIsSubmitting(true);
        router.post(route('tickets.storeLKKRangkaian', ticket.id_tiket), {
            ...data,
            is_draft: false,
            is_kutd_hantar: true,
            tindakan: 'KUTD_SAH_SEMAKAN'
        }, {
            preserveScroll: true,
            forceFormData: true,
            onFinish: () => setIsSubmitting(false),
            onSuccess: () => {
                alert("Laporan LKK berjaya dihantar untuk pengesahan Ketua Wilayah!");
            },
            onError: (err) => {
                console.error("Error Details:", err);
                alert("Gagal hantar laporan: Sila lengkapkan ruang yang ditanda merah.");
            }
        });
    };

    const handleSaveDraft = (e) => {
        e.preventDefault();
        if (isBorangLocked) return;

        setIsSubmitting(true);
        router.post(route('tickets.storeLKKRangkaian', ticket.id_tiket), {
            ...data,
            is_draft: true
        }, {
            preserveScroll: true,
            forceFormData: true,
            onFinish: () => setIsSubmitting(false),
            onSuccess: () => alert("Draf laporan LKK telah berjaya disimpan!"),
        });
    };

    // Summary view for review and completion stages
    if (isMenungguKW || isSelesai) {
        return <PaparanRingkasanLKK ticket={ticket} auth={auth} senaraiPegawai={senaraiPegawai} />;
    }

    const bersihkanUlasanTeknikal = () => {
        const rawData = data?.ulasan_teknikal;
        if (!rawData) return [];
        if (Array.isArray(rawData)) return rawData;
        if (typeof rawData === 'string') {
            try {
                const parsed = JSON.parse(rawData);
                if (Array.isArray(parsed)) return parsed;
            } catch (e) {
                return [{ teks: rawData }];
            }
        }
        return [];
    };

    const senaraiUlasanLkk = bersihkanUlasanTeknikal();

    // Filtered lists for the Pengesahan Section
    const senaraiDisediakanOleh = senaraiPegawai.filter(p => {
        const role = String(p.peranan || p.role || '').trim().toLowerCase();
        return !['ketua_wilayah', 'kw'].includes(role);
    });

    const senaraiDisemakOleh = senaraiPegawai.filter(p => {
        const role = String(p.peranan || p.role || '').trim().toLowerCase();
        return ['ketua_upp', 'kupp', 'ketua_utd', 'kutd', 'ketua_wilayah', 'kw'].includes(role);
    });

    return (
        <form onSubmit={handleSubmitLKK} className="space-y-4 bg-slate-50/40 p-4 md:p-3 rounded-2xl border border-white-200/60 shadow-sm w-full">

            {/* Header */}
            <div className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-sm w-full flex justify-between items-start">
                <div className="space-y-2">
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-md text-[10px] font-black bg-slate-100 text-slate-600 uppercase tracking-widest border border-slate-200/60 shadow-sm w-fit">
                        JPKN-BRK-02/B1
                    </span>
                    <h2 className="text-sm md:text-base font-black text-slate-900 uppercase tracking-tight leading-snug">
                        Laporan Kajian Keperluan Pemasangan / NaikTaraf <br className="hidden sm:inline" /> Infrastruktur Rangkaian Komputer
                    </h2>
                    <p className="text-xs text-slate-400 font-medium">
                        Sila lengkapkan laporan penilaian infrastruktur rangkaian.
                    </p>
                </div>
                {isBorangLocked && (
                    <span className="text-[9px] bg-red-100 text-red-700 px-3 py-1.5 rounded-full font-black uppercase flex items-center gap-1 shadow-sm"><Lock size={11} /> Paparan Sahaja</span>
                )}
            </div>

            {/* Nota / Ulasan Semakan */}
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

            {/* 1. Pendahuluan */}
            <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-sm space-y-2 relative">
                <label className="block text-xs font-black text-blue-900 uppercase tracking-wider">
                    1. Pendahuluan<span className="text-red-500">*</span>
                </label>
                <textarea
                    rows={5}
                    readOnly={isBorangLocked}
                    value={data.pendahuluan}
                    onChange={(e) => setData('pendahuluan', e.target.value)}
                    maxLength={2000}
                    className={`w-full p-3.5 text-xs text-gray-700 bg-white border rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all leading-relaxed ${errors.pendahuluan ? 'border-red-400' : 'border-gray-200'} ${isBorangLocked ? 'bg-gray-50/50 cursor-not-allowed' : ''}`}
                    placeholder="Sila taip pendahuluan / latar belakang di sini..."
                    required
                />
                <span className="absolute bottom-3 right-3 text-[10px] text-gray-400 font-bold">{data.pendahuluan.length} / 2000</span>
            </div>

            {/* Ulasan Teknikal oleh PIC */}
            <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-sm space-y-3 relative">
                <label className="block text-xs font-black text-blue-900 uppercase tracking-wider border-b pb-1.5">
                    Ulasan Teknikal
                </label>

                <div className="w-full p-4 text-xs text-gray-700 bg-gray-50/50 border border-gray-200 rounded-xl space-y-2.5 min-h-[100px] shadow-inner">
                    {senaraiUlasanLkk.length > 0 && senaraiUlasanLkk[0].teks ? (
                        senaraiUlasanLkk.map((item, idx) => (
                            <div key={idx} className="flex items-start gap-2 animate-in fade-in duration-150">
                                <span className="text-gray-400 font-black w-5 select-none">
                                    {idx + 1}.
                                </span>
                                <span className="text-gray-800 uppercase font-semibold leading-relaxed whitespace-pre-wrap">
                                    {item.teks}
                                </span>
                            </div>
                        ))
                    ) : (
                        <span className="text-gray-400 italic font-medium block pt-1">
                            Tiada ulasan teknikal lawatan tapak direkodkan.
                        </span>
                    )}
                </div>
            </div>

            {/* Cadangan Penambahbaikan */}
            <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-sm space-y-4 relative">
                <label className="block text-xs font-black text-blue-900 uppercase tracking-wider border-b pb-2">
                    Cadangan Penambahbaikan <span className="text-red-500">*</span>
                </label>

                <div className="space-y-2">
                    {Array.isArray(data.cadangan_penambahbaikan) && data.cadangan_penambahbaikan.map((item, idx) => (
                        <div key={idx} className="flex items-center gap-2 animate-in fade-in duration-150">
                            <span className="text-gray-400 w-5 text-xs font-semibold select-none">
                                {idx + 1}.
                            </span>

                            <input
                                type="text"
                                disabled={isBorangLocked}
                                value={item.teks || ''}
                                onChange={e => kemaskiniCadangan(idx, e.target.value)}
                                placeholder="Sila nyatakan cadangan penambahbaikan..."
                                className={`flex-1 h-11 text-xs font-semibold bg-white border rounded-xl px-4 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all ${
                                    errors.cadangan_penambahbaikan ? 'border-red-400' : 'border-gray-200'
                                } ${isBorangLocked ? 'bg-gray-50/50 cursor-not-allowed border-transparent' : ''}`}
                                required
                            />

                            {!isBorangLocked && (
                                <button
                                    type="button"
                                    onClick={() => buangCadangan(idx)}
                                    disabled={data.cadangan_penambahbaikan.length === 1}
                                    className="text-red-500 hover:text-red-700 p-2 disabled:opacity-30 cursor-pointer"
                                >
                                    <Trash2 size={16} />
                                </button>
                            )}
                        </div>
                    ))}

                    {!isBorangLocked && (
                        <button
                            type="button"
                            onClick={tambahCadangan}
                            className="inline-flex items-center gap-1.5 mt-2 px-3 py-1.5 text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors text-[10px] uppercase font-black cursor-pointer"
                        >
                            <Plus size={12} /> Tambah Cadangan
                        </button>
                    )}
                </div>
            </div>

            {/* Diagram Zone */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* 1.1 Logical Diagram */}
                <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-sm space-y-3">
                    <label className="block text-xs font-black text-blue-900 uppercase tracking-wider">1.1 Logical Diagram <span className="text-red-500">*</span></label>
                    <div className="border-2 border-dashed border-gray-200 rounded-2xl p-6 bg-slate-50/20 flex flex-col items-center justify-center min-h-[180px]">
                        {!logicalPreview ? (
                            <div className="flex flex-col items-center justify-center text-center space-y-2">
                                <div className="p-3 bg-blue-50 text-blue-600 rounded-full"><Upload size={24} /></div>
                                <span className="text-xs font-extrabold text-slate-800">Tiada fail diagram diupload</span>
                                {!isBorangLocked && (
                                    <label className="mt-2 inline-flex items-center px-4 py-2 bg-white border border-gray-200 text-xs font-bold text-gray-700 rounded-xl hover:bg-gray-50 shadow-sm cursor-pointer transition-all">
                                        Pilih Fail
                                        <input type="file" accept="image/*,.pdf" className="hidden" onChange={(e) => setData('logical_diagram', e.target.files[0])} />
                                    </label>
                                )}
                            </div>
                        ) : (
                            <div className="flex flex-col items-center w-full text-center">
                                {!isLogicalPdf ? (
                                    <img src={logicalPreview} alt="Logical Preview" className="max-h-28 object-contain rounded-lg mb-2 border border-gray-100 shadow-inner" />
                                ) : (
                                    <FileText size={40} className="text-red-500 mb-2" />
                                )}
                                <div className="flex items-center gap-2 bg-blue-50 text-blue-700 px-3 py-1.5 rounded-xl text-xs font-bold max-w-full">
                                    <span className="truncate max-w-[180px]">{logicalFileName}</span>
                                    {!isBorangLocked && (
                                        <button type="button" onClick={() => setData('logical_diagram', null)} className="text-blue-600 hover:text-red-500 ml-1"><X size={14} /></button>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                {/* 1.2 Physical Diagram */}
                <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-sm space-y-3">
                    <label className="block text-xs font-black text-blue-900 uppercase tracking-wider">1.2 Physical Diagram <span className="text-red-500">*</span></label>
                    <div className="border-2 border-dashed border-gray-200 rounded-2xl p-6 bg-slate-50/20 flex flex-col items-center justify-center min-h-[180px]">
                        {!physicalPreview ? (
                            <div className="flex flex-col items-center justify-center text-center space-y-2">
                                <div className="p-3 bg-blue-50 text-blue-600 rounded-full"><Upload size={24} /></div>
                                <span className="text-xs font-extrabold text-slate-800">Tiada fail diagram diupload</span>
                                {!isBorangLocked && (
                                    <label className="mt-2 inline-flex items-center px-4 py-2 bg-white border border-gray-200 text-xs font-bold text-gray-700 rounded-xl hover:bg-gray-50 shadow-sm cursor-pointer transition-all">
                                        Pilih Fail
                                        <input type="file" accept="image/*,.pdf" className="hidden" onChange={(e) => setData('physical_diagram', e.target.files[0])} />
                                    </label>
                                )}
                            </div>
                        ) : (
                            <div className="flex flex-col items-center w-full text-center">
                                {!isPhysicalPdf ? (
                                    <img src={physicalPreview} alt="Physical Preview" className="max-h-28 object-contain rounded-lg mb-2 border border-gray-100 shadow-inner" />
                                ) : (
                                    <FileText size={40} className="text-red-500 mb-2" />
                                )}
                                <div className="flex items-center gap-2 bg-blue-50 text-blue-700 px-3 py-1.5 rounded-xl text-xs font-bold max-w-full">
                                    <span className="truncate max-w-[180px]">{physicalFileName}</span>
                                    {!isBorangLocked && (
                                        <button type="button" onClick={() => setData('physical_diagram', null)} className="text-blue-600 hover:text-red-500 ml-1"><X size={14} /></button>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* 2. Objektif */}
            <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-sm space-y-4">
                <div className="flex items-center gap-2 text-blue-900 border-b pb-2">
                    <h4 className="text-xs uppercase tracking-wider font-black">
                        2. Objektif <span className="text-red-500">*</span>
                    </h4>
                </div>
                <div className="space-y-2">
                    {(Array.isArray(data.objektif) ? data.objektif : []).map((item, idx) => (
                        <div key={idx} className="flex items-center gap-2">
                            <span className="text-gray-400 w-5 text-xs font-semibold">
                                {String.fromCharCode(97 + idx)}.
                            </span>
                            <input
                                type="text"
                                readOnly={isBorangLocked}
                                value={item.teks || ''}
                                onChange={e => kemaskiniObjektif(idx, e.target.value)}
                                placeholder="Sila nyatakan objektif..."
                                className={`flex-1 h-10 border-gray-200 rounded-lg text-xs font-medium ${isBorangLocked ? 'bg-gray-50/50 cursor-not-allowed border-transparent' : ''}`}
                                required
                            />
                            {!isBorangLocked && (
                                <button type="button" onClick={() => buangObjektif(idx)} disabled={data.objektif.length === 1} className="text-red-500 hover:text-red-700 p-2 disabled:opacity-30 cursor-pointer">
                                    <Trash2 size={16} />
                                </button>
                            )}
                        </div>
                    ))}
                    {!isBorangLocked && (
                        <button type="button" onClick={tambahObjektif} className="inline-flex items-center gap-1.5 mt-2 px-3 py-1.5 text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors text-[10px] uppercase font-black cursor-pointer">
                            <Plus size={12} /> Tambah Objektif
                        </button>
                    )}
                </div>
            </div>

            {/* 3. Anggaran Kos */}
            <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-sm space-y-4">
                <div className="flex items-center gap-2 text-blue-900 border-b pb-2">
                    <h4 className="text-xs uppercase tracking-wider font-black">3. Anggaran Kos <span className="text-red-500">*</span> </h4>
                </div>
                <div className="border border-gray-200 rounded-xl overflow-hidden mt-2">
                    <table className="w-full text-left border-collapse">
                        <thead className="bg-gray-900 text-white text-[9px] uppercase tracking-wider">
                            <tr>
                                <th className="p-4 w-1/2">Item</th>
                                <th className="p-4 w-20 text-center">Kuantiti</th>
                                <th className="p-3 w-2/12 text-right">Anggaran Kos (RM)</th>
                                <th className="p-3 w-2/12 text-right">Jumlah (RM)</th>
                                {!isBorangLocked && <th className="p-3 w-1/12 text-center">Tindakan</th>}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 text-xs font-semibold">
                            {(data.kos_items || []).map((baris, idx) => {
                                const ktt = parseInt(baris.kuantiti) || 0;
                                const harga = parseFloat(baris.anggaran) || 0;
                                const jumlahBaris = ktt * harga;

                                return (
                                    <tr key={idx} className="hover:bg-gray-50/50">
                                        <td className="p-2">
                                            <input type="text" readOnly={isBorangLocked} value={baris.item} onChange={e => handleRowChange(idx, 'item', e.target.value)} placeholder="Sila nyatakan item..." className={`w-full h-9 border-gray-200 rounded-lg text-xs ${isBorangLocked ? 'bg-transparent border-transparent cursor-not-allowed text-slate-800 font-bold' : ''}`} required />
                                        </td>
                                        <td className="p-2 text-center">
                                            <input type="number" readOnly={isBorangLocked} min="1" value={baris.kuantiti} onChange={e => handleRowChange(idx, 'kuantiti', e.target.value)} className={`w-full h-9 border-gray-200 rounded-lg text-xs text-center ${isBorangLocked ? 'bg-transparent border-transparent cursor-not-allowed font-black' : ''}`} required />
                                        </td>
                                        <td className="p-2">
                                            <input type="number" readOnly={isBorangLocked} min="0" step="0.01" value={baris.anggaran} onChange={e => handleRowChange(idx, 'anggaran', e.target.value)} className={`w-full h-9 border-gray-200 rounded-lg text-xs text-right ${isBorangLocked ? 'bg-transparent border-transparent cursor-not-allowed font-bold' : ''}`} required />
                                        </td>
                                        <td className="p-3 text-right font-black text-gray-900 bg-slate-50/20">
                                            {jumlahBaris.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                        </td>
                                        {!isBorangLocked && (
                                            <td className="p-2 text-center">
                                                <button type="button" onClick={() => handleRemoveRow(idx)} className="text-red-500 hover:text-red-700 p-1.5" disabled={data.kos_items.length === 1}>
                                                    <Trash2 size={14} />
                                                </button>
                                            </td>
                                        )}
                                    </tr>
                                );
                            })}
                            <tr className="bg-blue-50/60 font-black text-blue-950">
                                <td colSpan="3" className="p-3 text-right uppercase tracking-wider text-[10px]">Jumlah Keseluruhan:</td>
                                <td className="p-3 text-right text-sm font-black">
                                    RM {hitungGrandTotal().toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                </td>
                                {!isBorangLocked && <td></td>}
                            </tr>
                        </tbody>
                    </table>
                    {!isBorangLocked && (
                        <div className="p-2 bg-gray-50 border-t border-gray-200">
                            <button type="button" onClick={handleAddRow} className="inline-flex items-center gap-1.5 px-3 py-1.5 text-blue-600 bg-white border border-blue-200 hover:bg-blue-50 rounded-lg transition-colors text-[10px] uppercase font-black shadow-sm">
                                <Plus size={12} /> Tambah Item Kos
                            </button>
                        </div>
                    )}
                </div>
            </div>

            {/* 4. Rumusan */}
            <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-sm space-y-2 relative">
                <label className="block text-xs font-black text-blue-900 uppercase tracking-wider">
                    4. Rumusan <span className="text-red-500">*</span>
                </label>
                <textarea
                    rows={5}
                    readOnly={isBorangLocked}
                    value={data.rumusan}
                    onChange={(e) => setData('rumusan', e.target.value)}
                    maxLength={2000}
                    className={`w-full p-3.5 text-xs text-gray-700 border rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all leading-relaxed ${errors.rumusan ? 'border-red-400' : 'border-gray-200'} ${isBorangLocked ? 'bg-gray-50/50 cursor-not-allowed' : ''}`}
                    placeholder="Sila taip rumusan di sini..."
                    required
                />
                <span className="absolute bottom-3 right-3 text-[10px] text-gray-400 font-bold">{data.rumusan.length} / 2000</span>
            </div>

            {/* 5. Pengesahan */}
            <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-sm space-y-4">
                <div className="flex items-center gap-2 text-blue-900 border-b pb-2">
                    <h4 className="text-xs uppercase tracking-wider font-black">
                         Pengesahan <span className="text-red-500">*</span>
                    </h4>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="space-y-2">
                        <label className="block text-[10px] font-black uppercase text-gray-500 tracking-wide">
                            Disediakan Oleh <span className="text-red-500">*</span>
                        </label>
                        <select
                            value={data.disediakan_oleh}
                            onChange={e => setData('disediakan_oleh', e.target.value)}
                            disabled={isBorangLocked}
                            className={`w-full h-11 text-xs font-bold border-gray-200 rounded-xl px-4 focus:outline-none focus:border-blue-500 shadow-sm uppercase text-gray-700 ${isBorangLocked ? 'bg-gray-50/50 cursor-not-allowed border-transparent' : 'bg-white cursor-pointer'}`}
                            required={!isBorangLocked}
                        >
                            <option value="">Pilih Pegawai</option>
                            {senaraiDisediakanOleh.map(p => <option key={`sedia-${p.no_ic}`} value={p.nama}>{p.nama}</option>)}
                        </select>
                    </div>
                    <div className="space-y-2">
                        <label className="block text-[10px] font-black uppercase text-gray-500 tracking-wide">
                            Disemak Oleh <span className="text-red-500">*</span>
                        </label>
                        <select
                            value={data.disemak_oleh}
                            onChange={e => setData('disemak_oleh', e.target.value)}
                            disabled={isBorangLocked}
                            className={`w-full h-11 text-xs font-bold border-gray-200 rounded-xl px-4 focus:outline-none focus:border-blue-500 shadow-sm uppercase text-gray-700 ${isBorangLocked ? 'bg-gray-50/50 cursor-not-allowed border-transparent' : 'bg-white cursor-pointer'}`}
                            required={!isBorangLocked}
                        >
                            <option value="">Pilih Pegawai</option>
                            {senaraiDisemakOleh.map(p => <option key={`semak-${p.no_ic}`} value={p.nama}>{p.nama}</option>)}
                        </select>
                    </div>
                </div>
            </div>

            {/* Button */}
            {!isBorangLocked && (
                <div className="flex justify-end items-center gap-3 pt-4 border-t border-gray-100">
                    <button type="button" disabled={isSubmitting} onClick={handleSaveDraft} className="flex items-center justify-center gap-2 bg-white hover:bg-gray-50 text-slate-700 font-extrabold text-xs uppercase tracking-wider px-5 h-11 border border-gray-200 rounded-xl shadow-sm transition-all cursor-pointer">
                        <Save size={15} /> Simpan Draf
                    </button>
                    <button type="submit" disabled={isSubmitting} className="flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs uppercase tracking-wider px-6 h-11 rounded-xl shadow-md transition-all cursor-pointer">
                        <Send size={14} /> {isSubmitting ? 'Memproses...' : 'Sahkan Laporan & Hantar'}
                    </button>
                </div>
            )}

        </form>
    );
}
