import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import Sidebar from '@/Components/Sidebar';
import Topbar from '@/Components/Topbar';
import { useState, useEffect, useRef } from 'react';
import {
    Eye,
    ArrowLeft,
    Clock,
    AlertCircle,
    CheckCircle2,
    FileCheck,
    Search,
    XCircle
} from 'lucide-react';

export default function SenaraiTiket({ auth, tickets, selectedKategori, selectedSubKategori, filters, search }) {

    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const isInitialRender = useRef(true);

    // Handle search form submission on Enter key
    const handleSearchSubmit = (e) => {
        if (e) e.preventDefault();

        router.get(route('tickets.index'), {
            search: searchTerm,
            kategori: filters.kategori,
            status: filters.status,
            sub_kategori: filters.sub_kategori
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true
        });
    };

    // Update local state when search input changes
    const handleSearchChange = (e) => {
        setSearchTerm(e.target.value);
    };

    // Debounce search input by 500ms before sending request to server
    useEffect(() => {
        if (isInitialRender.current) {
            isInitialRender.current = false;
            return;
        }

        const delayDebounce = setTimeout(() => {
            router.get(route('tickets.index'), {
                search: searchTerm,
                kategori: filters.kategori,
                status: filters.status,
                sub_kategori: filters.sub_kategori
            }, {
                preserveState: true,
                preserveScroll: true,
                replace: true
            });
        }, 500);

        return () => clearTimeout(delayDebounce);
    }, [searchTerm]);

    const statusStyles = {
        'Menunggu Klasifikasi': 'bg-purple-50 text-purple-700 border-purple-200',
        'Menunggu Semakan Dokumen': 'bg-blue-50 text-blue-700 border-blue-200',
        'Dokumen Tidak Lengkap': 'bg-red-50 text-red-700 border-red-200',
        'Tugasan UTD': 'bg-amber-50 text-amber-700 border-amber-200',
        'Tugasan UPP': 'bg-sky-50 text-sky-700 border-sky-200',
        'Dalam Tindakan Pegawai': 'bg-indigo-50 text-indigo-700 border-indigo-200',
        'Selesai': 'bg-green-50 text-green-700 border-green-200',
        'Menunggu Pengesahan': 'bg-orange-50 text-orange-700 border-orange-200',
        'LKK Perlu Pembetulan': 'bg-rose-50 text-red-700 border-rose-200',
        'Menunggu Validasi': 'bg-teal-50 text-teal-700 border-teal-200',
        'Menunggu Semakan': 'bg-pink-50 text-pink-700 border-pink-200',
        'Menunggu Kelulusan': 'bg-cyan-50 text-cyan-700 border-cyan-200',
        'Dalam Tindakan': 'bg-indigo-50 text-indigo-700 border-indigo-200',
        'Menunggu Semakan Laporan': 'bg-blue-50 text-blue-700 border-blue-200',
        'Laporan Perlu Pembetulan': 'bg-rose-50 text-rose-700 border-rose-200',
        'Sedia Diverifikasi': 'bg-violet-50 text-violet-700 border-violet-200',
        'Pembetulan Ketua': 'bg-amber-50 text-amber-700 border-amber-200',
    };

    // Calculate real-time ticket statistics from current data array
    const kiraStats = () => {
        const ticketArray = tickets?.data || [];

        return {
            baru: ticketArray.filter(t =>
                t.status_tiket === 'Menunggu Klasifikasi' ||
                t.status_tiket === 'Menunggu Semakan Dokumen'
            ).length,

            gagal: ticketArray.filter(t =>
                t.status_tiket === 'Dokumen Tidak Lengkap'
            ).length,

            proses: ticketArray.filter(t => [
                'Dalam Tindakan Pegawai',
                'Tugasan UTD',
                'Menunggu Pengesahan',
                'LKK Perlu Pembetulan',
                'Menunggu Validasi',
                'Menunggu Semakan',
                'Dalam Tindakan',
                'Menunggu Semakan Laporan',
                'Laporan Perlu Pembetulan',
                'Sedia Diverifikasi',
                'Pembetulan Ketua',
            ].includes(t.status_tiket)).length,

            siap: ticketArray.filter(t =>
                t.status_tiket === 'Selesai'
            ).length,
        };
    };

    const stats = kiraStats();

    // Format raw date string into Malaysian locale date format
    const formatTarikh = (dateString) => {
        if (!dateString) return 'Tiada Tarikh';
        const date = new Date(dateString);
        return date.toLocaleDateString('ms-MY', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });
    };

    return (
        <div className="min-h-screen bg-[#f4f6f9] flex flex-col md:flex-row pt-16 md:pt-0">
            <Head title={`Senarai Tiket - ${selectedSubKategori || selectedKategori || 'Semua'}`} />
            <Sidebar />

            <div className="flex-1 flex flex-col min-w-0 w-full">
                <Topbar title="Senarai Permohonan Tiket" />

                <main className="flex-1 p-4 md:p-6">
                    {/* Header back link and search form */}
                    <div className="flex items-center justify-between mb-6">
                        <div className="flex items-center">
                            <Link href={route('dashboard')} className="inline-flex items-center gap-1.5 text-xs font-bold text-blue-700 hover:text-blue-900 transition-colors uppercase tracking-wider">
                                <ArrowLeft size={13} strokeWidth={2.5} /> Kembali ke Dashboard
                            </Link>
                        </div>

                        {/* Search form */}
                        <form onSubmit={handleSearchSubmit} className="relative flex items-center w-full max-w-xs animate-in fade-in duration-200">

                            {/* Search Icon */}
                            <div className="absolute left-3 text-gray-400 pointer-events-none flex items-center justify-center">
                                <Search size={14} strokeWidth={2.5} />
                            </div>

                            {/* Search input field */}
                            <input
                                type="text"
                                placeholder="Cari ID, agensi atau perkara..."
                                value={searchTerm}
                                onChange={handleSearchChange}
                                className="w-full h-10 text-[11px] font-semibold text-gray-700 bg-white border border-gray-200 rounded-xl pl-9 pr-9 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/5 shadow-sm placeholder:text-gray-400 placeholder:font-normal transition-all"
                            />

                            {/* Clear search button */}
                            {searchTerm && (
                                <button
                                    type="button"
                                    onClick={() => setSearchTerm('')}
                                    className="absolute right-2.5 p-1 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors cursor-pointer flex items-center justify-center"
                                    title="Kosongkan carian"
                                >
                                    <XCircle size={13} strokeWidth={2.5} />
                                </button>
                            )}
                        </form>
                    </div>

                    {/* Tickets list data table */}
                    <div className="bg-white overflow-hidden shadow-sm rounded-2xl border border-gray-200/80 w-full">
                        <div className="overflow-x-auto w-full">
                            <table className="w-full text-left border-collapse min-w-[700px]">
                                <thead className="bg-[#001f4d] text-white">
                                    <tr>
                                        <th className="px-6 py-3.5 text-[10px] font-black uppercase tracking-wider">ID Tiket </th>
                                        <th className="px-6 py-3.5 text-[10px] font-black uppercase tracking-wider">Tarikh Terima</th>
                                        <th className="px-6 py-3.5 text-[10px] font-black uppercase tracking-wider">Agensi Pemohon</th>
                                        <th className="px-6 py-3.5 text-[10px] font-black uppercase tracking-wider text-center">Status Semasa</th>
                                        <th className="px-6 py-3.5 text-[10px] font-black uppercase tracking-wider">Pegawai</th>
                                        <th className="px-6 py-3.5 text-[10px] font-black uppercase tracking-wider text-center">Tindakan</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 text-xs font-bold text-gray-700">
                                {tickets.data && tickets.data.length > 0 ? (
                                    tickets.data.map((ticket) => (
                                            <tr key={ticket.id || ticket.id_tiket} className="hover:bg-blue-50/20 transition-colors group">

                                                {/* Ticket ID */}
                                                <td className="px-6 py-4" >
                                                    <div className="flex flex-col">
                                                        <span className="font-black text-blue-950 text-sm tracking-tight">{ticket.id_tiket}</span>
                                                        <span className="text-[9px] text-gray-400 font-black uppercase tracking-wider pt-0.5 flex items-center gap-1 flex-wrap">
                                                        </span>
                                                    </div>
                                                </td>

                                                {/* Received date */}
                                                <td className="px-6 py-4 text-gray-500 font-semibold">
                                                    {formatTarikh(ticket.tarikh_terima)}
                                                </td>

                                                {/* Agency */}
                                                <td className="px-6 py-4 uppercase text-gray-800 tracking-tight font-extrabold max-w-[220px] truncate">
                                                    {ticket.agensi}
                                                </td>

                                                {/* Status badge */}
                                                <td className="px-6 py-4 text-center">
                                                    <span className={`inline-block px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider border shadow-sm ${
                                                        statusStyles[ticket.status_tiket] || 'bg-gray-100 text-gray-600 border-gray-200'
                                                    }`}>
                                                        {ticket.kategori === 'Konsultasi Rangkaian'
                                                        && ticket.status_tiket === 'Menunggu Semakan Laporan'
                                                            ? 'Menunggu Semakan'
                                                            : ticket.status_tiket}
                                                    </span>
                                                </td>

                                               {/* Assigned officer (PIC) */}
                                                <td className="px-5 py-4">
                                                    {ticket.nama_pic ? (
                                                        <div className="flex flex-wrap gap-1 max-w-[220px]">
                                                            {ticket.nama_pic.split(', ').map((namaIndividu, idx) => {
                                                                const namaPendek = namaIndividu.split(' ')[0];
                                                                const namaSayaPendek = auth.user.nama.split(' ')[0];

                                                                const adakahIniSaya = namaPendek.toLowerCase() === namaSayaPendek.toLowerCase();

                                                                return adakahIniSaya ? (
                                                                    <span key={idx} className="inline-flex items-center gap-1 px-2.5 py-0.5 bg-blue-600 border border-blue-700 text-white font-black rounded-lg text-[10px] uppercase tracking-wider shadow-sm animate-in fade-in duration-200 shrink-0">
                                                                        Saya ({namaPendek})
                                                                    </span>
                                                                ) : (
                                                                    <span key={idx} className="inline-flex items-center gap-1 px-2.5 py-0.5 bg-gray-50 border border-gray-200 text-gray-700 font-extrabold rounded-lg text-[10px] uppercase tracking-wider shadow-sm shrink-0">
                                                                        {namaPendek}
                                                                    </span>
                                                                );
                                                            })}
                                                        </div>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-50 text-gray-400 font-bold text-[10px] uppercase tracking-wide rounded-md italic">
                                                            Belum Dilantik
                                                        </span>
                                                    )}
                                                </td>

                                                {/* Actions */}
                                                <td className="px-6 py-4 text-center">
                                                    <div className="flex justify-center">
                                                        <Link
                                                            href={route('tickets.show', ticket.id_tiket)}
                                                            className="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-50 text-blue-700 font-black rounded-xl text-xs hover:bg-[#002b66] hover:text-white transition-all shadow-sm active:scale-95 cursor-pointer"
                                                        >
                                                            <Eye size={12} /> Urus
                                                        </Link>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan="6" className="px-6 py-12 text-center text-gray-400 font-bold italic uppercase tracking-wider text-xs bg-gray-50/40">
                                                Tiada sebarang rekod permohonan tiket ditemui.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination controls */}
                        {tickets.links && (
                            <div className="px-6 py-4 bg-gray-50/50 border-t border-gray-100 flex items-center justify-between">
                                <p className="text-[10px] font-bold text-gray-500 uppercase tracking-widest">
                                    Menunjukkan {tickets.from || 0} hingga {tickets.to || 0} daripada {tickets.total || 0} rekod
                                </p>

                                <div className="flex gap-1">
                                    {tickets.links.map((link, index) => {
                                        let label = link.label;
                                        if (label.includes('Previous')) label = '« Sebelumnya';
                                        if (label.includes('Next')) label = 'Seterusnya »';

                                        return (
                                            <Link
                                                key={index}
                                                href={link.url || '#'}
                                                className={`px-3 py-1 text-[10px] font-black rounded-lg border transition-all ${
                                                    link.active
                                                        ? 'bg-blue-900 text-white border-blue-900'
                                                        : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-100'
                                                } ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`}
                                            >
                                                {label}
                                            </Link>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </div>
                </main>
            </div>
        </div>
    );
}
