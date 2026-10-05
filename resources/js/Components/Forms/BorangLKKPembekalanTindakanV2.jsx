import React from 'react';
import { useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    ClipboardList,
    Send,
} from 'lucide-react';

const parseRows = (value) => {
    if (Array.isArray(value)) {
        return value;
    }

    if (typeof value === 'string' && value.trim() !== '') {
        try {
            const decoded = JSON.parse(value);

            return Array.isArray(decoded) ? decoded : [];
        } catch {
            return [];
        }
    }

    return [];
};

export default function BorangLKKPembekalanTindakanV2({
    ticket,
    auth,
}) {
    const currentUser = auth?.user || {};
    const statusFormat = String(
        ticket.status_tiket || ''
    ).trim().toLowerCase();

    const assignedIds = [
        ...(ticket.petugas || []).map(
            (pegawai) => pegawai.no_ic
        ),
        ...(
            Array.isArray(ticket.pic_ic)
                ? ticket.pic_ic
                : ticket.pic_ic
                    ? [ticket.pic_ic]
                    : []
        ),
    ].filter(Boolean);

    const isAssignedTechnician =
        assignedIds.includes(currentUser.no_ic);

    const isTechnicianPhase =
        statusFormat === 'dalam tindakan'
        || (
            statusFormat === 'menunggu pembetulan'
            && !ticket.disahkan_oleh_ic
        );

    const report = ticket.laporan || {};

    const initialRows = parseRows(
        report.hasil_kajian
    ).map((row) => ({
        nama_pemohon:
            String(row.nama_pemohon || '').trim(),
        jawatan_pemohon:
            String(row.jawatan_pemohon || '').trim(),
        keadaan_semasa:
            String(row.keadaan_semasa || '').trim(),
        justifikasi_cadangan:
            String(
                row.justifikasi_cadangan || ''
            ).trim(),
    }));

    const {
        data,
        setData,
        post,
        processing,
        errors,
    } = useForm({
        hasil_kajian: initialRows,
    });

    const updateRow = (index, field, value) => {
        const rows = data.hasil_kajian.map(
            (row, rowIndex) => (
                rowIndex === index
                    ? {
                        ...row,
                        [field]: value,
                    }
                    : row
            )
        );

        setData('hasil_kajian', rows);
    };

    const submitReport = () => {
        if (
            !isTechnicianPhase
            || !isAssignedTechnician
        ) {
            return;
        }

        post(
            route(
                'tickets.workflow.submitProcurementReport',
                {
                    id_tiket: ticket.id_tiket,
                }
            ),
            {
                preserveScroll: true,
                onSuccess: () => {
                    alert(
                        'Laporan telah dihantar ke KUPP ' +
                        'untuk tindakan yang sewajarnya.'
                    );
                },
                onError: () => {
                    alert(
                        'Laporan tidak berjaya dihantar. ' +
                        'Sila semak semua medan.'
                    );
                },
            }
        );
    };

    if (!isAssignedTechnician) {
        return (
            <div className="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-xs font-semibold text-amber-900">
                Tiket ini hanya boleh dikemaskini oleh
                Juruteknik yang telah dilantik.
            </div>
        );
    }

    return (
        <div className="space-y-5">
            {statusFormat ===
                'laporan perlu pembetulan' && (
                <div className="rounded-2xl border-2 border-amber-300 bg-amber-50 p-5 shadow-sm">
                    <div className="mb-2 flex items-center gap-2 text-amber-900">
                        <AlertTriangle
                            size={18}
                            className="shrink-0 text-amber-600"
                        />
                        <h4 className="text-xs font-black uppercase tracking-wider">
                            Arahan Pembetulan KUPP
                        </h4>
                    </div>

                    <div className="whitespace-pre-wrap rounded-xl border border-amber-200 bg-white p-3 text-xs font-semibold leading-relaxed text-amber-950">
                        {ticket.ulasan_semakan ||
                            'Sila semak dan kemaskini laporan.'}
                    </div>
                </div>
            )}

            <div className="overflow-hidden rounded-2xl border border-emerald-300 bg-white shadow-sm">
                <div className="flex items-center gap-2 bg-[#002b66] px-5 py-3.5 text-white">
                    <ClipboardList size={16} />
                    <h4 className="text-xs font-black uppercase tracking-wider">
                        Butiran Hasil Kajian
                    </h4>
                </div>

                <div className="p-5">
                    {data.hasil_kajian.length === 0 ? (
                        <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-xs font-semibold text-red-700">
                            Senarai pemohon tidak ditemui.
                            Hubungi KUPP sebelum meneruskan
                            laporan.
                        </div>
                    ) : (
                        <div className="overflow-x-auto rounded-xl border border-gray-200">
                            <table className="min-w-[900px] w-full border-collapse text-left">
                                <thead className="bg-slate-100 text-[10px] font-black uppercase tracking-wider text-slate-700">
                                    <tr>
                                        <th className="w-12 border-r border-gray-200 p-3 text-center">
                                            Bil.
                                        </th>
                                        <th className="w-[24%] border-r border-gray-200 p-3">
                                            Butiran Pemohon
                                        </th>
                                        <th className="w-[35%] border-r border-gray-200 p-3">
                                            Keadaan Semasa
                                        </th>
                                        <th className="p-3">
                                            Justifikasi dan Cadangan
                                            Penyelesaian
                                        </th>
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-gray-100">
                                    {data.hasil_kajian.map(
                                        (row, index) => (
                                            <tr
                                                key={`${row.nama_pemohon}-${index}`}
                                                className="align-top"
                                            >
                                                <td className="border-r border-gray-100 p-3 text-center text-xs font-black text-slate-700">
                                                    {index + 1}
                                                </td>

                                                <td className="border-r border-gray-100 bg-slate-50/50 p-3">
                                                    <div className="text-xs font-black text-slate-900">
                                                        {row.nama_pemohon ||
                                                            '—'}
                                                    </div>
                                                    <div className="mt-1 text-[10px] font-semibold text-slate-500">
                                                        {row.jawatan_pemohon ||
                                                            'Jawatan tidak dinyatakan'}
                                                    </div>
                                                </td>

                                                <td className="border-r border-gray-100 p-2">
                                                    <textarea
                                                        value={
                                                            row.keadaan_semasa
                                                        }
                                                        onChange={
                                                            (event) =>
                                                                updateRow(
                                                                    index,
                                                                    'keadaan_semasa',
                                                                    event.target.value
                                                                )
                                                        }
                                                        maxLength={5000}
                                                        required
                                                        disabled={
                                                            processing
                                                            || !isTechnicianPhase
                                                        }
                                                        placeholder="Nyatakan keadaan semasa..."
                                                        className="min-h-[120px] w-full rounded-xl border-gray-200 text-xs font-semibold focus:border-emerald-500 focus:ring-emerald-500 disabled:cursor-not-allowed disabled:bg-gray-50"
                                                    />
                                                </td>

                                                <td className="p-2">
                                                    <textarea
                                                        value={
                                                            row.justifikasi_cadangan
                                                        }
                                                        onChange={
                                                            (event) =>
                                                                updateRow(
                                                                    index,
                                                                    'justifikasi_cadangan',
                                                                    event.target.value
                                                                )
                                                        }
                                                        maxLength={5000}
                                                        required
                                                        disabled={
                                                            processing
                                                            || !isTechnicianPhase
                                                        }
                                                        placeholder="Nyatakan justifikasi dan cadangan penyelesaian..."
                                                        className="min-h-[120px] w-full rounded-xl border-gray-200 text-xs font-semibold focus:border-emerald-500 focus:ring-emerald-500 disabled:cursor-not-allowed disabled:bg-gray-50"
                                                    />
                                                </td>
                                            </tr>
                                        )
                                    )}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {Object.keys(errors).length > 0 && (
                        <div className="mt-4 rounded-xl border border-red-200 bg-red-50 p-4">
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

                    <div className="mt-5 flex justify-end border-t border-gray-100 pt-4">
                        <button
                            type="button"
                            disabled={
                                processing
                                || !isTechnicianPhase
                                || data.hasil_kajian.length === 0
                            }
                            onClick={submitReport}
                            className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-blue-600 px-6 text-[10px] font-black uppercase text-white shadow-md transition-all hover:bg-blue-700 active:scale-95 disabled:cursor-not-allowed disabled:bg-gray-300"
                        >
                            {processing ? (
                                <>
                                    <CheckCircle2 size={14} />
                                    Memproses...
                                </>
                            ) : (
                                <>
                                    <Send size={14} />
                                    KEMASKINI TIKET
                                </>
                            )}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}