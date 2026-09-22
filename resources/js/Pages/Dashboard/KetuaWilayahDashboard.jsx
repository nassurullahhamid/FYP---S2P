import React from 'react';
import { Head, Link } from '@inertiajs/react';
import Sidebar from '../../Components/Sidebar';
import Topbar from '../../Components/Topbar';

import {
    FileText,
    Clock,
    CheckCircle2,
    AlertCircle,
    Layers,
    ListTree,
    Laptop,
    ChevronRight
} from 'lucide-react';

export default function KetuaWilayahDashboard({ auth, kpiUmum, statsKategori, recentTickets }) {

    const user = auth?.user;

    const cards = [
        {
            title: 'Menunggu Validasi',
            count: kpiUmum?.menunggu_validasi || 0,
            icon: <Clock className="text-rose-600" />,
            bg: 'bg-rose-50',
            border: 'border-rose-100',
            url: route('tickets.index', { status: 'Menunggu Validasi' })
        },
        {
            title: 'Jumlah Tiket',
            count: kpiUmum?.jumlah_tiket || 0,
            icon: <FileText className="text-yellow-600" />,
            bg: 'bg-yellow-50',
            border: 'border-yellow-100',
            url: route('tickets.index')
        },
        {
            title: 'Tiket Belum Diambil Tindakan',
            count: kpiUmum?.belum_tindakan || 0,
            icon: <AlertCircle className="text-amber-600" />,
            bg: 'bg-amber-50',
            border: 'border-amber-100',
            url: route('tickets.index', { status: ['Menunggu Klasifikasi', 'Menunggu Semakan Dokumen', 'Tugasan UTD' , 'Tugasan UPP' , 'Menunggu Kelulusan'] })
        },
        {
            title: 'Tiket Dalam Tindakan',
            count: kpiUmum?.dalam_tindakan || 0,
            icon: <FileText className="text-blue-600" />,
            bg: 'bg-blue-50',
            border: 'border-blue-100',
            url: route('tickets.index', { status: [
                    'Dalam Tindakan Pegawai',
                    'Menunggu Pengesahan',
                    'Menunggu Semakan',
                    'LKK Perlu Pembetulan',
                    'Dalam Tindakan',
                    'Menunggu Semakan Laporan',
                    'Laporan Perlu Pembetulan',
                    'Sedia Diverifikasi',
                    'Pembetulan Ketua',
                ] })
        },
        {
            title: 'Tiket Selesai',
            count: kpiUmum?.tiket_selesai || 0,
            icon: <CheckCircle2 className="text-emerald-600" />,
            bg: 'bg-emerald-50',
            border: 'border-emerald-100',
            url: route('tickets.index', { status: 'Selesai' })
        },
    ];

    return (
        <div className="min-h-screen bg-gray-100 flex flex-col md:flex-row pt-16 md:pt-0">
            <Head title="Dashboard Ketua Wilayah" />

            {/* SIDEBAR NAVIGATION LAYOUT */}
            <Sidebar />

            <div className="flex-1 flex flex-col min-w-0">
                <Topbar title={`Selamat Datang, ${user?.nama || 'Ketua Wilayah'}`} />

                <main className="flex-1 overflow-y-auto bg-[#f8fafc] p-6 md:p-8 animate-in fade-in duration-500 space-y-6">


                    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                        {cards.map((card, idx) => (
                            <Link
                                key={idx}
                                href={card.url}
                                className={`p-4 ${card.bg} border ${card.border} rounded-[2rem] shadow-sm flex items-center justify-between transition-transform hover:scale-[1.02] cursor-pointer w-full min-h-[85px]`}
                            >

                                <div className="flex-1 min-w-0 pr-1.5 flex flex-col justify-between h-full">
                                    <p className="text-[10px] font-black uppercase text-slate-400 tracking-wider leading-tight whitespace-normal break-words">
                                        {card.title}
                                    </p>
                                    <h3 className="text-xl font-black text-blue-950 tracking-tight mt-1">
                                        {card.count}
                                    </h3>
                                </div>


                                <div className="p-2.5 bg-white/80 border border-white rounded-xl shrink-0 shadow-sm">
                                    {React.cloneElement(card.icon, { size: 16 })}
                                </div>
                            </Link>
                        ))}
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                        {Object.entries(statsKategori || {}).map(([namaModul, data]) => (
                            <div key={namaModul} className="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm space-y-2.5">

                                {/* Header Modul */}
                                <div className="flex items-center gap-2 border-b border-gray-50 pb-2.5">
                                    {namaModul === 'Meja Bantuan' && <Laptop className="text-blue-600" size={18} />}
                                    {namaModul === 'Konsultasi Rangkaian' && <ListTree className="text-purple-600" size={18} />}
                                    {namaModul === 'Transformasi Digital' && <Layers className="text-pink-600" size={18} />}
                                    <div className="flex flex-col">
                                        <h2 className="font-black text-blue-950 text-xs tracking-tight">{namaModul}</h2>
                                    </div>
                                </div>

                                <div className="space-y-2">

                                    {/* JUMLAH KES KATEGORI */}
                                    <Link
                                        href={route('tickets.index', { kategori: namaModul })}
                                        className="flex items-center justify-between p-2.5 bg-slate-50/50 rounded-xl border border-slate-100 hover:bg-slate-100/50 transition-colors group"
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className="text-slate-500"><FileText size={16} /></div>
                                            <div>
                                                <p className="text-[9px] font-black text-slate-400 uppercase tracking-wide">Jumlah</p>
                                                <p className="text-base font-black text-slate-700 leading-tight">{data.total || 0}</p>
                                            </div>
                                        </div>
                                    </Link>

                                    {/*  BELUM DIAMBIL TINDAKAN */}
                                    <Link
                                        href={route('tickets.index', { kategori: namaModul, status: ['Menunggu Klasifikasi', 'Menunggu Semakan Dokumen', 'Tugasan UTD' , 'Tugasan UPP'] })}
                                        className="flex items-center justify-between p-2.5 bg-amber-50/50 rounded-xl border border-amber-100 hover:bg-amber-100/50 transition-colors group"
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className="text-amber-500"><AlertCircle size={16} /></div>
                                            <div>
                                                <p className="text-[9px] font-black text-amber-900 uppercase tracking-wide">Belum Diambil Tindakan</p>
                                                <p className="text-base font-black text-amber-600 leading-tight">{data.belum_tindakan || 0}</p>
                                            </div>
                                        </div>
                                    </Link>

                                    {/*  DALAM TINDAKAN */}
                                    <Link
                                        href={route('tickets.index', { kategori: namaModul, status: [
                    'Dalam Tindakan Pegawai',
                    'Menunggu Pengesahan',
                    'Menunggu Semakan',
                    'LKK Perlu Pembetulan',
                    'Dalam Tindakan',
                    'Menunggu Semakan Laporan',
                    'Laporan Perlu Pembetulan',
                    'Sedia Diverifikasi',
                    'Pembetulan Ketua',
                ] })}
                                        className="flex items-center justify-between p-2.5 bg-blue-50/50 rounded-xl border border-blue-100 hover:bg-blue-100/50 transition-colors group"
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className="text-blue-500"><Clock size={16} /></div>
                                            <div>
                                                <p className="text-[9px] font-black text-blue-900 uppercase tracking-wide">Dalam Tindakan</p>
                                                <p className="text-base font-black text-blue-600 leading-tight">{data.dalam_tindakan || 0}</p>
                                            </div>
                                        </div>
                                    </Link>

                                </div>
                            </div>
                        ))}
                    </div>

                    {/* RECENT TABLE */}
                    <div className="bg-white rounded-[2.5rem] shadow-sm border border-gray-100 overflow-hidden">
                        <div className="p-8 border-b border-gray-50 flex justify-between items-center bg-white">
                            <h4 className="font-black text-blue-900 uppercase tracking-widest text-sm">Aktiviti Terkini</h4>
                            <Link href={route('tickets.index', { from: 'dashboard' })}
                                className="text-xs font-bold text-blue-600 hover:underline tracking-tight uppercase">
                                Lihat Semua
                            </Link>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="bg-gray-50/50">
                                        <th className="px-8 py-4 text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">ID Tiket</th>
                                        <th className="px-8 py-4 text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">Agensi</th>
                                        <th className="px-8 py-4 text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-50">
                                    {recentTickets?.length > 0 ? (
                                        recentTickets.map((ticket) => (
                                            <tr key={ticket.id || ticket.id_tiket} className="group hover:bg-blue-50/30 transition-colors">
                                                <td className="px-8 py-5">
                                                    <div className="flex flex-col">
                                                        <span className="font-black text-blue-900 text-sm">{ticket.id_tiket}</span>
                                                        <span className="text-[10px] text-gray-400 font-bold uppercase tracking-widest">{ticket.kategori}</span>
                                                    </div>
                                                </td>
                                                <td className="px-8 py-5">
                                                    <span className="text-sm font-bold text-gray-700">{ticket.agensi}</span>
                                                </td>
                                                <td className="px-8 py-5">
                                                    <span className={`px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5 w-fit shadow-sm ${
                                                        ticket.status_tiket === 'Dokumen Tidak Lengkap' ? 'bg-red-50 border border-red-100 text-red-600' :
                                                        ticket.status_tiket === 'Menunggu Klasifikasi' ? 'bg-amber-50 border border-amber-100 text-amber-600' :
                                                        ticket.status_tiket === 'Dalam Pelaksanaan PIC' ? 'bg-blue-50 border border-blue-100 text-blue-600' :
                                                        ticket.status_tiket === 'Selesai' ? 'bg-emerald-50 border border-emerald-100 text-emerald-600' :
                                                        'bg-gray-50 border border-gray-100 text-gray-600'
                                                    }`}>
                                                        {ticket.status_tiket === 'Dokumen Tidak Lengkap' && <AlertCircle size={12} />}
                                                        {ticket.status_tiket === 'Menunggu Klasifikasi' && <Clock size={12} />}
                                                        <span>{ticket.status_tiket}</span>
                                                    </span>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan="3" className="px-8 py-10 text-center text-gray-400 text-sm italic font-medium">
                                                Tiada aktiviti tiket terkini.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    );
}
