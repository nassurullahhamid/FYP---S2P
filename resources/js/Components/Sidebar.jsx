import React, { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import {
    LayoutDashboard, FileText, Users, Boxes,
    Laptop, Layers, ListTree, ChevronDown, Menu
} from 'lucide-react';

export default function Sidebar() {

    const { noti_counts } = usePage().props;
    const [isOpen, setIsOpen] = useState(true);
    const { auth, stats } = usePage().props;

    const role = auth.user?.peranan || auth.user?.role;
    const currentUrl = new URL(window.location.href);
    const activeKategori = currentUrl.searchParams.get('kategori');

    const [openMenus, setOpenMenus] = useState({
        mb: activeKategori === 'Meja Bantuan' || !activeKategori,
        kr: activeKategori === 'Konsultasi Rangkaian',
        td: activeKategori === 'Transformasi Digital',
    });

    const mbTotalCount = (noti_counts?.penyelenggaraan_komputer || 0) +
                         (noti_counts?.penyelenggaraan_rangkaian || 0) +
                         (noti_counts?.sistem_aplikasi || 0) +
                         (noti_counts?.perkhidmatan_emel || 0) +
                         (noti_counts?.perkhidmatan_lintas_langsung || 0) +
                         (noti_counts?.peminjaman_ict || 0);

    const krTotalCount = (noti_counts?.pemasangan_baharu || 0) +
                         (noti_counts?.naiktaraf || 0);

    const tdTotalCount = (noti_counts?.pemodenan_bilik_mesyuarat || 0) +
                         (noti_counts?.pembekalan_peralatan_ict || 0);


    const toggleMenu = (menu) => {
        setOpenMenus(prev => ({ ...prev, [menu]: !prev[menu] }));
    };

    const isSubCategoryActive = (kategori, subKategori) => {
        const queryKategori = currentUrl.searchParams.get('kategori');
        const querySubKategori = currentUrl.searchParams.get('sub_kategori');

        return route().current('tickets.index') &&
               queryKategori === kategori &&
               querySubKategori === subKategori;
    };

    const isDashboardActive = () => route().current('dashboard');
    const isAdminRouteActive = (routeName) => route().current(routeName);

    const kakitanganTeknikal = ['ketua_upp', 'ketua_utd', 'ketua_wilayah', 'juruteknik'];

    return (
        <aside className={`transition-all duration-300 bg-[#0c183b] text-white sticky top-0 h-screen shadow-xl z-20 shrink-0 ${isOpen ? 'w-72' : 'w-20'}`}>
            <div className="p-6 h-full flex flex-col">

                {/* Header & Toggle */}
                <div className={`relative flex items-center ${isOpen ? 'justify-start pr-10' : 'justify-center'} mb-10 border-b border-blue-900/50 pb-6 min-h-[40px]`}>
                    {isOpen && (
                        <span className="text-left text-base font-black tracking-tighter leading-tight uppercase max-w-[90%] text-white pl-2 animate-in fade-in duration-350">
                            Sistem Pengurusan Perkhidmatan (S2P)
                        </span>
                    )}

                    <button
                        onClick={() => setIsOpen(!isOpen)}
                        className={`text-blue-300 hover:text-white p-1 hover:bg-blue-900/40 rounded-lg transition-colors cursor-pointer ${isOpen ? 'absolute right-0' : ''}`}
                    >
                        <Menu size={20} />
                    </button>
                </div>

                {/* Navigasi Menu */}
                <nav className="flex-1 space-y-2 overflow-y-auto pr-2 [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">

                    {/* DASHBOARD LINK */}
                    <Link
                        href={route('dashboard')}
                        className={`flex items-center gap-3 p-3 rounded-xl transition-all uppercase font-bold text-sm ${isOpen ? '' : 'justify-center'} ${
                            isDashboardActive() ? 'bg-blue-600 text-white shadow-lg' : 'text-blue-200 hover:bg-white/10'
                        }`}
                    >
                        <LayoutDashboard size={20} className="shrink-0" />
                        {isOpen && <span className="text-sm whitespace-nowrap animate-in fade-in duration-200">Dashboard</span>}
                    </Link>

                    {/* ADMIN MENU ACCESS */}
                    {role === 'admin' && (
                        <>
                            <Link
                                href={route('tickets.create')}
                                className={`flex items-center gap-3 p-3 rounded-xl transition-all uppercase font-bold text-sm ${isOpen ? '' : 'justify-center'} ${
                                    isAdminRouteActive('tickets.create') ? 'bg-blue-600 text-white shadow-lg' : 'text-blue-200 hover:bg-white/10'
                                }`}
                            >
                                <FileText size={20} className="shrink-0" />
                                {isOpen && <span className="text-sm whitespace-nowrap animate-in fade-in duration-200">Daftar Permohonan</span>}
                            </Link>

                            <Link
                                href={route('assets.index')}
                                className={`flex items-center gap-3 p-3 rounded-xl transition-all uppercase font-bold text-sm ${isOpen ? '' : 'justify-center'} ${
                                    isAdminRouteActive('assets.index') ? 'bg-blue-600 text-white shadow-lg' : 'text-blue-200 hover:bg-white/10'
                                }`}
                            >
                                <Boxes size={20} className="shrink-0" />
                                {isOpen && <span className="text-sm whitespace-nowrap animate-in fade-in duration-200">Pengurusan Aset</span>}
                            </Link>

                            <Link
                                href={route('users.index')}
                                className={`flex items-center gap-3 p-3 rounded-xl transition-all uppercase font-bold text-sm ${isOpen ? '' : 'justify-center'} ${
                                    isAdminRouteActive('users.index') ? 'bg-blue-600 text-white shadow-lg' : 'text-blue-200 hover:bg-white/10'
                                }`}
                            >
                                <Users size={20} className="shrink-0" />
                                {isOpen && <span className="text-sm whitespace-nowrap animate-in fade-in duration-200">Pengurusan Pengguna</span>}
                            </Link>
                        </>
                    )}

                    {/* KAKITANGAN TEKNIKAL MODULES */}
                    {kakitanganTeknikal.includes(role) && (
                        <>
                            {isOpen && (
                                <div className="pt-4 pb-2 px-3 animate-in fade-in duration-200">
                                    <p className="text-[10px] font-black text-blue-500 uppercase tracking-[0.2em]">Modul Perkhidmatan</p>
                                </div>
                            )}

                            {/* MEJA BANTUAN */}
                            <SidebarNavItem
                                name="Meja Bantuan"
                                icon={Laptop}
                                badge={mbTotalCount}
                                isSidebarOpen={isOpen}
                                isOpen={openMenus.mb}
                                onClick={() => toggleMenu('mb')}
                            >
                                <Link
                                    href={route('tickets.index', { kategori: 'Meja Bantuan', sub_kategori: 'Penyelenggaraan Komputer' })}
                                    className={`flex items-center justify-between p-2 text-[10px] font-bold uppercase tracking-widest border-l ml-2 pl-3 transition-colors ${
                                        isSubCategoryActive('Meja Bantuan', 'Penyelenggaraan Komputer')
                                            ? 'text-white border-blue-400 font-black bg-white/5 rounded-r-lg'
                                            : 'text-blue-300 hover:text-white border-blue-800/30'
                                    }`}
                                >
                                    <span>Penyelenggaraan Komputer</span>
                                    {noti_counts?.penyelenggaraan_komputer > 0 && (
                                        <span className="px-1.5 py-0.5 text-[9px] font-black bg-red-500 text-white rounded-full shadow-sm animate-pulse shrink-0 ml-2">
                                            {noti_counts.penyelenggaraan_komputer}
                                        </span>
                                    )}
                                </Link>

                                <Link
                                    href={route('tickets.index', { kategori: 'Meja Bantuan', sub_kategori: 'Penyelenggaraan Rangkaian' })}
                                    className={`flex items-center justify-between p-2 text-[10px] font-bold uppercase tracking-widest border-l ml-2 pl-3 transition-colors ${
                                        isSubCategoryActive('Meja Bantuan', 'Penyelenggaraan Rangkaian')
                                            ? 'text-white border-blue-400 font-black bg-white/5 rounded-r-lg'
                                            : 'text-blue-300 hover:text-white border-blue-800/30'
                                    }`}
                                >
                                    <span>Penyelenggaraan Rangkaian</span>
                                    {noti_counts?.penyelenggaraan_rangkaian > 0 && (
                                        <span className="px-1.5 py-0.5 text-[9px] font-black bg-red-500 text-white rounded-full shadow-sm animate-pulse shrink-0 ml-2">
                                            {noti_counts.penyelenggaraan_rangkaian}
                                        </span>
                                    )}
                                </Link>

                                <Link
                                    href={route('tickets.index', { kategori: 'Meja Bantuan', sub_kategori: 'Sistem Aplikasi' })}
                                    className={`flex items-center justify-between p-2 text-[10px] font-bold uppercase tracking-widest border-l ml-2 pl-3 transition-colors ${
                                        isSubCategoryActive('Meja Bantuan', 'Sistem Aplikasi')
                                            ? 'text-white border-blue-400 font-black bg-white/5 rounded-r-lg'
                                            : 'text-blue-300 hover:text-white border-blue-800/30'
                                    }`}
                                >
                                    <span>Sistem Aplikasi</span>
                                    {noti_counts?.sistem_aplikasi > 0 && (
                                        <span className="px-1.5 py-0.5 text-[9px] font-black bg-red-500 text-white rounded-full shadow-sm animate-pulse shrink-0 ml-2">
                                            {noti_counts.sistem_aplikasi}
                                        </span>
                                    )}
                                </Link>

                                <Link
                                    href={route('tickets.index', { kategori: 'Meja Bantuan', sub_kategori: 'Perkhidmatan E-mel' })}
                                    className={`flex items-center justify-between p-2 text-[10px] font-bold uppercase tracking-widest border-l ml-2 pl-3 transition-colors ${
                                        isSubCategoryActive('Meja Bantuan', 'Perkhidmatan E-mel')
                                            ? 'text-white border-blue-400 font-black bg-white/5 rounded-r-lg'
                                            : 'text-blue-300 hover:text-white border-blue-800/30'
                                    }`}
                                >
                                    <span>Perkhidmatan E-mel</span>
                                    {noti_counts?.perkhidmatan_emel > 0 && (
                                        <span className="px-1.5 py-0.5 text-[9px] font-black bg-red-500 text-white rounded-full shadow-sm animate-pulse shrink-0 ml-2">
                                            {noti_counts.perkhidmatan_emel}
                                        </span>
                                    )}
                                </Link>

                                <Link
                                    href={route('tickets.index', { kategori: 'Meja Bantuan', sub_kategori: 'Perkhidmatan Lintas Langsung' })}
                                    className={`flex items-center justify-between p-2 text-[10px] font-bold uppercase tracking-widest border-l ml-2 pl-3 transition-colors ${
                                        isSubCategoryActive('Meja Bantuan', 'Perkhidmatan Lintas Langsung')
                                            ? 'text-white border-blue-400 font-black bg-white/5 rounded-r-lg'
                                            : 'text-blue-300 hover:text-white border-blue-800/30'
                                    }`}
                                >
                                    <span>Perkhidmatan Lintas Langsung</span>
                                    {noti_counts?.perkhidmatan_lintas_langsung > 0 && (
                                        <span className="px-1.5 py-0.5 text-[9px] font-black bg-red-500 text-white rounded-full shadow-sm animate-pulse shrink-0 ml-2">
                                            {noti_counts.perkhidmatan_lintas_langsung}
                                        </span>
                                    )}
                                </Link>

                                <Link
                                    href={route('tickets.index', { kategori: 'Meja Bantuan', sub_kategori: 'Peminjaman Peralatan ICT' })}
                                    className={`flex items-center justify-between p-2 text-[10px] font-bold uppercase tracking-widest border-l ml-2 pl-3 transition-colors ${
                                        isSubCategoryActive('Meja Bantuan', 'Peminjaman Peralatan ICT')
                                            ? 'text-white border-blue-400 font-black bg-white/5 rounded-r-lg'
                                            : 'text-blue-300 hover:text-white border-blue-800/30'
                                    }`}
                                >
                                    <span>Peminjaman Peralatan ICT</span>
                                    {noti_counts?.peminjaman_ict > 0 && (
                                        <span className="px-1.5 py-0.5 text-[9px] font-black bg-red-500 text-white rounded-full shadow-sm animate-pulse shrink-0 ml-2">
                                            {noti_counts.peminjaman_ict}
                                        </span>
                                    )}
                                </Link>
                            </SidebarNavItem>

                            {/* KONSULTASI RANGKAIAN */}
                            <SidebarNavItem
                                name="Konsultasi Rangkaian"
                                icon={ListTree}
                                badge={krTotalCount}
                                isSidebarOpen={isOpen}
                                isOpen={openMenus.kr}
                                onClick={() => toggleMenu('kr')}
                            >
                                <Link
                                    href={route('tickets.index', { kategori: 'Konsultasi Rangkaian', sub_kategori: 'Pemasangan Baharu' })}
                                    className={`flex items-center justify-between p-2 text-[10px] font-bold uppercase tracking-widest border-l ml-2 pl-3 transition-colors ${
                                        isSubCategoryActive('Konsultasi Rangkaian', 'Pemasangan Baharu')
                                            ? 'text-white border-blue-400 font-black bg-white/5 rounded-r-lg'
                                            : 'text-blue-300 hover:text-white border-blue-800/30'
                                    }`}
                                >
                                    <span>Pemasangan Baharu</span>
                                    {noti_counts?.pemasangan_baharu > 0 && (
                                        <span className="px-1.5 py-0.5 text-[9px] font-black bg-red-500 text-white rounded-full shadow-sm animate-pulse shrink-0 ml-2">
                                            {noti_counts.pemasangan_baharu}
                                        </span>
                                    )}
                                </Link>

                                <Link
                                    href={route('tickets.index', { kategori: 'Konsultasi Rangkaian', sub_kategori: 'Naiktaraf' })}
                                    className={`flex items-center justify-between p-2 text-[10px] font-bold uppercase tracking-widest border-l ml-2 pl-3 transition-colors ${
                                        isSubCategoryActive('Konsultasi Rangkaian', 'Naiktaraf')
                                            ? 'text-white border-blue-400 font-black bg-white/5 rounded-r-lg'
                                            : 'text-blue-300 hover:text-white border-blue-800/30'
                                    }`}
                                >
                                    <span>Naiktaraf</span>
                                    {noti_counts?.naiktaraf > 0 && (
                                        <span className="px-1.5 py-0.5 text-[9px] font-black bg-red-500 text-white rounded-full shadow-sm animate-pulse shrink-0 ml-2">
                                            {noti_counts.naiktaraf}
                                        </span>
                                    )}
                                </Link>
                            </SidebarNavItem>

                            {/* TRANSFORMASI DIGITAL */}
                            <SidebarNavItem
                                name="Transformasi Digital"
                                icon={Layers}
                                badge={tdTotalCount}
                                isSidebarOpen={isOpen}
                                isOpen={openMenus.td}
                                onClick={() => toggleMenu('td')}
                            >
                                <Link
                                    href={route('tickets.index', { kategori: 'Transformasi Digital', sub_kategori: 'Pemodenan Bilik Mesyuarat' })}
                                    className={`flex items-center justify-between p-2 text-[10px] font-bold uppercase tracking-widest border-l ml-2 pl-3 transition-colors ${
                                        isSubCategoryActive('Transformasi Digital', 'Pemodenan Bilik Mesyuarat')
                                            ? 'text-white border-blue-400 font-black bg-white/5 rounded-r-lg'
                                            : 'text-blue-300 hover:text-white border-blue-800/30'
                                    }`}
                                >
                                    <span>Pemodenan Bilik Mesyuarat</span>
                                    {noti_counts?.pemodenan_bilik_mesyuarat > 0 && (
                                        <span className="px-1.5 py-0.5 text-[9px] font-black bg-red-500 text-white rounded-full shadow-sm animate-pulse shrink-0 ml-2">
                                            {noti_counts.pemodenan_bilik_mesyuarat}
                                        </span>
                                    )}
                                </Link>

                                <Link
                                    href={route('tickets.index', { kategori: 'Transformasi Digital', sub_kategori: 'Pembekalan Peralatan ICT' })}
                                    className={`flex items-center justify-between p-2 text-[10px] font-bold uppercase tracking-widest border-l ml-2 pl-3 transition-colors ${
                                        isSubCategoryActive('Transformasi Digital', 'Pembekalan Peralatan ICT')
                                            ? 'text-white border-blue-400 font-black bg-white/5 rounded-r-lg'
                                            : 'text-blue-300 hover:text-white border-blue-800/30'
                                    }`}
                                >
                                    <span>Pembekalan Peralatan ICT</span>
                                    {noti_counts?.pembekalan_peralatan_ict > 0 && (
                                        <span className="px-1.5 py-0.5 text-[9px] font-black bg-red-500 text-white rounded-full shadow-sm animate-pulse shrink-0 ml-2">
                                            {noti_counts.pembekalan_peralatan_ict}
                                        </span>
                                    )}
                                </Link>
                            </SidebarNavItem>
                        </>
                    )}
                </nav>
            </div>
        </aside>
    );
}

function SidebarNavItem({ name, icon: Icon, badge, isSidebarOpen, isOpen, onClick, children }) {
    return (
        <div className="space-y-1">
            <button
                type="button"
                onClick={onClick}
                className={`w-full flex items-center p-3 rounded-xl transition-all duration-200 border-none group relative text-blue-200 hover:bg-white/10 hover:text-white cursor-pointer ${isSidebarOpen ? 'gap-3' : 'justify-center'}`}
            >
                <Icon size={20} className="text-blue-400 group-hover:text-white shrink-0" />

                {isSidebarOpen && (
                    <>
                        <span className="font-bold text-sm uppercase tracking-wider text-left leading-tight flex-1 animate-in fade-in duration-200">
                            {name}
                        </span>
                        {badge > 0 && (
                            <span className="bg-red-500 text-white text-[10px] font-black px-2 py-0.5 rounded-full animate-pulse mr-1">
                                {badge}
                            </span>
                        )}
                        <ChevronDown size={14} className={`transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`} />
                    </>
                )}
            </button>

            {isOpen && isSidebarOpen && (
                <div className="ml-4 mt-1 space-y-1 pl-2 animate-in slide-in-from-top-1 duration-200">
                    {children}
                </div>
            )}
        </div>
    );
}
