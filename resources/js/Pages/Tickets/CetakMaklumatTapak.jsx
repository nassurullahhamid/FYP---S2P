import React from 'react';
import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';

export default function CetakMaklumatTapak({ ticket }) {
    // Read DB table relation data
    const kr = ticket?.konsultasi_rangkaian || {};
    const pegawai = ticket?.petugas || [];

    // Date and time format helpers
    const formatTarikh = (dateString) => {
        if (!dateString) return '';
        const cleanDate = dateString.split(' ')[0];
        const [year, month, day] = cleanDate.split('-');
        if (!year || !month || !day) return dateString;
        return `${day}/${month}/${year}`;
    };

    const formatMasa = (timeString) => {
        if (!timeString) return '';
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

    // Helper: Parse technical review comments safely
    let ulasanList = [];
    if (kr.ulasan_teknikal) {
        try {
            const parsed = JSON.parse(kr.ulasan_teknikal);
            if (Array.isArray(parsed)) {
                ulasanList = parsed;
            } else {
                ulasanList = [kr.ulasan_teknikal];
            }
        } catch (e) {
            ulasanList = [kr.ulasan_teknikal];
        }
    }

    // Helper: Case-insensitive string match check
    const checkMatch = (dbValue, targetValue) => {
        if (!dbValue || !targetValue) return false;
        return String(dbValue).trim().toLowerCase() === String(targetValue).trim().toLowerCase();
    };

    // Checkbox helper component
    const Checkbox = ({ label, checked }) => (
        <div className="inline-flex items-center gap-1.5 mr-6 shrink-0">
            <div className="w-3.5 h-3.5 border border-black flex items-center justify-center font-bold text-[10px] shrink-0 print:border-black">
                {checked ? '✓' : ''}
            </div>
            <span className="text-xs text-black whitespace-nowrap">{label}</span>
        </div>
    );

    return (
        <div className="min-h-screen bg-gray-200 py-10 print:bg-white print:py-0 text-black font-sans">
            <Head title={`Cetak Maklumat Tapak - ${ticket?.id_tiket || ''}`} />

            {/* Print button (hidden during print) */}
            <div className="max-w-4xl mx-auto mb-6 flex justify-end print:hidden">
                <button
                    onClick={() => window.print()}
                    className="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl font-black text-sm uppercase tracking-wider shadow-lg transition-all"
                >
                    <Printer size={18} /> Cetak Borang Tapak
                </button>
            </div>

            {/* A4 document layout wrapper */}
            <div className="max-w-[210mm] min-h-[297mm] mx-auto bg-white p-10 shadow-xl print:shadow-none print:w-full print:max-w-full print:p-2">

                {/* Document reference code */}
                <div className="text-right text-xs font-bold mb-1 mr-1 text-black">
                    JTDI/W-06
                </div>

                {/* Main header container */}
                <div className="border border-black mb-6">
                    <div className="border-b border-black p-2.5 text-center font-bold text-[15px] uppercase text-black bg-white">
                        Borang Lawatan Tapak Infrastruktur Rangkaian
                    </div>

                    <div className="p-3 text-xs space-y-3 font-semibold text-black">
                        <div className="flex items-center">
                            <div className="w-32 shrink-0">Jabatan/Agensi</div>
                            <div className="w-3 shrink-0">:</div>
                            <div className="flex-1 border-b border-black pb-0.5">{ticket.agensi || ''}</div>
                        </div>
                        <div className="flex items-center">
                            <div className="w-32 shrink-0">Lokasi Tapak</div>
                            <div className="w-3 shrink-0">:</div>
                            <div className="flex-1 border-b border-black pb-0.5">{kr.nama_lokasi_bangunan || ticket.lokasi || ''}</div>
                        </div>
                        <div className="flex items-center">
                            <div className="w-32 shrink-0">Tarikh Lawatan</div>
                            <div className="w-3 shrink-0">:</div>
                            <div className="flex-1 border-b border-black pb-0.5 tracking-[0.2em]">{formatTarikh(kr.tarikh_lawatan)}</div>
                        </div>
                        <div className="flex items-center">
                            <div className="w-32 shrink-0">Masa</div>
                            <div className="w-3 shrink-0">:</div>
                            <div className="flex-1 border-b border-black pb-0.5">{formatMasa(kr.masa_lawatan)}</div>
                        </div>
                    </div>
                </div>

                {/* 1. Visiting officer information */}
                <div className="mb-6">
                    <h3 className="text-xs font-bold mb-1.5 text-black">1. MAKLUMAT PEGAWAI LAWATAN</h3>
                    <table className="w-full border-collapse border border-black text-xs">
                        <thead>
                            <tr className="bg-[#d9e2f3] text-black">
                                <th className="border border-black p-1.5 w-10 text-center font-bold">Bil</th>
                                <th className="border border-black p-1.5 w-1/3 text-center font-bold">Nama Pegawai</th>
                                <th className="border border-black p-1.5 w-1/4 text-center font-bold">Jawatan</th>
                                <th className="border border-black p-1.5 w-1/4 text-center font-bold">No. Telefon</th>
                                <th className="border border-black p-1.5 text-center font-bold">Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            {pegawai && pegawai.length > 0 ? (
                                pegawai.map((p, idx) => (
                                    <tr key={idx} className="h-7">
                                        <td className="border border-black p-1 text-center font-medium">{idx + 1}</td>
                                        <td className="border border-black p-1 uppercase px-2">{p.nama || ''}</td>
                                        <td className="border border-black p-1 uppercase text-center">{p.jawatan || ''}</td>
                                        <td className="border border-black p-1 text-center">{p.no_telefon || ''}</td>
                                        <td className="border border-black p-1"></td>
                                    </tr>
                                ))
                            ) : (
                                <tr className="h-7">
                                    <td colSpan="5" className="border border-black p-2 text-center font-medium italic text-gray-500">
                                        Tiada rekod pegawai lawatan.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* 2. Site information */}
                <div className="mb-6">
                    <h3 className="text-xs font-bold mb-1.5 text-black">2. MAKLUMAT TAPAK</h3>
                    <table className="w-full border-collapse border border-black text-xs">
                        <thead>
                            <tr className="bg-[#d9e2f3] text-black">
                                <th className="border border-black p-2 w-[35%] text-center font-bold">Perkara</th>
                                <th className="border border-black p-2 text-center font-bold">Maklumat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr className="h-8">
                                <td className="border border-black p-2 font-bold">Nama Lokasi Bangunan</td>
                                <td className="border border-black p-2">
                                    <div className="border-b border-black w-11/12 pb-0.5 uppercase min-h-[16px]">{kr.nama_lokasi_bangunan || ''}</div>
                                </td>
                            </tr>
                            <tr className="h-8">
                                <td className="border border-black p-2 font-bold">Jenis Premis</td>
                                <td className="border border-black p-2 flex items-center flex-wrap">
                                    <Checkbox label="Pejabat Kerajaan" checked={checkMatch(kr.jenis_premis, 'Pejabat Kerajaan')} />
                                    <Checkbox label="Sekolah" checked={checkMatch(kr.jenis_premis, 'Sekolah')} />
                                    <div className="flex items-center shrink-0">
                                        <Checkbox label="Lain-lain:" checked={kr.jenis_premis && !checkMatch(kr.jenis_premis, 'Pejabat Kerajaan') && !checkMatch(kr.jenis_premis, 'Sekolah')} />
                                        <span className="inline-block border-b border-black w-32 pb-0.5 -ml-4 uppercase min-h-[16px]">
                                            {kr.jenis_premis && !checkMatch(kr.jenis_premis, 'Pejabat Kerajaan') && !checkMatch(kr.jenis_premis, 'Sekolah') ? kr.jenis_premis : ''}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            <tr className="h-8">
                                <td className="border border-black p-2 font-bold">Bilik Server</td>
                                <td className="border border-black p-2">
                                    <div className="flex items-center">
                                        <Checkbox label="Ada" checked={checkMatch(kr.bilik_server, 'Ada')} />
                                        <Checkbox label="Tiada" checked={checkMatch(kr.bilik_server, 'Tiada')} />
                                    </div>
                                </td>
                            </tr>
                            <tr className="h-8">
                                <td className="border border-black p-2 font-bold">Rack Server</td>
                                <td className="border border-black p-2">
                                    <div className="flex items-center">
                                        <Checkbox label="Ada" checked={checkMatch(kr.rack_server, 'Ada')} />
                                        <Checkbox label="Tiada" checked={checkMatch(kr.rack_server, 'Tiada')} />
                                    </div>
                                </td>
                            </tr>
                            <tr className="h-8">
                                <td className="border border-black p-2 font-bold">Sumber Kuasa Elektrik</td>
                                <td className="border border-black p-2 flex items-center flex-wrap">
                                    <Checkbox label="Ada" checked={checkMatch(kr.sumber_kuasa, 'Ada')} />
                                    <Checkbox label="Tiada" checked={checkMatch(kr.sumber_kuasa, 'Tiada')} />
                                    <div className="flex items-center shrink-0">
                                        <Checkbox label="Lain-lain:" checked={kr.sumber_kuasa && !checkMatch(kr.sumber_kuasa, 'Ada') && !checkMatch(kr.sumber_kuasa, 'Tiada')} />
                                        <span className="inline-block border-b border-black w-32 pb-0.5 -ml-4 uppercase min-h-[16px]">
                                            {kr.sumber_kuasa && !checkMatch(kr.sumber_kuasa, 'Ada') && !checkMatch(kr.sumber_kuasa, 'Tiada') ? kr.sumber_kuasa : ''}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            <tr className="h-8">
                                <td className="border border-black p-2 font-bold">Persekitaran Fizikal</td>
                                <td className="border border-black p-2">
                                    <div className="flex items-center">
                                        <Checkbox label="Berhawa Dingin" checked={checkMatch(kr.persekitaran_fizikal, 'Berhawa Dingin')} />
                                        <Checkbox label="Tidak Berhawa Dingin" checked={checkMatch(kr.persekitaran_fizikal, 'Tidak Berhawa Dingin')} />
                                    </div>
                                </td>
                            </tr>
                            <tr className="h-8">
                                <td className="border border-black p-2 font-bold">Liputan Rangkaian Sedia Ada</td>
                                <td className="border border-black p-2">
                                    <div className="flex items-center">
                                        <Checkbox label="LAN" checked={checkMatch(kr.liputan, 'LAN')} />
                                        <Checkbox label="WiFi" checked={checkMatch(kr.liputan, 'WiFi')} />
                                        <Checkbox label="Tiada" checked={checkMatch(kr.liputan, 'Tiada')} />
                                    </div>
                                </td>
                            </tr>
                            <tr className="h-8">
                                <td className="border border-black p-2 font-bold">Jenis Capaian</td>
                                <td className="border border-black p-2">
                                    <div className="flex items-center gap-6 w-full">
                                        <div className="flex items-center gap-2 w-1/2">
                                            <span>Jenis:</span>
                                            <span className="flex-1 border-b border-black inline-block pb-0.5 uppercase min-h-[16px]">{kr.jenis_capaian || ''}</span>
                                        </div>
                                        <div className="flex items-center gap-2 w-1/2 pr-4">
                                            <span>Kelajuan:</span>
                                            <span className="flex-1 border-b border-black inline-block pb-0.5 uppercase min-h-[16px]">{kr.kelajuan || ''}</span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr className="h-8">
                                <td className="border border-black p-2 font-bold">Rangkaian Dalaman (LAN)</td>
                                <td className="border border-black p-2">
                                    <div className="flex items-center">
                                        <Checkbox label="Ada" checked={checkMatch(kr.lan, 'Ada')} />
                                        <Checkbox label="Tiada" checked={checkMatch(kr.lan, 'Tiada')} />
                                    </div>
                                </td>
                            </tr>
                            <tr className="h-8">
                                <td className="border border-black p-2 font-bold">Access Point (AP)</td>
                                <td className="border border-black p-2">
                                    <div className="flex items-center">
                                        <Checkbox label="Ada" checked={checkMatch(kr.ap, 'Ada')} />
                                        <Checkbox label="Tiada" checked={checkMatch(kr.ap, 'Tiada')} />
                                    </div>
                                </td>
                            </tr>
                            <tr className="h-8">
                                <td className="border border-black p-2 font-bold">Firewall</td>
                                <td className="border border-black p-2">
                                    <div className="flex items-center">
                                        <Checkbox label="Ada" checked={checkMatch(kr.firewall, 'Ada')} />
                                        <Checkbox label="Tiada" checked={checkMatch(kr.firewall, 'Tiada')} />
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {/* 3. Summary */}
                <div className="mb-6">
                    <h3 className="text-xs font-bold mb-0.5 text-black">3. RUMUSAN</h3>
                    <p className="text-[10px] mb-1.5 text-black">(Ringkasan keadaan semasa, keperluan rangkaian, serta cadangan pelaksanaan)</p>
                    <div className="border border-black min-h-[80px] text-xs">
                        {kr.rumusan ? (
                            <div className="p-2 whitespace-pre-wrap leading-relaxed">{kr.rumusan}</div>
                        ) : (
                            <div className="flex flex-col h-full justify-around py-3 px-2 space-y-6">
                                <div className="border-b border-black w-full"></div>
                                <div className="border-b border-black w-full"></div>
                                <div className="border-b border-black w-full"></div>
                            </div>
                        )}
                    </div>
                </div>

                {/* 4. Visit remarks */}
                <div>
                    <h3 className="text-xs font-bold mb-0.5 text-black">4. ULASAN TEKNIKAL</h3>
                    <p className="text-xs mb-1.5 text-black">Disokong / Tidak Disokong:</p>
                    <div className="border border-black min-h-[60px] text-xs">
                        {ulasanList.length > 0 ? (
                            <div className="p-2 leading-relaxed space-y-1">
                                {ulasanList.map((ulasan, idx) => {
                                    // Extract string value if item is an object
                                    let text = typeof ulasan === 'object' && ulasan !== null
                                        ? Object.values(ulasan).filter(v => v).join(' - ')
                                        : ulasan;

                                    return (
                                        <div key={idx} className="flex gap-2">
                                            <span>{idx + 1}.</span>
                                            <span>{text}</span>
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="flex flex-col h-full justify-around py-3 px-2 space-y-6">
                                <div className="border-b border-black w-full"></div>
                                <div className="border-b border-black w-full"></div>
                            </div>
                        )}
                    </div>
                </div>

            </div>
        </div>
    );
}
