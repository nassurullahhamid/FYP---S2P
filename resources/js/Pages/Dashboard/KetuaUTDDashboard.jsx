import React from 'react';
import { Head, Link } from '@inertiajs/react';
import Sidebar from '../../Components/Sidebar';
import Topbar from '../../Components/Topbar';

import {
    FileText,
    Clock,
    CheckCircle2,
    Laptop,
    ListTree,
    Layers,
    AlertCircle,
    ChevronRight
} from 'lucide-react';

export default function KetuaUTDDashboard({ auth, stats, recentTickets }) {

    const user = auth?.user;

    const cards = [
        {
            title: 'Tiket Belum Diambil Tindakan',
            count: stats?.kpiUtama?.belum_tindakan || 0,
            icon: <AlertCircle className="text-amber-600" />,
            bg: 'bg-amber-50',
            border: 'border-amber-100',
            url: route('tickets.index', {
                status: [
                    'Menunggu Klasifikasi',
                    'Menunggu Semakan Dokumen',
                    'Tugasan UTD',
                    'Tugasan UPP',
                    'Menunggu Pengesahan',
                    'Menunggu Semakan',
                    'Menunggu Semakan Laporan',
                    'Menunggu Kelulusan',
                ],
            })
        },
        {
            title: 'Tiket Dalam Tindakan',
            count: stats?.kpiUtama?.dalam_tindakan || 0,
            icon: <Clock className="text-blue-600" />,
            bg: 'bg-blue-50',
            border: 'border-blue-100',
            url: route('tickets.index', {
                status: [
                    'Dalam Tindakan Pegawai',
                    'LKK Perlu Pembetulan',
                    'Dalam Tindakan',
                    'Laporan Perlu Pembetulan',
                    'Sedia Diverifikasi',
                    'Pembetulan Ketua',
                    'Menunggu Validasi',
                ],
            })
        },
        {
            title: 'Tiket Selesai',
            count: stats?.kpiUtama?.selesai || 0,
            icon: <CheckCircle2 className="text-emerald-600" />,
            bg: 'bg-emerald-50',
            border: 'border-emerald-100',
            url: route('tickets.index', { status: 'Selesai' })
        },

    ];

    const statusBadges = {
        'Menunggu Klasifikasi': 'bg-amber-50 border-amber-200 text-amber-700',
        'Menunggu Semakan Dokumen': 'bg-blue-50 border-blue-200 text-blue-700',
        'Dalam Tindakan Pegawai': 'bg-indigo-50 border-indigo-200 text-indigo-700',
        'Dokumen Tidak Lengkap': 'bg-red-50 border-red-200 text-red-700',
        'Tugasan UTD': 'bg-purple-50 border-purple-200 text-purple-700',
        'Selesai': 'bg-emerald-50 border-emerald-200 text-emerald-700',
        'Dalam Tindakan': 'bg-indigo-50 border-indigo-200 text-indigo-700',
        'Menunggu Semakan Laporan': 'bg-blue-50 border-blue-200 text-blue-700',
        'Laporan Perlu Pembetulan': 'bg-rose-50 border-rose-200 text-rose-700',
        'Sedia Diverifikasi': 'bg-violet-50 border-violet-200 text-violet-700',
        'Pembetulan Ketua': 'bg-amber-50 border-amber-200 text-amber-700',
        'Menunggu Validasi': 'bg-teal-50 border-teal-200 text-teal-700',
    };

    return (
        <div className="min-h-screen bg-gray-100 flex flex-col md:flex-row pt-16 md:pt-0">
            <Head title="Dashboard Ketua UTD" />

            <Sidebar />

            <div className="flex-1 flex flex-col min-w-0">
            <Topbar title={`Selamat Datang, ${user?.nama || 'Ketua UTD'}`} />

            <main className="flex-1 overflow-y-auto bg-[#f8fafc] p-6 md:p-8 animate-in fade-in duration-500 space-y-6">

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-5">
                            {cards.map((card, idx) => (
                                <Link
                                    key={idx}
                                    href={card.url}
                                    className={`p-6 ${card.bg} border ${card.border} rounded-[2rem] shadow-sm flex items-center justify-between transition-transform hover:scale-[1.02] cursor-pointer w-full`}
                                >
                                    <div className="space-y-1">
                                        <p className="text-[11px] font-black uppercase text-gray-400 tracking-wider">{card.title}</p>
                                        <h3 className="text-2xl font-black text-blue-950 tracking-tight">{card.count}</h3>
                                    </div>
                                    <div className="p-4 bg-white/80 border border-white rounded-2xl shrink-0 shadow-sm">
                                        {card.icon}
                                    </div>
                                </Link>
                            ))}
                    </div>

                            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                {Object.entries(stats?.kpiModul || {}).map(([namaModul, data]) => (
                                    <div key={namaModul} className="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm space-y-4">
                                        {/* Header Modul */}
                                        <div className="flex items-center gap-3 border-b border-gray-50 pb-4">
                                            {namaModul === 'Meja Bantuan' && <Laptop className="text-blue-600" size={24} />}
                                            {namaModul === 'Konsultasi Rangkaian' && <ListTree className="text-purple-600" size={24} />}
                                            {namaModul === 'Transformasi Digital' && <Layers className="text-pink-600" size={24} />}
                                            <div className="flex flex-col">
                                                <h2 className="font-black text-blue-950 text-sm">{namaModul}</h2>
                                                <p className="text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                                                    {namaModul === 'Meja Bantuan'}
                                                    {namaModul === 'Konsultasi Rangkaian'}
                                                    {namaModul === 'Transformasi Digital' }
                                                </p>
                                            </div>
                                        </div>

                                        <div className="space-y-3">
                                            {/* Belum Diambil Tindakan */}
                                            <Link
                                                href={route('tickets.index', {
                                                    kategori: namaModul,
                                                    status: [
                                                        'Menunggu Klasifikasi',
                                                        'Menunggu Semakan Dokumen',
                                                        'Tugasan UTD',
                                                        'Tugasan UPP',
                                                        'Menunggu Pengesahan',
                                                        'Menunggu Semakan',
                                                        'Menunggu Semakan Laporan',
                                                    ],
                                                })}
                                                className="flex items-center justify-between p-4 bg-amber-50/50 rounded-2xl border border-amber-100 hover:bg-amber-100/50 transition-colors group"
                                            >
                                                <div className="flex items-center gap-4">
                                                    <div className="text-amber-500"><AlertCircle size={24} /></div>
                                                    <div>
                                                        <p className="text-[10px] font-black text-amber-900 uppercase">Belum Diambil Tindakan</p>
                                                        <p className="text-2xl font-black text-amber-600">{data.belum_tindakan}</p>
                                                    </div>
                                                </div>

                                            </Link>

                                            {/* Dalam Tindakan */}
                                            <Link
                                                href={route('tickets.index', {
                                                    kategori: namaModul,
                                                    status: [
                                                        'Dalam Tindakan Pegawai',
                                                        'LKK Perlu Pembetulan',
                                                        'Dalam Tindakan',
                                                        'Laporan Perlu Pembetulan',
                                                        'Sedia Diverifikasi',
                                                        'Pembetulan Ketua',
                                                        'Menunggu Validasi',
                                                    ],
                                                })}
                                                className="flex items-center justify-between p-4 bg-blue-50/50 rounded-2xl border border-blue-100 hover:bg-blue-100/50 transition-colors group"
                                            >
                                                <div className="flex items-center gap-4">
                                                    <div className="text-blue-500"><Clock size={24} /></div>
                                                    <div>
                                                        <p className="text-[10px] font-black text-blue-900 uppercase">Dalam Tindakan</p>
                                                        <p className="text-2xl font-black text-blue-600">{data.dalam_tindakan}</p>
                                                    </div>
                                                </div>

                                            </Link>
                                        </div>
                                    </div>
                                ))}
                            </div>

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
                                            <tr key={ticket.id} className="group hover:bg-blue-50/30 transition-colors">
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
