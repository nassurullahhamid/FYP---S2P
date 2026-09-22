import React, { useMemo, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Search,
    UserPlus,
    Pencil,
    Trash2,
    Users,
    UserCheck,
    UserX,
    ShieldCheck,
    X,
    Save,
    AlertTriangle,
    Eye,
    EyeOff,
} from 'lucide-react';

import Sidebar from '@/Components/Sidebar';
import Topbar from '@/Components/Topbar';

const ROLE_OPTIONS = [
    { value: 'admin', label: 'Admin' },
    { value: 'ketua_upp', label: 'Ketua UPP' },
    { value: 'ketua_utd', label: 'Ketua UTD' },
    { value: 'juruteknik', label: 'Juruteknik' },
    { value: 'ketua_wilayah', label: 'Ketua Wilayah' },
];

const STATUS_OPTIONS = ['Aktif', 'Tidak Aktif'];
const JAWATAN_OPTIONS = [
    'Pegawai Teknologi Maklumat',
    'Penolong Pegawai Teknologi Maklumat',
    'Juruteknik Komputer',
    'Pembantu Tadbir',
    'Pembantu Khidmat Am',
];

const GRED_OPTIONS = [
    'F12', 'F10', 'F9', 'F7', 'F6', 'F5',
    'FT2', 'FT1', 'N2', 'N1', 'H1',
];

const EMPTY_FORM = {
    no_ic: '',
    nama: '',
    emel: '',
    no_telefon: '',
    jawatan: '',
    gred: '',
    peranan: 'juruteknik',
    status_pengguna: 'Aktif',
    password: '',
    password_confirmation: '',
};

function roleLabel(role) {
    return ROLE_OPTIONS.find((item) => item.value === role)?.label ?? role ?? '-';
}

function Modal({ open, title, subtitle, onClose, children, maxWidth = 'max-w-4xl' }) {
    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <button
                type="button"
                aria-label="Tutup modal"
                className="absolute inset-0 bg-slate-950/50 backdrop-blur-sm cursor-default"
                onClick={onClose}
            />

            <div
                className={`relative w-full ${maxWidth} max-h-[92vh] overflow-y-auto rounded-[2rem] bg-white shadow-2xl`}
            >
                <div className="sticky top-0 z-10 flex items-start justify-between border-b border-gray-100 bg-white px-6 py-5 md:px-8">
                    <div>
                        <h2 className="text-lg font-black uppercase tracking-tight text-blue-950">
                            {title}
                        </h2>
                        {subtitle && (
                            <p className="mt-1 text-xs font-medium text-gray-500">
                                {subtitle}
                            </p>
                        )}
                    </div>

                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-xl p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700"
                    >
                        <X size={20} />
                    </button>
                </div>

                {children}
            </div>
        </div>
    );
}

function FormField({ label, error, required = false, children }) {
    return (
        <div>
            <label className="mb-2 block text-[11px] font-black uppercase tracking-wider text-gray-600">
                {label}
                {required && <span className="ml-1 text-red-500">*</span>}
            </label>

            {children}

            {error && (
                <p className="mt-1.5 text-xs font-bold text-red-600">
                    {error}
                </p>
            )}
        </div>
    );
}

function StatCard({ icon: Icon, label, value }) {
    return (
        <div className="rounded-[2rem] border border-gray-100 bg-white p-5 shadow-sm">
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-[10px] font-black uppercase tracking-[0.16em] text-gray-400">
                        {label}
                    </p>
                    <p className="mt-2 text-3xl font-black tracking-tight text-blue-950">
                        {value}
                    </p>
                </div>

                <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                    <Icon size={23} />
                </div>
            </div>
        </div>
    );
}

export default function PengurusanPengguna({ users = [] }) {
    const { auth, flash = {}, errors: pageErrors = {} } = usePage().props;

    const currentUser = auth?.user;

    const [search, setSearch] = useState('');
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingUser, setEditingUser] = useState(null);
    const [deletingUser, setDeletingUser] = useState(null);

    const [showCreatePassword, setShowCreatePassword] = useState(false);
    const [showEditPassword, setShowEditPassword] = useState(false);

    const createForm = useForm(EMPTY_FORM);

    const editForm = useForm({
        nama: '',
        emel: '',
        no_telefon: '',
        jawatan: '',
        gred: '',
        peranan: '',
        status_pengguna: '',
        password: '',
        password_confirmation: '',
    });

    const filteredUsers = useMemo(() => {
        const keyword = search.trim().toLowerCase();

        if (!keyword) return users;

        return users.filter((user) => {
            const searchableValues = [
                user.no_ic,
                user.nama,
                user.emel,
                user.no_telefon,
                user.jawatan,
                user.gred,
                roleLabel(user.peranan),
                user.status_pengguna,
            ];

            return searchableValues.some((value) =>
                String(value ?? '').toLowerCase().includes(keyword)
            );
        });
    }, [search, users]);

    const stats = useMemo(() => {
        const total = users.length;

        const aktif = users.filter(
            (user) => user.status_pengguna === 'Aktif'
        ).length;

        const tidakAktif = users.filter(
            (user) => user.status_pengguna === 'Tidak Aktif'
        ).length;

        const admin = users.filter(
            (user) => user.peranan === 'admin'
        ).length;

        return {
            total,
            aktif,
            tidakAktif,
            admin,
        };
    }, [users]);

    const openCreateModal = () => {
        createForm.reset();
        createForm.clearErrors();
        setShowCreatePassword(false);
        setIsCreateOpen(true);
    };

    const closeCreateModal = () => {
        if (createForm.processing) return;

        setIsCreateOpen(false);
        createForm.reset();
        createForm.clearErrors();
        setShowCreatePassword(false);
    };

    const submitCreate = (event) => {
        event.preventDefault();

        createForm.post(route('users.store'), {
            preserveScroll: true,
            onSuccess: () => {
                setIsCreateOpen(false);
                createForm.reset();
                setShowCreatePassword(false);
            },
        });
    };

    const openEditModal = (user) => {
        setEditingUser(user);
        setShowEditPassword(false);
        editForm.clearErrors();

        editForm.setData({
            nama: user.nama ?? '',
            emel: user.emel ?? '',
            no_telefon: user.no_telefon ?? '',
            jawatan: user.jawatan ?? '',
            gred: user.gred ?? '',
            peranan: user.peranan ?? 'juruteknik',
            status_pengguna: user.status_pengguna ?? 'Aktif',
            password: '',
            password_confirmation: '',
        });
    };

    const closeEditModal = () => {
        if (editForm.processing) return;

        setEditingUser(null);
        editForm.reset();
        editForm.clearErrors();
        setShowEditPassword(false);
    };

    const submitEdit = (event) => {
        event.preventDefault();

        if (!editingUser) return;

        editForm.patch(
            route('users.update', { no_ic: editingUser.no_ic }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setEditingUser(null);
                    editForm.reset();
                    setShowEditPassword(false);
                },
            }
        );
    };

    const confirmDelete = () => {
        if (!deletingUser) return;

        router.delete(
            route('users.destroy', { no_ic: deletingUser.no_ic }),
            {
                preserveScroll: true,
                onSuccess: () => setDeletingUser(null),
                onError: () => {
                    setDeletingUser(null);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },
            }
        );
    };

    const inputClass =
        'w-full rounded-xl border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-700 shadow-sm transition focus:border-blue-500 focus:ring-blue-500';

    return (
        <>
            <Head title="Pengurusan Pengguna" />

            <div className="flex min-h-screen bg-gray-100">
                <Sidebar />

                <div className="min-w-0 flex-1">
                    <Topbar title="Pengurusan Pengguna" />

                    <main className="min-h-[calc(100vh-5rem)] bg-[#f8fafc] p-4 md:p-8">
                        <div className="mx-auto max-w-[1600px] space-y-6">

                            <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                                <div>
                                    <p className="text-xs font-black uppercase tracking-[0.18em] text-blue-600">
                                        Pentadbiran Sistem
                                    </p>

                                    <h2 className="mt-1 text-2xl font-black uppercase tracking-tight text-blue-950">
                                        Pengurusan Pengguna
                                    </h2>

                                    <p className="mt-1 max-w-2xl text-sm font-medium text-gray-500">
                                        Urus akaun, peranan dan status pengguna Sistem Pengurusan Perkhidmatan (S2P).
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    onClick={openCreateModal}
                                    className="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-xs font-black uppercase tracking-wider text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700"
                                >
                                    <UserPlus size={18} />
                                    Tambah Pengguna
                                </button>
                            </div>

                            {flash?.success && (
                                <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-700">
                                    {flash.success}
                                </div>
                            )}

                            {pageErrors?.sistem && (
                                <div className="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-bold text-red-700">
                                    <AlertTriangle size={18} className="mt-0.5 shrink-0" />
                                    <span>{pageErrors.sistem}</span>
                                </div>
                            )}

                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                <StatCard
                                    icon={Users}
                                    label="Jumlah Pengguna"
                                    value={stats.total}
                                />

                                <StatCard
                                    icon={UserCheck}
                                    label="Pengguna Aktif"
                                    value={stats.aktif}
                                />

                                <StatCard
                                    icon={UserX}
                                    label="Tidak Aktif"
                                    value={stats.tidakAktif}
                                />

                                <StatCard
                                    icon={ShieldCheck}
                                    label="Pentadbir"
                                    value={stats.admin}
                                />
                            </div>

                            <section className="overflow-hidden rounded-[2rem] border border-gray-100 bg-white shadow-sm">
                                <div className="flex flex-col justify-between gap-4 border-b border-gray-100 p-5 md:flex-row md:items-center md:p-6">
                                    <div>
                                        <h3 className="text-sm font-black uppercase tracking-wider text-blue-950">
                                            Senarai Pengguna
                                        </h3>
                                        <p className="mt-1 text-xs font-medium text-gray-400">
                                            {filteredUsers.length} daripada {users.length} rekod dipaparkan
                                        </p>
                                    </div>

                                    <div className="relative w-full md:w-80">
                                        <Search
                                            size={17}
                                            className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"
                                        />

                                        <input
                                            type="search"
                                            value={search}
                                            onChange={(event) => setSearch(event.target.value)}
                                            placeholder="Cari nama, IC, e-mel..."
                                            className="w-full rounded-xl border-gray-200 py-3 pl-11 pr-4 text-sm font-semibold text-gray-700 focus:border-blue-500 focus:ring-blue-500"
                                        />
                                    </div>
                                </div>

                                <div className="overflow-x-auto">
                                    <table className="min-w-full">
                                        <thead>
                                            <tr className="border-b border-gray-100 bg-gray-50/80 text-left">
                                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-500">
                                                    Pengguna
                                                </th>
                                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-500">
                                                    No. IC
                                                </th>
                                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-500">
                                                    Jawatan / Gred
                                                </th>
                                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-500">
                                                    Peranan
                                                </th>
                                                <th className="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-gray-500">
                                                    Status
                                                </th>
                                                <th className="px-6 py-4 text-right text-[10px] font-black uppercase tracking-widest text-gray-500">
                                                    Tindakan
                                                </th>
                                            </tr>
                                        </thead>

                                        <tbody className="divide-y divide-gray-50">
                                            {filteredUsers.length > 0 ? (
                                                filteredUsers.map((user) => {
                                                    const isCurrentUser =
                                                        currentUser?.no_ic === user.no_ic;

                                                    return (
                                                        <tr
                                                            key={user.no_ic}
                                                            className="transition hover:bg-blue-50/30"
                                                        >
                                                            <td className="px-6 py-4">
                                                                <div className="min-w-[210px]">
                                                                    <p className="text-sm font-black uppercase text-gray-800">
                                                                        {user.nama}
                                                                    </p>
                                                                    <p className="mt-1 text-xs font-medium text-gray-500">
                                                                        {user.emel}
                                                                    </p>
                                                                    <p className="mt-0.5 text-[11px] font-semibold text-gray-400">
                                                                        {user.no_telefon}
                                                                    </p>
                                                                </div>
                                                            </td>

                                                            <td className="whitespace-nowrap px-6 py-4 text-sm font-bold text-gray-600">
                                                                {user.no_ic}
                                                            </td>

                                                            <td className="px-6 py-4">
                                                                <p className="min-w-[150px] text-xs font-black uppercase text-gray-700">
                                                                    {user.jawatan}
                                                                </p>
                                                                <p className="mt-1 text-[11px] font-bold text-blue-600">
                                                                    Gred {user.gred}
                                                                </p>
                                                            </td>

                                                            <td className="px-6 py-4">
                                                                <span className="inline-flex whitespace-nowrap rounded-full bg-blue-50 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-blue-700">
                                                                    {roleLabel(user.peranan)}
                                                                </span>
                                                            </td>

                                                            <td className="px-6 py-4">
                                                                <span
                                                                    className={`inline-flex whitespace-nowrap rounded-full px-3 py-1.5 text-[10px] font-black uppercase tracking-wide ${
                                                                        user.status_pengguna === 'Aktif'
                                                                            ? 'bg-emerald-50 text-emerald-700'
                                                                            : 'bg-red-50 text-red-600'
                                                                    }`}
                                                                >
                                                                    {user.status_pengguna}
                                                                </span>
                                                            </td>

                                                            <td className="px-6 py-4">
                                                                <div className="flex justify-end gap-2">
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => openEditModal(user)}
                                                                        className="rounded-xl border border-blue-100 bg-blue-50 p-2.5 text-blue-600 transition hover:bg-blue-600 hover:text-white"
                                                                        title="Kemaskini pengguna"
                                                                    >
                                                                        <Pencil size={16} />
                                                                    </button>

                                                                    <button
                                                                        type="button"
                                                                        onClick={() => setDeletingUser(user)}
                                                                        disabled={isCurrentUser}
                                                                        className={`rounded-xl border p-2.5 transition ${
                                                                            isCurrentUser
                                                                                ? 'cursor-not-allowed border-gray-100 bg-gray-50 text-gray-300'
                                                                                : 'border-red-100 bg-red-50 text-red-600 hover:bg-red-600 hover:text-white'
                                                                        }`}
                                                                        title={
                                                                            isCurrentUser
                                                                                ? 'Akaun sendiri tidak boleh dipadam'
                                                                                : 'Padam pengguna'
                                                                        }
                                                                    >
                                                                        <Trash2 size={16} />
                                                                    </button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    );
                                                })
                                            ) : (
                                                <tr>
                                                    <td
                                                        colSpan="6"
                                                        className="px-6 py-16 text-center"
                                                    >
                                                        <Users
                                                            size={34}
                                                            className="mx-auto text-gray-300"
                                                        />
                                                        <p className="mt-3 text-sm font-black uppercase text-gray-400">
                                                            Tiada pengguna ditemui
                                                        </p>
                                                        <p className="mt-1 text-xs text-gray-400">
                                                            Cuba gunakan kata carian yang berbeza.
                                                        </p>
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        </div>
                    </main>
                </div>
            </div>

            {/* CREATE USER */}
            <Modal
                open={isCreateOpen}
                title="Tambah Pengguna"
                subtitle="Daftarkan akaun pengguna baharu ke dalam S2P."
                onClose={closeCreateModal}
            >
                <form onSubmit={submitCreate}>
                    <div className="grid gap-5 p-6 md:grid-cols-2 md:p-8">
                        <FormField
                            label="No. Kad Pengenalan"
                            error={createForm.errors.no_ic}
                            required
                        >
                            <input
                                type="text"
                                inputMode="numeric"
                                maxLength={12}
                                value={createForm.data.no_ic}
                                onChange={(event) =>
                                    createForm.setData(
                                        'no_ic',
                                        event.target.value.replace(/\D/g, '')
                                    )
                                }
                                className={inputClass}
                                placeholder="Contoh: 900101125555"
                            />
                        </FormField>

                        <FormField
                            label="Nama"
                            error={createForm.errors.nama}
                            required
                        >
                            <input
                                type="text"
                                value={createForm.data.nama}
                                onChange={(event) =>
                                    createForm.setData('nama', event.target.value)
                                }
                                className={inputClass}
                                placeholder="Contoh: ALI BIN ABU"
                            />
                        </FormField>

                        <FormField
                            label="E-mel"
                            error={createForm.errors.emel}
                            required
                        >
                            <input
                                type="email"
                                value={createForm.data.emel}
                                onChange={(event) =>
                                    createForm.setData('emel', event.target.value)
                                }
                                className={inputClass}
                                placeholder="Contoh : ali.abu@sabah.gov.my"
                            />
                        </FormField>

                        <FormField
                            label="No. Telefon"
                            error={createForm.errors.no_telefon}
                            required
                        >
                            <input
                                type="text"
                                value={createForm.data.no_telefon}
                                onChange={(event) =>
                                    createForm.setData('no_telefon', event.target.value)
                                }
                                className={inputClass}
                                placeholder="Contoh: 0123456789"
                            />
                        </FormField>

                        <FormField
                            label="Jawatan"
                            error={createForm.errors.jawatan}
                            required
                        >
                            <select
                                required
                                value={createForm.data.jawatan}
                                onChange={(event) =>
                                    createForm.setData('jawatan', event.target.value)
                                }
                                className={inputClass}
                            >
                                <option value="" disabled>
                                    -- Pilih Jawatan --
                                </option>
                                {JAWATAN_OPTIONS.map((jawatan) => (
                                    <option key={jawatan} value={jawatan}>
                                        {jawatan}
                                    </option>
                                ))}
                            </select>
                        </FormField>

                        <FormField
                            label="Gred"
                            error={createForm.errors.gred}
                            required
                        >
                            <select
                                required
                                value={createForm.data.gred}
                                onChange={(event) =>
                                    createForm.setData('gred', event.target.value)
                                }
                                className={inputClass}
                            >
                                <option value="" disabled>
                                    -- Pilih Gred --
                                </option>
                                {GRED_OPTIONS.map((gred) => (
                                    <option key={gred} value={gred}>
                                        {gred}
                                    </option>
                                ))}
                            </select>
                        </FormField>

                        <FormField
                            label="Peranan"
                            error={createForm.errors.peranan}
                            required
                        >
                            <select
                                value={createForm.data.peranan}
                                onChange={(event) =>
                                    createForm.setData('peranan', event.target.value)
                                }
                                className={inputClass}
                            >
                                {ROLE_OPTIONS.map((role) => (
                                    <option key={role.value} value={role.value}>
                                        {role.label}
                                    </option>
                                ))}
                            </select>
                        </FormField>

                        <FormField
                            label="Status Pengguna"
                            error={createForm.errors.status_pengguna}
                            required
                        >
                            <select
                                value={createForm.data.status_pengguna}
                                onChange={(event) =>
                                    createForm.setData(
                                        'status_pengguna',
                                        event.target.value
                                    )
                                }
                                className={inputClass}
                            >
                                {STATUS_OPTIONS.map((status) => (
                                    <option key={status} value={status}>
                                        {status}
                                    </option>
                                ))}
                            </select>
                        </FormField>

                        <FormField
                            label="Kata Laluan"
                            error={createForm.errors.password}
                            required
                        >
                            <div className="relative">
                                <input
                                    type={showCreatePassword ? 'text' : 'password'}
                                    value={createForm.data.password}
                                    onChange={(event) =>
                                        createForm.setData(
                                            'password',
                                            event.target.value
                                        )
                                    }
                                    className={`${inputClass} pr-12`}
                                    placeholder="Minimum 12 aksara: A-Z, a-z, nombor dan simbol"
                                />

                                <button
                                    type="button"
                                    onClick={() =>
                                        setShowCreatePassword((value) => !value)
                                    }
                                    className="absolute right-3 top-1/2 -translate-y-1/2 rounded-lg p-1.5 text-gray-400 hover:bg-gray-100"
                                >
                                    {showCreatePassword ? (
                                        <EyeOff size={17} />
                                    ) : (
                                        <Eye size={17} />
                                    )}
                                </button>
                            </div>
                        </FormField>

                        <FormField
                            label="Sahkan Kata Laluan"
                            required
                        >
                            <input
                                type={showCreatePassword ? 'text' : 'password'}
                                value={createForm.data.password_confirmation}
                                onChange={(event) =>
                                    createForm.setData(
                                        'password_confirmation',
                                        event.target.value
                                    )
                                }
                                className={inputClass}
                                placeholder="Masukkan semula kata laluan"
                            />
                        </FormField>
                    </div>

                    <div className="flex justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-5 md:px-8">
                        <button
                            type="button"
                            onClick={closeCreateModal}
                            disabled={createForm.processing}
                            className="rounded-xl border border-gray-200 bg-white px-5 py-3 text-xs font-black uppercase tracking-wider text-gray-600 transition hover:bg-gray-100"
                        >
                            Batal
                        </button>

                        <button
                            type="submit"
                            disabled={createForm.processing}
                            className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-xs font-black uppercase tracking-wider text-white transition hover:bg-blue-700 disabled:opacity-60"
                        >
                            <Save size={17} />
                            {createForm.processing
                                ? 'Menyimpan...'
                                : 'Simpan Pengguna'}
                        </button>
                    </div>
                </form>
            </Modal>

            {/* EDIT USER */}
            <Modal
                open={Boolean(editingUser)}
                title="Kemaskini Pengguna"
                subtitle={
                    editingUser
                        ? `No. Kad Pengenalan: ${editingUser.no_ic}`
                        : ''
                }
                onClose={closeEditModal}
            >
                <form onSubmit={submitEdit}>
                    <div className="grid gap-5 p-6 md:grid-cols-2 md:p-8">
                        <FormField
                            label="Nama"
                            error={editForm.errors.nama}
                            required
                        >
                            <input
                                type="text"
                                value={editForm.data.nama}
                                onChange={(event) =>
                                    editForm.setData('nama', event.target.value)
                                }
                                className={inputClass}
                            />
                        </FormField>

                        <FormField
                            label="E-mel"
                            error={editForm.errors.emel}
                            required
                        >
                            <input
                                type="email"
                                value={editForm.data.emel}
                                onChange={(event) =>
                                    editForm.setData('emel', event.target.value)
                                }
                                className={inputClass}
                            />
                        </FormField>

                        <FormField
                            label="No. Telefon"
                            error={editForm.errors.no_telefon}
                            required
                        >
                            <input
                                type="text"
                                value={editForm.data.no_telefon}
                                onChange={(event) =>
                                    editForm.setData(
                                        'no_telefon',
                                        event.target.value
                                    )
                                }
                                className={inputClass}
                            />
                        </FormField>

                        <FormField
                            label="Jawatan"
                            error={editForm.errors.jawatan}
                            required
                        >
                            <input
                                type="text"
                                value={editForm.data.jawatan}
                                onChange={(event) =>
                                    editForm.setData('jawatan', event.target.value)
                                }
                                className={inputClass}
                            />
                        </FormField>

                        <FormField
                            label="Gred"
                            error={editForm.errors.gred}
                            required
                        >
                            <input
                                type="text"
                                maxLength={10}
                                value={editForm.data.gred}
                                onChange={(event) =>
                                    editForm.setData('gred', event.target.value)
                                }
                                className={inputClass}
                            />
                        </FormField>

                        <FormField
                            label="Peranan"
                            error={editForm.errors.peranan}
                            required
                        >
                            <select
                                value={editForm.data.peranan}
                                onChange={(event) =>
                                    editForm.setData('peranan', event.target.value)
                                }
                                className={inputClass}
                            >
                                {ROLE_OPTIONS.map((role) => (
                                    <option key={role.value} value={role.value}>
                                        {role.label}
                                    </option>
                                ))}
                            </select>
                        </FormField>

                        <FormField
                            label="Status Pengguna"
                            error={editForm.errors.status_pengguna}
                            required
                        >
                            <select
                                value={editForm.data.status_pengguna}
                                onChange={(event) =>
                                    editForm.setData(
                                        'status_pengguna',
                                        event.target.value
                                    )
                                }
                                className={inputClass}
                            >
                                {STATUS_OPTIONS.map((status) => (
                                    <option key={status} value={status}>
                                        {status}
                                    </option>
                                ))}
                            </select>
                        </FormField>

                        <div className="hidden md:block" />

                        <FormField
                            label="Kata Laluan Baharu"
                            error={editForm.errors.password}
                        >
                            <div className="relative">
                                <input
                                    type={showEditPassword ? 'text' : 'password'}
                                    value={editForm.data.password}
                                    onChange={(event) =>
                                        editForm.setData(
                                            'password',
                                            event.target.value
                                        )
                                    }
                                    className={`${inputClass} pr-12`}
                                    placeholder="Kosongkan jika tidak mahu ditukar"
                                />

                                <button
                                    type="button"
                                    onClick={() =>
                                        setShowEditPassword((value) => !value)
                                    }
                                    className="absolute right-3 top-1/2 -translate-y-1/2 rounded-lg p-1.5 text-gray-400 hover:bg-gray-100"
                                >
                                    {showEditPassword ? (
                                        <EyeOff size={17} />
                                    ) : (
                                        <Eye size={17} />
                                    )}
                                </button>
                            </div>
                        </FormField>

                        <FormField label="Sahkan Kata Laluan Baharu">
                            <input
                                type={showEditPassword ? 'text' : 'password'}
                                value={editForm.data.password_confirmation}
                                onChange={(event) =>
                                    editForm.setData(
                                        'password_confirmation',
                                        event.target.value
                                    )
                                }
                                className={inputClass}
                                placeholder="Masukkan semula kata laluan baharu"
                            />
                        </FormField>
                    </div>

                    <div className="flex justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-5 md:px-8">
                        <button
                            type="button"
                            onClick={closeEditModal}
                            disabled={editForm.processing}
                            className="rounded-xl border border-gray-200 bg-white px-5 py-3 text-xs font-black uppercase tracking-wider text-gray-600 transition hover:bg-gray-100"
                        >
                            Batal
                        </button>

                        <button
                            type="submit"
                            disabled={editForm.processing}
                            className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-xs font-black uppercase tracking-wider text-white transition hover:bg-blue-700 disabled:opacity-60"
                        >
                            <Save size={17} />
                            {editForm.processing
                                ? 'Mengemaskini...'
                                : 'Simpan Perubahan'}
                        </button>
                    </div>
                </form>
            </Modal>

            {/* DELETE CONFIRMATION */}
            <Modal
                open={Boolean(deletingUser)}
                title="Padam Pengguna"
                subtitle="Tindakan ini akan memadam akaun pengguna daripada sistem."
                onClose={() => setDeletingUser(null)}
                maxWidth="max-w-lg"
            >
                <div className="p-6 md:p-8">
                    <div className="flex items-start gap-4 rounded-2xl border border-red-100 bg-red-50 p-5">
                        <div className="rounded-xl bg-red-100 p-2.5 text-red-600">
                            <AlertTriangle size={22} />
                        </div>

                        <div>
                            <p className="text-sm font-black uppercase text-gray-800">
                                Adakah anda pasti?
                            </p>

                            <p className="mt-2 text-sm leading-6 text-gray-600">
                                Akaun{' '}
                                <span className="font-black text-gray-800">
                                    {deletingUser?.nama}
                                </span>{' '}
                                dengan No. IC{' '}
                                <span className="font-black text-gray-800">
                                    {deletingUser?.no_ic}
                                </span>{' '}
                                akan dipadam.
                            </p>
                        </div>
                    </div>
                </div>

                <div className="flex justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-5 md:px-8">
                    <button
                        type="button"
                        onClick={() => setDeletingUser(null)}
                        className="rounded-xl border border-gray-200 bg-white px-5 py-3 text-xs font-black uppercase tracking-wider text-gray-600 transition hover:bg-gray-100"
                    >
                        Batal
                    </button>

                    <button
                        type="button"
                        onClick={confirmDelete}
                        className="inline-flex items-center gap-2 rounded-xl bg-red-600 px-5 py-3 text-xs font-black uppercase tracking-wider text-white transition hover:bg-red-700"
                    >
                        <Trash2 size={17} />
                        Padam Pengguna
                    </button>
                </div>
            </Modal>
        </>
    );
}
