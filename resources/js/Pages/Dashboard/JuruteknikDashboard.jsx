import React from 'react';
import { Head, Link } from '@inertiajs/react';
import Sidebar from '@/Components/Sidebar';
import Topbar from '@/Components/Topbar';

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

export default function JuruteknikDashboard({ auth, stats, recentTickets }) {
    const user = auth?.user;

    const cards = [
        {
            title: 'Jumlah Tugasan Saya',
            count: stats?.total_tickets || 0,
            icon: <FileText className="text-blue-600" />,
            bg: 'bg-blue-50/60',
            border: 'border-blue-100',
            url: route('tickets.index')
        },
        {
            title: 'Tugasan Dalam Tindakan',
            count: stats?.proses || 0,
            icon: <Clock className="text-amber-600" />,
            bg: 'bg-amber-50/60',
            border: 'border-amber-100',
            url: route('tickets.index', { status: 'proses' })
        },
        {
            title: 'Tugasan Selesai',
            count: stats?.selesai || 0,
            icon: <CheckCircle2 className="text-emerald-600" />,
            bg: 'bg-emerald-50/60',
            border: 'border-emerald-100',
            url: route('tickets.index', { status: 'Selesai' })
        },
    ];

    return (
        <div className="min-h-screen bg-gray-100 flex flex-col md:flex-row pt-16 md:pt-0">
            <Head title="Dashboard Juruteknik" />

            <Sidebar />

            <div className="flex-1 flex flex-col min-w-0">
                <Topbar title={`Selamat Datang, ${user?.nama || 'Juruteknik'}`} />

                <main className="flex-1 overflow-y-auto bg-[#f8fafc] p-6 md:p-8 animate-in fade-in duration-500 space-y-8">

                    {/* BANNER KPI UTAMA */}
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-5">
                        {cards.map((card, idx) => (
                            <Link
                                key={idx}
                                href={card.url}
                                className={`p-6 ${card.bg} border ${card.border} rounded-[2rem] shadow-sm flex items-center justify-between transition-all hover:scale-[1.01] cursor-pointer w-full`}
                            >
                                <div className="space-y-1">
                                    <p className="text-[11px] font-black uppercase text-gray-400 tracking-wider">{card.title}</p>
                                    <h3 className="text-2xl font-black text-blue-950 tracking-tight">{card.count}</h3>
                                </div>
                                <div className="p-4 bg-white border border-white rounded-2xl shrink-0 shadow-sm">
                                    {card.icon}
                                </div>
                            </Link>
                        ))}
                    </div>


<div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {Object.entries(stats?.kpiModul || {}).map(([namaModul, data]) => (
        <div key={namaModul} className="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm flex flex-col justify-between gap-4">

            {/* Header Kad Modul */}
            <div className="flex items-center gap-3 border-b border-gray-50 pb-4">
                {namaModul === 'Meja Bantuan' && <Laptop className="text-blue-600" size={22} />}
                {namaModul === 'Konsultasi Rangkaian' && <ListTree className="text-purple-600" size={22} />}
                {namaModul === 'Transformasi Digital' && <Layers className="text-pink-600" size={22} />}
                <div className="flex flex-col">
                    <h2 className="font-black text-blue-950 text-sm tracking-tight">{namaModul}</h2>
                </div>
            </div>

            <div className="space-y-3">
                <Link
                    href={route('tickets.index', { kategori: namaModul, status: 'proses' })}
                        className="flex items-center justify-between p-5 bg-blue-50/40 rounded-2xl border border-blue-100/70 hover:bg-blue-50/80 transition-colors group cursor-pointer w-full"
                >
                    <div className="flex items-center gap-3.5">
                        <div className="text-blue-500 bg-white p-2 rounded-xl border border-blue-100 shadow-sm">
                            <Clock size={20} />
                        </div>
                        <div>
                            <p className="text-[10px] font-black text-gray-400 uppercase tracking-wider">Tugasan Dalam Tindakan</p>
                            <p className="text-2xl font-black text-blue-600 tracking-tight">{data.dalam_tindakan || 0}</p>
                        </div>
                    </div>

                </Link>
            </div>

        </div>
    ))}
</div>

                    {/* RECENT TABLE */}
                    <div className="bg-white rounded-[2.5rem] shadow-sm border border-gray-100 overflow-hidden">
                        <div className="p-6 md:p-8 border-b border-gray-50 flex justify-between items-center bg-white">
                            <h4 className="font-black text-blue-900 uppercase tracking-widest text-xs">Aktiviti Terkini</h4>
                            <Link href={route('tickets.index')} className="text-xs font-bold text-blue-600 hover:underline tracking-tight uppercase">
                                Lihat Semua Tiket
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
                                            <tr key={ticket.id_tiket} className="group hover:bg-blue-50/30 transition-colors">
                                                <td className="px-8 py-5">
                                                    <div className="flex flex-col">
                                                        <Link href={route('tickets.show', { id_tiket: ticket.id_tiket })} className="font-black text-blue-900 text-sm hover:underline">
                                                            {ticket.id_tiket}
                                                        </Link>
                                                        <span className="text-[10px] text-gray-400 font-bold uppercase tracking-widest">{ticket.kategori}</span>
                                                    </div>
                                                </td>
                                                <td className="px-8 py-5">
                                                    <span className="text-sm font-bold text-gray-700">{ticket.agensi}</span>
                                                </td>
                                                <td className="px-8 py-5">
                                                    <span className={`px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5 w-fit shadow-sm ${
                                                        ticket.status_tiket === 'Selesai'
                                                            ? 'bg-emerald-50 border border-emerald-100 text-emerald-600'
                                                            : 'bg-indigo-50 border border-indigo-100 text-indigo-600'
                                                    }`}>
                                                        <Clock size={12} />
                                                        <span>{ticket.status_tiket}</span>
                                                    </span>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan="3" className="px-8 py-10 text-center text-gray-400 text-sm italic font-medium">
                                                Tiada tugasan aktif anda buat masa ini.
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
