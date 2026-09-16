import React from 'react';
import { Head, Link } from '@inertiajs/react';
import Sidebar from '@/Components/Sidebar';
import Topbar from '@/Components/Topbar';
import {
    Users,
    FileText,
    CheckCircle2,
    Clock,
    AlertCircle
} from 'lucide-react';

export default function AdminDashboard({ auth, stats, recentTickets }) {

    const user = auth?.user;

    const cards = [
        {
            title: 'Jumlah Tiket',
            count: stats?.total_tickets || 0,
            icon: <FileText className="text-yellow-600" />,
            bg: 'bg-yellow-50',
            border: 'border-yellow-100',
            url: route ('tickets.index')
        },
        {
            title: 'Tiket Aktif',
            count: stats?.proses || 0,
            icon: <Clock className="text-pink-600" />,
            bg: 'bg-pink-50',
            border: 'border-pink-100',
            url: route ('tickets.index' , {status: 'aktif'})

        },
        {
            title: 'Tiket Selesai',
            count: stats?.selesai || 0,
            icon: <CheckCircle2 className="text-emerald-600" />,
            bg: 'bg-emerald-50',
            border: 'border-emerald-100',
            url: route ('tickets.index' , {status: 'selesai'})

        },
        {
            title: 'Jumlah Pengguna',
            count: stats?.total_users || 0,
            icon: <Users className="text-purple-600" />,
            bg: 'bg-purple-50',
            border: 'border-purple-100',
            url: route ('users.index')

        },
    ];

    return (
        <div className="min-h-screen bg-gray-100 flex flex-col md:flex-row pt-16 md:pt-0">
            <Head title="Admin Dashboard" />

            {/* COMPONENTIZED SIDEBAR */}
            <Sidebar />

            <div className="flex-1 flex flex-col min-w-0">
                <Topbar title={`Selamat Datang, ${user?.nama || 'Admin'}`} />

                {/* MAIN CONTENT AREA */}
                <main className="flex-1 overflow-y-auto bg-[#f8fafc] p-6 md:p-8 animate-in fade-in duration-500 space-y-8">

                <div className="grid grid-cols-1 sm:grid-cols-4 gap-5">
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


                    {/* RECENT DATA ACTIVITY ROW */}
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
