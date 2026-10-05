import React, { useState, useRef } from 'react';
import { useForm, router } from '@inertiajs/react';
import { Plus, Trash2, Send, Save, Lock, XCircle, CheckCircle2, Calendar, UserCheck, CheckCircle, AlertTriangle } from 'lucide-react';
import PaparanRingkasanLKK from './PaparanRingkasanLKK';

export default function BorangLKKPembekalanICTV2({ ticket, senaraiPegawai = [], auth }) {

    const statusFormat = String(ticket.status_tiket || '').trim().toLowerCase();
    const laporanSemasa = ticket.laporan || {};
    const currentUser = auth?.user || {};

    const perananSemasa = String(currentUser.peranan || currentUser.role || '').trim().toLowerCase();
    const isKUPP = ['ketua_upp', 'ketua upp', 'kupp'].includes(perananSemasa);
    const isKUTD = ['ketua_utd', 'ketua utd', 'kutd'].includes(perananSemasa);
    const isKW = ['ketua_wilayah', 'ketua wilayah', 'kw'].includes(perananSemasa);

    const petugasICList = ticket?.petugas?.map(p => p.no_ic) || [];
    const assignedPics = Array.isArray(ticket?.pic_ic) ? ticket.pic_ic : (ticket?.pic_ic ? [ticket.pic_ic] : []);
    const allPicList = [...petugasICList, ...assignedPics].filter(Boolean);

    const isPICUser = allPicList.includes(currentUser?.no_ic) ||
                      (['pic', 'juruteknik'].includes(perananSemasa) && (allPicList.length === 0 || allPicList.includes(currentUser?.no_ic)));

    const parseJSONData = (dbValue, defaultObj) => {
        if (!dbValue) return defaultObj;
        if (typeof dbValue === 'string') {
            try { return JSON.parse(dbValue); } catch (e) { return defaultObj; }
        }
        if (typeof dbValue === 'object') return dbValue;
        return defaultObj;
    };

    const parseArrayData = (dbValue, defaultArray) => {
        if (!dbValue) return defaultArray;
        if (typeof dbValue === 'string') {
            try { return JSON.parse(dbValue); } catch (e) { return defaultArray; }
        }
        if (Array.isArray(dbValue)) return dbValue;
        return defaultArray;
    };

    const formatTarikhAuto = (tarikh) => tarikh ? tarikh.split(' ')[0] : '';
    const parsedPendahuluan = parseJSONData(laporanSemasa.pendahuluan, {});

    const isKUPP_Fasa1 =
        statusFormat === 'menunggu semakan'
        && isKUPP;

    const isKUTD_Fasa2 =
        statusFormat === 'disemak'
        && isKUTD;
    const isPIC_Fasa3 = ['dalam tindakan pegawai', 'tindakan pic', 'lkk perlu pembetulan'].includes(statusFormat) && isPICUser;
    const isPembetulan = statusFormat === 'lkk perlu pembetulan';

    const isKUTD_Fasa4 = ['menunggu semakan', 'semakan kutd', 'semakan laporan teknikal'].includes(statusFormat) && isKUTD;

    const isKUPP_Fasa5 = ['menunggu pengesahan', 'menunggu pengesahan lkk'].includes(statusFormat) && isKUPP;
    const isKW_Fasa6 = ['menunggu validasi', 'validasi kw', 'menunggu validasi kw'].includes(statusFormat) && isKW;

    const tunjukAgihanKUTD = isKUTD_Fasa2;

    // The PIC forms will show up if it's currently PIC's phase OR any phase after it.
    const tunjukFasa3PIC = isPIC_Fasa3 || isPembetulan || isKUTD_Fasa4 || isKUPP_Fasa5 || [
        'menunggu semakan',
        'semakan kutd',
        'semakan laporan teknikal',
        'menunggu pengesahan',
        'menunggu pengesahan lkk',
        'menunggu validasi',
        'menunggu validasi kw',
        'validasi kw',
        'selesai'
    ].includes(statusFormat);

    const tunjukPengesahanVerifikasi = isKUTD_Fasa4 || isKUPP_Fasa5 || ['menunggu pengesahan', 'menunggu pengesahan lkk', 'menunggu validasi', 'selesai'].includes(statusFormat);

    const statusBolehLihatPemohon = [
        'baru',
        'tindakan kupp',
        'tugasan upp',
        'menunggu klasifikasi',
        'tugasan utd',
        'tindakan kutd (agihan)',
        ''
    ];
    const paparSenaraiPemohon = isKUPP_Fasa1 || isKUTD_Fasa2 || statusBolehLihatPemohon.includes(statusFormat);

    const senaraiDisediakanOleh = senaraiPegawai.filter(p => {
        const role = String(p.peranan || p.role || '').toLowerCase().trim();
        return ['juruteknik', 'ketua_utd', 'kutd', 'ketua_upp', 'kupp', 'pic'].includes(role);
    });

    const senaraiDisemakOleh = senaraiPegawai.filter(p => {
        const role = String(p.peranan || p.role || '').toLowerCase().trim();
        return ['ketua_upp', 'ketua upp', 'kupp', 'ketua_utd', 'ketua utd', 'kutd', 'ketua_wilayah', 'kw'].includes(role);
    });

    const initialPendahuluan = {
        jabatan: parsedPendahuluan.jabatan || ticket.agensi || '',
        tarikh_terima: parsedPendahuluan.tarikh_terima || formatTarikhAuto(ticket.tarikh_terima || ticket.created_at) || '',
        pegawai_nama: parsedPendahuluan.pegawai_nama || ticket.nama_pemohon || '',
        pegawai_notel: parsedPendahuluan.pegawai_notel || ticket.notel_pemohon || '',
        pegawai_emel: parsedPendahuluan.pegawai_emel || ticket.emel_pemohon || '',
        no_rujukan: parsedPendahuluan.no_rujukan || '',
        bilangan_kakitangan: parsedPendahuluan.bilangan_kakitangan || '',
        tujuan: parsedPendahuluan.tujuan || '',
        peruntukan: parsedPendahuluan.peruntukan || '',
        tanggungjawab: parsedPendahuluan.tanggungjawab || '',
        emel_ketua_cawangan: parsedPendahuluan.emel_ketua_cawangan || '',
        pegawai_jawatan: parsedPendahuluan.pegawai_jawatan || '',
        cdo_nama: parsedPendahuluan.cdo_nama || '',
        kupp_sah: parsedPendahuluan.kupp_sah || false
    };

    // Fix: Make sure to start with at least 1 empty row if no data exists
    const { data, setData, post, processing } = useForm({
        pendahuluan: initialPendahuluan,
        tarikh_lawatan: ticket.tarikh_lawatan || ticket.transformasi_digital?.tarikh_lawatan || '',
        masa_lawatan: ticket.masa_lawatan || ticket.transformasi_digital?.masa_lawatan || '',
        catatan_lawatan:
            ticket.transformasi_digital?.catatan_lawatan || '',
        pic_ic: allPicList.length > 0 ? [...new Set(allPicList)] : [''],
        hasil_kajian: parseArrayData(laporanSemasa.hasil_kajian, [{ nama_pemohon: '', jawatan_pemohon: '', keadaan_semasa: '', justifikasi_cadangan: '', anggaran_kos: 0 }]),
        kos_items: parseArrayData(laporanSemasa.kos_items, [{ jenis_peralatan: '', kuantiti: 1, anggaran_kos: 0, jumlah: 0 }]),
        rumusan: laporanSemasa.rumusan || '',
        disediakan_oleh: laporanSemasa.disediakan_oleh || '',
        disemak_oleh: laporanSemasa.disemak_oleh || '',
        ulasan_ketua: '',
    });

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

    const kemaskiniMeta = (medan, nilai) => {
        if (!isKUPP_Fasa1) return;
        setData('pendahuluan', { ...data.pendahuluan, [medan]: nilai });
    };

    const tambahKajian = () => {
        if (!isKUPP_Fasa1) return;
        setData('hasil_kajian', [...data.hasil_kajian, { nama_pemohon: '', jawatan_pemohon: '', keadaan_semasa: '', justifikasi_cadangan: '', anggaran_kos: 0 }]);
    };

    const buangKajian = (idx) => {
        if (!isKUPP_Fasa1) return;
        setData('hasil_kajian', data.hasil_kajian.filter((_, i) => i !== idx));
    };

    const kemaskiniKajian = (idx, medan, nilai) => {
        const updated = [...data.hasil_kajian];
        updated[idx][medan] = nilai;
        setData('hasil_kajian', updated);
    };

    const tambahPeralatan = () => {
        if(isPIC_Fasa3) setData('kos_items', [...data.kos_items, { jenis_peralatan: '', kuantiti: 1, anggaran_kos: 0, jumlah: 0 }]);
    };

    const buangPeralatan = (idx) => {
        if(isPIC_Fasa3) setData('kos_items', data.kos_items.filter((_, i) => i !== idx));
    };

    const kemaskiniPeralatan = (idx, medan, nilai) => {
        if (!isPIC_Fasa3) return;
        const salinan = [...data.kos_items];
        salinan[idx][medan] = nilai;
        if (medan === 'kuantiti' || medan === 'anggaran_kos' || medan === 'harga_seunit') {
            const ktt = parseFloat(salinan[idx]['kuantiti']) || 0;
            const kos = parseFloat(salinan[idx]['anggaran_kos'] || salinan[idx]['harga_seunit']) || 0;
            salinan[idx]['jumlah'] = ktt * kos;
        }
        setData('kos_items', salinan);
    };

    const submitWorkflow = (action) => {
        const endpoint = {
            HANTAR_KE_KUTD:
                'tickets.workflow.reviewProcurement',
            AGIH_KE_PIC:
                'tickets.workflow.assignProcurement',
        }[action];

        if (!endpoint) {
            alert(
                'Tindakan ini akan disambungkan dalam fasa seterusnya.'
            );

            return;
        }

        const payload = action === 'AGIH_KE_PIC'
            ? {
                tarikh_lawatan: data.tarikh_lawatan,
                masa_lawatan: data.masa_lawatan,
                catatan_lawatan:
                    data.catatan_lawatan || null,
                senarai_pic_ic: (
                    Array.isArray(data.pic_ic)
                        ? data.pic_ic
                        : [data.pic_ic]
                ).filter(Boolean),
            }
            : {
                pendahuluan: data.pendahuluan,
                hasil_kajian: data.hasil_kajian.map(
                    (item) => ({
                        nama_pemohon:
                            item.nama_pemohon || '',
                        jawatan_pemohon:
                            item.jawatan_pemohon || '',
                    })
                ),
            };

        router.post(
            route(endpoint, {
                id_tiket: ticket.id_tiket,
            }),
            payload,
            {
                preserveScroll: true,
                onSuccess: () => {
                    alert(
                        'Maklumat kajian telah dikemaskini.'
                    );
                },
                onError: (errors) => {
                    const messages = Object.entries(errors)
                        .map(
                            ([field, message]) =>
                                `- ${field}: ${message}`
                        )
                        .join('\n');

                    alert(
                        `Tindakan tidak berjaya.\n\n${messages}`
                    );
                },
            }
        );
    };

    if (statusFormat === 'selesai' || isKW_Fasa6) {
        return <PaparanRingkasanLKK ticket={ticket} auth={auth} senaraiPegawai={senaraiPegawai} />;
    }

    return (
        <form onSubmit={(e) => { e.preventDefault(); }} className="space-y-6 text-xs font-bold text-gray-700 animate-in fade-in duration-200">

            {isPembetulan && (ticket.ulasan_semakan || ticket.catatan_penutupan) && (
                <div className="bg-amber-50 border-2 border-amber-300 p-4 md:p-5 rounded-2xl shadow-sm space-y-2 animate-in fade-in duration-200">
                    <div className="flex items-center gap-2 text-amber-900">
                        <AlertTriangle className="text-amber-600 shrink-0" size={18} />
                        <h4 className="text-xs uppercase font-black tracking-wider">
                            Nota Pembetulan / Ulasan Semakan
                        </h4>
                    </div>
                    <div className="text-xs font-semibold text-amber-950 bg-white/90 p-3 rounded-xl border border-amber-200/80 leading-relaxed whitespace-pre-wrap">
                        {ticket.ulasan_semakan || ticket.catatan_penutupan}
                    </div>
                </div>
            )}

            <div className={`p-6 rounded-2xl border shadow-sm space-y-4 ${isKUPP_Fasa1 ? 'bg-white border-blue-400 ring-2 ring-blue-500/5' : 'bg-white border-gray-200/80'}`}>
                <div className="text-blue-900 border-b border-blue-100 pb-2 flex justify-between items-center">
                    <h4 className="text-xs uppercase font-black flex items-center gap-2">
                        Butiran Permohonan <span className="text-red-500">*</span>
                    </h4>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-1">
                        <label className="text-[10px] uppercase text-gray-400 font-black">Kementerian / Jabatan</label>
                        <input type="text" readOnly value={data.pendahuluan.jabatan} className="w-full h-10 rounded-xl text-xs bg-gray-100 border-transparent text-gray-600 cursor-not-allowed font-bold" />
                    </div>
                    <div className="space-y-1">
                        <label className="text-[10px] uppercase text-gray-400 font-black">Tarikh Permohonan</label>
                        <input type="date" readOnly={!isKUPP_Fasa1} value={data.pendahuluan.tarikh_terima} onChange={e => kemaskiniMeta('tarikh_terima', e.target.value)} className={`w-full h-10 rounded-xl text-xs ${isKUPP_Fasa1 ? 'bg-white border-gray-200 focus:ring-blue-500' : 'bg-gray-50 border-gray-200 cursor-not-allowed text-gray-600'}`} required />
                    </div>

                    <div className="space-y-1">
                        <label className="text-[10px] uppercase text-gray-400 font-black">No. Rujukan Surat</label>
                        <input type="text" readOnly={!isKUPP_Fasa1} value={data.pendahuluan.no_rujukan} onChange={e => kemaskiniMeta('no_rujukan', e.target.value)} className={`w-full h-10 rounded-xl text-xs ${isKUPP_Fasa1 ? 'bg-white border-gray-200 focus:ring-blue-500' : 'bg-gray-50 border-gray-200 cursor-not-allowed text-gray-600'}`} required />
                    </div>
                    <div className="space-y-1">
                        <label className="text-[10px] uppercase text-gray-400 font-black">Bilangan Kakitangan</label>
                        <input type="number" readOnly={!isKUPP_Fasa1} value={data.pendahuluan.bilangan_kakitangan} onChange={e => kemaskiniMeta('bilangan_kakitangan', e.target.value)} className={`w-full h-10 rounded-xl text-xs ${isKUPP_Fasa1 ? 'bg-white border-gray-200' : 'bg-gray-50 border-gray-200 cursor-not-allowed text-gray-600'}`} required />
                    </div>

                    <div className="space-y-1 md:col-span-2">
                        <label className="text-[10px] uppercase text-gray-400 font-black">Tujuan Permohonan</label>
                        <textarea readOnly={!isKUPP_Fasa1} value={data.pendahuluan.tujuan} onChange={e => kemaskiniMeta('tujuan', e.target.value)} className={`w-full h-16 rounded-xl text-xs ${isKUPP_Fasa1 ? 'bg-white border-gray-200' : 'bg-gray-50 border-gray-200 cursor-not-allowed text-gray-600'}`} required />
                    </div>

                    <div className="space-y-1">
                        <label className="text-[10px] uppercase text-gray-400 font-black">Peruntukan Daripada</label>
                        <input type="text" readOnly={!isKUPP_Fasa1} value={data.pendahuluan.peruntukan} onChange={e => kemaskiniMeta('peruntukan', e.target.value)} className={`w-full h-10 rounded-xl text-xs ${isKUPP_Fasa1 ? 'bg-white border-gray-200' : 'bg-gray-50 border-gray-200 cursor-not-allowed text-gray-600'}`} required />
                    </div>
                    <div className="space-y-1">
                        <label className="text-[10px] uppercase text-gray-400 font-black">Tanggungjawab</label>
                        <input type="text" readOnly={!isKUPP_Fasa1} value={data.pendahuluan.tanggungjawab} onChange={e => kemaskiniMeta('tanggungjawab', e.target.value)} className={`w-full h-10 rounded-xl text-xs ${isKUPP_Fasa1 ? 'bg-white border-gray-200' : 'bg-gray-50 border-gray-200 cursor-not-allowed text-gray-600'}`} required />
                    </div>

                    <div className="space-y-1 md:col-span-2">
                        <label className="text-[10px] uppercase text-gray-400 font-black">Emel Ketua Cawangan</label>
                        <input type="email" readOnly={!isKUPP_Fasa1} value={data.pendahuluan.emel_ketua_cawangan} onChange={e => kemaskiniMeta('emel_ketua_cawangan', e.target.value)} className={`w-full h-10 rounded-xl text-xs ${isKUPP_Fasa1 ? 'bg-white border-gray-200' : 'bg-gray-50 border-gray-200 cursor-not-allowed text-gray-600'}`} required />
                    </div>
                </div>

                <div className="pt-4 border-t border-gray-100">
                    <h5 className="text-[10px] text-blue-600 uppercase font-black tracking-wider mb-3">Butiran Pegawai Yang Dihubungi (Agensi)</h5>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4 bg-gray-50/50 p-4 rounded-xl border border-gray-100">
                        <div>
                            <label className="text-[10px] text-gray-400 font-black">Nama</label>
                            <input type="text" readOnly value={data.pendahuluan.pegawai_nama} className="w-full h-9 rounded-lg text-xs bg-gray-100 border-transparent cursor-not-allowed font-bold text-gray-600" />
                        </div>
                        <div>
                            <label className="text-[10px] text-gray-400 font-black">No. Telefon</label>
                            <input type="text" readOnly value={data.pendahuluan.pegawai_notel} className="w-full h-9 rounded-lg text-xs bg-gray-100 border-transparent cursor-not-allowed font-bold text-gray-600" />
                        </div>
                        <div>
                            <label className="text-[10px] text-gray-400 font-black">Emel</label>
                            <input type="text" readOnly value={data.pendahuluan.pegawai_emel} className="w-full h-9 rounded-lg text-xs bg-gray-100 border-transparent cursor-not-allowed font-bold text-gray-600" />
                        </div>

                        <div className="md:col-span-2">
                            <label className="text-[10px] text-gray-400 font-black">Jawatan</label>
                            {isKUPP_Fasa1 ? (
                                <select value={data.pendahuluan.pegawai_jawatan} onChange={e => kemaskiniMeta('pegawai_jawatan', e.target.value)} className="w-full h-9 rounded-lg text-xs bg-white border-gray-200 focus:ring-blue-500 font-bold" required>
                                    <option value="">Pilih Jawatan...</option>
                                    <option value="Pegawai Tadbir">Pegawai Tadbir</option>
                                    <option value="Pembantu Tadbir">Pembantu Tadbir</option>
                                    <option value="Pembantu Khidmat Am">Pembantu Khidmat Am</option>
                                    <option value="Juruteknik Komputer">Juruteknik Komputer</option>
                                    <option value="Lain-lain">Lain-lain</option>
                                </select>
                            ) : (
                                <input type="text" readOnly value={data.pendahuluan.pegawai_jawatan} className="w-full h-9 rounded-lg text-xs bg-gray-100 border-gray-200 cursor-not-allowed font-bold text-gray-600" />
                            )}
                        </div>
                        <div>
                            <label className="text-[10px] text-gray-400 font-black">Nama CDO</label>
                            <input type="text" readOnly={!isKUPP_Fasa1} value={data.pendahuluan.cdo_nama} onChange={e => kemaskiniMeta('cdo_nama', e.target.value)} className={`w-full h-9 rounded-lg text-xs font-bold ${isKUPP_Fasa1 ? 'bg-white border-gray-200' : 'bg-gray-100 border-gray-200 cursor-not-allowed text-gray-600'}`} required />
                        </div>
                    </div>
                </div>
            </div>

            {paparSenaraiPemohon && (
                <div className={`p-6 rounded-2xl border shadow-sm space-y-4 ${isKUPP_Fasa1 ? 'bg-white border-blue-400 ring-2 ring-blue-500/5' : 'bg-white border-gray-200/80'}`}>
                    <div className="text-blue-900 border-b border-blue-100 pb-2">
                        <h4 className="text-xs uppercase font-black">Senarai Kakitangan / Pemohon  <span className="text-red-500">*</span></h4>
                    </div>

                    <div className="border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                        <table className="w-full text-left border-collapse">
                            <thead className="bg-[#002b66] text-white text-[9px] uppercase tracking-widest">
                                <tr>
                                    <th className="p-3 w-[45%]">Nama Pemohon</th>
                                    <th className="p-3 w-[40%]">Jawatan</th>
                                    {isKUPP_Fasa1 && <th className="p-3 w-[15%] text-center">Aksi</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {data.hasil_kajian.map((item, idx) => (
                                    <tr key={idx} className="bg-white">
                                        <td className="p-2 align-top bg-blue-50/10">
                                            <input
                                                type="text"
                                                disabled={!isKUPP_Fasa1}
                                                value={item.nama_pemohon}
                                                onChange={e => kemaskiniKajian(idx, 'nama_pemohon', e.target.value)}
                                                placeholder="Nama pemohon / kakitangan..."
                                                className="w-full h-10 rounded-lg text-xs font-bold bg-white border-blue-200 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-600"
                                                required={isKUPP_Fasa1}
                                            />
                                        </td>
                                        <td className="p-2 align-top bg-blue-50/10 border-l border-blue-100">
                                            {isKUPP_Fasa1 ? (
                                                <select
                                                    value={item.jawatan_pemohon}
                                                    onChange={e => kemaskiniKajian(idx, 'jawatan_pemohon', e.target.value)}
                                                    className="w-full h-10 rounded-lg text-xs font-bold bg-white border-blue-200 focus:ring-blue-500"
                                                    required
                                                >
                                                    <option value="">Pilih Jawatan...</option>
                                                    <option value="Pegawai Tadbir">Pegawai Tadbir</option>
                                                    <option value="Pembantu Tadbir">Pembantu Tadbir</option>
                                                    <option value="Pembantu Khidmat Am">Pembantu Khidmat Am</option>
                                                    <option value="Juruteknik Komputer">Juruteknik Komputer</option>
                                                    <option value="Lain-lain">Lain-lain</option>
                                                </select>
                                            ) : (
                                                <input
                                                    type="text"
                                                    readOnly
                                                    value={item.jawatan_pemohon}
                                                    className="w-full h-10 rounded-lg text-xs font-bold bg-gray-50 border-gray-200 text-gray-600 cursor-not-allowed"
                                                />
                                            )}
                                        </td>
                                        {isKUPP_Fasa1 && (
                                            <td className="p-2 align-middle text-center bg-blue-50/10 border-l border-blue-100">
                                                <button
                                                    type="button"
                                                    onClick={() => buangKajian(idx)}
                                                    disabled={data.hasil_kajian.length === 1}
                                                    className="text-red-500 hover:text-red-700 p-2 bg-red-50 rounded-lg border border-red-100 hover:bg-red-100 disabled:opacity-30 transition-colors"
                                                >
                                                    <Trash2 size={16} />
                                                </button>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        {isKUPP_Fasa1 && (
                            <div className="p-2 bg-blue-50/30 border-t border-blue-100">
                                <button type="button" onClick={tambahKajian} className="inline-flex items-center gap-1 px-3 py-2 text-[#002b66] bg-white border border-blue-200 rounded-lg text-[10px] font-black uppercase shadow-sm hover:bg-blue-100 transition-colors">
                                    <Plus size={12} /> Tambah Nama Kakitangan
                                </button>
                            </div>
                        )}
                    </div>
                </div>
            )}

            {tunjukAgihanKUTD && (
                <div className={`p-6 bg-white rounded-2xl border shadow-sm space-y-4 ${isKUTD_Fasa2 ? 'border-blue-400 ring-2 ring-orange-500/5' : 'border-gray-200/80'}`}>
                    <div className="flex items-center gap-2 text-blue-900 border-b pb-2">
                        <Calendar size={15} />
                        <h4 className="text-xs uppercase tracking-wider font-black">Agihan Tugasan</h4>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label className="block text-[10px] text-gray-500 uppercase mb-1 font-black">
                                Tarikh Lawatan Tapak <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="date"
                                value={data.tarikh_lawatan}
                                disabled={!isKUTD_Fasa2}
                                onChange={e => setData('tarikh_lawatan', e.target.value)}
                                className="w-full h-10 border-gray-200 rounded-lg text-xs disabled:bg-gray-50 text-gray-700 font-bold"
                                required={isKUTD_Fasa2}
                            />
                        </div>

                        <div>
                            <label className="block text-[10px] text-gray-500 uppercase mb-1 font-black">
                                Masa Lawatan Tapak <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="time"
                                value={data.masa_lawatan}
                                disabled={!isKUTD_Fasa2}
                                onChange={e => setData('masa_lawatan', e.target.value)}
                                className="w-full h-10 border-gray-200 rounded-lg text-xs disabled:bg-gray-50 text-gray-700 font-bold"
                                required={isKUTD_Fasa2}
                            />
                        </div>

                        <div className="sm:col-span-3">
                            <label className="block text-[10px] text-gray-500 uppercase mb-1 font-black">
                                Catatan Lawatan
                            </label>
                            <textarea
                                value={data.catatan_lawatan}
                                disabled={!isKUTD_Fasa2}
                                onChange={(event) =>
                                    setData(
                                        'catatan_lawatan',
                                        event.target.value
                                    )
                                }
                                maxLength={2000}
                                placeholder="Catatan tambahan lawatan (jika ada)"
                                className="w-full min-h-[80px] border-gray-200 rounded-lg text-xs disabled:bg-gray-50 text-gray-700 font-bold"
                            />
                        </div>
                        <div className="space-y-2">
                            <label className="block text-[10px] text-gray-500 uppercase mb-1 font-black">
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
                                            required={isKUTD_Fasa2}
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

            {/* Fasa 3: Butiran Hasil Kajian (PIC Editable) */}
            {tunjukFasa3PIC && (
                <div className={`p-6 bg-white rounded-2xl border shadow-sm space-y-4 ${isPIC_Fasa3 ? 'border-emerald-400 ring-2 ring-emerald-500/5' : 'border-gray-200/80'}`}>
                    <div className="text-blue-900 border-b pb-2">
                        <h4 className="text-xs uppercase font-black">Butiran Hasil Kajian <span className="text-red-500">*</span></h4>
                    </div>

                    <div className="border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                        <table className="w-full text-left border-collapse">
                            <thead className="bg-[#002b66] text-white text-[9px] uppercase tracking-widest">
                                <tr>
                                    <th className="p-3 w-[5%] border-r border-gray-800 text-center">Bil.</th>
                                    <th className="p-3 w-[25%] border-r border-gray-800">Butiran Pemohon</th>
                                    <th className="p-3 w-[25%] border-r border-gray-800">Keadaan Semasa</th>
                                    <th className="p-3 w-[30%] border-r border-gray-800">Justifikasi &amp; Cadangan Penyelesaian</th>
                                    <th className="p-3 w-[15%] text-right">Anggaran Kos (RM)</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {data.hasil_kajian.map((item, idx) => (
                                    <tr key={idx} className="bg-white">
                                        <td className="p-3 border-r align-top text-center font-bold text-slate-900">{idx + 1}</td>
                                        <td className="p-3 align-top bg-slate-50/30 border-r border-gray-100 text-xs font-bold text-slate-800">
                                            {item.nama_pemohon || '—'} <span className="block text-[10px] text-gray-500 font-normal mt-0.5">({item.jawatan_pemohon || 'Tiada Jawatan'})</span>
                                        </td>
                                        <td className="p-2 align-top border-r border-gray-100">
                                            <textarea
                                                value={item.keadaan_semasa || ''}
                                                onChange={e => kemaskiniKajian(idx, 'keadaan_semasa', e.target.value)}
                                                disabled={!isPIC_Fasa3}
                                                placeholder="Nyatakan keadaan semasa..."
                                                className={`w-full min-h-[70px] rounded-lg text-xs ${isPIC_Fasa3 ? 'bg-white border-blue-200' : 'bg-transparent border-transparent cursor-not-allowed font-semibold text-slate-800 p-0 resize-none'}`}
                                                required={isPIC_Fasa3}
                                            />
                                        </td>
                                        <td className="p-2 align-top border-r border-gray-100">
                                            <textarea
                                                value={item.justifikasi_cadangan || ''}
                                                onChange={e => kemaskiniKajian(idx, 'justifikasi_cadangan', e.target.value)}
                                                disabled={!isPIC_Fasa3}
                                                placeholder="Saranan spesifikasi PC..."
                                                className={`w-full min-h-[70px] rounded-lg text-xs ${isPIC_Fasa3 ? 'bg-white border-blue-200' : 'bg-transparent border-transparent cursor-not-allowed font-semibold text-slate-800 p-0 resize-none'}`}
                                                required={isPIC_Fasa3}
                                            />
                                        </td>
                                        <td className="p-2 align-top bg-slate-50/30">
                                            <input
                                                type="number"
                                                value={item.anggaran_kos || ''}
                                                onChange={e => kemaskiniKajian(idx, 'anggaran_kos', e.target.value)}
                                                disabled={!isPIC_Fasa3}
                                                min="0" step="0.01"
                                                placeholder="0.00"
                                                className={`w-full h-9 rounded-lg text-xs text-right font-black ${isPIC_Fasa3 ? 'bg-white border-blue-200' : 'bg-transparent border-transparent cursor-not-allowed text-slate-900 p-0'}`}
                                                required={isPIC_Fasa3}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="bg-gray-50 border-t border-gray-200">
                                <tr>
                                    <td colSpan={4} className="p-3 text-right text-[10px] font-black uppercase text-gray-700 pr-6">
                                        JUMLAH ANGGARAN KOS KESELURUHAN (RM)
                                    </td>
                                    <td className="p-3 text-right text-xs font-black text-blue-900 border-l border-gray-200">
                                        {data.hasil_kajian.reduce((sum, item) => sum + (parseFloat(item.anggaran_kos) || 0), 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            )}

            {/* Fasa 3: Anggaran Kos Keseluruhan Penghantaran (PIC) */}
            {tunjukFasa3PIC && (
                <div className={`p-6 bg-white rounded-2xl border shadow-sm space-y-4 ${isPIC_Fasa3 ? 'border-emerald-400 ring-2 ring-emerald-500/5' : 'border-gray-200/80'}`}>
                    <div className="text-blue-900 border-b pb-2">
                        <h4 className="text-xs uppercase font-black">Rumusan <span className="text-red-500">*</span></h4>
                    </div>

                    <div className="border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                        <table className="w-full text-left border-collapse">
                            <thead className="bg-[#002b66] text-white text-[9px] uppercase tracking-widest">
                                <tr>
                                    <th className="p-3 w-[50%] border-r border-gray-800">Jenis Peralatan (PC/NB/Pencetak/Pengimbas)</th>
                                    <th className="p-3 w-[15%] text-center border-r border-gray-800">Kuantiti</th>
                                    <th className="p-3 w-[15%] text-right border-r border-gray-800">Anggaran Kos (RM)</th>
                                    <th className="p-3 w-[15%] text-right">Jumlah (RM)</th>
                                    {isPIC_Fasa3 && <th className="p-3 text-center border-l border-gray-800">Aksi</th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {data.kos_items.map((baris, idx) => (
                                    <tr key={idx} className="bg-white">
                                        <td className="p-2 border-r border-gray-100">
                                            <input
                                                type="text"
                                                value={baris.jenis_peralatan || baris.item || ''}
                                                onChange={e => kemaskiniPeralatan(idx, 'jenis_peralatan', e.target.value)}
                                                disabled={!isPIC_Fasa3}
                                                placeholder="Sila nyatakan jenis peralatan..."
                                                className={`w-full h-9 rounded-lg text-xs font-bold ${isPIC_Fasa3 ? 'bg-white border-blue-200' : 'bg-transparent border-transparent cursor-not-allowed text-slate-800 p-0'}`}
                                                required={isPIC_Fasa3}
                                            />
                                        </td>
                                        <td className="p-2 border-r border-gray-100">
                                            <input
                                                type="number"
                                                min="1"
                                                value={baris.kuantiti || ''}
                                                onChange={e => kemaskiniPeralatan(idx, 'kuantiti', e.target.value)}
                                                disabled={!isPIC_Fasa3}
                                                className={`w-full h-9 rounded-lg text-xs text-center font-black ${isPIC_Fasa3 ? 'bg-white border-blue-200' : 'bg-transparent border-transparent cursor-not-allowed text-slate-800 p-0'}`}
                                                required={isPIC_Fasa3}
                                            />
                                        </td>
                                        <td className="p-2 border-r border-gray-100">
                                            <input
                                                type="number"
                                                min="0" step="0.01"
                                                value={baris.anggaran_kos || baris.harga_seunit || ''}
                                                onChange={e => kemaskiniPeralatan(idx, 'anggaran_kos', e.target.value)}
                                                disabled={!isPIC_Fasa3}
                                                placeholder="0.00"
                                                className={`w-full h-9 rounded-lg text-xs text-right font-bold ${isPIC_Fasa3 ? 'bg-white border-blue-200' : 'bg-transparent border-transparent cursor-not-allowed text-slate-800 p-0'}`}
                                                required={isPIC_Fasa3}
                                            />
                                        </td>
                                        <td className="p-3 text-right font-black text-slate-900 bg-slate-50/50">
                                            {((parseInt(baris.kuantiti) || 0) * (parseFloat(baris.anggaran_kos || baris.harga_seunit) || 0)).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                        </td>
                                        {isPIC_Fasa3 && (
                                            <td className="p-2 text-center bg-blue-50/10">
                                                <button type="button" onClick={() => buangPeralatan(idx)} disabled={data.kos_items.length === 1} className="text-red-500 hover:text-red-700 p-1.5 disabled:opacity-30">
                                                    <Trash2 size={14} />
                                                </button>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="bg-gray-50 border-t border-gray-200">
                                <tr>
                                    <td colSpan={3} className="p-3 text-right text-[10px] font-black uppercase text-gray-700 pr-6 border-r border-gray-200">
                                        JUMLAH KESELURUHAN (RM)
                                    </td>
                                    <td className="p-3 text-right text-xs font-black text-blue-900">
                                        {data.kos_items.reduce((sum, item) => sum + ((parseInt(item.kuantiti) || 0) * (parseFloat(item.anggaran_kos || item.harga_seunit) || 0)), 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </td>
                                    {isPIC_Fasa3 && <td></td>}
                                </tr>
                            </tfoot>
                        </table>
                        {isPIC_Fasa3 && (
                            <div className="p-2 bg-gray-50 border-t border-gray-200">
                                <button type="button" onClick={tambahPeralatan} className="inline-flex items-center gap-1.5 px-3 py-1.5 text-blue-600 bg-white border border-blue-200 hover:bg-blue-50 rounded-lg transition-colors text-[10px] uppercase font-black shadow-sm">
                                    <Plus size={12} /> Tambah Jenis Peralatan
                                </button>
                            </div>
                        )}
                    </div>
                </div>
            )}



            {tunjukPengesahanVerifikasi && (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                    <div className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-sm space-y-2">
                        <label className="block text-xs font-black text-slate-800 uppercase tracking-wider">
                            Disediakan Oleh {(isKUTD_Fasa4 || isKUPP_Fasa5) && <span className="text-red-500">*</span>}
                        </label>

                        {(isKUTD_Fasa4 || isKUPP_Fasa5) ? (
                            <select
                                value={data.disediakan_oleh}
                                onChange={(e) => setData('disediakan_oleh', e.target.value)}
                                className="w-full h-11 px-3 text-xs font-bold text-gray-700 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                                required
                            >
                                <option value="">Pilih pegawai</option>
                                {(senaraiDisediakanOleh || senaraiPegawai)
                                    ?.filter(u => {
                                        const role = String(u.peranan || u.role || '').trim().toLowerCase();
                                        return ['juruteknik', 'ketua_utd', 'kutd', 'ketua_upp', 'kupp', 'pic'].includes(role);
                                    })
                                    .map((u) => <option key={u.no_ic || u.nama} value={u.nama}>{u.nama}</option>)
                                }
                            </select>
                        ) : (
                            <div className="pt-2 space-y-2">
                                <div className="flex items-start">
                                    <span className="w-20 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Nama</span>
                                    <span className="w-3 text-slate-400">:</span>
                                    <span className="flex-1 text-xs font-black text-slate-900">{data.disediakan_oleh || laporanSemasa.disediakan_oleh || 'Tiada Data'}</span>
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

                    <div className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-sm space-y-2">
                        <label className="block text-xs font-black text-slate-800 uppercase tracking-wider">
                            Disemak Oleh {(isKUTD_Fasa4 || isKUPP_Fasa5) && <span className="text-red-500">*</span>}
                        </label>

                        {(isKUTD_Fasa4 || isKUPP_Fasa5) ? (
                            <select
                                value={data.disemak_oleh}
                                onChange={(e) => setData('disemak_oleh', e.target.value)}
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
                                    <span className="flex-1 text-xs font-black text-slate-900">{data.disemak_oleh || laporanSemasa.disemak_oleh || 'Tiada Data'}</span>
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

            <div className="flex justify-end pt-2 pb-6 gap-3">

                {isKUPP_Fasa1 && (
                    <>
                        <button type="button" disabled={processing} onClick={() => submitWorkflow('HANTAR_KE_KUTD')} className="px-6 h-11 bg-blue-600 text-white rounded-xl shadow-md hover:bg-blue-700 uppercase text-[10px] font-black cursor-pointer flex items-center gap-2 transition-all active:scale-95">
                            <Send size={14} /> KEMASKINI TIKET
                        </button>
                    </>
                )}

                {isKUTD_Fasa2 && (
                    <button type="button" disabled={processing} onClick={() => submitWorkflow('AGIH_KE_PIC')} className="px-6 h-11 bg-blue-600 text-white rounded-xl shadow-md hover:bg-blue-700 uppercase text-[10px] font-black cursor-pointer flex items-center gap-2 transition-all active:scale-95">
                        <UserCheck size={14} /> KEMASKINI TIKET
                    </button>
                )}

                {isPIC_Fasa3 && (
                    <>
                        <button type="button" disabled={processing} onClick={() => submitWorkflow('PIC_HANTAR_SEMAKAN')} className="px-6 h-11 bg-blue-600 text-white rounded-xl shadow-md hover:bg-blue-700 uppercase text-[10px] font-black cursor-pointer flex items-center gap-2 transition-all active:scale-95">
                            <Send size={14} /> Hantar Laporan Teknikal
                        </button>
                    </>
                )}

                {isKUTD_Fasa4 && (
                    <div className="flex justify-end gap-3 w-full">
                        <button
                            type="button"
                            disabled={processing}
                            onClick={() => submitWorkflow('KUTD_PEMBETULAN')}
                            className="inline-flex items-center gap-2 px-5 py-3 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded-xl shadow-sm uppercase tracking-wider text-[11px] font-black cursor-pointer transition-all"
                        >
                            <AlertTriangle size={14} /> Perlu Pembetulan
                        </button>

                        <button
                            type="button"
                            disabled={processing}
                            onClick={() => submitWorkflow('KUTD_SAH_SEMAKAN')}
                            className="px-6 h-11 bg-blue-600 text-white rounded-xl shadow-md hover:bg-blue-700 uppercase text-[10px] font-black cursor-pointer flex items-center gap-2 transition-all active:scale-95"
                        >
                            <CheckCircle size={14} /> Sahkan Laporan LKK
                        </button>
                    </div>
                )}

                {isKUPP_Fasa5 && (
                    <>
                        <button type="button" disabled={processing} onClick={() => submitWorkflow('KUPP_HANTAR_VALIDASI')} className="px-6 h-11 bg-blue-600 text-white rounded-xl shadow-md hover:bg-blue-700 uppercase text-[10px] font-black cursor-pointer flex items-center gap-2 transition-all active:scale-95">
                            <Send size={14} /> Sahkan Laporan LKK
                        </button>
                    </>
                )}

            </div>
        </form>
    );
}
