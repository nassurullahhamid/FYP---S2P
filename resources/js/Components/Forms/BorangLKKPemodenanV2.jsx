import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import {
    Calendar,
    CheckCircle2,
    Clock,
    Plus,
    Send,
    Trash2,
    UserCheck,
} from 'lucide-react';

const parseRows = (value, fallback) => {
    if (Array.isArray(value)) {
        return value.length > 0 ? value : fallback;
    }

    if (typeof value === 'string' && value.trim() !== '') {
        try {
            const parsed = JSON.parse(value);

            return Array.isArray(parsed) && parsed.length > 0
                ? parsed
                : fallback;
        } catch {
            return fallback;
        }
    }

    return fallback;
};

const formatApplicationDate = value => {
    if (!value) {
        return '';
    }

    const normalized = String(value)
        .trim()
        .split('T')[0]
        .split(' ')[0];

    const parts = normalized.split('-');

    if (
        parts.length !== 3
        || parts[0].length !== 4
    ) {
        return normalized;
    }

    return `${parts[2]}/${parts[1]}/${parts[0]}`;
};

export default function BorangLKKPemodenanV2({
    ticket,
    senaraiPegawai = [],
    auth,
}) {
    const currentUser = auth?.user || {};
    const currentRole = String(
        currentUser.peranan || currentUser.role || ''
    )
        .trim()
        .toLowerCase();

    const statusFormat = String(ticket.status_tiket || '')
        .trim()
        .toLowerCase();

    const laporan = ticket.laporan || {};

    const digitalRecord =
        ticket.transformasi_digital
        || ticket.transformasiDigital
        || {};

    const applicationDate = formatApplicationDate(
        ticket.tarikh_terima
        || ticket.created_at
    );

    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});

    const [pendahuluan, setPendahuluan] = useState(
        laporan.pendahuluan || ''
    );

    const [objektif, setObjektif] = useState(
        parseRows(
            laporan.objektif,
            [{ teks: '' }]
        )
    );

    const [skopKajian, setSkopKajian] = useState(
        parseRows(
            laporan.skop_kajian,
            [{ teks: '' }]
        )
    );

    const [tarikhLawatan, setTarikhLawatan] = useState(
        digitalRecord.tarikh_lawatan || ''
    );

    const [masaLawatan, setMasaLawatan] = useState(
        digitalRecord.masa_lawatan || ''
    );

    const [catatanLawatan, setCatatanLawatan] = useState(
        digitalRecord.catatan_lawatan || ''
    );

    const existingPicIds = Array.isArray(ticket.petugas)
        ? ticket.petugas
            .map(pegawai => pegawai?.no_ic)
            .filter(Boolean)
        : [];

    const [senaraiPicIc, setSenaraiPicIc] = useState(
        existingPicIds.length > 0
            ? existingPicIds
            : ['']
    );

    const isKUPP = [
        'ketua_upp',
        'ketua upp',
        'kupp',
    ].includes(currentRole);

    const isKUTD = [
        'ketua_utd',
        'ketua utd',
        'kutd',
    ].includes(currentRole);

    const isInitialReview =
        statusFormat === 'menunggu semakan'
        && isKUPP;

    const isAssignment =
        statusFormat === 'disemak'
        && isKUTD;

    const activeTechnicians = senaraiPegawai.filter(
        pegawai =>
            String(pegawai?.peranan || '')
                .trim()
                .toLowerCase() === 'juruteknik'
            && String(
                pegawai?.status_pengguna || 'Aktif'
            ).toLowerCase() === 'aktif'
            && pegawai?.no_ic
    );

    const cleanTextRows = rows =>
        rows
            .map(row => ({
                teks: String(row?.teks || '').trim(),
            }))
            .filter(row => row.teks !== '');

    const updateTextRow = (
        setter,
        rows,
        index,
        value
    ) => {
        const updated = [...rows];

        updated[index] = {
            ...updated[index],
            teks: value,
        };

        setter(updated);
    };

    const removeTextRow = (
        setter,
        rows,
        index
    ) => {
        if (rows.length <= 1) {
            return;
        }

        setter(
            rows.filter(
                (_, rowIndex) => rowIndex !== index
            )
        );
    };

    const addPic = () => {
        setSenaraiPicIc([
            ...senaraiPicIc,
            '',
        ]);
    };

    const updatePic = (index, value) => {
        const updated = [...senaraiPicIc];

        updated[index] = value;

        setSenaraiPicIc(updated);
    };

    const removePic = index => {
        if (senaraiPicIc.length <= 1) {
            return;
        }

        setSenaraiPicIc(
            senaraiPicIc.filter(
                (_, rowIndex) => rowIndex !== index
            )
        );
    };

    const submit = (routeName, payload, message) => {
        if (processing) {
            return;
        }

        setProcessing(true);
        setErrors({});

        router.post(
            route(
                routeName,
                ticket.id_tiket
            ),
            payload,
            {
                preserveScroll: true,
                onSuccess: () => {
                    alert(message);
                },
                onError: responseErrors => {
                    setErrors(responseErrors || {});
                },
                onFinish: () => {
                    setProcessing(false);
                },
            }
        );
    };

    const submitInitialReview = event => {
        event.preventDefault();

        submit(
            'tickets.workflow.reviewModernization',
            {
                pendahuluan:
                    String(pendahuluan).trim(),
                objektif:
                    cleanTextRows(objektif),
                skop_kajian:
                    cleanTextRows(skopKajian),
            },
            'Maklumat kajian berjaya dikemaskini dan dihantar kepada KUTD.'
        );
    };

    const submitAssignment = event => {
        event.preventDefault();

        submit(
            'tickets.workflow.assignModernization',
            {
                tarikh_lawatan: tarikhLawatan,
                masa_lawatan: masaLawatan,
                catatan_lawatan:
                    String(catatanLawatan).trim()
                    || null,
                senarai_pic_ic:
                    senaraiPicIc.filter(Boolean),
            },
            'Lawatan berjaya dijadualkan dan Juruteknik telah dilantik.'
        );
    };

    return (
        <div className="space-y-6 w-full text-xs font-bold text-gray-700">
            <div className="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
                <h2 className="text-sm md:text-base font-black text-slate-900 uppercase">
                    Laporan Kajian Keperluan Pemodenan Bilik Mesyuarat
                </h2>

                <p className="mt-1 text-[11px] text-slate-500">
                    Workflow V2 · Status: {ticket.status_tiket}
                </p>
            </div>

            {Object.keys(errors).length > 0 && (
                <div className="p-4 bg-red-50 border border-red-200 rounded-xl text-red-700">
                    <p className="font-black uppercase mb-2">
                        Sila semak maklumat berikut
                    </p>

                    <ul className="list-disc pl-5 space-y-1">
                        {Object.entries(errors).map(
                            ([field, message]) => (
                                <li key={field}>
                                    {Array.isArray(message)
                                        ? message.join(' ')
                                        : message}
                                </li>
                            )
                        )}
                    </ul>
                </div>
            )}

            <div className="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm space-y-4">
                <div className="flex items-center gap-2 text-blue-900 border-b pb-2">
                    <Calendar size={16} />

                    <h3 className="font-black uppercase">
                        Butiran Permohonan
                    </h3>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-1">
                        <label className="block uppercase text-[10px] font-black text-gray-500">
                            No. Tiket
                        </label>

                        <div className="min-h-10 flex items-center rounded-xl border border-gray-200 bg-gray-50 px-3 text-xs font-bold text-slate-700">
                            {ticket.id_tiket || '—'}
                        </div>
                    </div>

                    <div className="space-y-1">
                        <label className="block uppercase text-[10px] font-black text-gray-500">
                            Tarikh Permohonan
                        </label>

                        <div className="min-h-10 flex items-center rounded-xl border border-gray-200 bg-gray-50 px-3 text-xs font-bold text-slate-700">
                            {applicationDate || '—'}
                        </div>
                    </div>
                </div>
            </div>

            <form
                onSubmit={submitInitialReview}
                className="space-y-5"
            >
                <div className="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm space-y-4">
                    <div className="flex items-center gap-2 text-blue-900 border-b pb-2">
                        <CheckCircle2 size={16} />

                        <h3 className="font-black uppercase">
                            Maklumat Kajian KUPP
                        </h3>
                    </div>

                    <div className="space-y-2">
                        <label className="block uppercase text-[10px] font-black text-gray-500">
                            Pendahuluan
                        </label>

                        <textarea
                            value={pendahuluan}
                            onChange={event =>
                                setPendahuluan(
                                    event.target.value
                                )
                            }
                            disabled={!isInitialReview}
                            required={isInitialReview}
                            className="w-full min-h-[110px] border-gray-200 rounded-xl p-3 disabled:bg-gray-50"
                        />
                    </div>

                    <div className="space-y-3">
                        <label className="block uppercase text-[10px] font-black text-gray-500">
                            Objektif
                        </label>

                        {objektif.map((row, index) => (
                            <div
                                key={`objektif-${index}`}
                                className="flex gap-2"
                            >
                                <textarea
                                    value={row.teks || ''}
                                    onChange={event =>
                                        updateTextRow(
                                            setObjektif,
                                            objektif,
                                            index,
                                            event.target.value
                                        )
                                    }
                                    disabled={!isInitialReview}
                                    required={isInitialReview}
                                    className="flex-1 min-h-[70px] border-gray-200 rounded-xl p-3 disabled:bg-gray-50"
                                />

                                {isInitialReview && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            removeTextRow(
                                                setObjektif,
                                                objektif,
                                                index
                                            )
                                        }
                                        disabled={
                                            objektif.length <= 1
                                        }
                                        className="p-3 text-red-500 disabled:opacity-30"
                                    >
                                        <Trash2 size={16} />
                                    </button>
                                )}
                            </div>
                        ))}

                        {isInitialReview && (
                            <button
                                type="button"
                                onClick={() =>
                                    setObjektif([
                                        ...objektif,
                                        { teks: '' },
                                    ])
                                }
                                className="inline-flex items-center gap-2 px-3 py-2 bg-blue-50 text-blue-700 rounded-xl"
                            >
                                <Plus size={14} />
                                Tambah Objektif
                            </button>
                        )}
                    </div>

                    <div className="space-y-3">
                        <label className="block uppercase text-[10px] font-black text-gray-500">
                            Skop Kajian
                        </label>

                        {skopKajian.map((row, index) => (
                            <div
                                key={`skop-${index}`}
                                className="flex gap-2"
                            >
                                <textarea
                                    value={row.teks || ''}
                                    onChange={event =>
                                        updateTextRow(
                                            setSkopKajian,
                                            skopKajian,
                                            index,
                                            event.target.value
                                        )
                                    }
                                    disabled={!isInitialReview}
                                    required={isInitialReview}
                                    className="flex-1 min-h-[70px] border-gray-200 rounded-xl p-3 disabled:bg-gray-50"
                                />

                                {isInitialReview && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            removeTextRow(
                                                setSkopKajian,
                                                skopKajian,
                                                index
                                            )
                                        }
                                        disabled={
                                            skopKajian.length <= 1
                                        }
                                        className="p-3 text-red-500 disabled:opacity-30"
                                    >
                                        <Trash2 size={16} />
                                    </button>
                                )}
                            </div>
                        ))}

                        {isInitialReview && (
                            <button
                                type="button"
                                onClick={() =>
                                    setSkopKajian([
                                        ...skopKajian,
                                        { teks: '' },
                                    ])
                                }
                                className="inline-flex items-center gap-2 px-3 py-2 bg-blue-50 text-blue-700 rounded-xl"
                            >
                                <Plus size={14} />
                                Tambah Skop
                            </button>
                        )}
                    </div>

                    {isInitialReview && (
                        <div className="flex justify-end pt-3 border-t">
                            <button
                                type="submit"
                                disabled={processing}
                                className="inline-flex items-center gap-2 px-5 py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 text-white font-black uppercase rounded-xl"
                            >
                                <Send size={14} />

                                {processing
                                    ? 'Memproses...'
                                    : 'KEMASKINI TIKET'}
                            </button>
                        </div>
                    )}
                </div>
            </form>

            <form
                onSubmit={submitAssignment}
                className="space-y-5"
            >
                <div className="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm space-y-4">
                    <div className="flex items-center gap-2 text-blue-900 border-b pb-2">
                        <Calendar size={16} />

                        <h3 className="font-black uppercase">
                            Lawatan dan Pelantikan Juruteknik
                        </h3>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <label className="block uppercase text-[10px] font-black text-gray-500">
                                Tarikh Lawatan
                            </label>

                            <input
                                type="date"
                                value={tarikhLawatan}
                                onChange={event =>
                                    setTarikhLawatan(
                                        event.target.value
                                    )
                                }
                                disabled={!isAssignment}
                                required={isAssignment}
                                min={new Date()
                                    .toISOString()
                                    .split('T')[0]}
                                className="w-full border-gray-200 rounded-xl disabled:bg-gray-50"
                            />
                        </div>

                        <div className="space-y-2">
                            <label className="block uppercase text-[10px] font-black text-gray-500">
                                Masa Lawatan
                            </label>

                            <div className="relative">
                                <Clock
                                    size={15}
                                    className="absolute left-3 top-3 text-gray-400"
                                />

                                <input
                                    type="time"
                                    value={masaLawatan}
                                    onChange={event =>
                                        setMasaLawatan(
                                            event.target.value
                                        )
                                    }
                                    disabled={!isAssignment}
                                    required={isAssignment}
                                    className="w-full pl-9 border-gray-200 rounded-xl disabled:bg-gray-50"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="space-y-2">
                        <label className="block uppercase text-[10px] font-black text-gray-500">
                            Catatan Lawatan
                        </label>

                        <textarea
                            value={catatanLawatan}
                            onChange={event =>
                                setCatatanLawatan(
                                    event.target.value
                                )
                            }
                            disabled={!isAssignment}
                            className="w-full min-h-[80px] border-gray-200 rounded-xl p-3 disabled:bg-gray-50"
                        />
                    </div>

                    <div className="space-y-3">
                        <div className="flex items-center gap-2">
                            <UserCheck size={16} />

                            <label className="uppercase text-[10px] font-black text-gray-500">
                                Juruteknik/PIC
                            </label>
                        </div>

                        {senaraiPicIc.map(
                            (selectedIc, index) => (
                                <div
                                    key={`pic-${index}`}
                                    className="flex gap-2"
                                >
                                    <select
                                        value={selectedIc}
                                        onChange={event =>
                                            updatePic(
                                                index,
                                                event.target.value
                                            )
                                        }
                                        disabled={!isAssignment}
                                        required={isAssignment}
                                        className="flex-1 border-gray-200 rounded-xl disabled:bg-gray-50"
                                    >
                                        <option value="">
                                            Pilih Juruteknik
                                        </option>

                                        {activeTechnicians.map(
                                            pegawai => {
                                                const used =
                                                    senaraiPicIc.includes(
                                                        pegawai.no_ic
                                                    )
                                                    && selectedIc
                                                        !== pegawai.no_ic;

                                                return (
                                                    <option
                                                        key={
                                                            pegawai.no_ic
                                                        }
                                                        value={
                                                            pegawai.no_ic
                                                        }
                                                        disabled={used}
                                                    >
                                                        {pegawai.nama}
                                                    </option>
                                                );
                                            }
                                        )}
                                    </select>

                                    {isAssignment && (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                removePic(index)
                                            }
                                            disabled={
                                                senaraiPicIc.length
                                                <= 1
                                            }
                                            className="p-3 text-red-500 disabled:opacity-30"
                                        >
                                            <Trash2 size={16} />
                                        </button>
                                    )}
                                </div>
                            )
                        )}

                        {isAssignment && (
                            <button
                                type="button"
                                onClick={addPic}
                                className="inline-flex items-center gap-2 px-3 py-2 bg-blue-50 text-blue-700 rounded-xl"
                            >
                                <Plus size={14} />
                                Tambah Juruteknik
                            </button>
                        )}
                    </div>

                    {isAssignment && (
                        <div className="flex justify-end pt-3 border-t">
                            <button
                                type="submit"
                                disabled={processing}
                                className="inline-flex items-center gap-2 px-5 py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 text-white font-black uppercase rounded-xl"
                            >
                                <Send size={14} />

                                {processing
                                    ? 'Memproses...'
                                    : 'KEMASKINI TIKET'}
                            </button>
                        </div>
                    )}
                </div>
            </form>
        </div>
    );
}
