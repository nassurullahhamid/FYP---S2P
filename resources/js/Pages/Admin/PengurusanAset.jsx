import React, { useMemo, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Boxes,
    CheckCircle2,
    CircleX,
    Cpu,
    Edit3,
    HardDrive,
    Laptop,
    Monitor,
    Plus,
    Printer,
    Search,
    Trash2,
    Tv,
    X,
} from 'lucide-react';

import Sidebar from '@/Components/Sidebar';
import Topbar from '@/Components/Topbar';

const ASSET_TYPES = [
    'Komputer Riba',
    'Komputer Meja',
    'Printer',
    'TV',
    'Skrin Projektor',
    'Projektor',
];

const COMPUTER_TYPES = ['Komputer Riba', 'Komputer Meja'];

function Modal({ open, title, children, onClose, maxWidth = 'max-w-3xl' }) {
    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <button
                type="button"
                aria-label="Tutup modal"
                className="absolute inset-0 bg-slate-950/50 backdrop-blur-sm"
                onClick={onClose}
            />

            <div
                className={`relative z-10 w-full ${maxWidth} max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl`}
            >
                <div className="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4">
                    <h2 className="text-lg font-bold text-slate-800">
                        {title}
                    </h2>

                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                    >
                        <X size={20} />
                    </button>
                </div>

                <div className="p-6">{children}</div>
            </div>
        </div>
    );
}

function FormField({
    label,
    error,
    required = false,
    children,
}) {
    return (
        <div>
            <label className="mb-1.5 block text-sm font-semibold text-slate-700">
                {label}
                {required && (
                    <span className="ml-1 text-red-500">*</span>
                )}
            </label>

            {children}

            {error && (
                <p className="mt-1 text-sm text-red-600">
                    {error}
                </p>
            )}
        </div>
    );
}

function StatCard({ title, value, icon: Icon, description }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-sm font-medium text-slate-500">
                        {title}
                    </p>

                    <p className="mt-2 text-3xl font-bold text-slate-800">
                        {value}
                    </p>

                    {description && (
                        <p className="mt-1 text-xs text-slate-500">
                            {description}
                        </p>
                    )}
                </div>

                <div className="rounded-xl bg-slate-100 p-3 text-slate-700">
                    <Icon size={22} />
                </div>
            </div>
        </div>
    );
}

function AssetIcon({ type }) {
    if (type === 'Komputer Riba') {
        return <Laptop size={18} />;
    }

    if (type === 'Komputer Meja') {
        return <Monitor size={18} />;
    }

    if (type === 'Printer') {
        return <Printer size={18} />;
    }

    if (type === 'TV') {
        return <Tv size={18} />;
    }

    return <Boxes size={18} />;
}

function StatusBadge({ status }) {
    const available = status === 'Tersedia';
    const borrowed = status === 'Dipinjam';

    let className =
        'bg-slate-100 text-slate-700 border-slate-200';

    if (available) {
        className =
            'bg-emerald-50 text-emerald-700 border-emerald-200';
    }

    if (borrowed) {
        className =
            'bg-amber-50 text-amber-700 border-amber-200';
    }

    return (
        <span
            className={`inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold ${className}`}
        >
            {status || 'Tidak diketahui'}
        </span>
    );
}

function ComputerSpecificationFields({
    data,
    setData,
    errors,
}) {
    return (
        <div className="mt-2 rounded-xl border border-blue-100 bg-blue-50/50 p-4">
            <div className="mb-4 flex items-center gap-2">
                <Cpu size={18} className="text-blue-700" />

                <div>
                    <h3 className="text-sm font-bold text-slate-800">
                        Spesifikasi Komputer
                    </h3>
                    <p className="text-xs text-slate-500">
                        Wajib untuk Komputer Riba dan Komputer Meja.
                    </p>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <FormField
                    label="CPU / Processor"
                    required
                    error={errors.cpu}
                >
                    <input
                        type="text"
                        value={data.cpu}
                        onChange={(e) => setData('cpu', e.target.value)}
                        placeholder="Contoh: Intel Core i5-12400"
                        className="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    />
                </FormField>

                <FormField
                    label="RAM"
                    required
                    error={errors.ram}
                >
                    <input
                        type="text"
                        value={data.ram}
                        onChange={(e) => setData('ram', e.target.value)}
                        placeholder="Contoh: 16GB DDR4"
                        className="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    />
                </FormField>

                <FormField
                    label="Hard Disk / Storage"
                    required
                    error={errors.hard_disk}
                >
                    <input
                        type="text"
                        value={data.hard_disk}
                        onChange={(e) =>
                            setData('hard_disk', e.target.value)
                        }
                        placeholder="Contoh: 512GB SSD"
                        className="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    />
                </FormField>

                <FormField
                    label="Sistem Operasi"
                    required
                    error={errors.os}
                >
                    <input
                        type="text"
                        value={data.os}
                        onChange={(e) => setData('os', e.target.value)}
                        placeholder="Contoh: Windows 11 Pro"
                        className="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    />
                </FormField>
            </div>
        </div>
    );
}

export default function PengurusanAset({
    assets = [],
    summary = [],
    users = [],
}) {
    const { flash = {} } = usePage().props;

    const [search, setSearch] = useState('');
    const [typeFilter, setTypeFilter] = useState('');
    const [statusFilter, setStatusFilter] = useState('');

    const [createOpen, setCreateOpen] = useState(false);
    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [selectedAsset, setSelectedAsset] = useState(null);

    const createForm = useForm({
        serial_no: '',
        nama_aset: '',
        model: '',
        cpu: '',
        ram: '',
        hard_disk: '',
        os: '',
    });

    const editForm = useForm({
        nama_aset: '',
        model: '',
        pengguna_ic: '',
        cpu: '',
        ram: '',
        hard_disk: '',
        os: '',
    });

    const filteredAssets = useMemo(() => {
        const keyword = search.trim().toLowerCase();

        return assets.filter((asset) => {
            const matchesSearch =
                !keyword ||
                String(asset.serial_no ?? '')
                    .toLowerCase()
                    .includes(keyword) ||
                String(asset.nama_aset ?? '')
                    .toLowerCase()
                    .includes(keyword) ||
                String(asset.model ?? '')
                    .toLowerCase()
                    .includes(keyword) ||
                String(asset.cpu ?? '')
                    .toLowerCase()
                    .includes(keyword) ||
                String(asset.ram ?? '')
                    .toLowerCase()
                    .includes(keyword) ||
                String(asset.os ?? '')
                    .toLowerCase()
                    .includes(keyword);

            const matchesType =
                !typeFilter || asset.nama_aset === typeFilter;

            const matchesStatus =
                !statusFilter || asset.status === statusFilter;

            return matchesSearch && matchesType && matchesStatus;
        });
    }, [assets, search, typeFilter, statusFilter]);

    const statistics = useMemo(() => {
        const total = assets.length;

        const available = assets.filter(
            (asset) => asset.status === 'Tersedia',
        ).length;

        const borrowed = assets.filter(
            (asset) => asset.status === 'Dipinjam',
        ).length;

        const computer = assets.filter((asset) =>
            COMPUTER_TYPES.includes(asset.nama_aset),
        ).length;

        return {
            total,
            available,
            borrowed,
            computer,
        };
    }, [assets]);

    const openCreateModal = () => {
        createForm.reset();
        createForm.clearErrors();
        setCreateOpen(true);
    };

    const closeCreateModal = () => {
        setCreateOpen(false);
        createForm.reset();
        createForm.clearErrors();
    };

    const handleCreateTypeChange = (value) => {
        createForm.setData('nama_aset', value);

        if (!COMPUTER_TYPES.includes(value)) {
            createForm.setData((current) => ({
                ...current,
                nama_aset: value,
                cpu: '',
                ram: '',
                hard_disk: '',
                os: '',
            }));
        }
    };

    const submitCreate = (e) => {
        e.preventDefault();

        createForm.post(route('assets.store'), {
            preserveScroll: true,
            onSuccess: () => {
                closeCreateModal();
            },
        });
    };

    const openEditModal = (asset) => {
        setSelectedAsset(asset);

        editForm.setData({
            nama_aset: asset.nama_aset ?? '',
            model: asset.model ?? '',
            pengguna_ic: asset.pengguna_ic ?? '',
            cpu: asset.cpu ?? '',
            ram: asset.ram ?? '',
            hard_disk: asset.hard_disk ?? '',
            os: asset.os ?? '',
        });

        editForm.clearErrors();
        setEditOpen(true);
    };

    const closeEditModal = () => {
        setEditOpen(false);
        setSelectedAsset(null);
        editForm.reset();
        editForm.clearErrors();
    };

    const handleEditTypeChange = (value) => {
        editForm.setData('nama_aset', value);

        if (!COMPUTER_TYPES.includes(value)) {
            editForm.setData((current) => ({
                ...current,
                nama_aset: value,
                cpu: '',
                ram: '',
                hard_disk: '',
                os: '',
            }));
        }
    };

    const submitEdit = (e) => {
        e.preventDefault();

        if (!selectedAsset) return;

        editForm.patch(
            route('assets.update', {
                serial_no: selectedAsset.serial_no,
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    closeEditModal();
                },
            },
        );
    };

    const openDeleteModal = (asset) => {
        setSelectedAsset(asset);
        setDeleteOpen(true);
    };

    const closeDeleteModal = () => {
        setDeleteOpen(false);
        setSelectedAsset(null);
    };

    const confirmDelete = () => {
        if (!selectedAsset) return;

        router.delete(
            route('assets.destroy', {
                serial_no: selectedAsset.serial_no,
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    closeDeleteModal();
                },
            },
        );
    };

    return (
        <>
            <Head title="Pengurusan Aset" />

            <div className="flex min-h-screen bg-gray-100">
                <Sidebar />

                <div className="min-w-0 flex-1">
                    <Topbar title="Pengurusan Aset" />

                    <main className="p-4 sm:p-6 lg:p-8">
                        <div className="mx-auto max-w-7xl">
                            <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <h1 className="text-2xl font-bold tracking-tight text-slate-800 sm:text-3xl">
                                        Pengurusan Aset
                                    </h1>

                                    <p className="mt-1 text-sm text-slate-500">
                                        Pengurusan rekod dan inventori aset S2P.
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    onClick={openCreateModal}
                                    className="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800"
                                >
                                    <Plus size={18} />
                                    Tambah Aset
                                </button>
                            </div>

                            {flash.success && (
                                <div className="mb-6 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                                    <CheckCircle2
                                        size={20}
                                        className="mt-0.5 shrink-0"
                                    />
                                    <span>{flash.success}</span>
                                </div>
                            )}

                            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                <StatCard
                                    title="Jumlah Aset"
                                    value={statistics.total}
                                    icon={Boxes}
                                    description="Keseluruhan inventori"
                                />

                                <StatCard
                                    title="Tersedia"
                                    value={statistics.available}
                                    icon={CheckCircle2}
                                    description="Boleh digunakan atau dipinjam"
                                />

                                <StatCard
                                    title="Dipinjam"
                                    value={statistics.borrowed}
                                    icon={HardDrive}
                                    description="Sedang dalam peminjaman"
                                />

                                <StatCard
                                    title="Aset Komputer"
                                    value={statistics.computer}
                                    icon={Monitor}
                                    description="Komputer riba dan meja"
                                />
                            </div>

                            {summary.length > 0 && (
                                <div className="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <div className="mb-4">
                                        <h2 className="font-bold text-slate-800">
                                            Ringkasan Mengikut Kategori
                                        </h2>
                                        <p className="text-sm text-slate-500">
                                            Ringkasan inventori berdasarkan jenis aset.
                                        </p>
                                    </div>

                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                        {summary.map((item) => (
                                            <div
                                                key={item.nama_aset}
                                                className="rounded-xl border border-slate-200 bg-slate-50 p-4"
                                            >
                                                <div className="flex items-center gap-2 font-semibold text-slate-800">
                                                    <AssetIcon
                                                        type={item.nama_aset}
                                                    />
                                                    {item.nama_aset}
                                                </div>

                                                <div className="mt-3 grid grid-cols-3 gap-2 text-center">
                                                    <div>
                                                        <p className="text-lg font-bold text-slate-800">
                                                            {item.jumlah}
                                                        </p>
                                                        <p className="text-xs text-slate-500">
                                                            Jumlah
                                                        </p>
                                                    </div>

                                                    <div>
                                                        <p className="text-lg font-bold text-emerald-700">
                                                            {item.baki}
                                                        </p>
                                                        <p className="text-xs text-slate-500">
                                                            Tersedia
                                                        </p>
                                                    </div>

                                                    <div>
                                                        <p className="text-lg font-bold text-amber-700">
                                                            {item.dipinjam}
                                                        </p>
                                                        <p className="text-xs text-slate-500">
                                                            Dipinjam
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                                <div className="border-b border-slate-200 p-4 sm:p-5">
                                    <div className="grid grid-cols-1 gap-3 lg:grid-cols-12">
                                        <div className="relative lg:col-span-6">
                                            <Search
                                                size={18}
                                                className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
                                            />

                                            <input
                                                type="text"
                                                value={search}
                                                onChange={(e) =>
                                                    setSearch(e.target.value)
                                                }
                                                placeholder="Cari no. siri, kategori, model atau spesifikasi..."
                                                className="w-full rounded-xl border-slate-300 py-2.5 pl-10 pr-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            />
                                        </div>

                                        <div className="lg:col-span-3">
                                            <select
                                                value={typeFilter}
                                                onChange={(e) =>
                                                    setTypeFilter(e.target.value)
                                                }
                                                className="w-full rounded-xl border-slate-300 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            >
                                                <option value="">
                                                    Semua Kategori
                                                </option>

                                                {ASSET_TYPES.map((type) => (
                                                    <option
                                                        key={type}
                                                        value={type}
                                                    >
                                                        {type}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>

                                        <div className="lg:col-span-3">
                                            <select
                                                value={statusFilter}
                                                onChange={(e) =>
                                                    setStatusFilter(
                                                        e.target.value,
                                                    )
                                                }
                                                className="w-full rounded-xl border-slate-300 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            >
                                                <option value="">
                                                    Semua Status
                                                </option>
                                                <option value="Tersedia">
                                                    Tersedia
                                                </option>
                                                <option value="Dipinjam">
                                                    Dipinjam
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-slate-200">
                                        <thead className="bg-slate-50">
                                            <tr>
                                                <th className="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                                                    No. Siri
                                                </th>
                                                <th className="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                                                    Aset
                                                </th>
                                                <th className="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                                                    Model
                                                </th>
                                                <th className="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                                                    Spesifikasi
                                                </th>
                                                <th className="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                                                    Status
                                                </th>
                                                <th className="px-5 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">
                                                    Tindakan
                                                </th>
                                            </tr>
                                        </thead>

                                        <tbody className="divide-y divide-slate-100 bg-white">
                                            {filteredAssets.length === 0 ? (
                                                <tr>
                                                    <td
                                                        colSpan="6"
                                                        className="px-5 py-12 text-center"
                                                    >
                                                        <Boxes
                                                            size={36}
                                                            className="mx-auto mb-3 text-slate-300"
                                                        />

                                                        <p className="font-semibold text-slate-600">
                                                            Tiada aset ditemui
                                                        </p>

                                                        <p className="mt-1 text-sm text-slate-400">
                                                            Cuba ubah carian atau penapis.
                                                        </p>
                                                    </td>
                                                </tr>
                                            ) : (
                                                filteredAssets.map((asset) => (
                                                    <tr
                                                        key={asset.serial_no}
                                                        className="transition hover:bg-slate-50"
                                                    >
                                                        <td className="whitespace-nowrap px-5 py-4 text-sm font-semibold text-slate-800">
                                                            {asset.serial_no}
                                                        </td>

                                                        <td className="px-5 py-4">
                                                            <div className="flex items-center gap-2 text-sm font-medium text-slate-700">
                                                                <span className="text-slate-500">
                                                                    <AssetIcon
                                                                        type={
                                                                            asset.nama_aset
                                                                        }
                                                                    />
                                                                </span>
                                                                {
                                                                    asset.nama_aset
                                                                }
                                                            </div>
                                                        </td>

                                                        <td className="px-5 py-4 text-sm text-slate-600">
                                                            {asset.model || '-'}
                                                        </td>

                                                        <td className="px-5 py-4 text-sm text-slate-600">
                                                            {COMPUTER_TYPES.includes(
                                                                asset.nama_aset,
                                                            ) ? (
                                                                <div className="space-y-0.5 text-xs">
                                                                    <p>
                                                                        <span className="font-semibold">
                                                                            CPU:
                                                                        </span>{' '}
                                                                        {asset.cpu ||
                                                                            '-'}
                                                                    </p>
                                                                    <p>
                                                                        <span className="font-semibold">
                                                                            RAM:
                                                                        </span>{' '}
                                                                        {asset.ram ||
                                                                            '-'}
                                                                    </p>
                                                                    <p>
                                                                        <span className="font-semibold">
                                                                            Storage:
                                                                        </span>{' '}
                                                                        {asset.hard_disk ||
                                                                            '-'}
                                                                    </p>
                                                                    <p>
                                                                        <span className="font-semibold">
                                                                            OS:
                                                                        </span>{' '}
                                                                        {asset.os ||
                                                                            '-'}
                                                                    </p>
                                                                </div>
                                                            ) : (
                                                                <span className="text-slate-400">
                                                                    —
                                                                </span>
                                                            )}
                                                        </td>

                                                        <td className="px-5 py-4">
                                                            <StatusBadge
                                                                status={
                                                                    asset.status
                                                                }
                                                            />
                                                        </td>

                                                        <td className="whitespace-nowrap px-5 py-4 text-right">
                                                            <div className="flex justify-end gap-2">
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        openEditModal(
                                                                            asset,
                                                                        )
                                                                    }
                                                                    title="Edit aset"
                                                                    className="rounded-lg border border-slate-200 p-2 text-blue-700 transition hover:border-blue-200 hover:bg-blue-50"
                                                                >
                                                                    <Edit3
                                                                        size={
                                                                            17
                                                                        }
                                                                    />
                                                                </button>

                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        openDeleteModal(
                                                                            asset,
                                                                        )
                                                                    }
                                                                    title="Padam aset"
                                                                    className="rounded-lg border border-slate-200 p-2 text-red-600 transition hover:border-red-200 hover:bg-red-50"
                                                                >
                                                                    <Trash2
                                                                        size={
                                                                            17
                                                                        }
                                                                    />
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))
                                            )}
                                        </tbody>
                                    </table>
                                </div>

                                <div className="border-t border-slate-200 bg-slate-50 px-5 py-3 text-sm text-slate-500">
                                    Memaparkan{' '}
                                    <span className="font-semibold text-slate-700">
                                        {filteredAssets.length}
                                    </span>{' '}
                                    daripada{' '}
                                    <span className="font-semibold text-slate-700">
                                        {assets.length}
                                    </span>{' '}
                                    aset.
                                </div>
                            </div>
                        </div>
                    </main>
                </div>
            </div>

            <Modal
                open={createOpen}
                title="Tambah Aset Baharu"
                onClose={closeCreateModal}
            >
                <form onSubmit={submitCreate} className="space-y-5">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <FormField
                            label="Nombor Siri"
                            required
                            error={createForm.errors.serial_no}
                        >
                            <input
                                type="text"
                                value={createForm.data.serial_no}
                                onChange={(e) =>
                                    createForm.setData(
                                        'serial_no',
                                        e.target.value,
                                    )
                                }
                                placeholder="Masukkan nombor siri aset"
                                className="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                        </FormField>

                        <FormField
                            label="Kategori Aset"
                            required
                            error={createForm.errors.nama_aset}
                        >
                            <select
                                value={createForm.data.nama_aset}
                                onChange={(e) =>
                                    handleCreateTypeChange(e.target.value)
                                }
                                className="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                                <option value="">
                                    Pilih kategori aset
                                </option>

                                {ASSET_TYPES.map((type) => (
                                    <option key={type} value={type}>
                                        {type}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                    </div>

                    <FormField
                        label="Model"
                        required
                        error={createForm.errors.model}
                    >
                        <input
                            type="text"
                            value={createForm.data.model}
                            onChange={(e) =>
                                createForm.setData('model', e.target.value)
                            }
                            placeholder="Masukkan model aset"
                            className="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        />
                    </FormField>

                    {COMPUTER_TYPES.includes(
                        createForm.data.nama_aset,
                    ) && (
                        <ComputerSpecificationFields
                            data={createForm.data}
                            setData={createForm.setData}
                            errors={createForm.errors}
                        />
                    )}

                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                        Status aset baharu akan ditetapkan secara automatik
                        sebagai{' '}
                        <span className="font-bold text-emerald-700">
                            Tersedia
                        </span>
                        .
                    </div>

                    <div className="flex justify-end gap-3 border-t border-slate-200 pt-5">
                        <button
                            type="button"
                            onClick={closeCreateModal}
                            className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                        >
                            Batal
                        </button>

                        <button
                            type="submit"
                            disabled={createForm.processing}
                            className="rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {createForm.processing
                                ? 'Menyimpan...'
                                : 'Simpan Aset'}
                        </button>
                    </div>
                </form>
            </Modal>

            <Modal
                open={editOpen}
                title={
                    selectedAsset
                        ? `Edit Aset — ${selectedAsset.serial_no}`
                        : 'Edit Aset'
                }
                onClose={closeEditModal}
            >
                <form onSubmit={submitEdit} className="space-y-5">
                    {selectedAsset && (
                        <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Nombor Siri
                            </p>
                            <p className="mt-1 font-bold text-slate-800">
                                {selectedAsset.serial_no}
                            </p>
                        </div>
                    )}

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <FormField
                            label="Kategori Aset"
                            required
                            error={editForm.errors.nama_aset}
                        >
                            <select
                                value={editForm.data.nama_aset}
                                onChange={(e) =>
                                    handleEditTypeChange(e.target.value)
                                }
                                className="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                                <option value="">
                                    Pilih kategori aset
                                </option>

                                {ASSET_TYPES.map((type) => (
                                    <option key={type} value={type}>
                                        {type}
                                    </option>
                                ))}
                            </select>
                        </FormField>

                        <FormField
                            label="Model"
                            required
                            error={editForm.errors.model}
                        >
                            <input
                                type="text"
                                value={editForm.data.model}
                                onChange={(e) =>
                                    editForm.setData(
                                        'model',
                                        e.target.value,
                                    )
                                }
                                placeholder="Masukkan model aset"
                                className="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                        </FormField>
                    </div>

                    <FormField
                        label="Pegawai / Pengguna"
                        error={editForm.errors.pengguna_ic}
                    >
                        <select
                            value={editForm.data.pengguna_ic}
                            onChange={(e) =>
                                editForm.setData(
                                    'pengguna_ic',
                                    e.target.value,
                                )
                            }
                            className="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                            <option value="">
                                Tiada pengguna ditetapkan
                            </option>

                            {users.map((user) => (
                                <option
                                    key={user.no_ic}
                                    value={user.no_ic}
                                >
                                    {user.nama}
                                </option>
                            ))}
                        </select>
                    </FormField>

                    {COMPUTER_TYPES.includes(editForm.data.nama_aset) && (
                        <ComputerSpecificationFields
                            data={editForm.data}
                            setData={editForm.setData}
                            errors={editForm.errors}
                        />
                    )}

                    <div className="flex justify-end gap-3 border-t border-slate-200 pt-5">
                        <button
                            type="button"
                            onClick={closeEditModal}
                            className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                        >
                            Batal
                        </button>

                        <button
                            type="submit"
                            disabled={editForm.processing}
                            className="rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {editForm.processing
                                ? 'Mengemaskini...'
                                : 'Simpan Perubahan'}
                        </button>
                    </div>
                </form>
            </Modal>

            <Modal
                open={deleteOpen}
                title="Padam Aset"
                onClose={closeDeleteModal}
                maxWidth="max-w-lg"
            >
                <div>
                    <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100 text-red-600">
                        <CircleX size={24} />
                    </div>

                    <h3 className="text-lg font-bold text-slate-800">
                        Adakah anda pasti?
                    </h3>

                    <p className="mt-2 text-sm leading-6 text-slate-600">
                        Rekod aset{' '}
                        <span className="font-bold text-slate-800">
                            {selectedAsset?.serial_no}
                        </span>{' '}
                        akan dipadamkan daripada inventori. Tindakan ini
                        tidak boleh dibatalkan melalui halaman ini.
                    </p>

                    {selectedAsset?.status === 'Dipinjam' && (
                        <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                            Perhatian: aset ini mempunyai status{' '}
                            <strong>Dipinjam</strong>. Pastikan rekod
                            peminjaman telah disemak sebelum meneruskan.
                        </div>
                    )}

                    <div className="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={closeDeleteModal}
                            className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                        >
                            Batal
                        </button>

                        <button
                            type="button"
                            onClick={confirmDelete}
                            className="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700"
                        >
                            <Trash2 size={17} />
                            Padam Aset
                        </button>
                    </div>
                </div>
            </Modal>
        </>
    );
}
