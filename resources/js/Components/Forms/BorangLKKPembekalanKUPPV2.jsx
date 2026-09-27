import React, { useState } from 'react';
import {
    router,
    useForm,
} from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    ClipboardCheck,
    Plus,
    RefreshCw,
    Send,
    Trash2,
} from 'lucide-react';

const parseArray = (value, fallback = []) => {
    if (Array.isArray(value)) {
        return value;
    }

    if (typeof value === 'string' && value.trim() !== '') {
        try {
            const decoded = JSON.parse(value);

            return Array.isArray(decoded)
                ? decoded
                : fallback;
        } catch {
            return fallback;
        }
    }

    return fallback;
};

export default function BorangLKKPembekalanKUPPV2({
    ticket,
    auth,
}) {
    const currentUser = auth?.user || {};
    const report = ticket.laporan || {};

    const statusFormat = String(
        ticket.status_tiket || ''
    ).trim().toLowerCase();

    const isKUPP = [
        'ketua_upp',
        'ketua upp',
        'kupp',
    ].includes(
        String(
            currentUser.peranan
            || currentUser.role
            || ''
        ).trim().toLowerCase()
    );

    const isReportReview =
        statusFormat ===
        'menunggu semakan laporan';

    const isChiefCorrection =
        statusFormat === 'pembetulan ketua';

    const canEdit =
        isKUPP
        && (
            isReportReview
            || isChiefCorrection
        );

    const resultRows = parseArray(
        report.hasil_kajian
    );

    const initialCosts = parseArray(
        report.kos_items,
        []
    ).map((row) => ({
        jenis_peralatan: String(
            row.jenis_peralatan
            || row.item
            || ''
        ),
        kuantiti:
            row.kuantiti ?? 1,
        anggaran_kos:
            row.anggaran_kos
            ?? row.harga_seunit
            ?? 0,
        jumlah:
            row.jumlah ?? 0,
    }));

    const {
        data,
        setData,
        post,
        processing,
        errors,
    } = useForm({
        kos_items:
            initialCosts.length > 0
                ? initialCosts
                : [
                    {
                        jenis_peralatan: '',
                        kuantiti: 1,
                        anggaran_kos: 0,
                        jumlah: 0,
                    },
                ],
        rumusan: report.rumusan || '',
    });

    const [reviewNote, setReviewNote] =
        useState(ticket.ulasan_semakan || '');

    const [
        reviewProcessing,
        setReviewProcessing,
    ] = useState(false);

    const updateCost = (
        index,
        field,
        value
    ) => {
        const rows = data.kos_items.map(
            (row, rowIndex) => {
                if (rowIndex !== index) {
                    return row;
                }

                const updated = {
                    ...row,
                    [field]: value,
                };

                const quantity =
                    Number(updated.kuantiti) || 0;

                const unitCost =
                    Number(updated.anggaran_kos) || 0;

                updated.jumlah =
                    quantity * unitCost;

                return updated;
            }
        );

        setData('kos_items', rows);
    };

    const addCost = () => {
        setData(
            'kos_items',
            [
                ...data.kos_items,
                {
                    jenis_peralatan: '',
                    kuantiti: 1,
                    anggaran_kos: 0,
                    jumlah: 0,
                },
            ]
        );
    };

    const removeCost = (index) => {
        if (data.kos_items.length <= 1) {
            return;
        }

        setData(
            'kos_items',
            data.kos_items.filter(
                (_, rowIndex) =>
                    rowIndex !== index
            )
        );
    };

    const totalCost = data.kos_items.reduce(
        (total, row) =>
            total
            + (
                (Number(row.kuantiti) || 0)
                * (
                    Number(
                        row.anggaran_kos
                    ) || 0
                )
            ),
        0
    );

    const saveLkk = () => {
        if (!canEdit) {
            return;
        }

        post(
            route(
                'tickets.workflow.saveProcurementLkk',
                {
                    id_tiket: ticket.id_tiket,
                }
            ),
            {
                preserveScroll: true,
                onSuccess: () => {
                    alert(
                        'Anggaran kos dan rumusan ' +
                        'berjaya dikemaskini.'
                    );
                },
                onError: () => {
                    alert(
                        'Maklumat LKK tidak berjaya ' +
                        'disimpan. Sila semak borang.'
                    );
                },
            }
        );
    };

    const reviewLkk = (action) => {
        if (!canEdit || reviewProcessing) {
            return;
        }

        if (
            action === 'PEMBETULAN'
            && reviewNote.trim() === ''
        ) {
            alert(
                'Ulasan pembetulan mesti dinyatakan.'
            );

            return;
        }

        if (
            action === 'PEMBETULAN'
            && !isReportReview
        ) {
            alert(
                'Pembetulan kepada Juruteknik hanya ' +
                'boleh dibuat pada peringkat semakan laporan.'
            );

            return;
        }

        const confirmationMessage =
            action === 'VERIFIKASI'
                ? 'Hantar LKK ini kepada Ketua Wilayah untuk validasi?'
                : 'Kembalikan laporan ini kepada Juruteknik untuk pembetulan?';

        if (!window.confirm(confirmationMessage)) {
            return;
        }

        setReviewProcessing(true);

        router.post(
            route(
                'tickets.workflow.reviewProcurementLkk',
                {
                    id_tiket: ticket.id_tiket,
                }
            ),
            {
                tindakan: action,
                ulasan:
                    reviewNote.trim() || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    alert(
                        action === 'VERIFIKASI'
                            ? 'LKK berjaya diverifikasi dan dihantar kepada Ketua Wilayah.'
                            : 'Laporan dikembalikan kepada Juruteknik.'
                    );
                },
                onError: (responseErrors) => {
                    const messages =
                        Object.values(
                            responseErrors
                        ).join('\n');

                    alert(
                        messages
                        || 'Tindakan tidak berjaya.'
                    );
                },
                onFinish: () => {
                    setReviewProcessing(false);
                },
            }
        );
    };

    if (!isKUPP) {
        return (
            <div className="rounded-2xl border border-red-200 bg-red-50 p-5 text-xs font-semibold text-red-700">
                Hanya KUPP dibenarkan menyemak
                dan memverifikasi LKK Pembekalan.
            </div>
        );
    }

    return (
        <div className="space-y-5">
            {isChiefCorrection && (
                <div className="rounded-2xl border-2 border-amber-300 bg-amber-50 p-5 shadow-sm">
                    <div className="mb-2 flex items-center gap-2 text-amber-900">
                        <AlertTriangle
                            size={18}
                            className="text-amber-600"
                        />
                        <h4 className="text-xs font-black uppercase tracking-wider">
                            Arahan Pembetulan Ketua Wilayah
                        </h4>
                    </div>

                    <div className="whitespace-pre-wrap rounded-xl border border-amber-200 bg-white p-3 text-xs font-semibold leading-relaxed text-amber-950">
                        {ticket.ulasan_semakan ||
                            'Sila kemaskini LKK sebelum dihantar semula.'}
                    </div>
                </div>
            )}

            <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div className="flex items-center gap-2 bg-[#002b66] px-5 py-3.5 text-white">
                    <ClipboardCheck size={16} />
                    <h4 className="text-xs font-black uppercase tracking-wider">
                        Butiran Hasil Kajian Juruteknik
                    </h4>
                </div>

                <div className="overflow-x-auto p-5">
                    <table className="min-w-[900px] w-full border-collapse text-left">
                        <thead className="bg-slate-100 text-[10px] font-black uppercase tracking-wider text-slate-700">
                            <tr>
                                <th className="w-12 border border-gray-200 p-3 text-center">
                                    Bil.
                                </th>
                                <th className="border border-gray-200 p-3">
                                    Pemohon
                                </th>
                                <th className="border border-gray-200 p-3">
                                    Keadaan Semasa
                                </th>
                                <th className="border border-gray-200 p-3">
                                    Justifikasi dan Cadangan
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            {resultRows.length > 0 ? (
                                resultRows.map(
                                    (row, index) => (
                                        <tr
                                            key={`${row.nama_pemohon}-${index}`}
                                            className="align-top"
                                        >
                                            <td className="border border-gray-200 p-3 text-center text-xs font-black">
                                                {index + 1}
                                            </td>
                                            <td className="border border-gray-200 p-3">
                                                <div className="text-xs font-black text-slate-900">
                                                    {row.nama_pemohon ||
                                                        '—'}
                                                </div>
                                                <div className="mt-1 text-[10px] font-semibold text-slate-500">
                                                    {row.jawatan_pemohon ||
                                                        '—'}
                                                </div>
                                            </td>
                                            <td className="whitespace-pre-wrap border border-gray-200 p-3 text-xs font-semibold leading-relaxed">
                                                {row.keadaan_semasa ||
                                                    '—'}
                                            </td>
                                            <td className="whitespace-pre-wrap border border-gray-200 p-3 text-xs font-semibold leading-relaxed">
                                                {row.justifikasi_cadangan ||
                                                    '—'}
                                            </td>
                                        </tr>
                                    )
                                )
                            ) : (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="border border-gray-200 p-4 text-center text-xs font-semibold text-red-600"
                                    >
                                        Hasil kajian Juruteknik
                                        belum tersedia.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="overflow-hidden rounded-2xl border border-blue-200 bg-white shadow-sm">
                <div className="bg-blue-50 px-5 py-3.5">
                    <h4 className="text-xs font-black uppercase tracking-wider text-blue-900">
                        Anggaran Kos
                    </h4>
                </div>

                <div className="p-5">
                    <div className="overflow-x-auto rounded-xl border border-gray-200">
                        <table className="min-w-[760px] w-full border-collapse text-left">
                            <thead className="bg-slate-100 text-[10px] font-black uppercase tracking-wider text-slate-700">
                                <tr>
                                    <th className="border-r border-gray-200 p-3">
                                        Jenis Peralatan
                                    </th>
                                    <th className="w-28 border-r border-gray-200 p-3 text-center">
                                        Kuantiti
                                    </th>
                                    <th className="w-40 border-r border-gray-200 p-3 text-right">
                                        Kos Seunit (RM)
                                    </th>
                                    <th className="w-40 border-r border-gray-200 p-3 text-right">
                                        Jumlah (RM)
                                    </th>
                                    <th className="w-20 p-3 text-center">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-gray-100">
                                {data.kos_items.map(
                                    (row, index) => {
                                        const subtotal =
                                            (Number(
                                                row.kuantiti
                                            ) || 0)
                                            * (
                                                Number(
                                                    row.anggaran_kos
                                                ) || 0
                                            );

                                        return (
                                            <tr key={index}>
                                                <td className="border-r border-gray-100 p-2">
                                                    <input
                                                        type="text"
                                                        value={
                                                            row.jenis_peralatan
                                                        }
                                                        onChange={
                                                            (event) =>
                                                                updateCost(
                                                                    index,
                                                                    'jenis_peralatan',
                                                                    event.target.value
                                                                )
                                                        }
                                                        disabled={
                                                            processing
                                                            || reviewProcessing
                                                            || !canEdit
                                                        }
                                                        maxLength={1000}
                                                        required
                                                        className="h-10 w-full rounded-lg border-gray-200 text-xs font-semibold disabled:bg-gray-50"
                                                    />
                                                </td>

                                                <td className="border-r border-gray-100 p-2">
                                                    <input
                                                        type="number"
                                                        min="1"
                                                        step="1"
                                                        value={
                                                            row.kuantiti
                                                        }
                                                        onChange={
                                                            (event) =>
                                                                updateCost(
                                                                    index,
                                                                    'kuantiti',
                                                                    event.target.value
                                                                )
                                                        }
                                                        disabled={
                                                            processing
                                                            || reviewProcessing
                                                            || !canEdit
                                                        }
                                                        required
                                                        className="h-10 w-full rounded-lg border-gray-200 text-center text-xs font-black disabled:bg-gray-50"
                                                    />
                                                </td>

                                                <td className="border-r border-gray-100 p-2">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        value={
                                                            row.anggaran_kos
                                                        }
                                                        onChange={
                                                            (event) =>
                                                                updateCost(
                                                                    index,
                                                                    'anggaran_kos',
                                                                    event.target.value
                                                                )
                                                        }
                                                        disabled={
                                                            processing
                                                            || reviewProcessing
                                                            || !canEdit
                                                        }
                                                        required
                                                        className="h-10 w-full rounded-lg border-gray-200 text-right text-xs font-black disabled:bg-gray-50"
                                                    />
                                                </td>

                                                <td className="border-r border-gray-100 bg-slate-50 p-3 text-right text-xs font-black text-blue-900">
                                                    {subtotal.toLocaleString(
                                                        'en-MY',
                                                        {
                                                            minimumFractionDigits: 2,
                                                            maximumFractionDigits: 2,
                                                        }
                                                    )}
                                                </td>

                                                <td className="p-2 text-center">
                                                    <button
                                                        type="button"
                                                        disabled={
                                                            processing
                                                            || reviewProcessing
                                                            || !canEdit
                                                            || data.kos_items.length <= 1
                                                        }
                                                        onClick={() =>
                                                            removeCost(index)
                                                        }
                                                        className="rounded-lg bg-red-50 p-2 text-red-600 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-30"
                                                    >
                                                        <Trash2
                                                            size={15}
                                                        />
                                                    </button>
                                                </td>
                                            </tr>
                                        );
                                    }
                                )}
                            </tbody>

                            <tfoot className="bg-slate-100">
                                <tr>
                                    <td
                                        colSpan={3}
                                        className="p-3 text-right text-[10px] font-black uppercase text-slate-700"
                                    >
                                        Jumlah Keseluruhan
                                    </td>
                                    <td className="border-l border-gray-200 p-3 text-right text-xs font-black text-blue-900">
                                        RM{' '}
                                        {totalCost.toLocaleString(
                                            'en-MY',
                                            {
                                                minimumFractionDigits: 2,
                                                maximumFractionDigits: 2,
                                            }
                                        )}
                                    </td>
                                    <td />
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <button
                        type="button"
                        disabled={
                            processing
                            || reviewProcessing
                            || !canEdit
                        }
                        onClick={addCost}
                        className="mt-3 inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-[10px] font-black uppercase text-blue-700 hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <Plus size={13} />
                        Tambah Peralatan
                    </button>
                </div>
            </div>

            <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <label className="mb-2 block text-xs font-black uppercase tracking-wider text-slate-800">
                    Rumusan Laporan
                    <span className="ml-1 text-red-500">*</span>
                </label>

                <textarea
                    value={data.rumusan}
                    onChange={(event) =>
                        setData(
                            'rumusan',
                            event.target.value
                        )
                    }
                    disabled={
                        processing
                        || reviewProcessing
                        || !canEdit
                    }
                    maxLength={10000}
                    required
                    placeholder="Masukkan rumusan laporan Pembekalan..."
                    className="min-h-[130px] w-full rounded-xl border-gray-200 text-xs font-semibold leading-relaxed disabled:bg-gray-50"
                />

                <div className="mt-3 text-[10px] font-semibold text-slate-500">
                    Disediakan oleh:{' '}
                    <span className="font-black text-slate-800">
                        {report.disediakan_oleh ||
                            currentUser.nama ||
                            '—'}
                    </span>
                </div>
            </div>

            {isReportReview && (
                <div className="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                    <label className="mb-2 block text-xs font-black uppercase tracking-wider text-amber-900">
                        Ulasan Pembetulan
                    </label>

                    <textarea
                        value={reviewNote}
                        onChange={(event) =>
                            setReviewNote(
                                event.target.value
                            )
                        }
                        disabled={
                            processing
                            || reviewProcessing
                        }
                        maxLength={2000}
                        placeholder="Wajib diisi jika memilih SEMAKAN..."
                        className="min-h-[90px] w-full rounded-xl border-amber-200 bg-white text-xs font-semibold"
                    />
                </div>
            )}

            {Object.keys(errors).length > 0 && (
                <div className="rounded-xl border border-red-200 bg-red-50 p-4">
                    <div className="mb-2 flex items-center gap-2 text-xs font-black uppercase text-red-700">
                        <AlertTriangle size={15} />
                        Sila betulkan maklumat berikut
                    </div>

                    <ul className="list-disc space-y-1 pl-5 text-xs font-semibold text-red-700">
                        {Object.entries(errors).map(
                            ([field, message]) => (
                                <li key={field}>
                                    {message}
                                </li>
                            )
                        )}
                    </ul>
                </div>
            )}

            <div className="flex flex-wrap justify-end gap-3 border-t border-gray-100 pt-4">
                <button
                    type="button"
                    disabled={
                        processing
                        || reviewProcessing
                        || !canEdit
                    }
                    onClick={saveLkk}
                    className="inline-flex h-11 items-center gap-2 rounded-xl border border-blue-200 bg-white px-5 text-[10px] font-black uppercase text-blue-700 shadow-sm hover:bg-blue-50 disabled:cursor-not-allowed disabled:bg-gray-100"
                >
                    <RefreshCw size={14} />
                    KEMASKINI TIKET
                </button>

                {isReportReview && (
                    <button
                        type="button"
                        disabled={
                            processing
                            || reviewProcessing
                            || !canEdit
                        }
                        onClick={() =>
                            reviewLkk('PEMBETULAN')
                        }
                        className="inline-flex h-11 items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-5 text-[10px] font-black uppercase text-amber-800 shadow-sm hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <AlertTriangle size={14} />
                        SEMAKAN
                    </button>
                )}

                <button
                    type="button"
                    disabled={
                        processing
                        || reviewProcessing
                        || !canEdit
                    }
                    onClick={() =>
                        reviewLkk('VERIFIKASI')
                    }
                    className="inline-flex h-11 items-center gap-2 rounded-xl bg-emerald-600 px-6 text-[10px] font-black uppercase text-white shadow-md hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                >
                    <Send size={14} />
                    VERIFIKASI
                </button>
            </div>
        </div>
    );
}