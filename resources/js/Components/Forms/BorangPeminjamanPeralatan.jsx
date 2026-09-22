import React, { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Package, UserCog, CheckCircle2, RefreshCw, Send, Save, Info, Printer, ArrowLeft, AlertTriangle } from 'lucide-react';
import axios from 'axios';

export default function BorangPeminjamanPeralatan({ ticket, senaraiAset, senaraiPic, auth, dataKelulusan = null }) {
    // KUPP approval state
    const [idAset, setIdAset] = useState('');
    const [stokSemasa, setStokSemasa] = useState(0);
    const [kuantitiLulus, setKuantitiLulus] = useState('');
    const [picTerpilih, setPicTerpilih] = useState('');

    // Auto-generate assets state
    const [asetDijana, setAsetDijana] = useState([]);
    const [loading, setLoading] = useState(false);

    // Interactive PIC form management state
    const [paparBorangA4, setPaparBorangA4] = useState(false);
    const [asetDiurus, setAsetDiurus] = useState(null);
    const [formManual, setFormManual] = useState({
        no_pendaftaran_harta: '',
        status_perkakasan: 'Baru',
        mod_penggunaan: 'Dipinjamkan',
        jawatan_penerima: '',
        catatan: ''
    });

    const [ulasanPengesahan, setUlasanPengesahan] = useState('');

    // Status & role checks
    const adakahSudahLulus = !!dataKelulusan;
    const isCurrentUserPIC = auth?.user?.no_ic && ticket?.petugas?.some(p => p.no_ic === auth.user.no_ic);

    // Normalized user role & status formatting
    const currentUser = auth?.user || {};
    const currentRole = String(currentUser?.peranan || currentUser?.role || '').trim().toLowerCase();
    const statusFormat = String(ticket?.status_tiket || '').trim().toLowerCase();
    const isWorkflowV2 = Number(ticket?.workflow_version) === 2;

    const isKUPP = ['ketua_upp', 'ketua upp', 'kupp'].includes(currentRole);
    const isKUTD = ['ketua_utd', 'ketua utd', 'kutd'].includes(currentRole);
    const isKW = ['ketua_wilayah', 'ketua wilayah', 'kw'].includes(currentRole);
    const isPengurus = isKUPP || isKUTD || isKW;

    const isMenungguPengesahan = ['menunggu pengesahan', 'menunggu semakan', 'semakan kutd'].includes(statusFormat);
    const isMenungguValidasi = statusFormat === 'menunggu validasi';

    // Helper: Format user roles
    const formatPeranan = (peranan) => {
        if (!peranan) return '';
        const p = peranan.toLowerCase();
        if (p === 'juruteknik') return 'Juruteknik';
        if (p === 'ketua_upp') return 'Ketua UPP';
        if (p === 'ketua_utd') return 'Ketua UTD';
        if (p === 'ketua_wilayah') return 'Ketua Wilayah';
        return peranan;
    };

    // Pegawai Pelaksana (PIC) details for handover section
    const pegawaiPelaksana = ticket?.petugas?.[0] || null;
    const namaPegawaiSerah = pegawaiPelaksana?.nama || '-';
    const jawatanPegawaiSerah = formatPeranan(pegawaiPelaksana?.jawatan || pegawaiPelaksana?.peranan) || '-';

    // Helper: Case-insensitive string match check
    const checkMatch = (dbValue, targetValue) => {
        if (!dbValue || !targetValue) return false;
        return String(dbValue).trim().toLowerCase() === String(targetValue).trim().toLowerCase();
    };

    // Auto-generate asset list when asset and quantity are selected
    useEffect(() => {
        const janaSenaraiAset = async () => {
            if (idAset && kuantitiLulus > 0 && kuantitiLulus <= stokSemasa) {
                setLoading(true);
                try {
                    const generateAssetsRoute = isWorkflowV2
                        ? route('tickets.workflow.generateLoanAssets', ticket.id_tiket)
                        : route('tickets.janaSenaraiAset', ticket.id_tiket);

                    const response = await axios.post(generateAssetsRoute, {
                        id_aset: idAset,
                        kuantiti_lulus: kuantitiLulus
                    });
                    setAsetDijana(response.data.senarai_aset);
                } catch (error) {
                    console.error("Gagal menjana aset:", error);
                    setAsetDijana([]);
                } finally {
                    setLoading(false);
                }
            } else {
                setAsetDijana([]);
            }
        };

        const timer = setTimeout(janaSenaraiAset, 500);
        return () => clearTimeout(timer);
    }, [
        idAset,
        kuantitiLulus,
        isWorkflowV2,
        ticket.id_tiket
    ]);

    // Handle equipment selection
    const handlePilihAset = (val) => {
        setIdAset(val);
        const asetDipilih = senaraiAset.find(a => String(a.nama_aset) === String(val));
        setStokSemasa(asetDipilih ? asetDipilih.baki_stok : 0);
        setKuantitiLulus('');
    };

    // Submit KUPP approval
    const handleHantarKelulusan = () => {
        if (!idAset || kuantitiLulus <= 0 || !picTerpilih || asetDijana.length === 0) {
            alert("Ralat: Sila pastikan Peralatan dipilih, kuantiti > 0, PIC dipilih, dan senarai aset dijana.");
            return;
        }

        if (!confirm("Adakah anda pasti untuk luluskan peminjaman ini?")) return;

        const payload = {
            id_aset: idAset,
            kuantiti_lulus: kuantitiLulus,
            pic_ic: picTerpilih,
            senarai_aset: asetDijana
        };

        const reviewLoanRoute = isWorkflowV2
            ? route('tickets.workflow.reviewLoan', ticket.id_tiket)
            : route('tickets.storePeminjaman', ticket.id_tiket);

        router.post(reviewLoanRoute, payload, {
            preserveScroll: true,
            onSuccess: () => alert("Berjaya! Peminjaman diluluskan."),
        });
    };

    // Submit loan form to Manager for verification
    const handleHantarSemuaKeKUTD = () => {
        if (!confirm("Adakah anda pasti untuk menghantar borang peminjaman untuk pengesahan?")) return;

        const submitLoanRoute = isWorkflowV2
            ? route('tickets.workflow.submitLoan', ticket.id_tiket)
            : route('tickets.hantarKeKUTD', ticket.id_tiket);

        router.post(submitLoanRoute, {}, {
            preserveScroll: true,
            onSuccess: () => alert("Tiket peminjaman berjaya dihantar untuk pengesahan!"),
            onError: (errors) => {
                alert(
                    "Tiket tidak dapat dihantar.\nSebab: "
                    + (
                        errors.sistem
                        || Object.values(errors).join('\n')
                    )
                );
            },
        });
    };

    // Manager action handler (Return to PIC)
    const handlePemulangan = () => {
        if (!ulasanPengesahan.trim()) {
            alert("Sila nyatakan nota/ulasan sebab pemulangan perkakasan terlebih dahulu.");
            return;
        }

        if (!confirm("Adakah anda pasti untuk memulangkan tiket ini kepada PIC untuk pembetulan?")) return;

        router.post(route('tickets.prosesPengesahanKutd', ticket.id_tiket), {
            tindakan: 'pulang_pic',
            ulasan: ulasanPengesahan
        }, {
            preserveScroll: true,
            onSuccess: () => {
                alert("Tiket berjaya dikembalikan kepada PIC!");
                setUlasanPengesahan('');
            },
            onError: (errors) => {
                alert("Gagal! Sistem ralat: " + (errors.sistem || Object.values(errors).join('\n')));
            }
        });
    };

    // KUTD action handler (Send to KW for final validation)
    const handleHantarKeKW = () => {
        if (!confirm("Adakah anda pasti untuk menghantar tiket peminjaman ini kepada Ketua Wilayah untuk validasi akhir?")) return;

        router.post(route('tickets.prosesPengesahanKutd', ticket.id_tiket), {
            tindakan: 'hantar_kw',
            ulasan: ulasanPengesahan
        }, {
            preserveScroll: true,
            onSuccess: () => {
                alert("Tiket berjaya dihantar kepada Ketua Wilayah untuk validasi akhir!");
                setUlasanPengesahan('');
            },
            onError: (errors) => {
                alert("Gagal menghantar tiket kepada Ketua Wilayah: " + (errors.sistem || Object.values(errors).join('\n')));
            }
        });
    };

    // KUTD confirmation handler for workflow v2
    const handlePengesahanPeminjamanV2 = () => {
        if (
            !confirm(
                "Adakah anda pasti untuk mengesahkan dan menutup tiket peminjaman ini?"
            )
        ) {
            return;
        }

        router.post(
            route(
                'tickets.workflow.confirmLoan',
                ticket.id_tiket
            ),
            {
                ulasan: ulasanPengesahan
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    alert(
                        "Tiket peminjaman berjaya disahkan dan ditutup!"
                    );
                    setUlasanPengesahan('');
                },
                onError: (errors) => {
                    alert(
                        "Tiket tidak dapat disahkan.\nSebab: "
                        + (
                            errors.sistem
                            || Object.values(errors).join('\n')
                        )
                    );
                }
            }
        );
    };

    // Manager action handler (Approve and Close Ticket instantly)
    const handlePenutupan = () => {
        if (!confirm("Adakah anda pasti untuk mengesahkan borang peminjaman ini dan menutup tiket?")) return;

        router.post(route('tickets.sahkanTutupPeminjaman', ticket.id_tiket), {
            ulasan: ulasanPengesahan
        }, {
            preserveScroll: true,
            onSuccess: () => {
                alert("Tiket peminjaman peralatan telah berjaya disahkan dan ditutup rasmi!");
                setUlasanPengesahan('');
            },
            onError: (errors) => {
                alert("Gagal menutup tiket: " + (errors.sistem || Object.values(errors).join('\n')));
            }
        });
    };

    // Helper: Format date string
    const formatTarikh = (dateString) => {
        if (!dateString) return '';
        const cleanDate = dateString.split(' ')[0];
        const [year, month, day] = cleanDate.split('-');
        if (!year || !month || !day) return dateString;
        return `${day}/${month}/${year}`;
    };

    // Phase 2: PIC Interface & Print Review (After Approval)
    if (adakahSudahLulus) {
        const senaraiAsetLulus = dataKelulusan?.senarai_siri || [];

        // Screen 2B: Interactive A4 printable form layout
        if (paparBorangA4 && asetDiurus) {
            const sudahPernahSimpan = !!asetDiurus.jawatan_penerima;
            const bolehEdit = isCurrentUserPIC && !sudahPernahSimpan;

            const handleSimpanSahaja = () => {
                if (!formManual.jawatan_penerima) {
                    alert("Sila isi fild Jawatan Penerima terlebih dahulu.");
                    return;
                }

                if (!confirm("Adakah anda pasti untuk menyimpan maklumat borang ini?")) return;

                const serialAset = asetDiurus.serial_no || asetDiurus.no_siri;

                const saveLoanFormRoute = isWorkflowV2
                    ? route('tickets.workflow.saveLoanForm', ticket.id_tiket)
                    : route('tickets.simpanBorangPeminjaman', ticket.id_tiket);

                router.post(saveLoanFormRoute, {
                    serial_no: serialAset,
                    no_harta: formManual.no_pendaftaran_harta || '',
                    status_perkakasan: formManual.status_perkakasan,
                    mod_penggunaan: formManual.mod_penggunaan,
                    jawatan_penerima: formManual.jawatan_penerima,
                    catatan: formManual.catatan || ''
                }, {
                    preserveScroll: true,
                    onSuccess: () => {
                        alert("Maklumat borang peminjaman berjaya disimpan!");
                    },
                    onError: (errors) => {
                        console.error(errors);
                        alert("Gagal menyimpan borang!\nSebab: " + Object.values(errors).join('\n'));
                    }
                });
            };

            const triggerBukaCetakHalaman = () => {
                const params = new URLSearchParams({
                    no_harta: formManual.no_pendaftaran_harta,
                    status: formManual.status_perkakasan,
                    mod: formManual.mod_penggunaan,
                    jawatan: formManual.jawatan_penerima,
                    catatan: formManual.catatan
                }).toString();
                window.open(`/tickets/${ticket.id_tiket}/cetak-peminjaman/${asetDiurus.serial_no || asetDiurus.no_siri}?${params}`, '_blank');
            };

            return (
                <div className="space-y-4 animate-in fade-in duration-200">

                    {/* Top navigation bar for A4 form */}
                    <div className="flex justify-between items-center bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                        <button
                            type="button"
                            onClick={() => { setPaparBorangA4(false); setAsetDiurus(null); }}
                            className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-gray-600 hover:bg-gray-100 rounded-lg transition-colors cursor-pointer"
                        >
                            <ArrowLeft size={14} /> Kembali ke Senarai Perkakasan
                        </button>

                        <div className="flex gap-2">
                            {statusFormat === 'selesai' && (
                                <button
                                    type="button"
                                    onClick={triggerBukaCetakHalaman}
                                    className="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs uppercase rounded-xl shadow-sm transition-all active:scale-95 cursor-pointer"
                                >
                                    <Printer size={14} className="shrink-0" />
                                    <span>Cetak Borang Rasmi</span>
                                </button>
                            )}
                        </div>
                    </div>

                    {/* A4 Document layout (JTDI/W-01) */}
                    <div className="bg-white border border-gray-300 max-w-[210mm] mx-auto p-[12mm] text-black shadow-sm font-sans rounded-xl overflow-x-auto">

                        {/* Document Code */}
                        <div className="text-right text-xs font-bold mb-2 text-black">
                            JTDI/W-01
                        </div>

                        <div className="flex flex-col items-center justify-center mb-1.5">
                            <img src="/images/logo_jtdi.png" alt="Logo JTDI" className="h-12 object-contain" />
                            <div className="text-[7.5px] font-black text-center leading-tight mt-0.5 tracking-wider uppercase text-black" style={{ fontFamily: 'Arial, sans-serif' }}>
                                Jabatan Teknologi Digital<br/>Dan Inovasi Negeri Sabah
                            </div>
                        </div>

                        <div className="text-center font-bold text-[14px] mb-6 leading-relaxed text-black">
                            BORANG PEMINJAMAN PERKAKASAN ICT<br />
                            JABATAN TEKNOLOGI DIGITAL DAN INOVASI NEGERI SABAH<br />
                            <span className="font-normal text-[13px]">(Sila Isi Dua Salinan)</span>
                        </div>

                        {/* Master Form Table */}
                        <table className="w-full border-collapse border border-black text-xs text-black">
                            <colgroup>
                                <col style={{ width: '30%' }} />
                                <col style={{ width: '20%' }} />
                                <col style={{ width: '15%' }} />
                                <col style={{ width: '20%' }} />
                                <col style={{ width: '15%' }} />
                            </colgroup>
                            <tbody>
                                {/* Reference and acknowledgment section */}
                                <tr>
                                    <td colSpan="5" className="border border-black p-3 space-y-3">
                                        <div className="flex items-center gap-2">
                                            <span className="w-16">Ruj</span>
                                            <span>: ___________________________________</span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className="w-16">Kepada</span>
                                            <span>: <span className="uppercase inline-block border-b border-gray-300 min-w-[200px]">{ticket.agensi}</span></span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td colSpan="5" className="border border-black p-3 font-medium">
                                        Saya mengaku menerima perkakasan komputer seperti berikut :
                                    </td>
                                </tr>

                                {/* Hardware Information */}
                                <tr>
                                    <td colSpan="5" className="border border-black p-2 bg-gray-50/50">
                                        <strong className="italic text-[13px]">MAKLUMAT PERKAKASAN</strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td className="border border-black p-2">Jenis Perkakasan</td>
                                    <td colSpan="4" className="border border-black p-2 uppercase font-medium">{dataKelulusan?.nama_aset || '-'}</td>
                                </tr>
                                <tr>
                                    <td className="border border-black p-2">Jenama Perkakasan</td>
                                    <td colSpan="4" className="border border-black p-2 uppercase font-medium">{asetDiurus.jenama || asetDiurus.model || '-'}</td>
                                </tr>
                                <tr>
                                    <td className="border border-black p-2">No. Siri</td>
                                    <td colSpan="4" className="border border-black p-2 uppercase font-medium">{asetDiurus.serial_no || asetDiurus.no_siri || '-'}</td>
                                </tr>
                                <tr>
                                    <td className="border border-black p-2 align-middle">No Pendaftaran Harta</td>
                                    <td colSpan="4" className="border border-black p-1.5 uppercase font-medium">
                                        {bolehEdit ? (
                                            <input
                                                type="text"
                                                value={formManual.no_pendaftaran_harta}
                                                onChange={e => setFormManual({...formManual, no_pendaftaran_harta: e.target.value})}
                                                className="w-full h-8 px-2 border border-gray-300 rounded text-xs font-bold uppercase focus:ring-1 focus:ring-blue-500"
                                                placeholder="Sila isi jika ada..."
                                            />
                                        ) : (
                                            <span className="p-1 block">{formManual.no_pendaftaran_harta || '-'}</span>
                                        )}
                                    </td>
                                </tr>

                                {/* Hardware status and usage mode checkboxes */}
                                <tr>
                                    <td className="border border-black p-2">Status Perkakasan</td>
                                    <td className="border border-black p-2">Baru</td>
                                    <td className="border border-black p-2 text-center text-lg font-bold leading-none">
                                        {bolehEdit ? (
                                            <input type="radio" value="Baru" checked={formManual.status_perkakasan === 'Baru'} onChange={e => setFormManual({...formManual, status_perkakasan: e.target.value})} className="w-4 h-4 cursor-pointer" />
                                        ) : (
                                            checkMatch(formManual.status_perkakasan, 'Baru') ? '✓' : ''
                                        )}
                                    </td>
                                    <td className="border border-black p-2">Terpakai</td>
                                    <td className="border border-black p-2 text-center text-lg font-bold leading-none">
                                        {bolehEdit ? (
                                            <input type="radio" value="Terpakai" checked={formManual.status_perkakasan === 'Terpakai'} onChange={e => setFormManual({...formManual, status_perkakasan: e.target.value})} className="w-4 h-4 cursor-pointer" />
                                        ) : (
                                            checkMatch(formManual.status_perkakasan, 'Terpakai') ? '✓' : ''
                                        )}
                                    </td>
                                </tr>
                                <tr>
                                    <td className="border border-black p-2">Mod Penggunaan</td>
                                    <td className="border border-black p-2">Dipinjamkan</td>
                                    <td className="border border-black p-2 text-center text-lg font-bold leading-none">
                                        {bolehEdit ? (
                                            <input type="radio" value="Dipinjamkan" checked={formManual.mod_penggunaan === 'Dipinjamkan'} onChange={e => setFormManual({...formManual, mod_penggunaan: e.target.value})} className="w-4 h-4 cursor-pointer" />
                                        ) : (
                                            checkMatch(formManual.mod_penggunaan, 'Dipinjamkan') ? '✓' : ''
                                        )}
                                    </td>
                                    <td className="border border-black p-2">Diserahkan</td>
                                    <td className="border border-black p-2 text-center text-lg font-bold leading-none">
                                        {bolehEdit ? (
                                            <input type="radio" value="Diserahkan" checked={formManual.mod_penggunaan === 'Diserahkan'} onChange={e => setFormManual({...formManual, mod_penggunaan: e.target.value})} className="w-4 h-4 cursor-pointer" />
                                        ) : (
                                            checkMatch(formManual.mod_penggunaan, 'Diserahkan') ? '✓' : ''
                                        )}
                                    </td>
                                </tr>

                                {/* Recipient Information */}
                                <tr>
                                    <td colSpan="5" className="border border-black p-2 bg-gray-50/50">
                                        <strong className="italic text-[13px]">MAKLUMAT PENERIMA</strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td className="border border-black p-2">Nama Penerima</td>
                                    <td colSpan="4" className="border border-black p-2 uppercase font-medium">{ticket.nama_pemohon || '-'}</td>
                                </tr>
                                <tr>
                                    <td className="border border-black p-2 align-middle">Jawatan</td>
                                    <td colSpan="4" className="border border-black p-1.5 uppercase font-medium">
                                        {bolehEdit ? (
                                            <input
                                                type="text"
                                                value={formManual.jawatan_penerima}
                                                onChange={e => setFormManual({...formManual, jawatan_penerima: e.target.value})}
                                                className="w-full h-8 px-2 border border-gray-300 rounded text-xs font-bold uppercase focus:ring-1 focus:ring-blue-500"
                                                placeholder="Contoh: Pegawai Tadbir"
                                                required
                                            />
                                        ) : (
                                            <span className="p-1 block">{formManual.jawatan_penerima || '-'}</span>
                                        )}
                                    </td>
                                </tr>
                                <tr>
                                    <td className="border border-black p-2">Tarikh Terima</td>
                                    <td colSpan="2" className="border border-black p-2">{formatTarikh(dataKelulusan?.tarikh_lulus || ticket.created_at)}</td>
                                    <td className="border border-black p-2">Tandatangan</td>
                                    <td className="border border-black p-2"></td>
                                </tr>
                                <tr>
                                    <td className="border border-black p-2 align-top pt-3 h-16">Cop Jabatan</td>
                                    <td colSpan="4" className="border border-black p-2"></td>
                                </tr>

                                {/* Handover Officer Information */}
                                <tr>
                                    <td colSpan="5" className="border border-black p-2 bg-gray-50/50">
                                        <strong className="italic text-[13px]">MAKLUMAT PEGAWAI YANG MENYERAHKAN PERKAKASAN</strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td className="border border-black p-2">Nama Pegawai</td>
                                    <td colSpan="4" className="border border-black p-2 uppercase font-medium">{namaPegawaiSerah}</td>
                                </tr>
                                <tr>
                                    <td className="border border-black p-2">Jawatan</td>
                                    <td colSpan="2" className="border border-black p-2 uppercase font-medium">{jawatanPegawaiSerah}</td>
                                    <td className="border border-black p-2">Tandatangan</td>
                                    <td className="border border-black p-2"></td>
                                </tr>
                                <tr>
                                    <td className="border border-black p-2 align-middle">Catatan</td>
                                    <td colSpan="4" className="border border-black p-1.5 uppercase font-medium">
                                        {bolehEdit ? (
                                            <input
                                                type="text"
                                                value={formManual.catatan}
                                                onChange={e => setFormManual({...formManual, catatan: e.target.value})}
                                                className="w-full h-8 px-2 border border-gray-300 rounded text-xs font-bold uppercase focus:ring-1 focus:ring-blue-500"
                                                placeholder="Sila taip catatan sekiranya ada..."
                                            />
                                        ) : (
                                            <span className="p-1 block">{formManual.catatan || '-'}</span>
                                        )}
                                    </td>
                                </tr>
                                <tr>
                                    <td className="border border-black p-2">Tarikh Perkakasan Dikembalikan</td>
                                    <td colSpan="4" className="border border-black p-2"></td>
                                </tr>

                                {/* Terms and Conditions */}
                                <tr>
                                    <td colSpan="5" className="border border-black p-4 text-[11px] leading-relaxed">
                                        <strong className="block mb-2 text-xs">SYARAT DAN PERATURAN PINJAMAN ALATAN MEDIA</strong>
                                        <ul className="list-disc pl-5 space-y-1">
                                            <li>Peralatan yang dipinjam hanya untuk aktiviti-aktiviti rasmi Jabatan sahaja.</li>
                                            <li>Pengguna perlu memastikan peralatan yang diambil berfungsi dengan baik sebelum digunakan.</li>
                                            <li>Pemohon perlu menggunakan peralatan dalam masa yang dibenarkan dan mengembalikan peralatan pada tarikh dan masa yang ditetapkan serta berada dalam keadaan baik.</li>
                                            <li>Pemohon perlu bertanggungjawab terhadap keadaan, keselamatan, dan ganti rugi dikenakan sekiranya berlaku kerosakan/kehilangan pada peralatan yang dipinjam.</li>
                                            <li>Peralatan yang ingin dipinjam boleh diambil pada setiap hari bekerja (Isnin – Jumaat) bermula jam 9.00 pagi hingga 5.00 petang.</li>
                                            <li>Pengguna perlu membuat laporan bertulis kepada Ketua Wilayah, Jabatan Perkhidmatan Komputer Negeri jika peralatan yang dipinjam hilang/rosak sewaktu tempoh pinjaman.</li>
                                            <li>Jabatan Perkhidmatan Komputer Negeri berhak membatalkan kelulusan permohonan pada bila-bila masa sekiranya pemohon/pengguna tidak mematuhi syarat dan peraturan yang telah ditetapkan.</li>
                                        </ul>
                                    </td>
                                </tr>

                                {/* Footer note */}
                                <tr>
                                    <td colSpan="5" className="border border-black p-2 text-[10px]">
                                        Sila tanda mana-mana yang berkenaan. Salinan pertama dikembalikan kepada Jabatan Perkhidmatan Komputer Negeri.
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        {/* Save draft button */}
                        {bolehEdit && (
                            <div className="flex justify-end gap-3 pt-6 mt-4 border-t border-gray-200">
                                <button
                                    type="button"
                                    onClick={handleSimpanSahaja}
                                    className="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-black text-xs uppercase rounded-xl shadow-sm transition-all active:scale-95 cursor-pointer"
                                >
                                    <Save size={14} className="shrink-0" />
                                    <span>Simpan Maklumat</span>
                                </button>
                            </div>
                        )}

                    </div>
                </div>
            );
        }

        {/* Screen 2A: Default asset list view for PIC, KUTD, and KW */}
        return (
            <div className="space-y-5 animate-in fade-in duration-300">
                <div className="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                    <div className="flex items-center gap-3 mb-6 border-b border-gray-100 pb-4">
                        <div className="bg-[#002b66] text-white w-7 h-7 flex items-center justify-center rounded font-bold text-xs">
                            B.
                        </div>
                        <div>
                            <h3 className="text-sm font-black text-[#002b66] uppercase tracking-wider">Senarai Perkakasan Dipinjam</h3>
                        </div>
                    </div>

                    <div className="overflow-hidden border border-gray-200 rounded-lg">
                        <table className="w-full text-xs text-center border-collapse">
                            <thead className="bg-[#002b66] text-white">
                                <tr>
                                    <th className="p-3 border-r border-[#003d8a] font-bold tracking-wider w-16">BIL</th>
                                    <th className="p-3 border-r border-[#003d8a] font-bold tracking-wider">NO SIRI</th>
                                    <th className="p-3 border-r border-[#003d8a] font-bold tracking-wider">NAMA ASET</th>
                                    <th className="p-3 border-r border-[#003d8a] font-bold tracking-wider">MODEL</th>
                                    <th className="p-3 font-bold tracking-wider w-36">TINDAKAN</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white text-gray-700">
                                {senaraiAsetLulus.length > 0 ? (
                                    senaraiAsetLulus.map((aset, i) => {
                                        const sudahDiisi = !!aset.jawatan_penerima;

                                        return (
                                            <tr key={i} className="border-b border-gray-100 last:border-none hover:bg-gray-50/50">
                                                <td className="p-3 border-r border-gray-100">{i + 1}</td>
                                                <td className="p-3 border-r border-gray-100 text-gray-900 font-bold font-mono">{aset.serial_no || aset.no_siri}</td>
                                                <td className="p-3 border-r border-gray-100 font-medium">{dataKelulusan?.nama_aset}</td>
                                                <td className="p-3 border-r border-gray-100">{aset.jenama || aset.model || '-'}</td>
                                                <td className="p-3 text-center">
                                                    {sudahDiisi ? (
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                setAsetDiurus(aset);
                                                                setFormManual({
                                                                    no_pendaftaran_harta: aset.no_pendaftaran_harta || '',
                                                                    status_perkakasan: aset.status_perkakasan || 'Baru',
                                                                    mod_penggunaan: aset.mod_penggunaan || 'Dipinjamkan',
                                                                    jawatan_penerima: aset.jawatan_penerima || '',
                                                                    catatan: aset.catatan || ''
                                                                });
                                                                setPaparBorangA4(true);
                                                            }}
                                                            className="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-bold transition-all shadow-sm cursor-pointer"
                                                        >
                                                            <Info size={13} /> Lihat Borang
                                                        </button>
                                                    ) : isCurrentUserPIC ? (
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                setAsetDiurus(aset);
                                                                setFormManual({
                                                                    no_pendaftaran_harta: aset.no_pendaftaran_harta || '',
                                                                    status_perkakasan: aset.status_perkakasan || 'Baru',
                                                                    mod_penggunaan: aset.mod_penggunaan || 'Dipinjamkan',
                                                                    jawatan_penerima: aset.jawatan_penerima || ticket?.jawatan_pemohon || '',
                                                                    catatan: aset.catatan || ''
                                                                });
                                                                setPaparBorangA4(true);
                                                            }}
                                                            className="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 bg-amber-50 text-amber-700 hover:bg-amber-600 hover:text-white rounded-lg font-bold transition-all shadow-sm cursor-pointer"
                                                        >
                                                            <UserCog size={13} /> Urus
                                                        </button>
                                                    ) : (
                                                        <span className="text-[8px] font-black italic text-gray-700 tracking-wide">
                                                            Menunggu Tindakan Pegawai
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })
                                ) : (
                                    <tr>
                                        <td colSpan="5" className="p-4 text-center text-gray-400 italic">Tiada siri perkakasan dijumpai.</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Submit to Manager button for PIC */}
                    {isCurrentUserPIC && (
                        statusFormat === 'dalam tindakan pegawai'
                        || (
                            isWorkflowV2
                            && statusFormat === 'dalam tindakan'
                        )
                    ) && (
                        <div className="flex justify-end pt-5 border-t border-gray-100 mt-5">
                            <button
                                type="button"
                                onClick={handleHantarSemuaKeKUTD}
                                className="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-[#002b66] hover:bg-[#001f4d] text-white font-black text-xs uppercase rounded-xl shadow-sm transition-all active:scale-95 cursor-pointer"
                            >
                                <Send size={14} className="shrink-0" />
                                <span>KEMASKINI TIKET</span>
                            </button>
                        </div>
                    )}

                    {/* KUTD final confirmation for workflow v2 */}
                    {isWorkflowV2 && isMenungguPengesahan && isKUTD && (
                        <div className="bg-emerald-50/60 p-5 rounded-2xl border border-emerald-200/80 shadow-sm space-y-4 mt-6 animate-in fade-in slide-in-from-bottom-3 duration-200">
                            <div className="flex items-center gap-2">
                                <div className="p-1.5 bg-emerald-100 text-emerald-700 rounded-lg">
                                    <CheckCircle2 size={14} />
                                </div>

                                <h4 className="text-xs font-black text-emerald-900 uppercase tracking-wider">
                                    Pengesahan Akhir Peminjaman
                                </h4>
                            </div>

                            <p className="text-xs font-medium text-emerald-900/80 leading-relaxed">
                                Semak maklumat peralatan dan borang peminjaman sebelum mengesahkan penutupan tiket.
                            </p>

                            <div className="space-y-2">
                                <label className="text-[10px] uppercase font-black text-slate-500 tracking-wide block">
                                    Ulasan Pengesahan:
                                </label>

                                <textarea
                                    value={ulasanPengesahan}
                                    onChange={(e) => setUlasanPengesahan(e.target.value)}
                                    placeholder="Masukkan ulasan sekiranya perlu..."
                                    maxLength={2000}
                                    className="w-full p-3 bg-white border border-gray-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none"
                                    rows={3}
                                />
                            </div>

                            <div className="flex justify-end pt-3 border-t border-emerald-200/40">
                                <button
                                    type="button"
                                    onClick={handlePengesahanPeminjamanV2}
                                    className="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs uppercase rounded-xl shadow-sm transition-all active:scale-95 cursor-pointer"
                                >
                                    <CheckCircle2 size={14} className="shrink-0" />
                                    <span>Sahkan & Tutup Tiket</span>
                                </button>
                            </div>
                        </div>
                    )}

                    {/* Unified Manager (KUPP/KUTD/KW) validation block */}
                    {isMenungguPengesahan && isKUTD && !isWorkflowV2 && (
                        <div className="bg-emerald-50/60 p-5 rounded-2xl border border-emerald-200/80 shadow-sm space-y-4 mt-6 animate-in fade-in slide-in-from-bottom-3 duration-200">
                            <div className="flex items-center gap-2">
                                <div className="p-1.5 bg-emerald-100 text-emerald-700 rounded-lg">
                                    <CheckCircle2 size={14} />
                                </div>
                                <h4 className="text-xs font-black text-emerald-900 uppercase tracking-wider">
                                    Pengesahan & Penutupan Tiket Peminjaman
                                </h4>
                            </div>

                            <div className="space-y-2">
                                <label className="text-[10px] uppercase font-black text-slate-500 tracking-wide block">
                                    Nota / Ulasan Semakan:
                                </label>
                                <textarea
                                    value={ulasanPengesahan}
                                    onChange={(e) => setUlasanPengesahan(e.target.value)}
                                    placeholder="Sila tulis nota ulasan penutupan rasmi atau arahan pemulangan di sini..."
                                    className="w-full p-3 bg-white border border-gray-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none"
                                    rows={3}
                                />
                            </div>

                            <div className="flex justify-end gap-2 pt-3 border-t border-emerald-200/40">
                                <button
                                    type="button"
                                    onClick={handlePemulangan}
                                    className="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-white hover:bg-red-50 border border-red-200/70 text-red-600 font-black text-xs uppercase rounded-xl shadow-sm transition-all active:scale-95 cursor-pointer"
                                >
                                    <AlertTriangle size={14} className="shrink-0" />
                                    <span>Perlu Pembetulan</span>
                                </button>

                                <button
                                    type="button"
                                    onClick={handleHantarKeKW}
                                    className="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs uppercase rounded-xl shadow-sm transition-all active:scale-95 cursor-pointer"
                                >
                                    <CheckCircle2 size={14} className="shrink-0" />
                                    <span>Hantar ke KW</span>
                                </button>
                            </div>
                        </div>
                    )}

                    {/* KW Final Validation Block */}
                    {isMenungguValidasi && isKW && (
                        <div className="bg-emerald-50/60 p-5 rounded-2xl border border-emerald-200/80 shadow-sm space-y-4 mt-6">
                            <div className="flex items-center gap-2">
                                <div className="p-1.5 bg-emerald-100 text-emerald-700 rounded-lg">
                                    <CheckCircle2 size={14} />
                                </div>
                                <h4 className="text-xs font-black text-emerald-900 uppercase tracking-wider">
                                    Validasi Akhir Ketua Wilayah
                                </h4>
                            </div>

                            <div className="space-y-2">
                                <label className="text-[10px] uppercase font-black text-slate-500 tracking-wide block">
                                    Nota / Ulasan Validasi:
                                </label>
                                <textarea
                                    value={ulasanPengesahan}
                                    onChange={(e) => setUlasanPengesahan(e.target.value)}
                                    placeholder="Masukkan nota atau ulasan validasi akhir..."
                                    className="w-full p-3 bg-white border border-gray-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none"
                                    rows={3}
                                />
                            </div>

                            <div className="flex justify-end pt-3 border-t border-emerald-200/40">
                                <button
                                    type="button"
                                    onClick={handlePenutupan}
                                    className="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs uppercase rounded-xl shadow-sm transition-all active:scale-95 cursor-pointer"
                                >
                                    <CheckCircle2 size={14} className="shrink-0" />
                                    <span>Sahkan & Tutup Tiket</span>
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        );
    }

    if (
        isWorkflowV2
        && statusFormat === 'menunggu semakan'
        && !isKUPP
    ) {
        return (
            <div className="mt-4 rounded-xl border border-blue-200 bg-blue-50 p-5 text-sm font-semibold text-blue-800">
                Tiket peminjaman ini sedang menunggu semakan KUPP.
            </div>
        );
    }

    {/* Phase 1: General Approval Interface View (Before Approval) */}
    return (
        <div className="space-y-6 mt-4">

            {/* Section A: Equipment Information */}
            <div className="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                <div className="flex items-center gap-3 mb-6 border-b border-gray-100 pb-4">
                    <div className="bg-[#002b66] text-white w-7 h-7 flex items-center justify-center rounded font-bold text-xs">
                        A.
                    </div>
                    <h3 className="text-sm font-black text-[#002b66] uppercase tracking-wider">Maklumat Peralatan</h3>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div className="space-y-5">
                        <div className="space-y-2">
                            <label className="block text-xs font-bold text-gray-800">
                                Jenis Peralatan <span className="text-red-500">*</span>
                            </label>
                            <select
                                value={idAset}
                                onChange={(e) => handlePilihAset(e.target.value)}
                                className="w-full h-11 px-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#002b66]/20 focus:border-[#002b66]"
                            >
                                <option value=""> Sila Pilih Peralatan </option>
                                {[...new Set(senaraiAset?.map(a => a.nama_aset))].map((nama, idx) => (
                                    <option key={idx} value={nama}>
                                        {nama}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="space-y-2">
                            <label className="block text-xs font-bold text-gray-800">
                                Kuantiti Diluluskan <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="number"
                                min="1"
                                max={stokSemasa}
                                value={kuantitiLulus}
                                onChange={(e) => setKuantitiLulus(e.target.value)}
                                disabled={!idAset || stokSemasa === 0}
                                className="w-full h-11 px-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#002b66]/20 focus:border-[#002b66] disabled:bg-gray-100"
                            />
                        </div>
                    </div>

                    <div className="flex items-start">
                        <div className="w-full bg-[#f4f8fc] border-l-4 border-[#0066cc] p-5 rounded-r-lg mt-1">
                            <div className="flex items-center gap-2 text-[#004b99] font-black text-xs mb-3 uppercase tracking-wide">
                                <Info size={16} className="text-[#0066cc]" /> MAKLUMAT KUANTITI PERALATAN
                            </div>
                            <p className="text-xs text-gray-600 mb-2 font-medium">Kuantiti tersedia:</p>
                            <p className="text-2xl font-black text-[#002b66]">
                                {idAset ? stokSemasa : '0'} <span className="text-base font-bold text-gray-600">unit</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {/* Section B: Selected Assets List */}
            {(loading || asetDijana.length > 0) && (
                <div className="bg-white p-6 rounded-xl border border-gray-200 shadow-sm animate-in fade-in duration-300">
                    <div className="flex items-center gap-3 mb-6 border-b border-gray-100 pb-4">
                        <div className="bg-[#002b66] text-white w-7 h-7 flex items-center justify-center rounded font-bold text-xs">
                            B.
                        </div>
                        <h3 className="text-sm font-black text-[#002b66] uppercase tracking-wider">Senarai Aset Dipilih</h3>
                    </div>

                    {loading ? (
                        <div className="flex justify-center items-center py-8 text-[#0066cc] font-bold text-sm animate-pulse gap-2">
                            <RefreshCw size={18} className="animate-spin" /> Sedang memilih aset...
                        </div>
                    ) : (
                        <div className="space-y-4">
                            <div className="overflow-hidden border border-gray-200 rounded-lg">
                                <table className="w-full text-xs text-center border-collapse">
                                    <thead className="bg-[#002b66] text-white">
                                        <tr>
                                            <th className="p-3 border-r border-[#003d8a] font-bold tracking-wider w-16">BIL</th>
                                            <th className="p-3 border-r border-[#003d8a] font-bold tracking-wider">NO SIRI</th>
                                            <th className="p-3 border-r border-[#003d8a] font-bold tracking-wider">NAMA ASET</th>
                                            <th className="p-3 font-bold tracking-wider">MODEL</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white text-gray-700">
                                        {asetDijana.map((aset, i) => (
                                            <tr key={i} className="border-b border-gray-100 last:border-none">
                                                <td className="p-3 border-r border-gray-100">{i + 1}</td>
                                                <td className="p-3 border-r border-gray-100 text-gray-900 font-bold font-mono">{aset.serial_no || aset.no_siri}</td>
                                                <td className="p-3 border-r border-gray-100">{aset.nama_aset || idAset}</td>
                                                <td className="p-3">{aset.jenama || aset.model || '-'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* Section C: Officer Assignment */}
            <div className="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                <div className="flex items-center gap-3 mb-6 border-b border-gray-100 pb-4">
                    <div className="bg-[#002b66] text-white w-7 h-7 flex items-center justify-center rounded font-bold text-xs">
                        C.
                    </div>
                    <h3 className="text-sm font-black text-[#002b66] uppercase tracking-wider">Lantik Petugas</h3>
                </div>

                <div className="w-full md:w-1/2 space-y-2">
                    <label className="block text-xs font-bold text-gray-800">
                        Pegawai Pelaksana <span className="text-red-500">*</span>
                    </label>
                    <select
                        value={picTerpilih}
                        onChange={(e) => setPicTerpilih(e.target.value)}
                        className="w-full h-11 px-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#002b66]/20 focus:border-[#002b66]"
                    >
                        <option value="">Pilih Pegawai </option>
                        {senaraiPic?.map((pic) => (
                            <option key={pic.no_ic} value={pic.no_ic}>{pic.nama}</option>
                        ))}
                    </select>
                </div>
            </div>

            {/* General Approval Action Button */}
            <div className="flex justify-end pt-2">
                <button
                    type="button"
                    onClick={handleHantarKelulusan}
                    className="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-[#002b66] hover:bg-[#001f4d] text-white font-black text-xs uppercase rounded-xl shadow-sm transition-all active:scale-95 cursor-pointer"
                >
                    <Send size={14} className="shrink-0" />
                    <span>KEMASKINI TIKET</span>
                </button>
            </div>

        </div>
    );
}
