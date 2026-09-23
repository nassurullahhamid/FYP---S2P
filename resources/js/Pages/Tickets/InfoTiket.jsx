import React from 'react';
import { Head, useForm, Link, router } from '@inertiajs/react';
import Sidebar from '@/Components/Sidebar';
import Topbar from '@/Components/Topbar';
import FormMaklumatTapak from '@/Components/Forms/FormMaklumatTapak';
import BorangLKKRangkaian from '@/Components/Forms/BorangLKKRangkaian';
import BorangLKKTransformasi from '@/Components/Forms/BorangLKKTransformasi';
import BorangLKKPembekalanICT from '@/Components/Forms/BorangLKKPembekalanICT';
import PaparanRingkasanLKK from '@/Components/Forms/PaparanRingkasanLKK';
import BorangPeminjamanPeralatan from '@/Components/Forms/BorangPeminjamanPeralatan';
import axios from 'axios';
import { useState, useEffect, useRef } from 'react';
import {
    Calendar, User, CheckCircle, FileText, ArrowLeft, Phone, Ticket,
    Send, Clock, Package2, Users, Search, Boxes, ListTree,
    ClipboardList, Monitor, XCircle, Eye, Briefcase, Lock, CheckCircle2, Plus, Trash2
} from 'lucide-react';

export default function InfoTiket({ auth, backUrl, ticket, senaraiPengguna, senaraiPic, senaraiAset = [], auditTrail = [], dataKelulusan = null }) {

    const user = auth?.user;
    const [searchTerm, setSearchTerm] = useState('');

    const [activeTab, setActiveTab] = useState('butiran');
    const [q1Lantik, setQ1Lantik] = useState('Ya');
    const [q2Serah, setQ2Serah] = useState('Tidak');
    const [networkReviewProcessing, setNetworkReviewProcessing] = useState(false);

    const initialNetworkObjectives = (() => {
        const rawObjectives = ticket.laporan?.objektif;

        if (Array.isArray(rawObjectives)) {
            return rawObjectives.length > 0
                ? rawObjectives
                : [{ teks: '' }];
        }

        if (typeof rawObjectives === 'string') {
            try {
                const parsed = JSON.parse(rawObjectives);

                if (Array.isArray(parsed) && parsed.length > 0) {
                    return parsed;
                }
            } catch (error) {
                console.error(
                    'Gagal membaca objektif Rangkaian:',
                    error
                );
            }
        }

        return [{ teks: '' }];
    })();

    const { data, setData, post, processing, errors, transform } = useForm({
        kategori: ticket.kategori || '',
        sub_kategori: ticket.sub_kategori || '',
        tahap_keutamaan: ticket.tahap_keutamaan || '',
        bisa_kendalikan: 'Ya',
        no_ic: '',
        senarai_pic_ic: [],
        status_pelaksanaan: 'Dalam Proses',
        catatan_penutupan: ticket.catatan_penutupan || '',
        ulasan: ticket.ulasan_semakan || '',
        tarikh_lawatan: '',
        masa_lawatan: '',
        catatan_lawatan: '',
        pendahuluan: ticket.laporan?.pendahuluan || '',
        objektif: initialNetworkObjectives,
        serial_no: ticket.serial_no || '',
        kuantiti_dipinjam: ticket.kuantiti_dipinjam || 1,
    });

    const handleAddNetworkObjective = () => {
        const objectives = Array.isArray(data.objektif)
            ? data.objektif
            : [];

        if (objectives.length < 20) {
            setData(
                'objektif',
                [...objectives, { teks: '' }]
            );
        }
    };

    const handleUpdateNetworkObjective = (index, value) => {
        const objectives = Array.isArray(data.objektif)
            ? [...data.objektif]
            : [{ teks: '' }];

        objectives[index] = {
            ...objectives[index],
            teks: value,
        };

        setData('objektif', objectives);
    };

    const handleRemoveNetworkObjective = (index) => {
        const objectives = Array.isArray(data.objektif)
            ? data.objektif
            : [];

        if (objectives.length <= 1) return;

        setData(
            'objektif',
            objectives.filter(
                (_, objectiveIndex) =>
                    objectiveIndex !== index
            )
        );
    };

    const handleAddPic = () => {
        const currentPics = Array.isArray(data.senarai_pic_ic) ? data.senarai_pic_ic : [];
        setData('senarai_pic_ic', [...currentPics, '']);
    };

    const handleRemovePic = (index) => {
        const currentPics = Array.isArray(data.senarai_pic_ic) ? data.senarai_pic_ic : [];
        const updated = currentPics.filter((_, i) => i !== index);
        setData('senarai_pic_ic', updated);
    };

    const handleUpdatePic = (index, value) => {
        const currentPics = Array.isArray(data.senarai_pic_ic) && data.senarai_pic_ic.length > 0
            ? [...data.senarai_pic_ic]
            : [''];
        currentPics[index] = value;
        setData('senarai_pic_ic', currentPics);
    };

    const roleStr = String(user?.peranan || user?.role || '').toLowerCase();
    const isKUPP = roleStr.includes('kupp') || roleStr.includes('upp');
    const isKUTD = roleStr.includes('kutd') || roleStr.includes('utd');
    const isKW = roleStr.includes('kw') || roleStr.includes('wilayah');
    const isManagerRole = isKUPP || isKUTD || isKW;

    const isAlreadyClassified = ticket.status_tiket !== 'Menunggu Klasifikasi';
    const isWorkflowV2 = Number(ticket.workflow_version) === 2;

    const assignedPetugasIC = ticket?.petugas?.map(p => p.no_ic).filter(Boolean) || [];

    const isCurrentUserPIC = user?.no_ic && assignedPetugasIC.includes(user.no_ic);

    const isKR = String(ticket.kategori || data.kategori || '').toLowerCase().includes('rangkaian');
    const isTD = String(ticket.kategori || data.kategori || '').toLowerCase().includes('transformasi');
    const isMB = String(ticket.kategori || data.kategori || '').toLowerCase().includes('bantuan');

    const perlukanModulRangkaian = isKR || isTD;
    const adakahTapakSudahIsi = !!ticket.konsultasi_rangkaian?.jenis_premis;
    const isPembekalanICT = String(ticket.transformasi_digital?.sub_kategori || ticket.sub_kategori || '').toLowerCase().includes('pembekalan');

    const statusFormat = String(ticket.status_tiket || '').trim().toLowerCase();

    const statusPaparan =
        isWorkflowV2
        && isKR
        && statusFormat === 'menunggu semakan laporan'
            ? 'Menunggu Semakan'
            : ticket.status_tiket;
    const isDalamTindakan =
        statusFormat === 'dalam tindakan pegawai'
        || statusFormat === 'tindakan pic'
        || (
            isWorkflowV2
            && [
                'dalam tindakan',
                'laporan perlu pembetulan',
            ].includes(statusFormat)
        );
    const isSelesai = statusFormat === 'selesai';
    const isValidasiPhase = statusFormat.includes('validasi');

    const isAwalPhaseKR = [
        'menunggu klasifikasi',
        'menunggu semakan dokumen',
        'menunggu semakan',
        'tugasan upp',
        'tugasan utd',
    ].includes(statusFormat);
    const isLepasTindakanKR = [
        'menunggu pengesahan',
        'menunggu pengesahan lkk',
        'semakan kutd',
        'menunggu semakan laporan',
        'sedia diverifikasi',
        'lkk perlu pembetulan',
        'pembetulan ketua',
        'menunggu validasi',
        'menunggu validasi kw',
        'validasi kw',
        'selesai',
    ].includes(statusFormat);

    const isAuthorizedToEdit =
        (isKR && isKUTD && [
            'menunggu pengesahan',
            'menunggu pengesahan lkk',
            'semakan kutd',
            'sedia diverifikasi',
            'lkk perlu pembetulan',
            'pembetulan ketua',
        ].includes(statusFormat))
        ||
        (isTD && !isPembekalanICT && (
            (isKUPP && ['tugasan upp', 'menunggu pengesahan'].includes(statusFormat)) ||
            (isCurrentUserPIC && ['dalam tindakan pegawai', 'tindakan pic', 'lkk perlu pembetulan'].includes(statusFormat)) ||
            (isKUTD && ['tugasan utd', 'semakan laporan teknikal', 'menunggu semakan', 'semakan kutd'].includes(statusFormat))
        ))
        ||
        (isTD && isPembekalanICT && (
            (isKUPP && ['tugasan upp', 'menunggu pengesahan'].includes(statusFormat)) ||
            (isCurrentUserPIC && ['dalam tindakan pegawai', 'tindakan pic', 'lkk perlu pembetulan'].includes(statusFormat)) ||
            (isKUTD && ['tugasan utd', 'semakan laporan teknikal', 'menunggu semakan', 'lkk perlu pembetulan'].includes(statusFormat))
        ))
        ||
        (isKW && isValidasiPhase);

    const paparTabTapak = isKR ? ((isCurrentUserPIC && isDalamTindakan) || isLepasTindakanKR) : false;

    const paparTabLaporan =
        (isKR && (isKUTD ? isLepasTindakanKR : ['menunggu validasi', 'menunggu validasi kw', 'validasi kw', 'selesai'].includes(statusFormat)))
        ||
        (isTD && !['menunggu klasifikasi', 'menunggu semakan dokumen'].includes(statusFormat));

    const paparkanTabTeknikal = perlukanModulRangkaian && (paparTabTapak || paparTabLaporan);
    const isPeminjaman = String(ticket.sub_kategori || '').toLowerCase().includes('peminjaman');
    const paparTabPeminjaman = isPeminjaman && isAlreadyClassified && (isManagerRole || isCurrentUserPIC);
    const isTechCategory = isKR || isTD;

    useEffect(() => {
        if (isKR) {
            if (isDalamTindakan) {
                if (isCurrentUserPIC) {
                    setActiveTab('tapak');
                } else {
                    setActiveTab('butiran');
                }
            } else if (isAwalPhaseKR) {
                setActiveTab('butiran');
            } else if (isLepasTindakanKR) {
                if (isKUTD && isAuthorizedToEdit) {
                    setActiveTab('lkk');
                } else if (isValidasiPhase || isSelesai) {
                    setActiveTab('lkk');
                } else {
                    setActiveTab('tapak');
                }
            } else {
                setActiveTab('butiran');
            }
        } else if (isTD) {
            if (isAuthorizedToEdit || isValidasiPhase || isSelesai) {
                setActiveTab('lkk');
            } else {
                setActiveTab('butiran');
            }
        } else if (paparTabPeminjaman && statusFormat.includes('dokumen')) {
            setActiveTab('peminjaman');
        } else {
            setActiveTab('butiran');
        }
    }, [statusFormat, isKR, isTD, paparkanTabTeknikal, adakahTapakSudahIsi, paparTabTapak, paparTabLaporan, paparTabPeminjaman, isCurrentUserPIC, isKUTD, isKUPP, isKW, isDalamTindakan, isAuthorizedToEdit, isValidasiPhase, isSelesai, isAwalPhaseKR, isLepasTindakanKR]);

    useEffect(() => {
        if (q2Serah === 'Ya') {
            setData(prev => ({ ...prev, serial_no: '', kuantiti_dipinjam: 1, tarikh_lawatan: '', masa_lawatan: '' }));
        }
    }, [q2Serah]);

    const handleSubmitAction = (e) => {
        e.preventDefault();

        if (processing) return;

        const klasifikasi = statusFormat === 'menunggu klasifikasi';
        const semakanHelpdeskV2 =
            isWorkflowV2
            && statusFormat === 'menunggu semakan'
            && isMB
            && !isPeminjaman;

        const semakanNetworkV2 =
            isWorkflowV2
            && statusFormat === 'menunggu semakan'
            && isKR;

        if (klasifikasi && !isManagerRole) {
            alert('Hanya KUPP, KUTD atau KW boleh mengklasifikasikan tiket.');
            return;
        }

        if (semakanHelpdeskV2 && !isKUTD) {
            alert('Hanya KUTD boleh menyemak tiket Meja Bantuan ini.');
            return;
        }

        if (semakanNetworkV2 && !isKUTD) {
            alert('Hanya KUTD boleh menyemak tiket Rangkaian ini.');
            return;
        }

        transform((values) => {
            if (klasifikasi) {
                return {
                    kategori: values.kategori,
                    sub_kategori: values.sub_kategori,
                    tahap_keutamaan: values.tahap_keutamaan,
                };
            }

            if (semakanHelpdeskV2) {
                return {
                    pic_ic: values.no_ic,
                };
            }

            if (semakanNetworkV2) {
                return {
                    pendahuluan:
                        String(values.pendahuluan || '').trim(),
                    objektif: (
                        Array.isArray(values.objektif)
                            ? values.objektif
                            : []
                    )
                        .map(item => ({
                            teks: String(item?.teks || '').trim(),
                        }))
                        .filter(item => item.teks !== ''),
                    senarai_pic_ic: (
                        Array.isArray(values.senarai_pic_ic)
                            ? values.senarai_pic_ic
                            : []
                    ).filter(Boolean),
                    tarikh_lawatan: values.tarikh_lawatan,
                    masa_lawatan: values.masa_lawatan,
                    catatan_lawatan: values.catatan_lawatan,
                };
            }

            // Kekalkan payload proses lama tanpa mengubah state terus.
            return {
                ...values,
                bisa_kendalikan: isTD
                    ? 'Ya'
                    : (q2Serah === 'Ya' ? 'Tidak' : 'Ya'),
                no_ic: isTD ? (user?.no_ic || '') : values.no_ic,
            };
        });

        const submissionRoute = klasifikasi && isWorkflowV2
            ? route('tickets.workflow.classify', ticket.id_tiket)
            : semakanNetworkV2
                ? route(
                    'tickets.workflow.reviewNetwork',
                    ticket.id_tiket
                )
                : semakanHelpdeskV2
                    ? route(
                        'tickets.workflow.reviewHelpdesk',
                        ticket.id_tiket
                    )
                    : route(
                        'tickets.processAction',
                        ticket.id_tiket
                    );

        post(submissionRoute, {
            preserveScroll: true,
            onSuccess: () => alert(
                klasifikasi
                    ? 'Klasifikasi berjaya. Tiket kini Menunggu Semakan.'
                    : semakanNetworkV2
                        ? 'Tiket Rangkaian berjaya disemak, lawatan dijadualkan dan Juruteknik telah dilantik.'
                        : semakanHelpdeskV2
                            ? 'Tiket berjaya disemak dan petugas telah dilantik.'
                            : 'Tiket telah berjaya dikemaskini!'
            ),
            onError: () => {
                alert('Tindakan tidak berjaya. Sila semak mesej ralat pada borang.');
            },
            onFinish: () => transform((values) => values),
        });
    };

    const handlePicSubmit = (e) => {
        e.preventDefault();

        if (processing) return;

        const submissionRoute = isWorkflowV2
            ? route(
                'tickets.workflow.submitHelpdesk',
                ticket.id_tiket
            )
            : route(
                'tickets.hantarLaporanMB',
                ticket.id_tiket
            );

        transform((values) => ({
            catatan_penutupan: values.catatan_penutupan,
        }));

        post(submissionRoute, {
            preserveScroll: true,
            onSuccess: () => alert(
                'Catatan tindakan berjaya dihantar untuk pengesahan!'
            ),
            onError: (err) => {
                Object.values(err).forEach(
                    message => alert(message)
                );
            },
            onFinish: () => transform((values) => values),
        });
    };

    const handleNetworkSiteReview = () => {
        if (networkReviewProcessing) return;

        if (
            !confirm(
                'Adakah anda pasti mahu mengesahkan laporan tapak ini?'
            )
        ) {
            return;
        }

        setNetworkReviewProcessing(true);

        router.post(
            route(
                'tickets.workflow.reviewNetworkSiteReport',
                ticket.id_tiket
            ),
            {
                tindakan: 'TERIMA',
                ulasan: null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    alert(
                        'Laporan tapak berjaya disahkan. Penyediaan LKK boleh diteruskan.'
                    );
                },
                onError: (reviewErrors) => {
                    const messages = Object.values(
                        reviewErrors
                    ).flat();

                    alert(
                        messages.length > 0
                            ? messages.join('\n')
                            : 'Pengesahan laporan tapak tidak berjaya.'
                    );
                },
                onFinish: () => {
                    setNetworkReviewProcessing(false);
                },
            }
        );
    };
    const handleCheckboxChange = (picIc) => {
        if (data.senarai_pic_ic.includes(picIc)) {
            setData('senarai_pic_ic', data.senarai_pic_ic.filter(ic => ic !== picIc));
        } else {
            setData('senarai_pic_ic', [...data.senarai_pic_ic, picIc]);
        }
    };

    const validUsers = senaraiPengguna?.filter(
        p => p.no_ic && p.nama
    ) || [];

    const filteredUsers = validUsers.filter(
        p => p.nama.toLowerCase().includes(
            searchTerm.toLowerCase()
        )
    );

    const activeTechnicians = validUsers.filter(
        p => String(p.peranan || '').toLowerCase() === 'juruteknik'
            && String(p.status_pengguna || '').toLowerCase() === 'aktif'
    );

    const formatTarikh = (dateString) => {
        if (!dateString) return 'Belum Ditetapkan';
        const cleanDate = dateString.split(' ')[0];
        const [year, month, day] = cleanDate.split('-');
        if (!year || !month || !day) return dateString;
        return `${day}-${month}-${year}`;
    };

    const formatMasa = (timeString) => {
        if (!timeString) return 'Belum Ditetapkan';
        const [timePart] = timeString.split(' ');
        const parts = timePart.split(':');
        let hours = parseInt(parts[0], 10);
        const minutes = parts[1];
        if (isNaN(hours) || !minutes) return timeString;
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;
        const strHours = hours < 10 ? '0' + hours : hours;
        return `${strHours}:${minutes} ${ampm}`;
    };

    const subKategoriMapping = {
        'Meja Bantuan': [
            'Penyelenggaraan Komputer',
            'Penyelenggaraan Rangkaian',
            'Sistem Aplikasi',
            'Perkhidmatan E-mel',
            'Perkhidmatan Lintas Langsung',
            'Peminjaman Peralatan ICT'
        ],
        'Konsultasi Rangkaian': ['Pemasangan Baharu', 'Naiktaraf'],
        'Transformasi Digital': ['Pemodenan Bilik Mesyuarat', 'Pembekalan Peralatan ICT']
    };
    const senaraiSubSemasa = subKategoriMapping[data.kategori] || [];

    const paparButiranAm = activeTab === 'butiran';

    const tunjukBorangTindakanAm = !isSelesai && (
        (statusFormat === 'menunggu klasifikasi' && isManagerRole) ||
        (isWorkflowV2 && statusFormat === 'menunggu semakan' && isKUTD && isKR) ||
        (isWorkflowV2 && statusFormat === 'menunggu semakan' && isKUTD && isMB && !isPeminjaman) ||
        (statusFormat === 'menunggu semakan dokumen' && isKUPP && data.sub_kategori !== 'Peminjaman Peralatan ICT' && data.sub_kategori !== 'Pemodenan Bilik Mesyuarat') ||
        (statusFormat === 'tugasan utd' && isKUTD && !isTD)
    );

    return (
        <div className="min-h-screen bg-[#f4f6f9] flex flex-col md:flex-row pt-16 md:pt-0">
            <Head title={`Maklumat Tiket - ${ticket.id_tiket}`} />
            <Sidebar />

            <div className="flex-1 flex flex-col min-w-0 w-full">
                <Topbar title="Maklumat Tiket" />

                <main className="flex-1 p-4 md:p-6 w-full max-w-7xl mx-auto">
                    <div className="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start w-full">

                        <div className="lg:col-span-3 space-y-5 w-full">

                            <div className="flex items-center mb-1">
                                <Link
                                    href={backUrl}
                                    className="inline-flex items-center gap-2 text-xs font-bold text-blue-700 hover:text-blue-900 transition-colors uppercase tracking-wider focus:outline-none"
                                >
                                    <ArrowLeft size={14} strokeWidth={2.5} /> Kembali
                                </Link>
                            </div>

                            <div className="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-sm flex items-start gap-4 w-full">
                                <div className="p-3 bg-blue-50 text-blue-700 rounded-xl shrink-0">
                                    <Ticket size={24} />
                                </div>
                                <div className="flex-grow space-y-1 min-w-0">
                                    <div className="flex items-center gap-3 flex-wrap">
                                        <h2 className="text-base font-black text-blue-950 tracking-tight">Tiket #{ticket.id_tiket}</h2>
                                        <span className={`px-2.5 py-0.5 border text-[9px] font-black rounded-full uppercase tracking-wider shadow-sm ${
                                            ticket.status_tiket === 'Selesai' ? 'bg-green-50 border-green-200 text-green-600' : 'bg-amber-50 border-amber-200 text-amber-600'
                                        }`}>
                                            {statusPaparan || 'Menunggu Klasifikasi'}
                                        </span>
                                    </div>
                                    <h3 className="text-xs font-black text-gray-700 tracking-tight leading-relaxed truncate">Perkara: {ticket.perkara || 'Tiada Tajuk Perkara'}</h3>
                                    <div className="flex items-center gap-4 text-[11px] text-gray-400 font-bold pt-1 flex-wrap">
                                        <span className="flex items-center gap-1"><Calendar size={12} /> Tarikh Terima: <span className="text-gray-600">{ticket.tarikh_terima ? formatTarikh(ticket.tarikh_terima) : '04/06/2026'}</span></span>
                                        <span className="flex items-center gap-1"><Phone size={12} /> Saluran: <span className="text-gray-600">{ticket.saluran || 'Telefon'}</span></span>
                                    </div>
                                </div>
                            </div>

                            {((paparkanTabTeknikal && !isMB && (paparTabTapak || paparTabLaporan)) || paparTabPeminjaman) && (
                                <div className="flex bg-gray-200/60 p-1.5 rounded-xl gap-1 w-full overflow-x-auto custom-scrollbar">

                                    <button
                                        onClick={() => setActiveTab('butiran')}
                                        className={`flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-xs font-black uppercase tracking-wider transition-all whitespace-nowrap cursor-pointer flex-1 ${
                                            activeTab === 'butiran' ? 'bg-blue-600 text-white shadow-md' : 'text-gray-500 hover:text-gray-800'
                                        }`}
                                    >
                                        <FileText size={14} /> Butiran Am
                                    </button>

                                    {paparTabPeminjaman && (
                                        <button
                                            onClick={() => setActiveTab('peminjaman')}
                                            className={`flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-xs font-black uppercase tracking-wider transition-all whitespace-nowrap flex-1 ${
                                                activeTab === 'peminjaman' ? 'bg-blue-600 text-white shadow-md' : 'text-gray-500 hover:text-gray-800'
                                            }`}
                                        >
                                            <Package2 size={14} /> MAKLUMAT PERALATAN
                                        </button>
                                    )}

                                    {paparTabTapak && (
                                        <button
                                            onClick={() => setActiveTab('tapak')}
                                            className={`flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-xs font-black uppercase tracking-wider transition-all whitespace-nowrap cursor-pointer flex-1 ${
                                                activeTab === 'tapak' ? 'bg-blue-600 text-white shadow-md' : 'text-gray-500 hover:text-gray-800'
                                            }`}
                                        >
                                            <Briefcase size={14} /> Maklumat Tapak
                                        </button>
                                    )}

                                    {paparTabLaporan && (
                                        <button
                                            onClick={() => setActiveTab('lkk')}
                                            className={`flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-xs font-black uppercase tracking-wider transition-all whitespace-nowrap flex-1 ${
                                                activeTab === 'lkk' ? 'bg-blue-600 text-white shadow-md' : 'text-gray-500 hover:text-gray-800'
                                            }`}
                                        >
                                            <ClipboardList size={14} /> Laporan LKK
                                        </button>
                                    )}
                                </div>
                            )}

                            {paparButiranAm && (
                                <div className="space-y-5 animate-in fade-in duration-200">
                                    <div className="bg-white rounded-2xl border border-gray-200/80 shadow-sm overflow-hidden w-full">
                                        <div className="px-5 py-3.5 bg-[#001f4d] text-white">
                                            <h4 className="text-[10px] font-black uppercase tracking-wider">Butiran Tiket</h4>
                                        </div>

                                        <div className="p-6 space-y-6">
                                            <div className="space-y-3.5">
                                                <h5 className="text-[10px] uppercase tracking-wider text-blue-900 font-black mb-1 flex items-center gap-1"><User size={14} /> Butiran Pemohon</h5>
                                                <div className="text-xs font-bold text-gray-700 space-y-3.5 pl-1">
                                                    <div className="grid grid-cols-3"><span className="text-gray-400 font-medium">Nama Pemohon</span><span className="col-span-2 text-gray-800 pl-2">: &nbsp; {ticket.nama_pemohon}</span></div>
                                                    <div className="grid grid-cols-3"><span className="text-gray-400 font-medium">Email</span><span className="col-span-2 text-gray-600 font-medium pl-2">: &nbsp; {ticket.emel_pemohon}</span></div>
                                                    <div className="grid grid-cols-3"><span className="text-gray-400 font-medium">No. Telefon</span><span className="col-span-2 text-gray-800 pl-2">: &nbsp; {ticket.notel_pemohon || '012-3456789'}</span></div>
                                                    <div className="grid grid-cols-3"><span className="text-gray-400 font-medium">Agensi</span><span className="col-span-2 text-gray-800 uppercase pl-2">: &nbsp; {ticket.agensi}</span></div>
                                                    <div className="grid grid-cols-3"><span className="text-gray-400 font-medium">Lokasi</span><span className="col-span-2 text-gray-800 uppercase pl-2">: &nbsp; {ticket.lokasi || '-'}</span></div>
                                                    <div className="grid grid-cols-3"><span className="text-gray-400 font-medium">Daerah</span><span className="col-span-2 text-gray-800 uppercase pl-2">: &nbsp; {ticket.daerah || '-'}</span></div>
                                                </div>
                                            </div>

                                            <hr className="border-gray-100" />

                                            <div className="space-y-3.5">
                                                <h5 className="text-[10px] uppercase tracking-wider text-blue-900 font-black mb-1 flex items-center gap-1"><Ticket size={14} /> Butiran Spesifikasi</h5>
                                                <div className="text-xs font-bold text-gray-700 space-y-3.5 pl-1">
                                                    <div className="grid grid-cols-3"><span className="text-gray-400 font-medium">Perkara</span><span className="col-span-2 text-gray-800 pl-2">: &nbsp; {ticket.perkara}</span></div>
                                                    <div className="grid grid-cols-3"><span className="text-gray-400 font-medium">Kategori / Sub Kategori</span><span className="col-span-2 text-blue-950 uppercase pl-2">: &nbsp; {ticket.kategori || 'Belum Diklasifikasi'} <span className="text-gray-400 font-normal">({ticket.sub_kategori || 'Belum Ditetapkan'})</span></span></div>
                                                    {isAlreadyClassified && <div className="grid grid-cols-3"><span className="text-gray-400 font-medium">Tahap Keutamaan</span><span className="col-span-2 text-gray-800 uppercase pl-2">: &nbsp; {ticket.tahap_keutamaan || 'TIADA'}</span></div>}
                                                    <div className="grid grid-cols-3"><span className="text-gray-400 font-medium">Tarikh Terima</span><span className="col-span-2 text-gray-800 pl-2">: &nbsp; {ticket.tarikh_terima ? formatTarikh(ticket.tarikh_terima) : ''}</span></div>
                                                    <div className="grid grid-cols-3">
                                                        <span className="text-gray-400 font-medium">SLA</span>
                                                        <span className="col-span-2 text-gray-800 pl-2">: &nbsp; {ticket.tahap_keutamaan ? <span className="font-black text-blue-900 uppercase">{ticket.tahap_keutamaan === 'Tinggi' ? '3 Hari' : ticket.tahap_keutamaan === 'Sederhana' ? '7 Hari' : '14 Hari'}</span> : <span className="text-gray-400 italic font-medium">Belum Dijana </span>}</span>
                                                    </div>

                                                    {isMB && (
                                                        <div className="grid grid-cols-3">
                                                            <span className="text-gray-400 font-medium">Pegawai Pelaksana</span>
                                                            <span className="col-span-2 text-blue-950 font-black pl-2">: &nbsp; {ticket.petugas?.map(p => p.nama).join(', ') || 'Belum Dilantik'}</span>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>

                                            {perlukanModulRangkaian && (ticket.konsultasi_rangkaian?.tarikh_lawatan || ticket.transformasi_digital?.tarikh_lawatan) && (
                                                <>
                                                    <hr className="border-gray-100" />
                                                    <div className="space-y-3.5 animate-in fade-in duration-200">
                                                        <h5 className="text-[10px] uppercase tracking-wider text-blue-900 font-black mb-1 flex items-center gap-1">
                                                            <Briefcase size={14} /> Maklumat Penjadualan Lawatan Tapak
                                                        </h5>
                                                        <div className="text-xs font-bold text-gray-700 space-y-3.5 pl-1">
                                                            <div className="grid grid-cols-3">
                                                                <span className="text-gray-400 font-medium">Tarikh Lawatan</span>
                                                                <span className="col-span-2 text-gray-800 pl-2">: &nbsp; {formatTarikh(ticket.konsultasi_rangkaian?.tarikh_lawatan || ticket.transformasi_digital?.tarikh_lawatan)}</span>
                                                            </div>
                                                            <div className="grid grid-cols-3">
                                                                <span className="text-gray-400 font-medium">Masa Lawatan</span>
                                                                <span className="col-span-2 text-gray-800 pl-2">: &nbsp; {formatMasa(ticket.konsultasi_rangkaian?.masa_lawatan || ticket.transformasi_digital?.masa_lawatan)}</span>
                                                            </div>
                                                            <div className="grid grid-cols-3">
                                                                <span className="text-gray-400 font-medium">Pegawai Pelaksana</span>
                                                                <span className="col-span-2 text-blue-950 font-black pl-2">: &nbsp; {ticket.petugas && ticket.petugas.length > 0 ? ticket.petugas.map(p => p.nama).join(', ') : 'Belum Dilantik'}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </>
                                            )}
                                        </div>
                                    </div>

                                    <div className="p-5 bg-white border border-gray-200/80 rounded-2xl shadow-sm">
                                        <span className="block text-[10px] uppercase text-gray-400 font-black tracking-wider mb-2">Dokumen Lampiran</span>
                                        {ticket.lampiran ? (
                                            <div className="flex items-center justify-between p-3 bg-gray-50 border border-gray-200 rounded-xl group hover:bg-gray-100/70 transition-colors w-full">
                                                <div className="flex items-center gap-2 min-w-0">
                                                    <div className="p-2 bg-blue-50 text-blue-600 rounded-lg shrink-0"><FileText size={18} /></div>
                                                    <p className="text-xs font-black text-gray-700 truncate uppercase">{ticket.lampiran.split('/').pop()}</p>
                                                </div>
                                                <a href={`/storage/${ticket.lampiran}`} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-200 text-xs font-black text-gray-700 rounded-lg hover:bg-blue-50 hover:text-blue-700 transition-all shadow-sm cursor-pointer"><Eye size={13} /> Lihat Fail</a>
                                            </div>
                                        ) : <p className="text-xs text-gray-400 font-bold italic">Tiada lampiran fail dimuat naik.</p>}
                                    </div>

                                    {(statusFormat === 'menunggu klasifikasi' ? isManagerRole : tunjukBorangTindakanAm) && (
                                        <form onSubmit={handleSubmitAction} className="space-y-5 w-full">
                                            {errors.sistem && <div className="p-3 bg-red-50 border border-red-200 rounded-xl text-xs font-bold text-red-600">{errors.sistem}</div>}
                                            {/* Ralat klasifikasi */}
                                            {['kategori', 'sub_kategori', 'tahap_keutamaan'].map((field) => (
                                                errors[field] ? (
                                                    <p key={field} role="alert" className="text-xs font-bold text-red-600">
                                                        {errors[field]}
                                                    </p>
                                                ) : null
                                            ))}

                                            {statusFormat === 'menunggu klasifikasi' && (
                                                <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-sm space-y-4">
                                                    <div className="flex items-center gap-3 text-blue-900 border-b pb-3">
                                                        <div className="p-2 bg-blue-50 rounded-xl text-blue-700"><ClipboardList size={18} /></div>
                                                        <h4 className="font-black text-xs uppercase tracking-wider">Klasifikasi Permohonan</h4>
                                                    </div>
                                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                                        <div className="space-y-2">
                                                            <label className="text-[10px] font-black uppercase text-gray-400 block">Kategori <span className="text-red-500 font-bold ml-1">*</span></label>
                                                            <select value={data.kategori} onChange={e => setData(prev => ({ ...prev, kategori: e.target.value, sub_kategori: '' }))} className="w-full h-11 rounded-xl text-xs bg-white border-gray-200 font-bold text-gray-700" required>
                                                                <option value="">Pilih Kategori</option>
                                                                <option value="Meja Bantuan">Meja Bantuan</option>
                                                                <option value="Konsultasi Rangkaian">Konsultasi Rangkaian</option>
                                                                <option value="Transformasi Digital">Transformasi Digital</option>
                                                            </select>
                                                        </div>
                                                        <div className="space-y-2">
                                                            <label className="text-[10px] font-black uppercase text-gray-400 block">Sub-Kategori <span className="text-red-500 font-bold ml-1">*</span></label>
                                                            <select value={data.sub_kategori} onChange={e => setData('sub_kategori', e.target.value)} className="w-full h-11 rounded-xl text-xs bg-white border-gray-200 font-bold text-gray-700" required disabled={!data.kategori}>
                                                                <option value="">Pilih Sub-Kategori</option>
                                                                {senaraiSubSemasa.map((sub, idx) => <option key={idx} value={sub}>{sub}</option>)}
                                                            </select>
                                                        </div>
                                                        <div className="space-y-2">
                                                            <label className="text-[10px] font-black uppercase text-gray-400 block">Tahap Keutamaan <span className="text-red-500 font-bold ml-1">*</span></label>
                                                            <select value={data.tahap_keutamaan} onChange={e => setData('tahap_keutamaan', e.target.value)} className="w-full h-11 rounded-xl text-xs bg-white border-gray-200 font-bold text-gray-700" required>
                                                                <option value="">Pilih Keutamaan</option>
                                                                <option value="Rendah">Rendah</option>
                                                                <option value="Sederhana">Sederhana</option>
                                                                <option value="Tinggi">Tinggi</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                            )}





                                            {isWorkflowV2 &&
                                            statusFormat === 'menunggu semakan' &&
                                            isKUTD &&
                                            isKR && (
                                                <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-sm space-y-5 animate-in fade-in duration-200">
                                                    <div className="flex items-center gap-3 text-blue-900 border-b pb-3">
                                                        <div className="p-2 bg-blue-50 rounded-xl text-blue-700">
                                                            <Users size={18} />
                                                        </div>

                                                        <h4 className="font-black text-xs uppercase tracking-wider">
                                                            Semakan, Jadual Lawatan dan Pelantikan Juruteknik
                                                        </h4>
                                                    </div>

                                                    {errors.sistem && (
                                                        <p
                                                            role="alert"
                                                            className="text-xs font-bold text-red-600"
                                                        >
                                                            {errors.sistem}
                                                        </p>
                                                    )}

                                                    <div className="space-y-2">
                                                        <label className="block text-[10px] font-black text-gray-500 uppercase tracking-wide">
                                                            Pendahuluan
                                                            <span className="text-red-500 ml-1">*</span>
                                                        </label>

                                                        <textarea
                                                            value={data.pendahuluan}
                                                            onChange={e => setData('pendahuluan', e.target.value)}
                                                            maxLength={10000}
                                                            rows={5}
                                                            placeholder="Masukkan pendahuluan atau latar belakang permohonan Rangkaian..."
                                                            className="w-full p-3 bg-white border border-gray-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-blue-500/20 focus:outline-none"
                                                            required
                                                        />

                                                        {errors.pendahuluan && (
                                                            <p role="alert" className="text-xs font-bold text-red-600">
                                                                {errors.pendahuluan}
                                                            </p>
                                                        )}
                                                    </div>

                                                    <div className="space-y-3">
                                                        <label className="block text-[10px] font-black text-gray-500 uppercase tracking-wide">
                                                            Objektif
                                                            <span className="text-red-500 ml-1">*</span>
                                                        </label>

                                                        {errors.objektif && (
                                                            <p role="alert" className="text-xs font-bold text-red-600">
                                                                {errors.objektif}
                                                            </p>
                                                        )}

                                                        {(Array.isArray(data.objektif)
                                                            ? data.objektif
                                                            : [{ teks: '' }]
                                                        ).map((objective, index) => (
                                                            <div key={index} className="flex items-start gap-2">
                                                                <span className="h-11 min-w-8 inline-flex items-center justify-center bg-slate-100 border border-slate-200 rounded-xl text-xs font-black text-slate-600">
                                                                    {index + 1}
                                                                </span>

                                                                <div className="flex-1 space-y-1">
                                                                    <textarea
                                                                        value={objective?.teks || ''}
                                                                        onChange={e => handleUpdateNetworkObjective(index, e.target.value)}
                                                                        maxLength={2000}
                                                                        rows={2}
                                                                        placeholder="Nyatakan objektif lawatan atau kajian..."
                                                                        className="w-full p-3 bg-white border border-gray-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-blue-500/20 focus:outline-none"
                                                                        required
                                                                    />

                                                                    {errors[`objektif.${index}.teks`] && (
                                                                        <p role="alert" className="text-xs font-bold text-red-600">
                                                                            {errors[`objektif.${index}.teks`]}
                                                                        </p>
                                                                    )}
                                                                </div>

                                                                {(Array.isArray(data.objektif)
                                                                    ? data.objektif.length
                                                                    : 1
                                                                ) > 1 && (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => handleRemoveNetworkObjective(index)}
                                                                        className="text-red-500 hover:text-red-700 p-2.5 hover:bg-red-50 rounded-xl border border-red-100 transition-colors"
                                                                        title="Padam Objektif"
                                                                    >
                                                                        <Trash2 size={16} />
                                                                    </button>
                                                                )}
                                                            </div>
                                                        ))}

                                                        <button
                                                            type="button"
                                                            onClick={handleAddNetworkObjective}
                                                            disabled={
                                                                Array.isArray(data.objektif)
                                                                && data.objektif.length >= 20
                                                            }
                                                            className="inline-flex items-center gap-1.5 px-3.5 py-2 text-blue-600 bg-blue-50 hover:bg-blue-100 disabled:opacity-50 border border-blue-200/60 rounded-xl transition-colors text-[10px] uppercase font-black shadow-sm"
                                                        >
                                                            <Plus size={13} />
                                                            Tambah Objektif
                                                        </button>
                                                    </div>
                                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                        <div className="space-y-2">
                                                            <label className="block text-[10px] font-black text-gray-500 uppercase tracking-wide">
                                                                Tarikh Lawatan
                                                                <span className="text-red-500 ml-1">*</span>
                                                            </label>

                                                            <input
                                                                type="date"
                                                                value={data.tarikh_lawatan}
                                                                min={new Date().toISOString().split('T')[0]}
                                                                onChange={e => setData('tarikh_lawatan', e.target.value)}
                                                                className="w-full h-11 text-xs font-bold bg-white border border-gray-200 rounded-xl px-4 focus:outline-none focus:border-blue-500 shadow-sm"
                                                                required
                                                            />

                                                            {errors.tarikh_lawatan && (
                                                                <p role="alert" className="text-xs font-bold text-red-600">
                                                                    {errors.tarikh_lawatan}
                                                                </p>
                                                            )}
                                                        </div>

                                                        <div className="space-y-2">
                                                            <label className="block text-[10px] font-black text-gray-500 uppercase tracking-wide">
                                                                Masa Lawatan
                                                                <span className="text-red-500 ml-1">*</span>
                                                            </label>

                                                            <input
                                                                type="time"
                                                                value={data.masa_lawatan}
                                                                onChange={e => setData('masa_lawatan', e.target.value)}
                                                                className="w-full h-11 text-xs font-bold bg-white border border-gray-200 rounded-xl px-4 focus:outline-none focus:border-blue-500 shadow-sm"
                                                                required
                                                            />

                                                            {errors.masa_lawatan && (
                                                                <p role="alert" className="text-xs font-bold text-red-600">
                                                                    {errors.masa_lawatan}
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>

                                                    <div className="space-y-2">
                                                        <label className="block text-[10px] font-black text-gray-500 uppercase tracking-wide">
                                                            Catatan Lawatan
                                                        </label>

                                                        <textarea
                                                            value={data.catatan_lawatan}
                                                            onChange={e => setData('catatan_lawatan', e.target.value)}
                                                            maxLength={2000}
                                                            rows={3}
                                                            placeholder="Masukkan catatan atau arahan lawatan sekiranya perlu..."
                                                            className="w-full p-3 bg-white border border-gray-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-blue-500/20 focus:outline-none"
                                                        />

                                                        {errors.catatan_lawatan && (
                                                            <p role="alert" className="text-xs font-bold text-red-600">
                                                                {errors.catatan_lawatan}
                                                            </p>
                                                        )}
                                                    </div>

                                                    <div className="space-y-3">
                                                        <label className="block text-[10px] font-black text-gray-500 uppercase tracking-wide">
                                                            Juruteknik
                                                            <span className="text-red-500 ml-1">*</span>
                                                        </label>

                                                        {errors.senarai_pic_ic && (
                                                            <p role="alert" className="text-xs font-bold text-red-600">
                                                                {errors.senarai_pic_ic}
                                                            </p>
                                                        )}

                                                        {(Array.isArray(data.senarai_pic_ic) && data.senarai_pic_ic.length > 0
                                                            ? data.senarai_pic_ic
                                                            : ['']
                                                        ).map((selectedIc, index) => (
                                                            <div key={index} className="flex items-center gap-2">
                                                                <select
                                                                    value={selectedIc}
                                                                    onChange={e => handleUpdatePic(index, e.target.value)}
                                                                    className="w-full h-11 text-xs font-bold bg-white border border-gray-200 rounded-xl px-4 focus:outline-none focus:border-blue-500 shadow-sm cursor-pointer uppercase text-gray-700"
                                                                    required
                                                                >
                                                                    <option value="">
                                                                        Pilih Juruteknik
                                                                    </option>

                                                                    {activeTechnicians.map(pegawai => {
                                                                        const selectedTechnicians = Array.isArray(data.senarai_pic_ic)
                                                                            ? data.senarai_pic_ic
                                                                            : [];

                                                                        const alreadySelected =
                                                                            selectedTechnicians.includes(pegawai.no_ic)
                                                                            && pegawai.no_ic !== selectedIc;

                                                                        return (
                                                                            <option
                                                                                key={pegawai.no_ic}
                                                                                value={pegawai.no_ic}
                                                                                disabled={alreadySelected}
                                                                            >
                                                                                {pegawai.nama}
                                                                                {alreadySelected ? ' (Telah Dipilih)' : ''}
                                                                            </option>
                                                                        );
                                                                    })}
                                                                </select>

                                                                {(Array.isArray(data.senarai_pic_ic)
                                                                    ? data.senarai_pic_ic.length
                                                                    : 1
                                                                ) > 1 && (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => handleRemovePic(index)}
                                                                        className="text-red-500 hover:text-red-700 p-2.5 hover:bg-red-50 rounded-xl border border-red-100 transition-colors"
                                                                        title="Padam Juruteknik"
                                                                    >
                                                                        <Trash2 size={16} />
                                                                    </button>
                                                                )}
                                                            </div>
                                                        ))}

                                                        <button
                                                            type="button"
                                                            onClick={handleAddPic}
                                                            className="inline-flex items-center gap-1.5 px-3.5 py-2 text-blue-600 bg-blue-50 hover:bg-blue-100 border border-blue-200/60 rounded-xl transition-colors text-[10px] uppercase font-black shadow-sm"
                                                        >
                                                            <Plus size={13} />
                                                            Tambah Juruteknik
                                                        </button>
                                                    </div>
                                                </div>
                                            )}
                                            {isWorkflowV2 &&
                                            statusFormat === 'menunggu semakan' &&
                                            isKUTD &&
                                            isMB &&
                                            !isPeminjaman && (
                                                <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-sm space-y-5 animate-in fade-in duration-200">
                                                    <div className="flex items-center gap-3 text-blue-900 border-b pb-3">
                                                        <div className="p-2 bg-blue-50 rounded-xl text-blue-700">
                                                            <Users size={18} />
                                                        </div>

                                                        <h4 className="font-black text-xs uppercase tracking-wider">
                                                            Semakan dan Pelantikan Petugas
                                                        </h4>
                                                    </div>

                                                    {errors.pic_ic && (
                                                        <p
                                                            role="alert"
                                                            className="text-xs font-bold text-red-600"
                                                        >
                                                            {errors.pic_ic}
                                                        </p>
                                                    )}

                                                    <div className="space-y-2">
                                                        <label className="block text-[10px] font-black text-gray-500 uppercase tracking-wide">
                                                            Juruteknik/Petugas
                                                            <span className="text-red-500 ml-1">*</span>
                                                        </label>

                                                        <select
                                                            value={data.no_ic}
                                                            onChange={e => setData('no_ic', e.target.value)}
                                                            className="w-full h-11 text-xs font-bold bg-white border border-gray-200 rounded-xl px-4 focus:outline-none focus:border-blue-500 shadow-sm cursor-pointer uppercase text-gray-700"
                                                            required
                                                        >
                                                            <option value="">
                                                                Pilih Juruteknik
                                                            </option>

                                                            {activeTechnicians.map(pegawai => (
                                                                <option
                                                                    key={pegawai.no_ic}
                                                                    value={pegawai.no_ic}
                                                                >
                                                                    {pegawai.nama}
                                                                </option>
                                                            ))}
                                                        </select>
                                                    </div>
                                                </div>
                                            )}

                                            {isKUPP &&
                                            (statusFormat === 'menunggu semakan dokumen') &&
                                            data.sub_kategori !== 'Peminjaman Peralatan ICT' &&
                                            !isTD && (
                                                <div className="space-y-4 w-full">
                                                    <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-sm space-y-4">
                                                        <h4 className="font-black text-sm text-gray-800">1. Lantik Pegawai Pelaksana? <span className="text-red-500 font-bold ml-1">*</span></h4>
                                                        <div className="flex items-center gap-6 text-xs font-bold text-gray-700">
                                                            <label className="flex items-center gap-2"><input type="radio" name="q1" value="Ya" checked={q1Lantik === 'Ya'} onChange={e => setQ1Lantik(e.target.value)} /><span>Ya</span></label>
                                                            <label className="flex items-center gap-2"><input type="radio" name="q1" value="Tidak" checked={q1Lantik === 'Tidak'} onChange={e => { setQ1Lantik(e.target.value); setData('no_ic', ''); }} /><span>Tidak</span></label>
                                                        </div>

                                                        {q1Lantik === 'Ya' && (
                                                            <div className="space-y-4">
                                                                <select value={data.no_ic} onChange={e => setData('no_ic', e.target.value)} className="w-full h-11 rounded-xl text-xs border-gray-200 font-bold" required={q1Lantik === 'Ya' && q2Serah === 'Tidak'}>
                                                                    <option value="">Pilih Pegawai Pelaksana</option>
                                                                    {senaraiPengguna.map(p => <option key={p.no_ic} value={p.no_ic}>{p.nama}</option>)}
                                                                </select>
                                                            </div>
                                                        )}
                                                    </div>

                                                    <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-sm space-y-4">
                                                        <h4 className="font-black text-sm text-gray-800">2. Serahan Tugas ke UTD? <span className="text-red-500">*</span></h4>
                                                        <div className="flex items-center gap-6 text-xs font-bold text-gray-700">
                                                            <label className="flex items-center gap-2"><input type="radio" name="q2" value="Ya" checked={q2Serah === 'Ya'} onChange={e => setQ2Serah(e.target.value)} /><span>Ya</span></label>
                                                            <label className="flex items-center gap-2"><input type="radio" name="q2" value="Tidak" checked={q2Serah === 'Tidak'} onChange={e => setQ2Serah(e.target.value)} /><span>Tidak</span></label>
                                                        </div>
                                                    </div>
                                                </div>
                                            )}

                                            {statusFormat === 'tugasan utd' && isKUTD && !isTD && (
                                                <div className="bg-white p-6 rounded-2xl border border-gray-200/80 shadow-sm space-y-5 animate-in fade-in duration-200">
                                                    <h4 className="font-black text-blue-900 text-xs border-b pb-2 uppercase flex items-center gap-2">
                                                        Pengagihan Tugasan
                                                    </h4>

                                                    {isTechCategory && (
                                                        <div className="bg-gray-50/50 p-4 rounded-xl border border-gray-100 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                            <div className="space-y-1.5">
                                                                <label className="block text-[10px] font-black text-gray-500 uppercase tracking-wide">
                                                                    Tarikh Lawatan <span className="text-red-500 font-bold ml-1">*</span>
                                                                </label>
                                                                <input
                                                                    type="date"
                                                                    value={data.tarikh_lawatan}
                                                                    onChange={e => setData('tarikh_lawatan', e.target.value)}
                                                                    className="w-full text-xs font-bold bg-white border border-gray-200 rounded-xl h-11 px-4 focus:ring-blue-500"
                                                                    required
                                                                />
                                                            </div>
                                                            <div className="space-y-1.5">
                                                                <label className="block text-[10px] font-black text-gray-500 uppercase tracking-wide">
                                                                    Masa Lawatan <span className="text-red-500 font-bold ml-1">*</span>
                                                                </label>
                                                                <input
                                                                    type="time"
                                                                    value={data.masa_lawatan}
                                                                    onChange={e => setData('masa_lawatan', e.target.value)}
                                                                    className="w-full text-xs font-bold bg-white border border-gray-200 rounded-xl h-11 px-4 focus:ring-blue-500"
                                                                    required
                                                                />
                                                            </div>
                                                        </div>
                                                    )}

                                                    <div className="space-y-3 pt-2">
                                                        <label className="block text-[10px] font-black text-gray-500 uppercase tracking-wide">
                                                            Pegawai Pelaksana <span className="text-red-500 font-bold ml-1">*</span>
                                                        </label>

                                                        {isMB ? (
                                                            <select
                                                                value={data.senarai_pic_ic[0] || ''}
                                                                onChange={e => setData('senarai_pic_ic', e.target.value ? [e.target.value] : [])}
                                                                className="w-full h-11 text-xs font-bold bg-white border border-gray-200 rounded-xl px-4 focus:outline-none focus:border-blue-500 shadow-sm cursor-pointer transition-all uppercase text-gray-700"
                                                                required
                                                            >
                                                                <option value="">Pilih Pegawai Pelaksana</option>
                                                                {filteredUsers.map(p => (
                                                                    <option key={p.no_ic} value={p.no_ic}>
                                                                        {p.nama}
                                                                    </option>
                                                                ))}
                                                            </select>
                                                        ) : (
                                                            <div className="space-y-2">
                                                                {(Array.isArray(data.senarai_pic_ic) && data.senarai_pic_ic.length > 0 ? data.senarai_pic_ic : ['']).map((selectedIc, idx) => (
                                                                    <div key={idx} className="flex items-center gap-2">
                                                                        <select
                                                                            value={selectedIc}
                                                                            onChange={e => handleUpdatePic(idx, e.target.value)}
                                                                            className="w-full h-11 text-xs font-bold bg-white border border-gray-200 rounded-xl px-4 focus:outline-none focus:border-blue-500 shadow-sm cursor-pointer transition-all uppercase text-gray-700"
                                                                            required
                                                                        >
                                                                            <option value="">Pilih Pegawai Teknikal</option>
                                                                            {senaraiPengguna.map((pegawai) => {
                                                                                const senaraiPilihan = Array.isArray(data.senarai_pic_ic) ? data.senarai_pic_ic : [];
                                                                                const sudahDipilih = senaraiPilihan.includes(pegawai.no_ic) && pegawai.no_ic !== selectedIc;
                                                                                return (
                                                                                    <option key={pegawai.no_ic} value={pegawai.no_ic} disabled={sudahDipilih}>
                                                                                        {pegawai.nama} {sudahDipilih ? '(Telah Dipilih)' : ''}
                                                                                    </option>
                                                                                );
                                                                            })}
                                                                        </select>

                                                                        {(Array.isArray(data.senarai_pic_ic) ? data.senarai_pic_ic.length : 1) > 1 && (
                                                                            <button
                                                                                type="button"
                                                                                onClick={() => handleRemovePic(idx)}
                                                                                className="text-red-500 hover:text-red-700 p-2.5 hover:bg-red-50 rounded-xl border border-red-100 transition-colors"
                                                                                title="Padam Pegawai"
                                                                            >
                                                                                <Trash2 size={16} />
                                                                            </button>
                                                                        )}
                                                                    </div>
                                                                ))}

                                                                <button
                                                                    type="button"
                                                                    onClick={handleAddPic}
                                                                    className="inline-flex items-center gap-1.5 mt-2 px-3.5 py-2 text-blue-600 bg-blue-50 hover:bg-blue-100 border border-blue-200/60 rounded-xl transition-colors text-[10px] uppercase font-black shadow-sm"
                                                                >
                                                                    <Plus size={13} /> Tambah Pegawai Pelaksana
                                                                </button>
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            )}

                                            <div className="flex justify-end items-center pt-3 border-t border-gray-100">
                                                <button type="submit" disabled={processing} className="inline-flex items-center gap-2 px-5 py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 text-white font-black text-xs uppercase tracking-wider rounded-xl shadow-md w-full sm:w-auto justify-center cursor-pointer">
                                                    <Send size={13} /> {processing ? 'Memproses...' : 'KEMASKINI TIKET'}
                                                </button>
                                            </div>
                                        </form>
                                    )}

                                    {isDalamTindakan && isCurrentUserPIC && isMB && !dataKelulusan && (
                                        <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                                            <div className="px-5 py-3.5 bg-[#002b66] text-white"><h4 className="text-[10px] font-black uppercase">Catatan Penutupan <span className="text-red-500 font-bold ml-1">*</span></h4></div>
                                            <form onSubmit={handlePicSubmit} className="p-5 space-y-4 text-xs font-bold">
                                                <textarea
                                                    value={data.catatan_penutupan}
                                                    onChange={e => setData('catatan_penutupan', e.target.value)}
                                                    placeholder="Sila nyatakan catatan tindakan..."
                                                    minLength={isWorkflowV2 ? 10 : undefined}
                                                    maxLength={2000}
                                                    className="w-full text-xs border rounded-xl p-3 min-h-[120px]"
                                                    required
                                                />
                                                <div className="flex justify-end">
                                                    <button
                                                        type="submit"
                                                        disabled={processing}
                                                        className="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 text-white font-black text-xs uppercase rounded-xl shadow-sm transition-all active:scale-95 cursor-pointer"
                                                    >
                                                        <Send size={14} className="shrink-0" />
                                                        <span>
                                                            {processing ? 'Memproses...' : 'Hantar untuk Pengesahan'}
                                                        </span>
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    )}

                                    {statusFormat === 'menunggu pengesahan' && isMB && !dataKelulusan && (
                                        <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden animate-in fade-in duration-200">
                                            <div className="px-5 py-3.5 bg-[#002b66] text-white">
                                                <h4 className="text-[10px] font-black uppercase tracking-wider">Laporan Aduan</h4>
                                            </div>
                                            <div className="p-5 space-y-4 text-xs font-bold">
                                                <div className="p-3 bg-gray-50 border border-gray-100 rounded-xl font-semibold text-gray-700 whitespace-pre-wrap leading-relaxed">
                                                    {ticket.catatan_penutupan || 'Tiada catatan penutupan.'}
                                                </div>

                                                {isWorkflowV2 && isKUTD && (
                                                    <div className="space-y-2">
                                                        <label className="block text-[10px] font-black text-gray-500 uppercase tracking-wide">
                                                            Ulasan Pengesahan
                                                        </label>

                                                        <textarea
                                                            value={data.ulasan}
                                                            onChange={e => setData('ulasan', e.target.value)}
                                                            placeholder="Masukkan ulasan sekiranya perlu..."
                                                            maxLength={2000}
                                                            rows={3}
                                                            className="w-full p-3 bg-white border border-gray-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none"
                                                        />

                                                        {errors.ulasan && (
                                                            <p
                                                                role="alert"
                                                                className="text-xs font-bold text-red-600"
                                                            >
                                                                {errors.ulasan}
                                                            </p>
                                                        )}
                                                    </div>
                                                )}

                                                {(() => {
                                                    const wasHandedOverToUTD = auditTrail.some(log =>
                                                        String(log.aktiviti).toLowerCase().includes('dihantar ke utd')
                                                    );

                                                    const isAuthorizedManager = isWorkflowV2
                                                        ? isKUTD
                                                        : (wasHandedOverToUTD ? isKUTD : isKUPP);

                                                    if (!isAuthorizedManager) return null;

                                                    return (
                                                        <div className="flex justify-end pt-3 border-t border-gray-100">
                                                            <button
                                                                type="button"
                                                                onClick={() => {
                                                                    if (confirm("Adakah anda pasti untuk mengesahkan laporan dan menutup tiket?")) {
                                                                        const confirmationRoute = isWorkflowV2
                                                                            ? route(
                                                                                'tickets.workflow.confirmHelpdesk',
                                                                                ticket.id_tiket
                                                                            )
                                                                            : route(
                                                                                'tickets.sahkanTutupMB',
                                                                                ticket.id_tiket
                                                                            );

                                                                        const confirmationData = isWorkflowV2
                                                                            ? { ulasan: data.ulasan }
                                                                            : {};

                                                                        router.post(
                                                                            confirmationRoute,
                                                                            confirmationData,
                                                                            {
                                                                                preserveScroll: true,
                                                                                onSuccess: () => alert(
                                                                                    "Tiket telah berjaya ditutup!"
                                                                                ),
                                                                                onError: (confirmationErrors) => {
                                                                                    Object.values(
                                                                                        confirmationErrors
                                                                                    ).forEach(
                                                                                        message => alert(message)
                                                                                    );
                                                                                }
                                                                            }
                                                                        );
                                                                    }
                                                                }}
                                                                className="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs uppercase rounded-xl shadow-sm transition-all active:scale-95 cursor-pointer"
                                                            >
                                                                <CheckCircle2 size={14} className="shrink-0" />
                                                                <span>Sahkan &amp; Tutup Tiket</span>
                                                            </button>
                                                        </div>
                                                    );
                                                })()}
                                            </div>
                                        </div>
                                    )}

                                    {isSelesai && isMB && !dataKelulusan && (
                                        <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden animate-in fade-in duration-200">
                                            <div className="px-5 py-3.5 bg-[#002b66] text-white"><h4 className="text-[10px] font-black uppercase tracking-wider">Laporan</h4></div>
                                            <div className="p-5 space-y-2 text-xs font-bold">
                                                <div className="p-3 bg-gray-50 border border-gray-100 rounded-xl font-semibold text-gray-700 whitespace-pre-wrap leading-relaxed shadow-inner">{ticket.catatan_penutupan || 'Tiada catatan.'}</div>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}

                            {activeTab === 'peminjaman' && paparTabPeminjaman && (
                                <div className="space-y-5 animate-in fade-in duration-200 w-full">
                                    <BorangPeminjamanPeralatan
                                        ticket={ticket}
                                        senaraiAset={senaraiAset}
                                        senaraiPic={senaraiPengguna}
                                        auth={auth}
                                        dataKelulusan={dataKelulusan}
                                    />
                                </div>
                            )}

                            {activeTab === 'tapak' && paparTabTapak && !isTD && !isMB && (
                                <div className="space-y-5 animate-in fade-in duration-200 w-full">
                                    <FormMaklumatTapak
                                        ticket={ticket}
                                        auth={auth}
                                        senaraiPengguna={senaraiPengguna}
                                    />

                                    {isWorkflowV2 &&
                                    statusFormat === 'menunggu semakan laporan' &&
                                    isKUTD && (
                                        <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden animate-in fade-in duration-200">
                                            <div className="px-5 py-3.5 bg-[#002b66] text-white">
                                                <h4 className="text-[10px] font-black uppercase tracking-wider">
                                                    Semakan Laporan Tapak
                                                </h4>
                                            </div>

                                            <div className="p-5 space-y-4">
                                                <div className="p-4 bg-blue-50 border border-blue-100 rounded-xl text-xs font-semibold text-blue-900 leading-relaxed">
                                                    Semak semua maklumat tapak di atas sebelum mengesahkan laporan dan meneruskan penyediaan LKK.
                                                </div>

                                                <div className="flex justify-end pt-3 border-t border-gray-100">
                                                    <button
                                                        type="button"
                                                        disabled={networkReviewProcessing}
                                                        onClick={handleNetworkSiteReview}
                                                        className="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 text-white font-black text-xs uppercase rounded-xl shadow-sm transition-all active:scale-95 cursor-pointer"
                                                    >
                                                        <CheckCircle2 size={14} />
                                                        <span>
                                                            {networkReviewProcessing
                                                                ? 'Memproses...'
                                                                : 'SAHKAN'}
                                                        </span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}

                            {activeTab === 'lkk' && paparTabLaporan && (
                                <div className="animate-in fade-in duration-200">
                                    {isAuthorizedToEdit ? (
                                        isTD ? (
                                            isPembekalanICT ? (
                                                <BorangLKKPembekalanICT ticket={ticket} senaraiPegawai={senaraiPengguna} auth={auth} />
                                            ) : (
                                                <BorangLKKTransformasi ticket={ticket} senaraiPegawai={senaraiPengguna} auth={auth} />
                                            )
                                        ) : (
                                            <BorangLKKRangkaian ticket={ticket} senaraiPegawai={senaraiPengguna} auth={auth} />
                                        )
                                    ) : (
                                        <PaparanRingkasanLKK
                                            ticket={ticket}
                                            auth={auth}
                                            senaraiPegawai={senaraiPengguna}
                                        />
                                    )}
                                </div>
                            )}
                        </div>

                        <div className="lg:col-span-1 space-y-4 w-full sticky top-6">
                            <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                                <div className="flex items-center gap-2 p-4 border-b border-gray-50 bg-white">
                                    <Clock size={16} className="text-blue-800" />
                                    <h3 className="text-xs font-black text-blue-900 uppercase tracking-wider">Audit Trail</h3>
                                </div>

                                <div className="p-4">
                                    <div className="relative">
                                        <div className="absolute top-2 bottom-2 left-[15px] w-px bg-gray-200"></div>

                                        {(() => {
                                            const getTimelineStyle = (aktiviti) => {
                                                const act = String(aktiviti).toLowerCase();
                                                if (act.includes('dicipta') || act.includes('daftar')) return { icon: <FileText size={14} />, color: 'text-emerald-500', border: 'border-emerald-200' };
                                                if (act.includes('kategori') || act.includes('klasifikasi')) return { icon: <ListTree size={14} />, color: 'text-orange-400', border: 'border-orange-200' };
                                                if (act.includes('disemak') && !act.includes('divalidasi') && !act.includes('laporan')) return { icon: <ClipboardList size={14} />, color: 'text-purple-500', border: 'border-purple-200' };
                                                if (act.includes('ditugaskan') || act.includes('pegawai') || act.includes('dilantik') || act.includes('dijadual')) return { icon: <Users size={14} />, color: 'text-blue-500', border: 'border-blue-200' };
                                                if (act.includes('draf') || act.includes('laporan') || act.includes('lkk')) return { icon: <ClipboardList size={14} />, color: 'text-blue-600', border: 'border-blue-200' };
                                                if (act.includes('disahkan') || act.includes('divalidasi') || act.includes('ditutup') || act.includes('selesai')) return { icon: <CheckCircle2 size={14} />, color: 'text-emerald-600', border: 'border-emerald-200' };
                                                return { icon: <FileText size={14} />, color: 'text-slate-500', border: 'border-slate-200' };
                                            };

                                            const formatDateTime = (dateString) => {
                                                if (!dateString) return { date: '-', time: '-' };
                                                const d = new Date(dateString);
                                                const day = String(d.getDate()).padStart(2, '0');
                                                const month = String(d.getMonth() + 1).padStart(2, '0');
                                                const year = d.getFullYear();
                                                let hours = d.getHours();
                                                const minutes = String(d.getMinutes()).padStart(2, '0');
                                                const ampm = hours >= 12 ? 'PM' : 'AM';
                                                hours = hours % 12;
                                                hours = hours ? hours : 12;
                                                const strHours = String(hours).padStart(2, '0');
                                                return { date: `${day}-${month}-${year}`, time: `${strHours}:${minutes} ${ampm}` };
                                            };

                                            const formatAktiviti = (akt) => {
                                                const K = String(akt || '').toLowerCase().trim();
                                                if (K === 'tiket dicipta') return 'Tiket dicipta';
                                                if (K === 'klasifikasi ditetapkan' || K === 'kategori tiket ditetapkan') return 'Kategori tiket ditetapkan';
                                                if (K === 'dokumen disemak') return 'Dokumen disemak';
                                                if (K === 'petugas dilantik' || K === 'petugas ditugaskan' || K === 'pegawai dilantik') return 'Petugas ditugaskan';
                                                if (K === 'laporan dihantar' || K === 'lkk dihantar') return 'Laporan dihantar';
                                                if (K === 'laporan disemak & disahkan' || K === 'laporan disemak dan disahkan' || K === 'laporan lkk diluluskan' || K === 'lkk disemak & disahkan') return 'Laporan disemak dan disahkan';
                                                if (K === 'tiket selesai' || K === 'tiket ditutup' || K === 'tiket diluluskan & ditutup') return 'Tiket ditutup';
                                                return akt;
                                            };

                                            const rawRekod = typeof auditTrail !== 'undefined' ? auditTrail : (ticket?.audit_trail || ticket?.jejak_tiket || []);

                                            const rekodJejak = rawRekod.filter((log, idx, self) =>
                                                idx === self.findIndex((t) => (
                                                    String(t.aktiviti).toLowerCase().trim() === String(log.aktiviti).toLowerCase().trim() &&
                                                    String(t.nama_pelaku).toLowerCase().trim() === String(log.nama_pelaku).toLowerCase().trim()
                                                ))
                                            );

                                            if (!rekodJejak || rekodJejak.length === 0) {
                                                return (
                                                    <div className="text-center py-6 text-xs text-gray-400 italic font-medium">
                                                        Tiada rekod jejak tiket ditemui.
                                                    </div>
                                                );
                                            }

                                            return rekodJejak.map((log, index) => {
                                                const style = getTimelineStyle(log.aktiviti);
                                                const dt = formatDateTime(log.created_at);
                                                const tajukAktiviti = formatAktiviti(log.aktiviti);

                                                return (
                                                    <div key={log.id || index} className="relative flex items-start mb-5 last:mb-0 gap-3 group">
                                                        <div className={`relative z-10 shrink-0 w-8 h-8 rounded-full border flex items-center justify-center bg-white transition-colors ${style.border} ${style.color}`}>
                                                            {style.icon}
                                                        </div>

                                                        <div className="flex-1 min-w-0 pt-0.5">
                                                            <h4 className="text-[11px] font-bold text-slate-800 leading-tight break-words">
                                                                {tajukAktiviti}
                                                            </h4>
                                                            {String(tajukAktiviti).toLowerCase() !== 'tiket ditutup' && (
                                                                <p className="text-[10px] font-black uppercase text-slate-500 mt-0.5">
                                                                    Oleh <span className="text-slate-700 font-black">{log.nama_pelaku}</span>
                                                                </p>
                                                            )}
                                                            <p className="text-[9px] font-medium text-slate-400 mt-1 flex items-center gap-1.5 whitespace-nowrap">
                                                                <span>{dt.date}</span>
                                                                <span className="text-gray-300">|</span>
                                                                <span>{dt.time}</span>
                                                            </p>
                                                            {log.pesanan && !log.pesanan.toLowerCase().includes('oleh') && !log.pesanan.toLowerCase().includes('selesai') && !log.pesanan.toLowerCase().includes('tiket ditutup') && (
                                                                <p className="text-[10px] font-medium text-slate-500 mt-1 leading-snug">
                                                                    {log.pesanan.toLowerCase().includes('pegawai pelaksana:') ? (
                                                                        <>
                                                                            Pegawai pelaksana: <strong className="text-gray-900 font-black uppercase">{log.pesanan.split(/pegawai pelaksana:/i)[1]?.trim()}</strong>
                                                                        </>
                                                                    ) : (
                                                                        log.pesanan
                                                                    )}
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                );
                                            });
                                        })()}
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </main>
            </div>
        </div>
    );
}
