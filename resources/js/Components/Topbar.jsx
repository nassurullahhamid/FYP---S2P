import React from 'react';
import Dropdown from '@/Components/Dropdown';
import { usePage, router } from '@inertiajs/react';
import axios from 'axios';
import {
    User, Settings, LogOut, ChevronDown, Bell, MessageSquare, FileCheck
} from 'lucide-react';

export default function Topbar({ title = "Dashboard" }) {
    // Access authenticated user and global props from Inertia context
    const { auth } = usePage().props;
    const user = auth?.user;
    const currentRole = user?.peranan || user?.role;

    // Filter notifications based on the current user's role
    const notifications = (auth?.notifications || []).filter(noti => {
        const notiData = typeof noti.data === 'string' ? JSON.parse(noti.data) : noti.data;

        if (!notiData?.target_role) return true;

        if (Array.isArray(notiData.target_role)) {
            return notiData.target_role.includes(currentRole);
        }

        return notiData.target_role === currentRole;
    });

    // Handle notification click, mark as read via API, and navigate
    const handleNotificationClick = async (e, n) => {
        e.preventDefault();

        const notiData = typeof n.data === 'string' ? JSON.parse(n.data) : n.data;

        try {
            // Mark notification as read
            await axios.post(route('api.notifications.read', { id: n.id }));

            // Reload Inertia auth state and navigate to target route
            router.reload({
                only: ['auth'],
                onSuccess: () => {
                    if (notiData?.url) {
                        router.get(notiData.url);
                    } else if (notiData?.id_tiket) {
                        router.get(route('tickets.show', { id_ticket: notiData.id_tiket }));
                    }
                }
            });

        } catch (err) {
            console.error("Failed to process notification:", err);
            // Fallback navigation if API request fails
            if (notiData?.url) {
                router.get(notiData.url);
            } else if (notiData?.id_tiket) {
                router.get(route('tickets.show', { id_ticket: notiData.id_tiket }));
            }
        }
    };

    return (
        <header className="bg-white shadow-sm h-20 flex items-center justify-between px-8 sticky top-0 z-10 w-full">
            {/* Page title and welcome header */}
            <div className="flex flex-col">
                <h1 className="text-xl font-black text-blue-900 uppercase tracking-tighter leading-none">{title}</h1>
                {title === "Dashboard" && (
                    <p className="text-sm font-bold text-gray-500 mt-1">
                        Selamat Datang, <span className="text-blue-600 uppercase">{user?.nama}</span>!
                    </p>
                )}
            </div>

            <div className="flex items-center gap-4 md:gap-6">
                {/* Notifications dropdown menu */}
                <Dropdown>
                    <Dropdown.Trigger>
                        <div className="relative cursor-pointer p-2.5 rounded-xl hover:bg-gray-50 transition-all border border-transparent hover:border-gray-100 group">
                            <Bell size={22} className={notifications.length > 0 ? "text-blue-600" : "text-gray-400"} />
                            {notifications.length > 0 && (
                                <span className="absolute top-1.5 right-1.5 w-4 h-4 bg-red-500 text-white text-[9px] flex items-center justify-center rounded-full border-2 border-white font-black animate-bounce">
                                    {notifications.length}
                                </span>
                            )}
                        </div>
                    </Dropdown.Trigger>

                    <Dropdown.Content align="right" width="80">
                        {/* Notification header */}
                        <div className="p-4 border-b border-gray-50 bg-gray-50/50 flex justify-between items-center">
                            <h4 className="text-xs font-black text-blue-900 uppercase tracking-widest">Notifikasi</h4>
                            {notifications.length > 0 && (
                                <span className="px-2 py-0.5 bg-blue-100 text-blue-700 text-[9px] font-bold rounded-full uppercase">
                                    {notifications.length} Baharu
                                </span>
                            )}
                        </div>

                        {/* Notification items list */}
                        <div className="max-h-64 overflow-y-auto divide-y divide-gray-50">
                        {notifications.length > 0 ? (
                            notifications.map((noti) => {
                                const notiData = typeof noti.data === 'string' ? JSON.parse(noti.data) : noti.data;
                                const isSemakan = notiData?.tajuk?.includes('Semakan');

                                return (
                                    <button
                                        key={noti.id}
                                        onClick={(e) => handleNotificationClick(e, noti)}
                                        className="w-full px-4 py-3 text-left hover:bg-blue-50/50 transition-colors flex items-start gap-3 focus:outline-none cursor-pointer group"
                                    >
                                        <div className={`p-2 rounded-lg mt-0.5 shrink-0 ${isSemakan ? 'bg-amber-50 text-amber-600 group-hover:bg-amber-100' : 'bg-blue-50 text-blue-600 group-hover:bg-blue-100'}`}>
                                            {isSemakan ? <FileCheck size={14} /> : <MessageSquare size={14} />}
                                        </div>

                                        <div className="flex-1 min-w-0 space-y-0.5">
                                            <p className="text-xs font-black text-gray-800 truncate">{notiData?.tajuk || 'Kemas Kini Tiket'}</p>
                                            <p className="text-[11px] text-gray-500 font-medium leading-normal line-clamp-2">{notiData?.pesanan}</p>
                                        </div>
                                    </button>
                                );
                            })
                        ) : (
                            <div className="p-6 text-center text-xs text-gray-400 font-medium italic">
                                Tiada notifikasi baru buat masa ini.
                            </div>
                        )}
                        </div>
                    </Dropdown.Content>
                </Dropdown>

                {/* User profile dropdown menu */}
                <Dropdown>
                    <Dropdown.Trigger>
                        <button className="flex items-center gap-3 p-1.5 rounded-2xl hover:bg-gray-50 transition focus:outline-none border border-gray-100 shadow-sm bg-white group min-h-[52px]">

                            {/* User Avatar Icon */}
                            <div className="h-10 w-10 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-colors shrink-0">
                                <User size={22} strokeWidth={2.5} />
                            </div>

                            {/* User details */}
                            <div className="text-left hidden sm:flex flex-col justify-center pr-1 max-w-[165px] min-w-[120px]">
                                <span className="font-black text-gray-800 uppercase tracking-tighter leading-tight text-sm">
                                    {user?.nama || 'Pengguna S2P'}
                                </span>

                                <span className="text-[9px] font-black text-blue-600 uppercase tracking-wide leading-tight mt-0.5 whitespace-normal break-words">
                                    {user?.jawatan || 'TIADA JAWATAN'}
                                </span>
                            </div>

                            <ChevronDown size={14} className="text-gray-400 mr-1 shrink-0" />
                        </button>
                    </Dropdown.Trigger>

                    <Dropdown.Content align="right" width="48">
                        <div className="block px-4 py-3 text-[10px] text-gray-400 font-black uppercase tracking-widest border-b border-gray-50">
                            Akaun Pengguna
                        </div>

                        {/* Profile Edit Link */}
                        <Dropdown.Link
                            href={route('profile.edit')}
                            className="flex items-center gap-2 py-3 font-bold text-gray-700"
                        >
                            <Settings size={14} className="text-gray-400" />
                            <span>Profil Saya</span>
                        </Dropdown.Link>

                        {/* Logout Action */}
                        <Dropdown.Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="flex items-center gap-2 py-3 text-red-600 font-black w-full text-left"
                        >
                            <LogOut size={14} />
                            <span>Log Keluar</span>
                        </Dropdown.Link>
                    </Dropdown.Content>
                </Dropdown>
            </div>
        </header>
    );
}
