import React, { useState } from 'react';
import { Link, useForm, router } from '@inertiajs/react';
import { Printer, FileText, User, ShieldCheck, CheckCircle2, AlertTriangle, Building, Phone, Mail, Briefcase } from 'lucide-react';

export default function PaparanRingkasanLKK({ ticket, auth, senaraiPegawai }) {
    const laporan = ticket.laporan || {};

    const [ulasanKetua, setUlasanKetua] = useState('');
    const [disediakanOleh, setDisediakanOleh] = useState(laporan.disediakan_oleh || '');
    const [disemakOleh, setDisemakOleh] = useState(laporan.disemak_oleh || '');

    const isTD = ticket.kategori === 'Transformasi Digital';
    const isMB = ticket.kategori === 'Meja Bantuan';
    const isKR = ticket.kategori === 'Konsultasi Rangkaian';

    const subKat = String(
        ticket.sub_kategori ||
        ticket.transformasi_digital?.sub_kategori ||
        ticket.transformasiDigital?.sub_kategori ||
        ticket.meja_bantuan?.sub_kategori ||
        ticket.mejaBantuan?.sub_kategori ||
        ''
    ).toLowerCase();

    const isPembekalan = isTD && subKat.includes('pembekalan');
    const isPeminjaman = isMB && subKat.includes('peminjaman');
    const isBilikMesyuarat = isTD && subKat.includes('pemodenan bilik mesyuarat');

    const parseData = (dataStr, defaultArray = []) => {
        if (!dataStr) return defaultArray;
        if (typeof dataStr === 'string') {
            try {
                const parsed = JSON.parse(dataStr);
                return Array.isArray(parsed) ? parsed : [parsed];
            } catch {
                return [dataStr];
            }
        }
        return Array.isArray(dataStr) ? dataStr : defaultArray;
    };

    const parseObjectData = (dataStr, defaultObj = {}) => {
        if (!dataStr) return defaultObj;
        if (typeof dataStr === 'string') {
            try { return JSON.parse(dataStr); } catch { return defaultObj; }
        }
        return typeof dataStr === 'object' ? dataStr : defaultObj;
    };

    const kosItems = parseData(laporan.kos_items, []);
    const keadaanSemasa = parseData(laporan.keadaan_semasa, []);
    const skopKajian = parseData(laporan.skop_kajian, []);
    const gambarTapak = parseData(laporan.gambar_tapak, []);
    const gambarCadangan = parseData(laporan.gambar_cadangan, []);
    const senaraiObjektif = parseData(laporan.objektif, []);

    const metaPendahuluan = parseObjectData(laporan.pendahuluan, {});
    const hasilKajian = parseData(laporan.hasil_kajian, []);

    const adaKeadaanSemasa = keadaanSemasa.length > 0 && keadaanSemasa.some(item =>
        typeof item === 'object'
            ? ((item.aspek && item.aspek.trim() !== '' && item.aspek !== '-') || (item.ulasan && item.ulasan.trim() !== '' && item.ulasan !== '-'))
            : String(item).trim() !== '' && String(item) !== '-'
    );
    const adaGambarTapak = gambarTapak.length > 0 && gambarTapak.some(g => g && g !== '[]' && g !== 'null');
    const adaGambarCadangan = gambarCadangan.length > 0 && gambarCadangan.some(g => g && g !== '[]' && g !== 'null');
    const adaKosItems = kosItems.length > 0 && kosItems.some(row => {
        const nama = row.item || row.jenis_peralatan || '';
        const harga = parseFloat(row.anggaran_kos || row.anggaran || row.harga_seunit || row.jumlah || 0);
        return nama.trim() !== '' && nama !== '-' && harga > 0;
    });
    const adaRumusan = laporan.rumusan &&
        laporan.rumusan.trim() !== '' &&
        laporan.rumusan !== 'n/a' &&
        !laporan.rumusan.toLowerCase().includes('tiada maklumat rumusan');

    const isLKKKajianLengkap = hasilKajian.length > 0 && hasilKajian.some(row => {
        const ks = String(row.keadaan_semasa || '').trim();
        const jc = String(row.justifikasi_cadangan || '').trim();
        const kos = parseFloat(row.anggaran_kos || 0);
        return (ks !== '' && ks !== '-') || (jc !== '' && jc !== '-') || kos > 0;
    });

    const hitungGrandTotal = () => {
        return kosItems.reduce((total, row) => {
            const kuantiti = parseInt(row.kuantiti) || 0;
            const harga = parseFloat(row.anggaran_kos || row.anggaran || row.harga_seunit || row.jumlah || 0);

            if (row.jumlah && !row.anggaran_kos && !row.anggaran) {
                return total + parseFloat(row.jumlah);
            }
            return total + (kuantiti * harga);
        }, 0);
    };

    const handleCetakLKK = () => {
        window.open(`/tickets/${ticket.id_tiket}/cetak-lkk`, '_blank');
    };

    const perananUser = String(auth?.user?.peranan || auth?.user?.role || '').trim().toLowerCase();
    const statusFormat = String(ticket.status_tiket || '').trim().toLowerCase();

    const isKUTD = ['ketua_utd', 'ketua utd', 'kutd'].includes(perananUser);
    const isKUPP = ['ketua_upp', 'ketua upp', 'kupp'].includes(perananUser);
    const isKW   = ['ketua_wilayah', 'ketua wilayah', 'kw'].includes(perananUser);

    const isPenilaiFasa1 = (isKR && isKUTD) || (isTD && isKUPP);
    const isBolehPengesahPeminjaman = isPeminjaman && (isKUPP || isKUTD || isKW);
    const labelPenyemak = isKR ? 'KUTD' : 'KUPP';

    const isStatusPeminjamanSah = [
        'menunggu pengesahan',
        'menunggu pengesahan lkk',
        'menunggu semakan',
        'semakan kutd',
        'menunggu validasi',
        'validasi kw',
        'menunggu validasi kw'
    ].includes(statusFormat);

    const bolehEdit =
        (isKUTD && (isKR || isPembekalan) && ['menunggu pengesahan', 'menunggu pengesahan lkk', 'menunggu semakan', 'semakan kutd', 'semakan laporan teknikal'].includes(statusFormat)) ||
        (isKUPP && isBilikMesyuarat && ['menunggu pengesahan lkk', 'menunggu pengesahan'].includes(statusFormat));

    const handleTindakan = (jenisAksi) => {
        if (['pulang_pic', 'pulang_semak', 'kw_pembetulan', 'pulang_pic_td'].includes(jenisAksi) && !ulasanKetua.trim()) {
            alert("Sila masukkan ulasan/nota pembetulan terlebih dahulu!");
            return;
        }

        if (bolehEdit && ['hantar_kw', 'kupp_sahkan'].includes(jenisAksi) && (!disediakanOleh || !disemakOleh)) {
            alert("Sila pilih nama pegawai untuk ruangan 'Disediakan Oleh' dan 'Disemak Oleh'!");
            return;
        }

        if (!confirm("Adakah anda pasti dengan tindakan ini?")) return;

        const targetRoute = isKR
            ? route('tickets.storeLKKRangkaian', ticket.id_tiket)
            : route('tickets.lkk.storeTD', ticket.id_tiket);

        let namaTindakan = jenisAksi;
        if (jenisAksi === 'kw_sahkan' || jenisAksi === 'lulus_tutup') {
            namaTindakan = 'KW_VALIDASI_SELESAI';
        } else if (jenisAksi === 'sahkan_peminjaman_selesai') {
            namaTindakan = 'SAHKAN_PEMINJAMAN_SELESAI';
        } else if (jenisAksi === 'pulang_semak') {
            namaTindakan = 'KW_PEMBETULAN';
        } else if (jenisAksi === 'pulang_pic') {
            namaTindakan = 'KUTD_PEMBETULAN';
        } else if (jenisAksi === 'hantar_kw') {
            namaTindakan = 'KUTD_SAH_SEMAKAN';
        } else if (jenisAksi === 'kupp_sahkan') {
            namaTindakan = 'KUPP_HANTAR_VALIDASI';
        }

        const payload = {
            _method: 'POST',
            is_draft: false,
            tindakan: namaTindakan,
            is_kupp_sahkan: jenisAksi === 'kupp_sahkan',
            is_kutd_hantar: jenisAksi === 'hantar_kw',
            is_kw_sahkan: jenisAksi === 'kw_sahkan' || jenisAksi === 'lulus_tutup' || jenisAksi === 'sahkan_peminjaman_selesai',
            is_kw_pembetulan: ['pulang_pic', 'pulang_semak', 'kw_pembetulan', 'pulang_pic_td'].includes(jenisAksi),
            ulasan_ketua: ulasanKetua,

            pendahuluan: laporan.pendahuluan || 'n/a',
            disediakan_oleh: disediakanOleh,
            disemak_oleh: disemakOleh,
            objektif: laporan.objektif || 'n/a',
            rumusan: laporan.rumusan || 'n/a',
            kos_items: typeof laporan.kos_items === 'string' ? JSON.parse(laporan.kos_items || '[]') : (laporan.kos_items || []),
            keadaan_semasa: laporan.keadaan_semasa || 'n/a',
            skop_kajian: laporan.skop_kajian || 'n/a',
            hasil_kajian: laporan.hasil_kajian || 'n/a',
            cadangan_penambahbaikan: laporan.cadangan_penambahbaikan || 'n/a'
        };

        router.post(targetRoute, payload, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                alert("Tindakan berjaya diproses!");
                setUlasanKetua('');
            },
            onError: (err) => {
                console.error("Ralat:", err);
                alert("Gagal: " + Object.values(err).join('\n'));
            }
        });
    };

    const tukarKeArraySelamat = (rawData) => {
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

    const senaraiUlasanRingkasan = tukarKeArraySelamat(laporan?.ulasan_teknikal);
    const senaraiCadanganRingkasan = tukarKeArraySelamat(laporan?.cadangan_penambahbaikan);

    return (
        <div className="w-full bg-slate-100/50 p-2 rounded-3xl border border-slate-200/60 shadow-inner space-y-4 font-sans text-xs font-medium text-slate-700">

            <div className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-sm flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6 w-full">
                <div className="space-y-2 flex-1 min-w-0">

                    {!isBilikMesyuarat && (
                        <div className="mb-2">
                            <span className="px-2.5 py-1 bg-blue-50 text-blue-700 text-[10px] font-black rounded-md border border-blue-100 uppercase tracking-wider inline-block">
                                {isPembekalan ? 'BPI/B02V1.1' : 'JPKN-BRK-02/B1'}
                            </span>
                        </div>
                    )}

                    <h2 className="text-sm md:text-base font-black text-slate-900 uppercase tracking-tight leading-snug">
                        {isPembekalan
                            ? 'Laporan Kajian Keperluan Pembekalan Peralatan ICT'
                            : isPeminjaman
                                ? 'Laporan Permohonan Peminjaman Peralatan ICT'
                                : isBilikMesyuarat
                                    ? 'Laporan Kajian Kesauran Pemodenan Bilik Mesyuarat'
                                    : 'Laporan Kajian Keperluan Pemasangan/PenaikTarafan Infrastruktur Rangkaian Komputer'}
                    </h2>
                </div>

                {ticket?.status_tiket === 'Selesai' && (
                    <div className="flex items-center gap-4 text-[11px] font-bold text-gray-500 shrink-0 w-full lg:w-auto justify-start sm:justify-end border-t lg:border-t-0 pt-3 lg:pt-0 border-gray-100 animate-in fade-in duration-200">
                        <button
                            type="button"
                            onClick={handleCetakLKK}
                            className="inline-flex items-center gap-1.5 bg-white border border-gray-200 hover:border-blue-500 hover:text-blue-600 text-slate-700 font-black text-[10px] uppercase tracking-wider px-4 h-9 rounded-xl transition-all shadow-sm cursor-pointer active:scale-95"
                        >
                            <Printer size={13} /> Cetak Laporan
                        </button>
                    </div>
                )}
            </div>

            {isKR && (
                <>
                    <div className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-sm space-y-4">
                        <h3 className="text-xs font-black text-blue-700 uppercase tracking-wider">1. Pendahuluan</h3>
                        <div className="border border-gray-100 rounded-xl divide-y divide-gray-100 bg-white">
                            <div className="p-4 grid grid-cols-1 md:grid-cols-4 gap-2">
                                <span className="text-gray-400 font-bold md:col-span-1">Pendahuluan / Latar Belakang</span>
                                <span className="text-slate-700 font-medium text-justify leading-relaxed whitespace-pre-line md:col-span-3">{laporan.pendahuluan || '-'}</span>
                            </div>
                            <div className="p-4 grid grid-cols-1 md:grid-cols-4 gap-2 border-b border-gray-50 last:border-none">
                                <span className="text-gray-400 font-bold md:col-span-1">Ulasan Teknikal</span>
                                <div className="md:col-span-3 space-y-2">
                                    {senaraiUlasanRingkasan.length > 0 && senaraiUlasanRingkasan[0].teks ? (
                                        senaraiUlasanRingkasan.map((item, idx) => (
                                            <div key={idx} className="flex items-start gap-1.5 animate-in fade-in duration-150">
                                                <span className="text-gray-400 font-black w-5 select-none">{idx + 1}.</span>
                                                <span className="text-slate-700 font-semibold text-justify leading-relaxed uppercase whitespace-pre-line">
                                                    {item.teks}
                                                </span>
                                            </div>
                                        ))
                                    ) : (
                                        <span className="text-gray-400 italic font-medium">-</span>
                                    )}
                               </div>
                            </div>
                            <div className="p-4 grid grid-cols-1 md:grid-cols-4 gap-2 border-b border-gray-50 last:border-none">
                                <span className="text-gray-400 font-bold md:col-span-1">Cadangan</span>
                                <div className="md:col-span-3 space-y-2">
                                    {senaraiCadanganRingkasan.length > 0 && senaraiCadanganRingkasan[0].teks ? (
                                        senaraiCadanganRingkasan.map((item, idx) => (
                                            <div key={idx} className="flex items-start gap-1.5 animate-in fade-in duration-150">
                                                <span className="text-gray-400 font-black w-5 select-none">{idx + 1}.</span>
                                                <span className="text-slate-700 font-semibold text-justify leading-relaxed uppercase whitespace-pre-line">
                                                    {item.teks}
                                                </span>
                                            </div>
                                        ))
                                    ) : (
                                        <span className="text-gray-400 italic font-medium">-</span>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-sm space-y-3">
                            <h4 className="text-[10px] font-black text-blue-900 uppercase tracking-wide">1.1 Diagram Rangkaian Logikal</h4>
                            <div className="flex items-center justify-between p-3.5 bg-slate-50/50 border border-gray-200 rounded-xl w-full">
                                <div className="flex items-center gap-2 min-w-0">
                                    <div className="p-2 bg-white border border-gray-100 rounded-lg shadow-sm shrink-0 text-red-500 font-black text-[9px] text-center w-9">PDF</div>
                                    <div className="min-w-0">
                                        <p className="text-xs font-black text-slate-800 truncate">{laporan.logical_diagram ? 'logical-diagram.pdf' : 'Tiada fail uploaded'}</p>
                                    </div>
                                </div>
                                {laporan.logical_diagram && <a href={`/storage/${laporan.logical_diagram}`} target="_blank" rel="noreferrer" className="inline-flex items-center px-3.5 h-8 bg-white hover:bg-blue-50 border border-gray-200 text-blue-600 font-black text-[10px] uppercase rounded-xl shadow-sm">Lihat</a>}
                            </div>
                        </div>

                        <div className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-sm space-y-3">
                            <h4 className="text-[10px] font-black text-blue-900 uppercase tracking-wide">1.2 Diagram Rangkaian Fizikal</h4>
                            <div className="flex items-center justify-between p-3.5 bg-slate-50/50 border border-gray-200 rounded-xl w-full">
                                <div className="flex items-center gap-2 min-w-0">
                                    <div className="p-2 bg-white border border-gray-100 rounded-lg shadow-sm shrink-0 text-green-600 font-black text-[9px] text-center w-9">PNG</div>
                                    <div className="min-w-0">
                                        <p className="text-xs font-black text-slate-800 truncate">{laporan.physical_diagram ? 'physical-layout.png' : 'Tiada fail uploaded'}</p>
                                    </div>
                                </div>
                                {laporan.physical_diagram && <a href={`/storage/${laporan.physical_diagram}`} target="_blank" rel="noreferrer" className="inline-flex items-center px-3.5 h-8 bg-white hover:bg-blue-50 border border-gray-200 text-blue-600 font-black text-[10px] uppercase rounded-xl shadow-sm">Lihat</a>}
                            </div>
                        </div>
                    </div>

                    <div className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-sm space-y-3">
                        <h3 className="text-xs font-black text-blue-700 uppercase tracking-wider">2. OBJEKTIF</h3>
                        <div className="p-4 bg-slate-50/50 border border-gray-100 rounded-xl text-slate-700 font-medium">
                            {senaraiObjektif.length > 0 ? (
                                <ol className="list-[lower-alpha] pl-5 space-y-1.5 marker:text-blue-900 font-black">
                                    {senaraiObjektif.map((obj, i) => <li key={i} className="pl-1">{obj.teks || obj}</li>)}
                                </ol>
                            ) : <p className="italic text-gray-400">Tiada maklumat objektif direkodkan.</p>}
                        </div>
                    </div>
                </>
            )}

            {isBilikMesyuarat && (
                <>
                    <div className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-sm space-y-3">
                        <h3 className="text-xs font-black text-blue-700 uppercase tracking-wider">1. PENDAHULUAN</h3>
                        <div className="p-4 bg-slate-50/50 border border-gray-100 rounded-xl leading-relaxed text-justify text-slate-700 font-medium">
                            {laporan.pendahuluan || 'Tiada maklumat pendahuluan diisi.'}
                        </div>
                    </div>

                    <div className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-sm space-y-3">
                        <h3 className="text-xs font-black text-blue-700 uppercase tracking-wider">2. OBJEKTIF</h3>
                        <div className="p-4 bg-slate-50/50 border border-gray-100 rounded-xl leading-relaxed text-slate-700 font-medium">
                            {senaraiObjektif.length > 0 ? (
                                <ol className="list-[lower-alpha] pl-5 space-y-1.5 marker:text-blue-900 font-black">
                                    {senaraiObjektif.map((obj, i) => <li key={i} className="pl-1">{obj.teks || obj}</li>)}
                                </ol>
                            ) : <p className="italic text-gray-400">Tiada maklumat objektif direkodkan.</p>}
                        </div>
                    </div>

                    <div className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-sm space-y-3">
                        <h3 className="text-xs font-black text-blue-700 uppercase tracking-wider">3. SKOP KAJIAN</h3>
                        <div className="p-4 bg-slate-50 border border-gray-100 rounded-xl text-slate-700 font-medium">
                            <ol className="list-[lower-alpha] pl-5 space-y-1.5 marker:text-blue-900 font-black">
                                {skopKajian.map((skop, i) => <li key={i} className="pl-1">{skop.teks || skop}</li>)}
                            </ol>
                        </div>
                    </div>

                    {(adaGambarTapak || adaKeadaanSemasa) && (
                        <div className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-sm space-y-4">
                            <h3 className="text-xs font-black text-blue-700 uppercase tracking-wider">4. PEMERHATIAN KEADAAN SEMASA</h3>
                            {adaGambarTapak && (
                                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    {gambarTapak.map((img, i) => {
                                        const pathBersih = String(img).replace(/["']/g, '').replace(/\\/g, '');
                                        return <img key={i} src={`/storage/${pathBersih}`} alt="Tapak" className="h-24 w-full object-cover rounded-xl border shadow-sm" />;
                                    })}
                                </div>
                            )}
                            {adaKeadaanSemasa && (
                                <div className="border border-gray-200 rounded-xl overflow-hidden bg-white">
                                    <table className="w-full text-left border-collapse">
                                        <thead>
                                            <tr className="bg-slate-50 text-[9px] font-black uppercase text-gray-500 border-b border-gray-100">
                                                <th className="p-3 w-1/3 border-r">Aspek Pemerhatian</th>
                                                <th className="p-3">Ulasan &amp; Analisis Teknikal</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-50 bg-white">
                                            {keadaanSemasa.map((item, idx) => (
                                                <tr key={idx}>
                                                    <td className="p-3 font-black text-slate-800 bg-slate-50/20 border-r">{item.aspek}</td>
                                                    <td className="p-3 text-slate-600 font-medium">{item.ulasan}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    )}

                    {adaGambarCadangan && (
                        <div className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-sm space-y-3">
                            <h3 className="text-xs font-black text-blue-700 uppercase tracking-wider">5. CADANGAN SUSUN ATUR</h3>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                {gambarCadangan.map((img, i) => {
                                    const pathBersih = String(img).replace(/["']/g, '').replace(/\\/g, '');
                                    return <img key={i} src={`/storage/${pathBersih}`} alt="Cadangan" className="max-h-56 w-full object-cover rounded-xl border border-gray-200" />;
                                })}
                            </div>
                        </div>
                    )}
                </>
            )}

            {(isPembekalan || isPeminjaman) && (
                <div className="space-y-8">
                    <div>
                        <h3 className="text-[13px] font-black text-blue-700 uppercase tracking-wider mb-3 px-1">
                            BUTIRAN PERMOHONAN
                        </h3>
                        <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden grid grid-cols-1 md:grid-cols-4">

                            <div className="p-4 border-b border-gray-100 md:border-r md:col-span-3">
                                <span className="block text-xs font-semibold text-gray-400 mb-1">Kementerian / Jabatan:</span>
                                <span className="block text-sm font-bold text-slate-900 uppercase">{metaPendahuluan.jabatan || '-'}</span>
                            </div>
                            <div className="p-4 border-b border-gray-100 md:col-span-1">
                                <span className="block text-xs font-semibold text-gray-400 mb-1">Bilangan Kakitangan:</span>
                                <span className="block text-sm font-bold text-slate-900">{metaPendahuluan.bilangan_kakitangan || '-'}</span>
                            </div>

                            <div className="p-4 border-b border-gray-100 md:border-r md:col-span-2">
                                <span className="block text-xs font-semibold text-gray-400 mb-1">Tarikh Permohonan Diterima (surat/emel):</span>
                                <span className="block text-sm font-bold text-slate-900">{metaPendahuluan.tarikh_terima || '-'}</span>
                            </div>
                            <div className="p-4 border-b border-gray-100 md:col-span-2">
                                <span className="block text-xs font-semibold text-gray-400 mb-1">No. Rujukan Surat:</span>
                                <span className="block text-sm font-bold text-slate-900 uppercase">{metaPendahuluan.no_rujukan || '-'}</span>
                            </div>

                            <div className="p-4 border-b border-gray-100 md:col-span-4">
                                <span className="block text-xs font-semibold text-gray-400 mb-1">Tujuan Permohonan:</span>
                                <span className="block text-sm font-bold text-slate-900 whitespace-pre-wrap uppercase">{metaPendahuluan.tujuan || '-'}</span>
                            </div>

                            <div className="p-4 border-b border-gray-100 md:col-span-4">
                                <span className="block text-xs font-semibold text-gray-400 mb-1">Peruntukan Daripada:</span>
                                <span className="block text-sm font-bold text-slate-900 uppercase">{metaPendahuluan.peruntukan || '-'}</span>
                            </div>

                            <div className="p-4 border-gray-100 md:border-r border-b md:border-b-0 md:col-span-2">
                                <span className="block text-xs font-semibold text-gray-400 mb-1">Tanggungjawab:</span>
                                <span className="block text-sm font-bold text-slate-900 uppercase">{metaPendahuluan.tanggungjawab || '-'}</span>
                            </div>
                            <div className="p-4 border-gray-100 md:col-span-2">
                                <span className="block text-xs font-semibold text-gray-400 mb-1">E-mel Ketua Cawangan / PID:</span>
                                <span className="block text-sm font-bold text-slate-900">{metaPendahuluan.emel_ketua_cawangan || '-'}</span>
                            </div>

                        </div>
                    </div>

                    <div>
                        <h3 className="text-[13px] font-black text-blue-700 uppercase tracking-wider mb-3 px-1">
                            BUTIRAN PEGAWAI YANG DIHUBUNGI (AGENSI)
                        </h3>
                        <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden grid grid-cols-1 md:grid-cols-2">

                            <div className="flex items-center gap-4 p-4 border-b border-gray-100 md:border-r">
                                <div className="min-w-0">
                                    <span className="block text-xs font-semibold text-gray-400 mb-0.5">Nama:</span>
                                    <span className="block text-sm font-bold text-slate-900 truncate uppercase">{metaPendahuluan.pegawai_nama || '-'}</span>
                                </div>
                            </div>

                            <div className="flex items-center gap-4 p-4 border-b border-gray-100">
                                <div className="min-w-0">
                                    <span className="block text-xs font-semibold text-gray-400 mb-0.5">Jawatan:</span>
                                    <span className="block text-sm font-bold text-slate-900 truncate">{metaPendahuluan.pegawai_jawatan || '-'}</span>
                                </div>
                            </div>

                            <div className="flex items-center gap-4 p-4 border-b border-gray-100 md:border-r">
                                <div className="min-w-0">
                                    <span className="block text-xs font-semibold text-gray-400 mb-0.5">No. Telefon:</span>
                                    <span className="block text-sm font-bold text-slate-900 truncate">{metaPendahuluan.pegawai_notel || '-'}</span>
                                </div>
                            </div>

                            <div className="flex items-center gap-4 p-4 border-b border-gray-100">
                                <div className="min-w-0">
                                    <span className="block text-xs font-semibold text-gray-400 mb-0.5">E-mel:</span>
                                    <span className="block text-sm font-bold text-slate-900 truncate">{metaPendahuluan.pegawai_emel || '-'}</span>
                                </div>
                            </div>

                            <div className="flex items-center gap-4 p-4 border-gray-100 md:border-r border-b md:border-b-0">
                                <div className="min-w-0">
                                    <span className="block text-xs font-semibold text-gray-400 mb-0.5">Nama CDO:</span>
                                    <span className="block text-sm font-bold text-slate-900 truncate uppercase">{metaPendahuluan.cdo_nama || '-'}</span>
                                </div>
                            </div>

                            <div className="hidden md:block p-4 border-gray-100 bg-gray-50/20">
                            </div>

                        </div>
                    </div>

                    {hasilKajian.length > 0 && (
                        isLKKKajianLengkap ? (
                            <div className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-sm space-y-3">
                                <h3 className="text-xs font-black text-blue-700 uppercase tracking-wider">BUTIRAN HASIL KAJIAN</h3>
                                <div className="border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                                    <table className="w-full text-left text-xs border-collapse">
                                        <thead>
                                            <tr className="bg-[#001f4d] text-white font-black text-[9px] uppercase tracking-wider">
                                                <th className="p-3 w-[5%] border-r border-gray-800 text-center">Bil.</th>
                                                <th className="p-3 w-[25%] border-r border-gray-800">Butiran Permohonan</th>
                                                <th className="p-3 w-[25%] border-r border-gray-800">Keadaan Semasa</th>
                                                <th className="p-3 w-[30%] border-r border-gray-800">Justifikasi &amp; Cadangan Penyelesaian</th>
                                                <th className="p-3 w-[15%] text-right">Anggaran Kos (RM)</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200 bg-white font-medium text-slate-700">
                                            {hasilKajian.map((row, idx) => (
                                                <tr key={idx} className="hover:bg-slate-50/50">
                                                    <td className="p-3 border-r align-top text-center font-bold text-slate-900">
                                                        {idx + 1}
                                                    </td>
                                                    <td className="p-3 border-r whitespace-pre-line align-top font-bold text-slate-900">
                                                        {row.nama_pemohon}
                                                        <span className="block text-[10px] text-gray-500 font-normal mt-0.5">({row.jawatan_pemohon || 'Tiada Jawatan'})</span>
                                                    </td>
                                                    <td className="p-3 border-r whitespace-pre-line align-top text-justify">{row.keadaan_semasa || '-'}</td>
                                                    <td className="p-3 border-r whitespace-pre-line align-top text-justify">{row.justifikasi_cadangan || '-'}</td>
                                                    <td className="p-3 text-right font-black align-top bg-slate-50/20">{(Number(row.anggaran_kos) || 0).toFixed(2)}</td>
                                                </tr>
                                            ))}
                                            <tr className="bg-slate-100 font-black border-t border-gray-300">
                                                <td colSpan="4" className="p-3.5 text-right uppercase tracking-wider text-[10px] text-slate-800 border-r border-gray-300">
                                                    Jumlah Anggaran Kos:
                                                </td>
                                                <td className="p-3.5 text-right text-xs font-black text-slate-900 bg-slate-200/50">
                                                    RM {hasilKajian.reduce((total, row) => total + (Number(row.anggaran_kos) || 0), 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        ) : (
                            <div className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-sm space-y-3">
                                <h3 className="text-xs font-black text-blue-700 uppercase tracking-wider">BUTIRAN HASIL KAJIAN</h3>
                                <div className="border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                                    <table className="w-full text-left text-xs border-collapse">
                                        <thead>
                                            <tr className="bg-[#001f4d] text-white font-black text-[9px] uppercase tracking-wider">
                                                <th className="p-3 w-[10%] border-r border-gray-800 text-center">Bil.</th>
                                                <th className="p-3 w-[50%] border-r border-gray-800">Butiran Pemohon</th>
                                                <th className="p-3 w-[40%]">Jawatan</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200 bg-white font-medium text-slate-700">
                                            {hasilKajian.map((row, idx) => (
                                                <tr key={idx} className="hover:bg-slate-50/50">
                                                    <td className="p-3 border-r text-center font-bold text-slate-900">{idx + 1}</td>
                                                    <td className="p-3 border-r font-bold text-slate-900 uppercase">{row.nama_pemohon || '-'}</td>
                                                    <td className="p-3 font-semibold text-slate-700 uppercase">{row.jawatan_pemohon || '-'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )
                    )}
                </div>
            )}

            {adaKosItems && (
                <div className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-sm space-y-3">
                    <h3 className="text-[13px] font-black text-blue-700 uppercase tracking-wider mb-3 px-1">
                        {(isPembekalan || isPeminjaman) ? 'RUMUSAN' : isBilikMesyuarat ? '6. ANGGARAN KOS' : '3. ANGGARAN KOS'}
                    </h3>
                    <div className="border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                        <table className="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr className="bg-[#001f4d] text-white font-black text-[9px] uppercase tracking-wider">
                                    <th className="p-3 w-14 text-center border-r border-gray-800">Bil.</th>
                                    <th className="p-3 border-r border-gray-800 pl-4">{(isPembekalan || isPeminjaman) ? 'Jenis Peralatan (PC/NB/Pencetak/Pengimbas)' : 'Item'}</th>
                                    <th className="p-3 w-28 text-center border-r border-gray-800">Kuantiti</th>
                                    <th className="p-3 w-36 text-right border-r border-gray-800">Anggaran Kos (RM)</th>
                                    <th className="p-3 w-40 text-right">Jumlah Anggaran Kos (RM)</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 bg-white text-slate-700 font-medium">
                                {kosItems.map((row, index) => {
                                    const namaPeralatan = row.item || row.jenis_peralatan || '-';
                                    const hargaKos = parseFloat(row.anggaran_kos || row.anggaran || row.harga_seunit || 0);
                                    const subtotal = (parseInt(row.kuantiti) || 0) * hargaKos;
                                    return (
                                        <tr key={index} className="hover:bg-slate-50/50 transition-colors">
                                            <td className="p-3 text-center text-slate-900 font-bold border-r">{index + 1}</td>
                                            <td className="p-3 border-r font-bold text-slate-900 pl-4">{namaPeralatan}</td>
                                            <td className="p-3 text-center border-r font-bold text-slate-900">{row.kuantiti}</td>
                                            <td className="p-3 text-right border-r font-bold text-slate-900">{hargaKos.toFixed(2)}</td>
                                            <td className="p-3 font-black text-slate-900 text-right bg-slate-50/20">{subtotal.toFixed(2)}</td>
                                        </tr>
                                    );
                                })}

                                <tr className="bg-slate-100 font-black border-t border-gray-300">
                                    <td colSpan="4" className="p-3.5 text-right uppercase tracking-wider text-[10px] text-slate-800 border-r border-gray-300">
                                        {(isPembekalan || isPeminjaman) ? 'Jumlah Keseluruhan Anggaran Kos:' : 'Jumlah Keseluruhan:'}
                                    </td>
                                    <td className="p-3.5 text-right text-xs font-black text-slate-900 bg-slate-200/50">
                                        RM {hitungGrandTotal().toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {!isPembekalan && !isPeminjaman && adaRumusan && (
                <div className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-sm space-y-3">
                    <h3 className="text-xs font-black text-blue-700 uppercase tracking-wider">
                        {isTD ? '7. RUMUSAN' : '4. RUMUSAN'}
                    </h3>
                    <div className="p-4 bg-slate-50/30 border border-gray-200 rounded-xl text-justify text-slate-700 font-medium">
                        {laporan.rumusan}
                    </div>
                </div>
            )}

            {/* Officer Sign-off Section (Prepared By & Reviewed By) */}
            {!isPeminjaman && (bolehEdit || laporan.disediakan_oleh || laporan.disemak_oleh) && (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 mt-4 border-t border-gray-100">

                    {/* Prepared By Officer */}
                    <div className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-sm space-y-2">
                        <label className="block text-xs font-black text-slate-800 uppercase tracking-wider">
                            Disediakan Oleh {bolehEdit && <span className="text-red-500">*</span>}
                        </label>

                        {bolehEdit ? (
                            <select
                                value={disediakanOleh}
                                onChange={(e) => setDisediakanOleh(e.target.value)}
                                className="w-full h-11 px-3 text-xs font-bold text-gray-700 bg-white border rounded-xl"
                                required
                            >
                                <option value="">Pilih pegawai</option>
                                {senaraiPegawai
                                    ?.filter(u => {
                                        const role = String(u.peranan || u.role || '').trim().toLowerCase();
                                        return ['juruteknik', 'pic', 'ketua_utd', 'kutd', 'ketua_upp', 'kupp'].includes(role);
                                    })
                                    .map((u) => <option key={u.no_ic} value={u.nama}>{u.nama}</option>)
                                }
                            </select>
                        ) : (
                            <div className="pt-2 space-y-2">
                                <div className="flex items-start">
                                    <span className="w-20 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Nama</span>
                                    <span className="w-3 text-slate-400">:</span>
                                    <span className="flex-1 text-xs font-black text-slate-900">{laporan.disediakan_oleh || 'Belum Ditetapkan'}</span>
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
                                        {laporan.updated_at ? new Date(laporan.updated_at).toLocaleDateString('ms-MY', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '-'}
                                    </span>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Reviewed By Officer */}
                    <div className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-sm space-y-2">
                        <label className="block text-xs font-black text-slate-800 uppercase tracking-wider">
                            Disemak Oleh {bolehEdit && <span className="text-red-500">*</span>}
                        </label>

                        {bolehEdit ? (
                            <select
                                value={disemakOleh}
                                onChange={(e) => setDisemakOleh(e.target.value)}
                                className="w-full h-11 px-3 text-xs font-bold text-gray-700 bg-white border rounded-xl"
                                required
                            >
                                <option value="">Pilih pegawai</option>
                                {senaraiPegawai
                                    ?.filter(u => {
                                        const role = String(u.peranan || u.role || '').trim().toLowerCase();
                                        return ['ketua_upp', 'kupp', 'ketua_utd', 'kutd', 'ketua_wilayah', 'kw'].includes(role);
                                    })
                                    .map((u) => <option key={u.no_ic} value={u.nama}>{u.nama}</option>)
                                }
                            </select>
                        ) : (
                            <div className="pt-2 space-y-2">
                                <div className="flex items-start">
                                    <span className="w-20 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Nama</span>
                                    <span className="w-3 text-slate-400">:</span>
                                    <span className="flex-1 text-xs font-black text-slate-900">{laporan.disemak_oleh || 'Belum Ditetapkan'}</span>
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
                                        {laporan.updated_at ? new Date(laporan.updated_at).toLocaleDateString('ms-MY', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '-'}
                                    </span>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            )}

            {isPeminjaman && isStatusPeminjamanSah && isBolehPengesahPeminjaman && (
                <div className="bg-emerald-50/60 p-5 rounded-2xl border border-emerald-200/80 shadow-sm space-y-4">
                    <div className="flex items-center gap-2">
                        <div className="p-1.5 bg-emerald-100 text-emerald-700 rounded-lg"><ShieldCheck size={14} /></div>
                        <h4 className="text-xs font-black text-emerald-900 uppercase tracking-wider">Pengesahan &amp; Penutupan Tiket Peminjaman Peralatan ICT</h4>
                    </div>

                    <div className="space-y-2">
                        <label className="text-[10px] uppercase font-black text-slate-500 tracking-wide block">Nota / Ulasan Semakan :</label>
                        <textarea value={ulasanKetua} onChange={(e) => setUlasanKetua(e.target.value)} placeholder="Tulis ulasan pengesahan di sini..." className="w-full p-3 bg-white border border-gray-200 rounded-xl text-xs font-medium text-slate-800 focus:outline-none" rows={3} />
                    </div>

                    <div className="flex justify-end gap-2 pt-2 border-t border-emerald-200/40">
                        <button type="button" onClick={() => handleTindakan('pulang_pic')} className="flex items-center gap-1.5 px-4 h-9 bg-white hover:bg-red-50 border border-red-200/70 text-red-600 font-bold text-xs uppercase rounded-xl shadow-sm cursor-pointer"><AlertTriangle size={13} /> Perlu Pembetulan</button>
                        <button type="button" onClick={() => handleTindakan('sahkan_peminjaman_selesai')} className="flex items-center gap-1.5 px-5 h-9 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs uppercase rounded-xl shadow-md cursor-pointer"><CheckCircle2 size={13} /> Sahkan &amp; Tutup Tiket</button>
                    </div>
                </div>
            )}

            {!isPeminjaman && ['menunggu pengesahan lkk', 'menunggu pengesahan'].includes(statusFormat) && isPenilaiFasa1 && (
                <div className="bg-amber-50/60 p-5 rounded-2xl border border-amber-200/80 shadow-sm space-y-4">
                    <div className="flex items-center gap-2">
                        <div className="p-1.5 bg-amber-100 text-amber-700 rounded-lg"><ShieldCheck size={14} /></div>
                        <h4 className="text-xs font-black text-amber-900 uppercase tracking-wider">Semakan &amp; Verifikasi Ketua Unit </h4>
                    </div>
                    <div className="space-y-2">
                        <label className="text-[10px] uppercase font-black text-slate-500 tracking-wide block">Nota / Ulasan Semakan :</label>
                        <textarea value={ulasanKetua} onChange={(e) => setUlasanKetua(e.target.value)} placeholder="Tulis ulasan semakan kelulusan atau sebab pemulangan di sini..." className="w-full p-3 bg-white border border-gray-200 rounded-xl text-xs font-medium text-slate-800 focus:outline-none" rows={3} />
                    </div>
                    <div className="flex justify-end gap-2 pt-2 border-t border-amber-200/40">
                        <button type="button" onClick={() => handleTindakan('pulang_pic')} className="flex items-center gap-1.5 px-4 h-9 bg-white hover:bg-red-50 border border-red-200/70 text-red-600 font-bold text-xs uppercase rounded-xl shadow-sm cursor-pointer"><AlertTriangle size={13} /> Perlu Pembetulan</button>
                        {isKR ? (
                            <button type="button" onClick={() => handleTindakan('hantar_kw')} className="flex items-center gap-1.5 px-5 h-9 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs uppercase rounded-xl shadow-md cursor-pointer"><CheckCircle2 size={13} /> Sahkan ke Ketua Wilayah</button>
                        ) : (
                            <button type="button" onClick={() => handleTindakan('kupp_sahkan')} className="flex items-center gap-1.5 px-5 h-9 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs uppercase rounded-xl shadow-md cursor-pointer"><CheckCircle2 size={13} /> Sahkan ke Ketua Wilayah</button>
                        )}
                    </div>
                </div>
            )}

            {!isPeminjaman && ['menunggu validasi kw', 'menunggu validasi'].includes(statusFormat) && isKW && (
                <div className="bg-blue-50/60 p-5 rounded-2xl border border-blue-200/80 shadow-sm space-y-4">
                    <div className="flex items-center gap-2">
                        <div className="p-1.5 bg-blue-100 text-blue-700 rounded-lg"><ShieldCheck size={14} /></div>
                        <h4 className="text-xs font-black text-blue-900 uppercase tracking-wider">Tindakan Validasi Akhir </h4>
                    </div>
                    <div className="space-y-2">
                        <label className="text-[10px] uppercase font-black text-slate-500 tracking-wide block">Nota / Ulasan Semakan:</label>
                        <textarea value={ulasanKetua} onChange={(e) => setUlasanKetua(e.target.value)} placeholder="Tulis ulasan validasi di sini..." className="w-full p-3 bg-white border border-gray-200 rounded-xl text-xs font-medium text-slate-800 focus:outline-none" rows={3} />
                    </div>
                    <div className="flex justify-end gap-2 pt-2 border-t border-blue-200/40">
                        <button type="button" onClick={() => handleTindakan('pulang_semak')} className="flex items-center gap-1.5 px-4 h-9 bg-white hover:bg-amber-50 border border-amber-200 text-amber-600 font-bold text-xs uppercase rounded-xl shadow-sm cursor-pointer"><AlertTriangle size={13} /> Perlu Pembetulan</button>
                        <button type="button" onClick={() => handleTindakan('kw_sahkan')} className="flex items-center gap-1.5 px-5 h-9 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs uppercase rounded-xl shadow-md cursor-pointer"><CheckCircle2 size={13} /> Validasi &amp; Tutup Tiket</button>
                    </div>
                </div>
            )}

        </div>
    );
}
