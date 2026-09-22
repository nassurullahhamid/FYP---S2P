import React, { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Save, Edit, CheckCircle2, Briefcase, Users, LayoutTemplate, FileText, Plus, Trash2, Printer, Send, Clock, Upload, X } from 'lucide-react';

export default function FormMaklumatTapak({ ticket, auth, senaraiPengguna }) {
    const dataTapak = ticket.konsultasi_rangkaian || {};
    const dataLaporan = ticket.laporan || {};

    const user = auth?.user;
    const assignedPetugasIC = ticket?.petugas?.map(p => p.no_ic).filter(Boolean) || [];

    const statusFormat = String(ticket.status_tiket || '').trim().toLowerCase();
    const isCurrentUserPIC = user?.no_ic && assignedPetugasIC.includes(user.no_ic);
    const isWorkflowV2 = Number(ticket.workflow_version) === 2;

    const isActiveSiteReportStatus = isWorkflowV2
        ? ['dalam tindakan', 'laporan perlu pembetulan'].includes(statusFormat)
        : statusFormat === 'dalam tindakan pegawai';

    // Hanya Juruteknik yang dilantik boleh mengisi atau membetulkan laporan tapak.
    const bolehEditTapak = isCurrentUserPIC && isActiveSiteReportStatus;

    // Toggle edit mode strictly for PIC when details are not filled yet
    const [isEditing, setIsEditing] = useState(bolehEditTapak && !dataTapak.jenis_premis);

    // Safely parse initial technical review comments array
    const dapatkanUlasanAwal = () => {
        if (!dataTapak.ulasan_teknikal) return [{ teks: '' }];

        if (Array.isArray(dataTapak.ulasan_teknikal)) {
            return dataTapak.ulasan_teknikal.length > 0 ? dataTapak.ulasan_teknikal : [{ teks: '' }];
        }

        if (typeof dataTapak.ulasan_teknikal === 'string') {
            try {
                const parsed = JSON.parse(dataTapak.ulasan_teknikal);
                if (Array.isArray(parsed)) return parsed;
            } catch (e) {
                return [{ teks: dataTapak.ulasan_teknikal }];
            }
            return [{ teks: dataTapak.ulasan_teknikal }];
        }
        return [{ teks: '' }];
    };

    const dapatkanCadanganAwal = () => {
        const rawCadangan =
            dataLaporan.cadangan_penambahbaikan;

        if (!rawCadangan) return [{ teks: '' }];

        if (Array.isArray(rawCadangan)) {
            return rawCadangan.length > 0
                ? rawCadangan
                : [{ teks: '' }];
        }

        if (typeof rawCadangan === 'string') {
            try {
                const parsed = JSON.parse(rawCadangan);

                if (
                    Array.isArray(parsed)
                    && parsed.length > 0
                ) {
                    return parsed;
                }
            } catch (error) {
                console.error(
                    'Gagal membaca cadangan Rangkaian:',
                    error
                );
            }
        }

        return [{ teks: '' }];
    };

    // Initialize form state
    const { data, setData, post, processing, errors } = useForm({
        nama_lokasi_bangunan: dataTapak.nama_lokasi_bangunan || '',
        jenis_premis: dataTapak.jenis_premis || 'Pejabat Kerajaan',
        bilik_server: dataTapak.bilik_server || 'TIADA',
        rack_server: dataTapak.rack_server || 'TIADA',
        sumber_kuasa: dataTapak.sumber_kuasa || 'ADA',
        persekitaran_fizikal: dataTapak.persekitaran_fizikal || 'BERHAWA DINGIN',
        liputan: dataTapak.liputan || 'LAN',
        jenis_capaian: dataTapak.jenis_capaian || 'SABAH NET',
        kelajuan: dataTapak.kelajuan || '30 MBPS',
        lan: dataTapak.lan || 'TIADA',
        ap: dataTapak.ap || 'TIADA',
        firewall: dataTapak.firewall || 'TIADA',
        rumusan: dataTapak.rumusan || '',
        ulasan_teknikal: dapatkanUlasanAwal(),
        cadangan_penambahbaikan: dapatkanCadanganAwal(),
        logical_diagram: null,
        physical_diagram: null,
    });

    // Technical comments dynamic array handlers
    const tambahUlasan = () => {
        setData('ulasan_teknikal', [...data.ulasan_teknikal, { teks: '' }]);
    };

    const buangUlasan = (indexAkanDibuang) => {
        const arrayBaru = data.ulasan_teknikal.filter((_, idx) => idx !== indexAkanDibuang);
        setData('ulasan_teknikal', arrayBaru);
    };

    const kemaskiniUlasan = (indexAkanDiedit, nilaiBaru) => {
        const arrayBaru = data.ulasan_teknikal.map((item, idx) =>
            idx === indexAkanDiedit ? { ...item, teks: nilaiBaru } : item
        );
        setData('ulasan_teknikal', arrayBaru);
    };

    const tambahCadangan = () => {
        const cadangan = Array.isArray(
            data.cadangan_penambahbaikan
        )
            ? data.cadangan_penambahbaikan
            : [];

        if (cadangan.length >= 20) return;

        setData(
            'cadangan_penambahbaikan',
            [...cadangan, { teks: '' }]
        );
    };

    const kemaskiniCadangan = (index, value) => {
        const cadangan = Array.isArray(
            data.cadangan_penambahbaikan
        )
            ? [...data.cadangan_penambahbaikan]
            : [{ teks: '' }];

        cadangan[index] = {
            ...cadangan[index],
            teks: value,
        };

        setData(
            'cadangan_penambahbaikan',
            cadangan
        );
    };

    const buangCadangan = (index) => {
        const cadangan = Array.isArray(
            data.cadangan_penambahbaikan
        )
            ? data.cadangan_penambahbaikan
            : [];

        if (cadangan.length <= 1) return;

        setData(
            'cadangan_penambahbaikan',
            cadangan.filter(
                (_, itemIndex) => itemIndex !== index
            )
        );
    };

    const logicalFileName = data.logical_diagram
        ? data.logical_diagram.name
        : dataLaporan.logical_diagram
            ?.split('/')
            .pop();

    const physicalFileName = data.physical_diagram
        ? data.physical_diagram.name
        : dataLaporan.physical_diagram
            ?.split('/')
            .pop();

    // Hantar laporan tapak melalui route yang sepadan dengan versi workflow.
    const handleHantarTapak = (e) => {
        e.preventDefault();

        if (processing) return;

        const submissionRoute = isWorkflowV2
            ? route(
                'tickets.workflow.submitNetworkSiteReport',
                ticket.id_tiket
            )
            : route(
                'tickets.storeLaporanTapak',
                ticket.id_tiket
            );

        post(submissionRoute, {
            preserveScroll: true,
            forceFormData: isWorkflowV2,
            onSuccess: () => {
                alert(
                    isWorkflowV2
                        ? 'Laporan tapak berjaya dihantar untuk semakan KUTD!'
                        : 'Maklumat tapak berjaya disimpan dan dihantar ke KUTD!'
                );
                setIsEditing(false);
            },
            onError: (submissionErrors) => {
                const messages = Object.values(
                    submissionErrors
                ).flat();

                if (messages.length === 0) {
                    alert(
                        'Laporan tapak tidak berjaya dihantar.'
                    );
                    return;
                }

                messages.forEach(
                    message => alert(message)
                );
            },
        });
    };

    // Date and time formatting helpers
    const formatTarikh = (dateString) => {
        if (!dateString) return 'Belum Ditetapkan';
        const cleanDate = dateString.split(' ')[0];
        const [year, month, day] = cleanDate.split('-');
        if (!year || !month || !day) return dateString;
        return `${day}-${month}-${year}`;
    };

    const formatMasa = (timeString) => {
        if (!timeString) return 'Belum Ditetapkan';
        const [timePart] = timeString.split(' ');
        const parts = timePart.split(':');
        let hours = parseInt(parts[0], 10);
        const minutes = parts[1];
        if (isNaN(hours) || !minutes) return timeString;
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;
        const strHours = hours < 10 ? '0' + hours : hours;
        return `${strHours}:${minutes} ${ampm}`;
    };

    // Extract visit date and time
    const tarikhLawatan = ticket.konsultasi_rangkaian?.tarikh_lawatan || ticket.transformasi_digital?.tarikh_lawatan;
    const masaLawatan = ticket.konsultasi_rangkaian?.masa_lawatan || ticket.transformasi_digital?.masa_lawatan;

    // Print site information
    const handleCetakTapak = () => {
        if (!ticket || !ticket.id_tiket) {
            alert("Ralat: ID Tiket tidak dijumpai.");
            return;
        }
        window.open(route('tickets.cetakMaklumatTapak', { id_tiket: ticket.id_tiket }), '_blank');
    };

    // Read-only view for site information
    if (!isEditing) {
        return (
            <div className="w-full space-y-5 animate-in fade-in duration-300">
                <div className="bg-white rounded-2xl border border-gray-200/80 shadow-sm overflow-hidden w-full">

                    {/* Header Section */}
                    <div className="flex justify-between items-center px-5 py-3.5 bg-[#001f4d] text-white">
                        <h4 className="text-[10px] font-black uppercase tracking-wider flex items-center gap-2">
                            Lawatan Tapak Infrastruktur Rangkaian
                        </h4>
                        <div className="flex items-center gap-2">
                            {bolehEditTapak && (
                                <button
                                    type="button"
                                    onClick={() => setIsEditing(true)}
                                    className="inline-flex items-center gap-1.5 text-[10px] uppercase font-black bg-blue-600 hover:bg-blue-700 px-3 py-1.5 rounded-lg text-white transition-all shadow-sm cursor-pointer active:scale-95"
                                >
                                    <Edit size={12} /> Kemaskini
                                </button>
                            )}
                            <button
                                type="button"
                                onClick={handleCetakTapak}
                                className="inline-flex items-center gap-1.5 text-[10px] uppercase font-black bg-emerald-600 hover:bg-emerald-700 px-3 py-1.5 rounded-lg text-white transition-all shadow-sm cursor-pointer active:scale-95"
                            >
                                <Printer size={12} /> Cetak Maklumat Tapak
                            </button>
                        </div>
                    </div>

                    <div className="p-6 space-y-6">

                        {/* Section 1: Visit Details */}
                        <div className="space-y-3.5">
                            <h5 className="text-[10px] uppercase tracking-wider text-blue-900 font-black mb-2 flex items-center gap-1.5">
                                <Briefcase size={14} /> Butiran Lawatan
                            </h5>
                            <div className="text-xs font-bold text-gray-700 space-y-3.5 pl-1">
                                <div className="grid grid-cols-1 sm:grid-cols-4 md:grid-cols-3">
                                    <span className="text-gray-400 font-medium sm:col-span-1">Agensi</span>
                                    <span className="text-gray-800 uppercase sm:col-span-3 md:col-span-2 sm:pl-2">: &nbsp; {ticket.agensi || '-'}</span>
                                </div>
                                <div className="grid grid-cols-1 sm:grid-cols-4 md:grid-cols-3">
                                    <span className="text-gray-400 font-medium sm:col-span-1">Daerah</span>
                                    <span className="text-gray-800 uppercase sm:col-span-3 md:col-span-2 sm:pl-2">: &nbsp; {ticket.daerah || '-'}</span>
                                </div>
                                <div className="grid grid-cols-1 sm:grid-cols-4 md:grid-cols-3">
                                    <span className="text-gray-400 font-medium sm:col-span-1">Tarikh Lawatan</span>
                                    <span className="text-gray-800 sm:col-span-3 md:col-span-2 sm:pl-2">: &nbsp; {formatTarikh(tarikhLawatan)}</span>
                                </div>
                                <div className="grid grid-cols-1 sm:grid-cols-4 md:grid-cols-3">
                                    <span className="text-gray-400 font-medium sm:col-span-1">Masa Lawatan</span>
                                    <span className="text-gray-800 sm:col-span-3 md:col-span-2 sm:pl-2">: &nbsp; {formatMasa(masaLawatan)}</span>
                                </div>
                            </div>
                        </div>

                        <hr className="border-gray-100" />

                        {/* Section 2: Assigned Officers Details */}
                        <div className="space-y-3.5">
                            <h5 className="text-[10px] uppercase tracking-wider text-blue-900 font-black mb-2 flex items-center gap-1.5">
                                <Users size={14} /> Maklumat Pegawai Lawatan
                            </h5>
                            <div className="pl-1 overflow-x-auto">
                                <table className="w-full min-w-[500px] text-left text-xs border-collapse rounded-lg overflow-hidden border border-gray-100">
                                    <thead className="bg-slate-50 text-gray-500 uppercase text-[9px] tracking-wider font-black">
                                        <tr>
                                            <th className="p-3 w-12 text-center border-b border-gray-100">Bil.</th>
                                            <th className="p-3 border-b border-gray-100">Nama Pegawai</th>
                                            <th className="p-3 border-b border-gray-100">Jawatan</th>
                                            <th className="p-3 border-b border-gray-100">No. Telefon</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-50 bg-white">
                                        {ticket.petugas && ticket.petugas.length > 0 ? (
                                            ticket.petugas.map((pegawai, index) => (
                                                <tr key={index} className="hover:bg-slate-50/50">
                                                    <td className="p-2.5 text-center font-bold text-gray-700">{index + 1}</td>
                                                    <td className="p-2.5 font-black text-gray-800 uppercase">{pegawai.nama}</td>
                                                    <td className="p-2.5 font-bold text-gray-600 uppercase">{pegawai.jawatan || '-'}</td>
                                                    <td className="p-2.5 font-bold text-gray-600">{pegawai.no_telefon || '-'}</td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan="4" className="p-4 text-center text-gray-400 italic">Tiada petugas dilantik.</td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <hr className="border-gray-100" />

                        {/* Section 3: Site Infrastructure Details */}
                        <div className="space-y-3.5 mt-6">
                            <h5 className="text-[10px] uppercase tracking-wider text-blue-900 font-black mb-1 flex items-center gap-1">
                                <Briefcase size={14} /> Maklumat Tapak
                            </h5>
                            <div className="text-xs font-bold text-gray-700 space-y-3.5 pl-1">
                                <div className="grid grid-cols-3">
                                    <span className="text-gray-400 font-medium">Nama Lokasi / Bangunan</span>
                                    <span className="col-span-2 text-gray-800 uppercase pl-2">: &nbsp; {data.nama_lokasi_bangunan || '-'}</span>
                                </div>
                                <div className="grid grid-cols-3">
                                    <span className="text-gray-400 font-medium">Jenis Premis</span>
                                    <span className="col-span-2 text-gray-800 uppercase pl-2">: &nbsp; {data.jenis_premis || '-'}</span>
                                </div>
                                <div className="grid grid-cols-3">
                                    <span className="text-gray-400 font-medium">Bilik Server</span>
                                    <span className="col-span-2 text-gray-800 uppercase pl-2">: &nbsp; {data.bilik_server || '-'}</span>
                                </div>
                                <div className="grid grid-cols-3">
                                    <span className="text-gray-400 font-medium">Rack Server</span>
                                    <span className="col-span-2 text-gray-800 uppercase pl-2">: &nbsp; {data.rack_server || '-'}</span>
                                </div>
                                <div className="grid grid-cols-3">
                                    <span className="text-gray-400 font-medium">Sumber Kuasa Elektrik</span>
                                    <span className="col-span-2 text-gray-800 uppercase pl-2">: &nbsp; {data.sumber_kuasa || '-'}</span>
                                </div>
                                <div className="grid grid-cols-3">
                                    <span className="text-gray-400 font-medium">Persekitaran Fizikal</span>
                                    <span className="col-span-2 text-gray-800 uppercase pl-2">: &nbsp; {data.persekitaran_fizikal || '-'}</span>
                                </div>
                                <div className="grid grid-cols-3">
                                    <span className="text-gray-400 font-medium">Liputan Rangkaian Sedia Ada</span>
                                    <span className="col-span-2 text-gray-800 uppercase pl-2">: &nbsp; {data.liputan || '-'}</span>
                                </div>
                                <div className="grid grid-cols-3">
                                    <span className="text-gray-400 font-medium">Jenis Capaian</span>
                                    <span className="col-span-2 text-gray-800 uppercase pl-2">: &nbsp; {data.jenis_capaian || '-'}</span>
                                </div>
                                <div className="grid grid-cols-3">
                                    <span className="text-gray-400 font-medium">Kelajuan</span>
                                    <span className="col-span-2 text-gray-800 uppercase pl-2">: &nbsp; {data.kelajuan || '-'}</span>
                                </div>
                                <div className="grid grid-cols-3">
                                    <span className="text-gray-400 font-medium">Rangkaian Dalaman (LAN)</span>
                                    <span className="col-span-2 text-gray-800 uppercase pl-2">: &nbsp; {data.lan || '-'}</span>
                                </div>
                                <div className="grid grid-cols-3">
                                    <span className="text-gray-400 font-medium">Access Point (AP)</span>
                                    <span className="col-span-2 text-gray-800 uppercase pl-2">: &nbsp; {data.ap || '-'}</span>
                                </div>
                                <div className="grid grid-cols-3">
                                    <span className="text-gray-400 font-medium">Firewall</span>
                                    <span className="col-span-2 text-gray-800 uppercase pl-2">: &nbsp; {data.firewall || '-'}</span>
                                </div>
                            </div>
                        </div>

                        <hr className="border-gray-100" />

                        {/* Section 4: Site Summary */}
                        <div className="space-y-3.5">
                            <h5 className="text-[10px] uppercase tracking-wider text-blue-900 font-black mb-1 flex items-center gap-1.5">
                                <FileText size={14} /> Rumusan Tapak
                            </h5>
                            <div className="p-4 bg-gray-50/80 border border-gray-100 rounded-xl text-xs font-semibold text-gray-700 whitespace-pre-wrap leading-relaxed shadow-inner min-h-[80px]">
                                {data.rumusan || 'Tiada rumusan direkodkan.'}
                            </div>
                        </div>

                        <hr className="border-gray-100" />

                        {/* Section 5: Visit Technical Comments */}
                        <div className="space-y-3.5">
                            <h5 className="text-[10px] uppercase tracking-wider text-blue-900 font-black mb-1 flex items-center gap-1.5">
                                <FileText size={14} /> Ulasan Lawatan
                            </h5>
                            <div className="p-4 bg-gray-50/80 border border-gray-100 rounded-xl text-xs font-semibold text-gray-700 space-y-2.5 shadow-inner min-h-[100px]">
                                {data.ulasan_teknikal && data.ulasan_teknikal.length > 0 && data.ulasan_teknikal[0].teks ? (
                                    data.ulasan_teknikal.map((item, idx) => (
                                        <div key={idx} className="flex items-start gap-1">
                                            <span className="text-gray-400 font-black w-5">{idx + 1}.</span>
                                            <span className="text-gray-800 uppercase leading-relaxed">{item.teks}</span>
                                        </div>
                                    ))
                                ) : (
                                    <span className="text-gray-400 italic font-medium block pt-1">
                                        Tiada ulasan teknikal lawatan tapak direkodkan.
                                    </span>
                                )}
                            </div>
                        </div>

                        <hr className="border-gray-100" />

                        <div className="space-y-3.5">
                            <h5 className="text-[10px] uppercase tracking-wider text-blue-900 font-black flex items-center gap-1.5">
                                <FileText size={14} />
                                Cadangan Penambahbaikan
                            </h5>

                            <div className="p-4 bg-gray-50/80 border border-gray-100 rounded-xl text-xs font-semibold text-gray-700 space-y-2.5 min-h-[100px]">
                                {Array.isArray(data.cadangan_penambahbaikan)
                                && data.cadangan_penambahbaikan.length > 0
                                && data.cadangan_penambahbaikan[0]?.teks ? (
                                    data.cadangan_penambahbaikan.map((item, index) => (
                                        <div key={index} className="flex items-start gap-2">
                                            <span className="text-gray-400 font-black w-5">
                                                {index + 1}.
                                            </span>
                                            <span className="text-gray-800 font-semibold leading-relaxed whitespace-pre-wrap">
                                                {item.teks}
                                            </span>
                                        </div>
                                    ))
                                ) : (
                                    <span className="text-gray-400 italic">
                                        Tiada cadangan direkodkan.
                                    </span>
                                )}
                            </div>
                        </div>

                        <hr className="border-gray-100" />

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="p-4 border border-gray-200 rounded-xl bg-slate-50">
                                <p className="text-[10px] uppercase font-black text-blue-900 mb-2">
                                    Logical Diagram
                                </p>

                                {dataLaporan.logical_diagram ? (
                                    <a
                                        href={`/storage/${dataLaporan.logical_diagram}`}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="text-xs font-bold text-blue-600 hover:underline break-all"
                                    >
                                        {logicalFileName}
                                    </a>
                                ) : (
                                    <p className="text-xs italic text-gray-400">
                                        Tiada fail.
                                    </p>
                                )}
                            </div>

                            <div className="p-4 border border-gray-200 rounded-xl bg-slate-50">
                                <p className="text-[10px] uppercase font-black text-blue-900 mb-2">
                                    Physical Diagram
                                </p>

                                {dataLaporan.physical_diagram ? (
                                    <a
                                        href={`/storage/${dataLaporan.physical_diagram}`}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="text-xs font-bold text-blue-600 hover:underline break-all"
                                    >
                                        {physicalFileName}
                                    </a>
                                ) : (
                                    <p className="text-xs italic text-gray-400">
                                        Tiada fail.
                                    </p>
                                )}
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        );
    }

    // Interactive edit mode form (Only for PIC)
    return (
        <form onSubmit={handleHantarTapak} className="w-full space-y-6 text-xs font-bold text-gray-700 animate-in fade-in duration-300">

            <div className="bg-white p-6 md:p-8 rounded-2xl border border-gray-200/70 shadow-sm space-y-5">

                <div className="flex justify-between items-center">
                    <h3 className="text-blue-800 font-black text-xs uppercase tracking-wider pl-1">
                        Borang Maklumat Tapak <span className="text-red-500 font-bold ml-1">*</span>
                    </h3>
                    {dataTapak.jenis_premis && (
                        <button type="button" onClick={() => setIsEditing(false)} className="text-[10px] text-gray-400 font-bold uppercase hover:text-gray-700 transition-colors underline cursor-pointer">Batal Kemaskini</button>
                    )}
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 w-full">
                    <div className="bg-white p-5 rounded-xl border border-gray-200/70 shadow-sm space-y-2.5 md:col-span-2">
                        <label className="block text-xs font-black text-gray-800">Nama Lokasi / Bangunan </label>
                        <input
                            type="text"
                            value={data.nama_lokasi_bangunan}
                            onChange={e => setData('nama_lokasi_bangunan', e.target.value)}
                            placeholder="Sila nyatakan nama lokasi atau bangunan..."
                            className="w-full h-11 text-xs font-semibold bg-white border border-gray-200 rounded-xl px-4 focus:outline-none focus:border-blue-500 shadow-sm placeholder:text-gray-300 placeholder:font-normal"
                        />
                    </div>

                    <div className="bg-white p-5 rounded-xl border border-gray-200/70 shadow-sm space-y-2.5">
                        <label className="block text-xs font-black text-gray-800">Jenis Premis <span className="text-red-500 font-bold ml-1">*</span></label>
                        <div className="flex items-center gap-5 pt-1 text-xs font-semibold text-gray-700">
                            {['Pejabat Kerajaan', 'Sekolah', 'Lain-Lain'].map(val => (
                                <label key={val} className="flex items-center gap-2 cursor-pointer select-none">
                                    <input type="radio" name="jenis_premis" value={val} checked={data.jenis_premis === val} onChange={e => setData('jenis_premis', e.target.value)} className="text-blue-600 focus:ring-blue-500 border-gray-300 w-4 h-4 cursor-pointer" />
                                    <span>{val}</span>
                                </label>
                            ))}
                        </div>
                    </div>

                    <div className="bg-white p-5 rounded-xl border border-gray-200/70 shadow-sm space-y-2.5">
                        <label className="block text-xs font-black text-gray-800">Bilik Server <span className="text-red-500 font-bold ml-1">*</span></label>
                        <div className="flex items-center gap-6 pt-1 text-xs font-semibold text-gray-700">
                            {[
                                { label: 'Ada', value: 'ADA' },
                                { label: 'Tiada', value: 'TIADA' }
                            ].map(item => (
                                <label key={item.value} className="flex items-center gap-2 cursor-pointer select-none">
                                    <input type="radio" name="bilik_server" value={item.value} checked={data.bilik_server === item.value} onChange={e => setData('bilik_server', e.target.value)} className="text-blue-600 focus:ring-blue-500 border-gray-300 w-4 h-4 cursor-pointer" />
                                    <span>{item.label}</span>
                                </label>
                            ))}
                        </div>
                    </div>

                    <div className="bg-white p-5 rounded-xl border border-gray-200/70 shadow-sm space-y-2.5">
                        <label className="block text-xs font-black text-gray-800">Rack Server <span className="text-red-500 font-bold ml-1">*</span></label>
                        <div className="flex items-center gap-6 pt-1 text-xs font-semibold text-gray-700">
                            {[
                                { label: 'Ada', value: 'ADA' },
                                { label: 'Tiada', value: 'TIADA' }
                            ].map(item => (
                                <label key={item.value} className="flex items-center gap-2 cursor-pointer select-none">
                                    <input type="radio" name="rack_server" value={item.value} checked={data.rack_server === item.value} onChange={e => setData('rack_server', e.target.value)} className="text-blue-600 focus:ring-blue-500 border-gray-300 w-4 h-4 cursor-pointer" />
                                    <span>{item.label}</span>
                                </label>
                            ))}
                        </div>
                    </div>

                    <div className="bg-white p-5 rounded-xl border border-gray-200/70 shadow-sm space-y-2.5">
                        <label className="block text-xs font-black text-gray-800">Sumber Kuasa Elektrik <span className="text-red-500 font-bold ml-1">*</span></label>
                        <div className="flex items-center gap-5 pt-1 text-xs font-semibold text-gray-700">
                            {[
                                { label: 'Ada', value: 'ADA' },
                                { label: 'Tiada', value: 'TIADA' },
                                { label: 'Lain-Lain', value: 'LAIN-LAIN' }
                            ].map(item => (
                                <label key={item.value} className="flex items-center gap-2 cursor-pointer select-none">
                                    <input type="radio" name="sumber_kuasa" value={item.value} checked={data.sumber_kuasa === item.value} onChange={e => setData('sumber_kuasa', e.target.value)} className="text-blue-600 focus:ring-blue-500 border-gray-300 w-4 h-4 cursor-pointer" />
                                    <span>{item.label}</span>
                                </label>
                            ))}
                        </div>
                    </div>

                    <div className="bg-white p-5 rounded-xl border border-gray-200/70 shadow-sm space-y-2.5 md:col-span-2">
                        <label className="block text-xs font-black text-gray-800">Persekitaran Fizikal <span className="text-red-500 font-bold ml-1">*</span></label>
                        <div className="flex items-center gap-6 pt-1 text-xs font-semibold text-gray-700">
                            {[
                                { label: 'Berhawa Dingin', value: 'BERHAWA DINGIN' },
                                { label: 'Tidak Berhawa Dingin', value: 'TIDAK BERHAWA DINGIN' }
                            ].map(item => (
                                <label key={item.value} className="flex items-center gap-2 cursor-pointer select-none">
                                    <input type="radio" name="persekitaran_fizikal" value={item.value} checked={data.persekitaran_fizikal === item.value} onChange={e => setData('persekitaran_fizikal', e.target.value)} className="text-blue-600 focus:ring-blue-500 border-gray-300 w-4 h-4 cursor-pointer" />
                                    <span>{item.label}</span>
                                </label>
                            ))}
                        </div>
                    </div>

                    <div className="bg-white p-5 rounded-xl border border-gray-200/70 shadow-sm space-y-2.5">
                        <label className="block text-xs font-black text-gray-800">Liputan Rangkaian Sedia Ada <span className="text-red-500 font-bold ml-1">*</span></label>
                        <div className="flex items-center gap-6 pt-1 text-xs font-semibold text-gray-700">
                            {['LAN', 'WiFi', 'Tiada'].map(val => (
                                <label key={val} className="flex items-center gap-2 cursor-pointer select-none">
                                    <input type="radio" name="liputan" value={val === 'WiFi' ? 'WIFI' : val === 'Tiada' ? 'TIADA' : 'LAN'} checked={data.liputan === (val === 'WiFi' ? 'WIFI' : val === 'Tiada' ? 'TIADA' : 'LAN')} onChange={e => setData('liputan', e.target.value)} className="text-blue-600 focus:ring-blue-500 border-gray-300 w-4 h-4 cursor-pointer" />
                                    <span>{val}</span>
                                </label>
                            ))}
                        </div>
                    </div>

                    <div className="bg-white p-5 rounded-xl border border-gray-200/70 shadow-sm space-y-2.5">
                        <label className="block text-xs font-black text-gray-800">Rangkaian Dalaman (LAN) <span className="text-red-500 font-bold ml-1">*</span></label>
                        <div className="flex items-center gap-6 pt-1 text-xs font-semibold text-gray-700">
                            {[
                                { label: 'Ada', value: 'ADA' },
                                { label: 'Tiada', value: 'TIADA' }
                            ].map(item => (
                                <label key={item.value} className="flex items-center gap-2 cursor-pointer select-none">
                                    <input type="radio" name="lan" value={item.value} checked={data.lan === item.value} onChange={e => setData('lan', e.target.value)} className="text-blue-600 focus:ring-blue-500 border-gray-300 w-4 h-4 cursor-pointer" />
                                    <span>{item.label}</span>
                                </label>
                            ))}
                        </div>
                    </div>

                    <div className="bg-white p-5 rounded-xl border border-gray-200/70 shadow-sm space-y-2.5">
                        <label className="block text-xs font-black text-gray-800">Access Point (AP) <span className="text-red-500 font-bold ml-1">*</span></label>
                        <div className="flex items-center gap-6 pt-1 text-xs font-semibold text-gray-700">
                            {[
                                { label: 'Ada', value: 'ADA' },
                                { label: 'Tiada', value: 'TIADA' }
                            ].map(item => (
                                <label key={item.value} className="flex items-center gap-2 cursor-pointer select-none">
                                    <input type="radio" name="ap" value={item.value} checked={data.ap === item.value} onChange={e => setData('ap', e.target.value)} className="text-blue-600 focus:ring-blue-500 border-gray-300 w-4 h-4 cursor-pointer" />
                                    <span>{item.label}</span>
                                </label>
                            ))}
                        </div>
                    </div>

                    <div className="bg-white p-5 rounded-xl border border-gray-200/70 shadow-sm space-y-2.5">
                        <label className="block text-xs font-black text-gray-800">Firewall <span className="text-red-500 font-bold ml-1">*</span></label>
                        <div className="flex items-center gap-6 pt-1 text-xs font-semibold text-gray-700">
                            {[
                                { label: 'Ada', value: 'ADA' },
                                { label: 'Tiada', value: 'TIADA' }
                            ].map(item => (
                                <label key={item.value} className="flex items-center gap-2 cursor-pointer select-none">
                                    <input type="radio" name="firewall" value={item.value} checked={data.firewall === item.value} onChange={e => setData('firewall', e.target.value)} className="text-blue-600 focus:ring-blue-500 border-gray-300 w-4 h-4 cursor-pointer" />
                                    <span>{item.label}</span>
                                </label>
                            ))}
                        </div>
                    </div>

                    <div className="bg-white p-5 rounded-xl border border-gray-200/70 shadow-sm space-y-2.5">
                        <label className="block text-xs font-black text-gray-800">Jenis Capaian <span className="text-red-500 font-bold ml-1">*</span></label>
                        <select value={data.jenis_capaian} onChange={e => setData('jenis_capaian', e.target.value)} className="w-full h-11 rounded-xl text-xs bg-white border border-gray-200 px-4 font-bold text-gray-700 focus:outline-none focus:border-blue-500 shadow-sm cursor-pointer transition-all">
                            <option value="SABAH NET">SABAH NET</option>
                            <option value="TM (UNIFI)">TM (UNIFI)</option>
                        </select>
                    </div>

                    <div className="bg-white p-5 rounded-xl border border-gray-200/70 shadow-sm space-y-2.5">
                        <label className="block text-xs font-black text-gray-800">Kelajuan <span className="text-red-500 font-bold ml-1">*</span></label>
                        <select value={data.kelajuan} onChange={e => setData('kelajuan', e.target.value)} className="w-full h-11 rounded-xl text-xs bg-white border border-gray-200 px-4 font-bold text-gray-700 focus:outline-none focus:border-blue-500 shadow-sm cursor-pointer transition-all">
                            <option value="30 MBPS">30 MBPS</option>
                            <option value="100 MBPS">100 MBPS</option>
                            <option value="300 MBPS">300 MBPS</option>
                            <option value="800 MBPS">800 MBPS</option>
                        </select>
                    </div>
                </div>
            </div>

            {/* Site summary section */}
            <div className="bg-white p-6 md:p-8 rounded-2xl border border-gray-200/70 shadow-sm space-y-4 w-full">
                <h3 className="text-blue-800 font-black text-xs uppercase tracking-wider pl-1">
                    Rumusan Tapak <span className="text-red-500 font-bold ml-1">*</span>
                </h3>
                <textarea
                    value={data.rumusan}
                    onChange={e => setData('rumusan', e.target.value)}
                    placeholder="(Ringkaskan keadaan semasa, keperluan rangkaian, serta cadangan umum pelaksanaan)"
                    className="w-full text-xs font-semibold bg-white border border-gray-200 rounded-xl px-4 py-3 text-gray-700 focus:outline-none focus:border-blue-500 min-h-[110px] shadow-sm resize-none transition-all placeholder:text-gray-300 placeholder:font-normal leading-relaxed"
                />
            </div>

            {/* Dynamic technical review inputs */}
            <div className="bg-white p-6 md:p-8 rounded-2xl border border-gray-200/70 shadow-sm space-y-4 w-full">
                <h3 className="text-blue-800 font-black text-xs uppercase tracking-wider pl-1 flex items-center gap-1.5">
                     Ulasan Lawatan <span className="text-red-500 font-bold ml-1">*</span>
                </h3>

                <div className="space-y-2">
                    {data.ulasan_teknikal.map((item, idx) => (
                        <div key={idx} className="flex items-center gap-2">
                            <span className="text-gray-400 w-5 text-xs font-semibold">
                                {idx + 1}.
                            </span>

                            <input
                                type="text"
                                value={item.teks || ''}
                                onChange={e => kemaskiniUlasan(idx, e.target.value)}
                                placeholder="Sila nyatakan ulasan teknikal..."
                                className="flex-1 h-11 text-xs font-semibold bg-white border border-gray-200 rounded-xl px-4 focus:outline-none focus:border-blue-500 shadow-sm"
                                required
                            />

                            <button
                                type="button"
                                onClick={() => buangUlasan(idx)}
                                disabled={data.ulasan_teknikal.length === 1}
                                className="text-red-500 hover:text-red-700 p-2 disabled:opacity-30 cursor-pointer"
                            >
                                <Trash2 size={16} />
                            </button>
                        </div>
                    ))}

                    <button
                        type="button"
                        onClick={tambahUlasan}
                        className="inline-flex items-center gap-1.5 mt-2 px-3 py-1.5 text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors text-[10px] uppercase font-black cursor-pointer"
                    >
                        <Plus size={12} /> Tambah Ulasan
                    </button>
                </div>
            </div>

            {/* Cadangan Penambahbaikan oleh Juruteknik */}
            <div className="bg-white p-6 md:p-8 rounded-2xl border border-gray-200/70 shadow-sm space-y-4 w-full">
                <h3 className="text-blue-800 font-black text-xs uppercase tracking-wider">
                    Cadangan Penambahbaikan
                    <span className="text-red-500 ml-1">*</span>
                </h3>

                {errors.cadangan_penambahbaikan && (
                    <p role="alert" className="text-xs font-bold text-red-600">
                        {errors.cadangan_penambahbaikan}
                    </p>
                )}

                <div className="space-y-2">
                    {(Array.isArray(data.cadangan_penambahbaikan)
                        ? data.cadangan_penambahbaikan
                        : [{ teks: '' }]
                    ).map((item, index) => (
                        <div key={index} className="flex items-start gap-2">
                            <span className="h-11 min-w-8 inline-flex items-center justify-center bg-slate-100 border border-slate-200 rounded-xl text-xs font-black text-slate-600">
                                {index + 1}
                            </span>

                            <div className="flex-1 space-y-1">
                                <textarea
                                    value={item?.teks || ''}
                                    onChange={event => kemaskiniCadangan(index, event.target.value)}
                                    maxLength={2000}
                                    rows={2}
                                    placeholder="Nyatakan cadangan penambahbaikan..."
                                    className="w-full p-3 bg-white border border-gray-200 rounded-xl text-xs font-semibold focus:outline-none focus:border-blue-500"
                                    required
                                />

                                {errors[`cadangan_penambahbaikan.${index}.teks`] && (
                                    <p role="alert" className="text-xs font-bold text-red-600">
                                        {errors[`cadangan_penambahbaikan.${index}.teks`]}
                                    </p>
                                )}
                            </div>

                            {data.cadangan_penambahbaikan.length > 1 && (
                                <button
                                    type="button"
                                    onClick={() => buangCadangan(index)}
                                    className="text-red-500 hover:text-red-700 p-2.5 border border-red-100 rounded-xl"
                                    title="Padam Cadangan"
                                >
                                    <Trash2 size={16} />
                                </button>
                            )}
                        </div>
                    ))}
                </div>

                <button
                    type="button"
                    onClick={tambahCadangan}
                    disabled={data.cadangan_penambahbaikan.length >= 20}
                    className="inline-flex items-center gap-1.5 px-3 py-2 text-blue-600 bg-blue-50 hover:bg-blue-100 disabled:opacity-50 rounded-lg text-[10px] uppercase font-black"
                >
                    <Plus size={12} />
                    Tambah Cadangan
                </button>
            </div>

            {/* Diagram oleh Juruteknik */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div className="bg-white p-6 rounded-2xl border border-gray-200/70 shadow-sm space-y-3">
                    <h3 className="text-blue-800 font-black text-xs uppercase">
                        Logical Diagram
                        <span className="text-red-500 ml-1">*</span>
                    </h3>

                    <label className="flex flex-col items-center justify-center min-h-36 border-2 border-dashed border-gray-200 rounded-xl bg-slate-50 cursor-pointer hover:border-blue-300">
                        <Upload size={24} className="text-blue-600 mb-2" />
                        <span className="text-xs font-bold text-gray-700 text-center break-all px-3">
                            {logicalFileName || 'Pilih fail PNG, JPG, JPEG atau PDF'}
                        </span>

                        <input
                            type="file"
                            accept=".png,.jpg,.jpeg,.pdf"
                            className="hidden"
                            onChange={event => setData(
                                'logical_diagram',
                                event.target.files?.[0] || null
                            )}
                        />
                    </label>

                    {data.logical_diagram && (
                        <button
                            type="button"
                            onClick={() => setData('logical_diagram', null)}
                            className="inline-flex items-center gap-1 text-xs font-bold text-red-600"
                        >
                            <X size={13} />
                            Buang pilihan
                        </button>
                    )}

                    {dataLaporan.logical_diagram && (
                        <a
                            href={`/storage/${dataLaporan.logical_diagram}`}
                            target="_blank"
                            rel="noreferrer"
                            className="block text-xs font-bold text-blue-600 hover:underline"
                        >
                            Lihat fail sedia ada
                        </a>
                    )}

                    {errors.logical_diagram && (
                        <p role="alert" className="text-xs font-bold text-red-600">
                            {errors.logical_diagram}
                        </p>
                    )}
                </div>

                <div className="bg-white p-6 rounded-2xl border border-gray-200/70 shadow-sm space-y-3">
                    <h3 className="text-blue-800 font-black text-xs uppercase">
                        Physical Diagram
                        <span className="text-red-500 ml-1">*</span>
                    </h3>

                    <label className="flex flex-col items-center justify-center min-h-36 border-2 border-dashed border-gray-200 rounded-xl bg-slate-50 cursor-pointer hover:border-blue-300">
                        <Upload size={24} className="text-blue-600 mb-2" />
                        <span className="text-xs font-bold text-gray-700 text-center break-all px-3">
                            {physicalFileName || 'Pilih fail PNG, JPG, JPEG atau PDF'}
                        </span>

                        <input
                            type="file"
                            accept=".png,.jpg,.jpeg,.pdf"
                            className="hidden"
                            onChange={event => setData(
                                'physical_diagram',
                                event.target.files?.[0] || null
                            )}
                        />
                    </label>

                    {data.physical_diagram && (
                        <button
                            type="button"
                            onClick={() => setData('physical_diagram', null)}
                            className="inline-flex items-center gap-1 text-xs font-bold text-red-600"
                        >
                            <X size={13} />
                            Buang pilihan
                        </button>
                    )}

                    {dataLaporan.physical_diagram && (
                        <a
                            href={`/storage/${dataLaporan.physical_diagram}`}
                            target="_blank"
                            rel="noreferrer"
                            className="block text-xs font-bold text-blue-600 hover:underline"
                        >
                            Lihat fail sedia ada
                        </a>
                    )}

                    {errors.physical_diagram && (
                        <p role="alert" className="text-xs font-bold text-red-600">
                            {errors.physical_diagram}
                        </p>
                    )}
                </div>
            </div>

            {/* Form submission action */}
            <div className="flex justify-end items-center pt-2 w-full">
                <button
                    type="submit"
                    disabled={processing}
                    className="inline-flex items-center gap-2 px-6 py-3.5 bg-blue-900 hover:bg-blue-950 disabled:bg-gray-200 disabled:text-gray-400 disabled:cursor-not-allowed text-white rounded-xl font-black text-xs uppercase tracking-wider shadow-md transition-all active:scale-[0.99] cursor-pointer w-full sm:w-auto justify-center"
                >
                    <Send size={13} /> {processing ? 'Menyimpan...' : 'Hantar Maklumat Tapak'}
                </button>
            </div>

        </form>
    );
}
