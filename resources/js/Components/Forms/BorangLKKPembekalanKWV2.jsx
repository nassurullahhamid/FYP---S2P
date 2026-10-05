import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,

} from 'lucide-react';
import PaparanRingkasanLKK from './PaparanRingkasanLKK';

export default function BorangLKKPembekalanKWV2({
    ticket,
    auth,
    senaraiPegawai = [],
}) {
    const currentUser = auth?.user || {};

    const role = String(
        currentUser.peranan
        || currentUser.role
        || ''
    ).trim().toLowerCase();

    const statusFormat = String(
        ticket.status_tiket || ''
    ).trim().toLowerCase();

    const isKW = [
        'ketua_wilayah',
        'ketua wilayah',
        'kw',
    ].includes(role);

    const canValidate =
        isKW
        && statusFormat === 'menunggu validasi';

    const [reviewNote, setReviewNote] =
        useState('');

    const [processing, setProcessing] =
        useState(false);

    const submitValidation = (action) => {
        if (!canValidate || processing) {
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

        const message =
            action === 'LULUS'
                ? 'Adakah laporan disahkan untuk divalidasi?'
                : 'Laporan dikembalikan semula kepada KUPP?';

        if (!window.confirm(message)) {
            return;
        }

        setProcessing(true);

        router.post(
            route(
                'tickets.workflow.validateProcurementLkk',
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
                        action === 'LULUS'
                            ? 'Laporan telah divalidasi.'
                            : 'Telah dihantar kepada KUPP untuk tindakan yang sewajarnya.'
                    );
                },
                onError: (errors) => {
                    const messages =
                        Object.values(errors).join('\n');

                    alert(
                        messages
                        || 'Tindakan validasi tidak berjaya.'
                    );
                },
                onFinish: () => {
                    setProcessing(false);
                },
            }
        );
    };

    if (!isKW) {
        return (
            <div className="rounded-2xl border border-red-200 bg-red-50 p-5 text-xs font-semibold text-red-700">
                Hanya Ketua Wilayah dibenarkan
                membuat validasi akhir LKK Pembekalan.
            </div>
        );
    }

    return (
        <div className="space-y-5">

            <PaparanRingkasanLKK
                ticket={ticket}
                auth={auth}
                senaraiPegawai={senaraiPegawai}
            />

            <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <label className="mb-2 block text-xs font-black uppercase tracking-wider text-slate-800">
                    Ulasan
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
                        || !canValidate
                    }
                    maxLength={2000}
                    placeholder="Perkara"
                    className="min-h-[110px] w-full rounded-xl border-gray-200 text-xs font-semibold leading-relaxed disabled:cursor-not-allowed disabled:bg-gray-50"
                />

                <div className="mt-3 text-[10px] font-semibold text-slate-500">
                    Pegawai validasi:{' '}
                    <span className="font-black text-slate-800">
                        {currentUser.nama || '—'}
                    </span>
                </div>
            </div>

            <div className="flex flex-wrap justify-end gap-3 border-t border-gray-100 pt-4">
                <button
                    type="button"
                    disabled={
                        processing
                        || !canValidate
                    }
                    onClick={() =>
                        submitValidation(
                            'PEMBETULAN'
                        )
                    }
                    className="inline-flex h-11 items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-5 text-[10px] font-black uppercase text-amber-800 shadow-sm hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <AlertTriangle size={14} />
                    PEMBETULAN LAPORAN
                </button>

                <button
                    type="button"
                    disabled={
                        processing
                        || !canValidate
                    }
                    onClick={() =>
                        submitValidation('LULUS')
                    }
                    className="inline-flex h-11 items-center gap-2 rounded-xl bg-emerald-600 px-6 text-[10px] font-black uppercase text-white shadow-md hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                >
                    <CheckCircle2 size={14} />
                    {processing
                        ? 'MEMPROSES...'
                        : 'VALIDASI'}
                </button>
            </div>
        </div>
    );
}