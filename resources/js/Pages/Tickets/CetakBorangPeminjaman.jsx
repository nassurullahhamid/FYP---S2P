import React from 'react';
import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';

export default function CetakBorangPeminjaman({ ticket, pic }) {
    // Parse item and report data
    const laporan = ticket?.laporan || {};
    let kosItems = {};
    try {
        kosItems = laporan.kos_items ? (typeof laporan.kos_items === 'string' ? JSON.parse(laporan.kos_items) : laporan.kos_items) : {};
    } catch (e) {
        kosItems = {};
    }

    const senaraiSiri = kosItems?.senarai_siri || [];

    // Get serial number from URL parameters
    const urlParts = typeof window !== 'undefined' ? window.location.pathname.split('/') : [];
    const targetSerial = urlParts[urlParts.length - 1];

    const itemData = senaraiSiri.find(item => String(item.serial_no) === String(targetSerial) || String(item.no_siri) === String(targetSerial)) || senaraiSiri[0] || {};

    // Helper: Format date string
    const formatTarikh = (dateString) => {
        if (!dateString) return '';
        const cleanDate = dateString.split(' ')[0];
        const [year, month, day] = cleanDate.split('-');
        if (!year || !month || !day) return dateString;
        return `${day}/${month}/${year}`;
    };

    // Helper: Case-insensitive string match check
    const checkMatch = (val, target) => {
        return String(val || '').trim().toLowerCase() === String(target).trim().toLowerCase();
    };

    // Helper: Format user roles
    const formatPeranan = (peranan) => {
        if (!peranan) return '';
        const p = peranan.toLowerCase();
        if (p === 'juruteknik') return 'Juruteknik';
        if (p === 'ketua_upp') return 'Ketua UPP';
        if (p === 'ketua_utd') return 'Ketua UTD';
        if (p === 'ketua_wilayah') return 'Ketua Wilayah';
        return peranan;
    };

    return (
        <div className="min-h-screen print:min-h-0 print:h-auto bg-[#e5e7eb] py-8 print:bg-white print:py-0 text-black font-sans flex flex-col items-center box-border">
            <Head title={`Cetak Peminjaman - ${targetSerial || ticket?.id_tiket}`} />

            {/* Print action button (hidden during print) */}
            <div className="w-[210mm] mb-4 flex justify-end print:hidden">
                <button
                    onClick={() => window.print()}
                    className="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl font-black text-sm uppercase tracking-wider shadow-lg transition-all"
                >
                    <Printer size={18} /> Cetak Borang
                </button>
            </div>

            {/* A4 document layout wrapper */}
            <div className="w-[210mm] min-h-[297mm] print:min-h-0 print:h-auto bg-white pt-6 pb-6 px-10 shadow-xl print:shadow-none print:w-full print:max-w-full print:p-2 box-border flex flex-col">

                {/* Document code */}
                <div className="text-right text-[11px] font-bold mb-1">
                    JTDI/W-01
                </div>

                {/* Header logo and department title */}
                <div className="flex flex-col items-center justify-center mb-1.5">
                    <img src="/images/logo_jtdi.png" alt="Logo JTDI" className="h-12 object-contain" />
                    <div className="text-[7.5px] font-black text-center leading-tight mt-0.5 tracking-wider uppercase text-black" style={{ fontFamily: 'Arial, sans-serif' }}>
                        Jabatan Teknologi Digital<br/>Dan Inovasi Negeri Sabah
                    </div>
                </div>

                {/* Form title */}
                <div className="text-center font-bold text-[13px] mb-3 leading-snug">
                    BORANG PEMINJAMAN PERKAKASAN ICT<br />
                    JABATAN TEKNOLOGI DIGITAL DAN INOVASI NEGERI SABAH<br />
                    <span className="font-normal text-[12px]">(Sila Isi Dua Salinan)</span>
                </div>

                {/* Master form table */}
                <table className="w-full border-collapse border border-black text-[11px]">
                    <colgroup>
                        <col style={{ width: '30%' }} />
                        <col style={{ width: '20%' }} />
                        <col style={{ width: '15%' }} />
                        <col style={{ width: '20%' }} />
                        <col style={{ width: '15%' }} />
                    </colgroup>
                    <tbody>

                        {/* Reference and acknowledgment section */}
                        <tr>
                            <td colSpan="5" className="border border-black px-3 py-2 space-y-2">
                                <div className="flex items-center gap-2">
                                    <span className="w-14">Ruj</span>
                                    <span>: ___________________________________</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="w-14">Kepada</span>
                                    <span>: <span className="uppercase font-semibold inline-block border-b border-gray-400 min-w-[200px]">{ticket.agensi}</span></span>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td colSpan="5" className="border border-black px-3 py-1.5 font-medium">
                                Saya mengaku menerima perkakasan komputer seperti berikut :
                            </td>
                        </tr>

                        {/* Hardware information */}
                        <tr>
                            <td colSpan="5" className="border border-black px-2 py-1 bg-gray-50/50">
                                <strong className="italic text-[12px]">MAKLUMAT PERKAKASAN</strong>
                            </td>
                        </tr>
                        <tr>
                            <td className="border border-black px-2 py-1.5">Jenis Perkakasan</td>
                            <td colSpan="4" className="border border-black px-2 py-1.5 uppercase font-medium">{kosItems.nama_aset || '-'}</td>
                        </tr>
                        <tr>
                            <td className="border border-black px-2 py-1.5">Jenama Perkakasan</td>
                            <td colSpan="4" className="border border-black px-2 py-1.5 uppercase font-medium">{itemData.model || itemData.jenama || '-'}</td>
                        </tr>
                        <tr>
                            <td className="border border-black px-2 py-1.5">No. Siri</td>
                            <td colSpan="4" className="border border-black px-2 py-1.5 uppercase font-medium">{itemData.serial_no || itemData.no_siri || '-'}</td>
                        </tr>
                        <tr>
                            <td className="border border-black px-2 py-1.5">No Pendaftaran Harta</td>
                            <td colSpan="4" className="border border-black px-2 py-1.5 uppercase font-medium">{itemData.no_pendaftaran_harta || '-'}</td>
                        </tr>

                        {/* Hardware status and usage mode checkboxes */}
                        <tr>
                            <td className="border border-black px-2 py-1.5">Status Perkakasan <span className="ml-1">*</span></td>
                            <td className="border border-black px-2 py-1.5">Baru</td>
                            <td className="border border-black px-2 py-1.5 text-center text-sm font-bold leading-none">
                                {checkMatch(itemData.status_perkakasan, 'Baru') ? '✓' : ''}
                            </td>
                            <td className="border border-black px-2 py-1.5">Terpakai</td>
                            <td className="border border-black px-2 py-1.5 text-center text-sm font-bold leading-none">
                                {checkMatch(itemData.status_perkakasan, 'Terpakai') ? '✓' : ''}
                            </td>
                        </tr>
                        <tr>
                            <td className="border border-black px-2 py-1.5">Mod Penggunaan <span className="ml-1">*</span></td>
                            <td className="border border-black px-2 py-1.5">Dipinjamkan</td>
                            <td className="border border-black px-2 py-1.5 text-center text-sm font-bold leading-none">
                                {checkMatch(itemData.mod_penggunaan, 'Dipinjamkan') ? '✓' : ''}
                            </td>
                            <td className="border border-black px-2 py-1.5">Diserahkan</td>
                            <td className="border border-black px-2 py-1.5 text-center text-sm font-bold leading-none">
                                {checkMatch(itemData.mod_penggunaan, 'Diserahkan') ? '✓' : ''}
                            </td>
                        </tr>

                        {/* Recipient information */}
                        <tr>
                            <td colSpan="5" className="border border-black px-2 py-1 bg-gray-50/50">
                                <strong className="italic text-[12px]">MAKLUMAT PENERIMA</strong>
                            </td>
                        </tr>
                        <tr>
                            <td className="border border-black px-2 py-1.5">Nama Penerima</td>
                            <td colSpan="4" className="border border-black px-2 py-1.5 uppercase font-medium">{ticket.nama_pemohon || '-'}</td>
                        </tr>
                        <tr>
                            <td className="border border-black px-2 py-1.5">Jawatan</td>
                            <td colSpan="4" className="border border-black px-2 py-1.5 uppercase font-medium">{itemData.jawatan_penerima || '-'}</td>
                        </tr>
                        <tr>
                            <td className="border border-black px-2 py-1.5">Tarikh Terima</td>
                            <td colSpan="2" className="border border-black px-2 py-1.5">{formatTarikh(kosItems.tarikh_lulus || ticket.created_at)}</td>
                            <td className="border border-black px-2 py-1.5">Tandatangan</td>
                            <td className="border border-black px-2 py-1.5"></td>
                        </tr>
                        <tr>
                            <td className="border border-black px-2 py-1.5 align-top pt-2 h-12">Cop Jabatan</td>
                            <td colSpan="4" className="border border-black px-2 py-1.5"></td>
                        </tr>

                        {/* Handover officer information */}
                        <tr>
                            <td colSpan="5" className="border border-black px-2 py-1 bg-gray-50/50">
                                <strong className="italic text-[12px]">MAKLUMAT PEGAWAI YANG MENYERAHKAN PERKAKASAN</strong>
                            </td>
                        </tr>
                        <tr>
                            <td className="border border-black px-2 py-1.5">Nama Pegawai</td>
                            <td colSpan="4" className="border border-black px-2 py-1.5 uppercase font-medium">{pic?.nama || '-'}</td>
                        </tr>
                        <tr>
                            <td className="border border-black px-2 py-1.5">Jawatan</td>
                            <td colSpan="2" className="border border-black px-2 py-1.5 uppercase font-medium">{formatPeranan(pic?.peranan) || '-'}</td>
                            <td className="border border-black px-2 py-1.5">Tandatangan</td>
                            <td className="border border-black px-2 py-1.5"></td>
                        </tr>
                        <tr>
                            <td className="border border-black px-2 py-1.5">Catatan</td>
                            <td colSpan="4" className="border border-black px-2 py-1.5 uppercase font-medium">{itemData.catatan || '-'}</td>
                        </tr>
                        <tr>
                            <td className="border border-black px-2 py-1.5">Tarikh Perkakasan Dikembalikan</td>
                            <td colSpan="4" className="border border-black px-2 py-1.5"></td>
                        </tr>

                        {/* Terms and conditions */}
                        <tr>
                            <td colSpan="5" className="border border-black px-3 py-2 text-[10px] leading-normal">
                                <strong className="block mb-1 font-bold">SYARAT DAN PERATURAN PINJAMAN ALATAN MEDIA</strong>
                                <ul className="list-disc pl-4 space-y-0.5">
                                    <li>Peralatan yang dipinjam hanya untuk aktiviti-aktiviti rasmi Jabatan sahaja.</li>
                                    <li>Pengguna perlu memastikan peralatan yang diambil berfungsi dengan baik sebelum digunakan.</li>
                                    <li>Pemohon perlu menggunakan peralatan dalam masa yang dibenarkan dan mengembalikan peralatan pada tarikh dan masa yang ditetapkan serta berada dalam keadaan baik.</li>
                                    <li>Pemohon perlu bertanggungjawab terhadap keadaan, keselamatan, dan ganti rugi dikenakan sekiranya berlaku kerosakan/kehilangan pada peralatan yang dipinjam.</li>
                                    <li>Peralatan yang ingin dipinjam boleh diambil pada setiap hari bekerja (Isnin – Jumaat) bermula jam 9.00 pagi hingga 5.00 petang.</li>
                                    <li>Pengguna perlu membuat laporan bertulis kepada Ketua Wilayah, Jabatan Perkhidmatan Komputer Negeri jika peralatan yang dipinjam hilang/rosak sewaktu tempoh pinjaman.</li>
                                    <li>Jabatan Perkhidmatan Komputer Negeri berhak membatalkan kelulusan permohonan pada bila-bila masa sekiranya pemohon/pengguna tidak mematuhi syarat dan peraturan yang telah ditetapkan.</li>
                                </ul>
                            </td>
                        </tr>

                        {/* Footer note */}
                        <tr>
                            <td colSpan="5" className="border border-black px-2 py-1 text-[9px]">
                                Sila tanda mana-mana yang berkenaan. Salinan pertama dikembalikan kepada Jabatan Perkhidmatan Komputer Negeri.
                            </td>
                        </tr>

                    </tbody>
                </table>
            </div>

            <style jsx>{`
                @media print {
                    @page {
                        size: A4 portrait;
                        margin: 0.5cm;
                    }
                    body {
                        background-color: white;
                        padding: 0 !important;
                        margin: 0 !important;
                        height: auto !important;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                }
            `}</style>
        </div>
    );
}
