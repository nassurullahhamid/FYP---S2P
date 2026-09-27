import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import {
    CheckCircle2,
    DollarSign,
    Plus,
    RotateCcw,
    Send,
    ShieldCheck,
    Trash2,
    Upload,
} from 'lucide-react';

const parseRows = (value, fallback = []) => {
    if (Array.isArray(value)) {
        return value.length > 0 ? value : fallback;
    }

    if (typeof value === 'string' && value.trim() !== '') {
        try {
            const parsed = JSON.parse(value);

            return Array.isArray(parsed)
                ? parsed
                : fallback;
        } catch {
            return fallback;
        }
    }

    return fallback;
};

const MAX_SITE_FILES = 20;
const MAX_FILE_SIZE = 5 * 1024 * 1024;
const MAX_TOTAL_UPLOAD_SIZE = 100 * 1024 * 1024;

const formatFileSize = bytes => (
    `${(bytes / 1024 / 1024).toFixed(2)} MB`
);

export default function BorangLKKPemodenanTindakanV2({
    ticket,
    auth,
}) {
    const laporan = ticket.laporan || {};
    const currentUser = auth?.user || {};

    const role = String(
        currentUser.peranan || currentUser.role || ''
    )
        .trim()
        .toLowerCase();

    const status = String(ticket.status_tiket || '')
        .trim()
        .toLowerCase();

    const assignedIds = Array.isArray(ticket.petugas)
        ? ticket.petugas
            .map(pegawai => pegawai?.no_ic)
            .filter(Boolean)
        : [];

    const isKUPP = [
        'ketua_upp',
        'ketua upp',
        'kupp',
    ].includes(role);

    const isKW = [
        'ketua_wilayah',
        'ketua wilayah',
        'kw',
    ].includes(role);

    const isAssignedPic =
        Boolean(currentUser.no_ic)
        && assignedIds.includes(currentUser.no_ic);

    const canPicEdit =
        [
            'dalam tindakan',
            'laporan perlu pembetulan',
        ].includes(status)
        && isAssignedPic;

    const canKuppEdit =
        [
            'menunggu semakan laporan',
            'pembetulan ketua',
        ].includes(status)
        && isKUPP;

    const canKwValidate =
        status === 'menunggu validasi'
        && isKW;

    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});

    const [keadaanSemasa, setKeadaanSemasa] = useState(
        parseRows(
            laporan.keadaan_semasa,
            [{ aspek: '', ulasan: '' }]
        )
    );

    const [cadangan, setCadangan] = useState(
        typeof laporan.cadangan_penambahbaikan === 'string'
            ? laporan.cadangan_penambahbaikan
            : ''
    );

    const [gambarTapak, setGambarTapak] = useState([]);
    const [gambarCadangan, setGambarCadangan] = useState(null);

    const validateFileSize = file => {
        if (file.size > MAX_FILE_SIZE) {
            alert(
                `${file.name} melebihi had 5 MB ` +
                `(${formatFileSize(file.size)}).`
            );

            return false;
        }

        return true;
    };

    const handleSiteFiles = event => {
        const files = Array.from(
            event.target.files || []
        );

        if (files.length > MAX_SITE_FILES) {
            alert(
                `Maksimum ${MAX_SITE_FILES} gambar tapak ` +
                'dibenarkan.'
            );

            event.target.value = '';
            setGambarTapak([]);

            return;
        }

        if (!files.every(validateFileSize)) {
            event.target.value = '';
            setGambarTapak([]);

            return;
        }

        const totalSize = files.reduce(
            (total, file) => total + file.size,
            0
        );

        if (totalSize > MAX_TOTAL_UPLOAD_SIZE) {
            alert(
                'Jumlah saiz gambar tapak tidak boleh ' +
                'melebihi 100 MB.'
            );

            event.target.value = '';
            setGambarTapak([]);

            return;
        }

        setGambarTapak(files);
    };

    const handleProposalFile = event => {
        const file = event.target.files?.[0] || null;

        if (!file) {
            setGambarCadangan(null);

            return;
        }

        const allowedTypes = [
            'image/jpeg',
            'image/png',
            'application/pdf',
        ];

        if (!allowedTypes.includes(file.type)) {
            alert(
                'Cadangan pemasangan mestilah fail ' +
                'JPG, JPEG, PNG atau PDF.'
            );

            event.target.value = '';
            setGambarCadangan(null);

            return;
        }

        if (!validateFileSize(file)) {
            event.target.value = '';
            setGambarCadangan(null);

            return;
        }

        setGambarCadangan(file);
    };

    const [kosItems, setKosItems] = useState(
        parseRows(
            laporan.kos_items,
            [
                {
                    item: '',
                    kuantiti: '',
                    harga_seunit: '',
                },
            ]
        )
    );

    const [rumusan, setRumusan] = useState(
        laporan.rumusan || ''
    );

    const [ulasan, setUlasan] = useState('');

    const send = (
        routeName,
        payload,
        successMessage,
        forceFormData = false
    ) => {
        if (processing) {
            return;
        }

        setProcessing(true);
        setErrors({});

        router.post(
            route(routeName, ticket.id_tiket),
            payload,
            {
                preserveScroll: true,
                forceFormData,
                onSuccess: () => {
                    alert(successMessage);
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

    const updateObservation = (
        index,
        field,
        value
    ) => {
        const updated = [...keadaanSemasa];

        updated[index] = {
            ...updated[index],
            [field]: value,
        };

        setKeadaanSemasa(updated);
    };

    const submitPic = event => {
        event.preventDefault();

        send(
            'tickets.workflow.submitModernizationReport',
            {
                keadaan_semasa:
                    keadaanSemasa
                        .map(row => ({
                            aspek: String(
                                row.aspek || ''
                            ).trim(),
                            ulasan: String(
                                row.ulasan || ''
                            ).trim(),
                        }))
                        .filter(
                            row =>
                                row.aspek !== ''
                                || row.ulasan !== ''
                        ),
                cadangan_penambahbaikan:
                    String(cadangan).trim() || null,
                gambar_tapak: gambarTapak,
                gambar_cadangan: gambarCadangan,
            },
            'Laporan berjaya dihantar kepada KUPP.',
            true
        );
    };

    const updateCost = (
        index,
        field,
        value
    ) => {
        const updated = [...kosItems];

        updated[index] = {
            ...updated[index],
            [field]: value,
        };

        setKosItems(updated);
    };

    const submitKuppReport = event => {
        event.preventDefault();

        send(
            'tickets.workflow.saveModernizationLkk',
            {
                kos_items:
                    kosItems
                        .map(row => ({
                            item: String(
                                row.item || ''
                            ).trim(),
                            kuantiti: row.kuantiti,
                            harga_seunit:
                                row.harga_seunit,
                        }))
                        .filter(
                            row =>
                                row.item !== ''
                                || row.kuantiti !== ''
                                || row.harga_seunit !== ''
                        ),
                rumusan: String(rumusan).trim(),
            },
            'Anggaran kos dan rumusan berjaya dikemaskini.'
        );
    };

    const reviewKupp = action => {
        const comment = String(ulasan).trim();

        if (
            action === 'PEMBETULAN'
            && comment === ''
        ) {
            alert('Sila masukkan ulasan pembetulan.');

            return;
        }

        if (
            !window.confirm(
                action === 'PEMBETULAN'
                    ? 'Kembalikan laporan kepada Juruteknik?'
                    : 'Verifikasi dan hantar LKK kepada Ketua Wilayah?'
            )
        ) {
            return;
        }

        send(
            'tickets.workflow.reviewModernizationLkk',
            {
                tindakan: action,
                ulasan:
                    action === 'PEMBETULAN'
                        ? comment
                        : null,
            },
            action === 'PEMBETULAN'
                ? 'Laporan dikembalikan kepada Juruteknik.'
                : 'LKK berjaya diverifikasi.'
        );
    };

    const validateKw = action => {
        const comment = String(ulasan).trim();

        if (
            action === 'PEMBETULAN'
            && comment === ''
        ) {
            alert('Sila masukkan ulasan pembetulan.');

            return;
        }

        if (
            !window.confirm(
                action === 'PEMBETULAN'
                    ? 'Kembalikan LKK kepada KUPP?'
                    : 'Validasi LKK dan tutup tiket?'
            )
        ) {
            return;
        }

        send(
            'tickets.workflow.validateModernizationLkk',
            {
                tindakan:
                    action === 'VALIDASI'
                        ? 'LULUS'
                        : 'PEMBETULAN',
                ulasan:
                    action === 'PEMBETULAN'
                        ? comment
                        : null,
            },
            action === 'PEMBETULAN'
                ? 'LKK dikembalikan kepada KUPP.'
                : 'LKK berjaya divalidasi dan tiket ditutup.'
        );
    };

    return (
        <div className="space-y-6 text-xs font-bold text-gray-700">
            <div className="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
                <h2 className="text-sm md:text-base font-black uppercase text-slate-900">
                    Laporan Kajian Keperluan Pemodenan Bilik Mesyuarat
                </h2>

                <p className="mt-1 text-[11px] text-slate-500">
                    Workflow V2 · Status: {ticket.status_tiket}
                </p>
            </div>

            {Object.keys(errors).length > 0 && (
                <div className="p-4 bg-red-50 border border-red-200 rounded-xl text-red-700">
                    <p className="font-black uppercase mb-2">
                        Tindakan tidak dapat diproses
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

            {ticket.ulasan_semakan && (
                <div className="p-4 bg-amber-50 border border-amber-200 rounded-xl">
                    <p className="font-black uppercase text-amber-800">
                        Ulasan Pembetulan
                    </p>

                    <p className="mt-1 whitespace-pre-wrap text-amber-950">
                        {ticket.ulasan_semakan}
                    </p>
                </div>
            )}

            <form
                onSubmit={submitPic}
                className="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm space-y-4"
            >
                <h3 className="font-black uppercase text-blue-900 border-b pb-2">
                    Pemerhatian Keadaan Semasa
                </h3>

                {keadaanSemasa.map((row, index) => (
                    <div
                        key={`keadaan-${index}`}
                        className="grid grid-cols-1 md:grid-cols-[1fr_2fr_auto] gap-2"
                    >
                        <input
                            type="text"
                            value={row.aspek || ''}
                            onChange={event =>
                                updateObservation(
                                    index,
                                    'aspek',
                                    event.target.value
                                )
                            }
                            disabled={!canPicEdit}
                            required={canPicEdit}
                            placeholder="Pemerhatian"
                            className="border-gray-200 rounded-xl disabled:bg-gray-50"
                        />
                        <textarea
                            value={row.ulasan || ''}
                            onChange={event =>
                                updateObservation(
                                    index,
                                    'ulasan',
                                    event.target.value
                                )
                            }
                            disabled={!canPicEdit}
                            required={canPicEdit}
                            maxLength={3000}
                            placeholder="Ulasan"
                            className="min-h-[70px] border-gray-200 rounded-xl p-3 disabled:bg-gray-50"
                        />


                        {canPicEdit && (
                            <button
                                type="button"
                                disabled={
                                    keadaanSemasa.length <= 1
                                }
                                onClick={() =>
                                    setKeadaanSemasa(
                                        keadaanSemasa.filter(
                                            (_, rowIndex) =>
                                                rowIndex !== index
                                        )
                                    )
                                }
                                className="p-3 text-red-500 disabled:opacity-30"
                            >
                                <Trash2 size={16} />
                            </button>
                        )}
                    </div>
                ))}

                {canPicEdit && (
                    <button
                        type="button"
                        onClick={() =>
                            setKeadaanSemasa([
                                ...keadaanSemasa,
                                {
                                    aspek: '',
                                    ulasan: '',
                                },
                            ])
                        }
                        className="inline-flex items-center gap-2 px-3 py-2 bg-blue-50 text-blue-700 rounded-xl"
                    >
                        <Plus size={14} />
                        Tambah Pemerhatian
                    </button>
                )}

                <div className="space-y-2">
                    <label className="block uppercase text-[10px] font-black text-gray-500">
                        Cadangan Susun Atur Pemasangan Pemodenan
                    </label>

                    <textarea
                        value={cadangan}
                        onChange={event =>
                            setCadangan(event.target.value)
                        }
                        disabled={!canPicEdit}
                        className="w-full min-h-[90px] border-gray-200 rounded-xl p-3 disabled:bg-gray-50"
                    />
                </div>

                {canPicEdit && (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label className="p-4 border border-dashed border-blue-300 bg-blue-50 rounded-xl">
                            <span className="flex items-center gap-2 uppercase text-blue-800">
                                <Upload size={15} />
                                Gambar Tapak
                            </span>

                            <input
                                type="file"
                                accept="image/*"
                                multiple
                                onChange={handleSiteFiles}
                                className="mt-3 block w-full"
                            />

                            <span className="block mt-2 text-[10px]">
                                {gambarTapak.length} daripada 20 fail dipilih
                            </span>

                            <span className="block mt-1 text-[10px] text-blue-700">
                                Format imej · maksimum 5 MB setiap fail
                            </span>
                        </label>

                        <label className="p-4 border border-dashed border-emerald-300 bg-emerald-50 rounded-xl">
                            <span className="flex items-center gap-2 uppercase text-emerald-800">
                                <Upload size={15} />
                                Cadangan Pemasangan
                            </span>

                            <input
                                type="file"
                                accept=".jpg,.jpeg,.png,.pdf"
                                onChange={handleProposalFile}
                                className="mt-3 block w-full"
                            />

                            <span className="block mt-2 text-[10px]">
                                {gambarCadangan
                                    ? gambarCadangan.name
                                    : 'Fail sedia ada akan dikekalkan'}
                            </span>

                            <span className="block mt-1 text-[10px] text-emerald-700">
                                JPG, JPEG, PNG atau PDF · maksimum 5 MB
                            </span>
                        </label>
                    </div>
                )}

                {canPicEdit && (
                    <div className="flex justify-end pt-3 border-t">
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center gap-2 px-5 py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 text-white uppercase font-black rounded-xl"
                        >
                            <Send size={14} />
                            {processing
                                ? 'Memproses...'
                                : 'KEMASKINI TIKET'}
                        </button>
                    </div>
                )}
            </form>

            <form
                onSubmit={submitKuppReport}
                className="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm space-y-4"
            >
                <div className="flex items-center gap-2 text-blue-900 border-b pb-2">
                    <DollarSign size={16} />

                    <h3 className="font-black uppercase">
                        Anggaran Kos dan Rumusan
                    </h3>
                </div>

                {kosItems.map((row, index) => (
                    <div
                        key={`kos-${index}`}
                        className="grid grid-cols-1 md:grid-cols-[2fr_1fr_1fr_auto] gap-2"
                    >
                        <input
                            value={row.item || ''}
                            onChange={event =>
                                updateCost(
                                    index,
                                    'item',
                                    event.target.value
                                )
                            }
                            disabled={!canKuppEdit}
                            required={canKuppEdit}
                            placeholder="Item"
                            className="border-gray-200 rounded-xl disabled:bg-gray-50"
                        />

                        <input
                            type="number"
                            min="1"
                            value={row.kuantiti || ''}
                            onChange={event =>
                                updateCost(
                                    index,
                                    'kuantiti',
                                    event.target.value
                                )
                            }
                            disabled={!canKuppEdit}
                            required={canKuppEdit}
                            placeholder="Kuantiti"
                            className="border-gray-200 rounded-xl disabled:bg-gray-50"
                        />

                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            value={row.harga_seunit || ''}
                            onChange={event =>
                                updateCost(
                                    index,
                                    'harga_seunit',
                                    event.target.value
                                )
                            }
                            disabled={!canKuppEdit}
                            required={canKuppEdit}
                            placeholder="Harga seunit"
                            className="border-gray-200 rounded-xl disabled:bg-gray-50"
                        />

                        {canKuppEdit && (
                            <button
                                type="button"
                                disabled={kosItems.length <= 1}
                                onClick={() =>
                                    setKosItems(
                                        kosItems.filter(
                                            (_, rowIndex) =>
                                                rowIndex !== index
                                        )
                                    )
                                }
                                className="p-3 text-red-500 disabled:opacity-30"
                            >
                                <Trash2 size={16} />
                            </button>
                        )}
                    </div>
                ))}

                {canKuppEdit && (
                    <button
                        type="button"
                        onClick={() =>
                            setKosItems([
                                ...kosItems,
                                {
                                    item: '',
                                    kuantiti: '',
                                    harga_seunit: '',
                                },
                            ])
                        }
                        className="inline-flex items-center gap-2 px-3 py-2 bg-blue-50 text-blue-700 rounded-xl"
                    >
                        <Plus size={14} />
                        Tambah Item Kos
                    </button>
                )}

                <textarea
                    value={rumusan}
                    onChange={event =>
                        setRumusan(event.target.value)
                    }
                    disabled={!canKuppEdit}
                    required={canKuppEdit}
                    placeholder="Rumusan laporan"
                    className="w-full min-h-[110px] border-gray-200 rounded-xl p-3 disabled:bg-gray-50"
                />

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="p-4 bg-blue-50 border border-blue-100 rounded-xl">
                        <p className="uppercase text-[10px] text-blue-700">
                            Disediakan Oleh
                        </p>

                        <p className="mt-1 uppercase font-black text-blue-950">
                            {laporan.disediakan_oleh
                                || (canKuppEdit
                                    ? currentUser.nama
                                    : 'Belum direkod')}
                        </p>
                    </div>

                    <div className="p-4 bg-slate-50 border border-slate-200 rounded-xl">
                        <p className="uppercase text-[10px] text-slate-500">
                            Disemak Oleh
                        </p>

                        <p className="mt-1 uppercase font-black text-slate-800">
                            {laporan.disemak_oleh
                                || 'Akan direkod oleh Ketua Wilayah'}
                        </p>
                    </div>
                </div>

                {canKuppEdit && (
                    <div className="flex justify-end pt-3 border-t">
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center gap-2 px-5 py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 text-white uppercase font-black rounded-xl"
                        >
                            <Send size={14} />
                            {processing
                                ? 'Memproses...'
                                : 'KEMASKINI TIKET'}
                        </button>
                    </div>
                )}
            </form>

            {(canKuppEdit || canKwValidate) && (
                <div className="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm space-y-4">
                    {canKwValidate && (
                        <textarea
                            value={ulasan}
                            onChange={event =>
                                setUlasan(event.target.value)
                            }
                            placeholder="Ulasan—wajib untuk semakan"
                            className="w-full min-h-[90px] border-gray-200 rounded-xl p-3"
                        />
                    )}

                    <div className="flex flex-col sm:flex-row justify-end gap-3">
                        {canKwValidate && (
                            <button
                                type="button"
                                disabled={processing}
                                onClick={() =>
                                    validateKw('PEMBETULAN')
                                }
                                className="inline-flex items-center justify-center gap-2 px-5 py-3 bg-amber-500 hover:bg-amber-600 disabled:bg-gray-300 text-white uppercase font-black rounded-xl"
                            >
                                <RotateCcw size={14} />
                                SEMAKAN
                            </button>
                        )}

                        {canKuppEdit && (
                            <button
                                type="button"
                                disabled={processing}
                                onClick={() =>
                                    reviewKupp('VERIFIKASI')
                                }
                                className="inline-flex items-center justify-center gap-2 px-5 py-3 bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 text-white uppercase font-black rounded-xl"
                            >
                                <ShieldCheck size={14} />
                                VERIFIKASI TIKET
                            </button>
                        )}

                        {canKwValidate && (
                            <button
                                type="button"
                                disabled={processing}
                                onClick={() =>
                                    validateKw('VALIDASI')
                                }
                                className="inline-flex items-center justify-center gap-2 px-5 py-3 bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 text-white uppercase font-black rounded-xl"
                            >
                                <CheckCircle2 size={14} />
                                VALIDASI TIKET
                            </button>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
