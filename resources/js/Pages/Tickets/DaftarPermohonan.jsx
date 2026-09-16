import React, { useEffect, useRef, useState } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import Sidebar from '@/Components/Sidebar';
import Topbar from '@/Components/Topbar';
import { FilePlus2, Upload, User, ClipboardList, CheckCircle, Search } from 'lucide-react';

export default function DaftarPermohonan() {
    const senaraiAgensi = [
        'JABATAN AIR NEGERI SABAH',
        'JABATAN ARKIB NEGERI SABAH',
        'JABATAN BENDAHARI NEGERI',
        'JABATAN HAL EHWAL AGAMA ISLAM NEGERI (JHEAINS)',
        'JABATAN HIDUPAN LIAR',
        'JABATAN HIDUPAN LIAR SEPILOK',
        'JABATAN KERJA RAYA',
        'JABATAN MUZIUM SABAH',
        'JABATAN PELABUHAN DAN DERMAGA',
        'JABATAN PENGAIRAN DAN SALIRAN SABAH',
        'JABATAN PERANCANG BANDAR DAN WILAYAH',
        'JABATAN PERHUTANAN SABAH',
        'JABATAN PERIKANAN SABAH',
        'JABATAN PERKHIDMATAN KEBAJIKAN AM',
        'JABATAN TEKNOLOGI DIGITAL DAN INOVASI',
        'JABATAN PERKHIDMATAN PEMBENTUNGAN SABAH',
        'JABATAN PERKHIDMATAN VETERINAR SABAH',
        'JABATAN PERLINDUNGAN ALAM SEKITAR',
        'JABATAN PERTANIAN SABAH',
        'JABATAN TANAH DAN UKUR',
        'KEMENTERIAN BELIA DAN SUKAN SABAH',
        'LEMBAGA SUKAN NEGERI SABAH',
        'MAHKAMAH ANAK NEGERI',
        'MAJLIS PERBANDARAN SANDAKAN',
        'PERPUSTAKAAN NEGERI SABAH',
        'PUSAT ZAKAT',
        'UNIT PEMIMPIN PEMBANGUNAN MASYARAKAT (UPPM)',
        'SEKOLAH AGAMA NEGERI (SAN)',
    ];

    const senaraiDaerah = ['Beluran', 'Sandakan', 'Tongod', 'Telupid', 'Kinabatangan', 'Sukau'];
    const senaraiSaluran = ['E-mel', 'Telefon', 'Kaunter', 'Surat'];

    const subKategoriMapping = {
        'Meja Bantuan': ['Penyelenggaraan Komputer', 'Penyelenggaraan Rangkaian', 'Sistem Aplikasi', 'Perkhidmatan E-mel', 'Perkhidmatan Lintas Langsung', 'Peminjaman Peralatan ICT'],
        'Konsultasi Rangkaian': ['Pemasangan Baharu', 'Naiktaraf'],
        'Transformasi Digital': ['Pemodenan Bilik Mesyuarat', 'Pembekalan Peralatan ICT']
    };

    // File input reference for hidden input trigger
    const fileInputRef = useRef(null);

    const { data, setData, post, errors, processing, reset } = useForm({
        perkara: '', saluran: '', nama_pemohon: '', emel_pemohon: '',
        notel_pemohon: '', agensi: '', lokasi: '', daerah: '',
        kategori: '', sub_kategori: '', lampiran: null
    });

    useEffect(() => {
        setData('sub_kategori', '');
    }, [data.kategori]);

    const handleSubmit = (e) => {
        e.preventDefault();

        post(route('tickets.store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                alert("Tiket telah berjaya dicipta dan telah dihantar untuk pengesahan klasifikasi.");
            },
            onError: (err) => console.error(err)
        });
    };

    const [searchTerm, setSearchTerm] = useState('');
    const [isOpenDropdown, setIsOpenDropdown] = useState(false);

    return (
        <div className="min-h-screen bg-gray-50 flex flex-col md:flex-row pt-16 md:pt-0">
            <Head title="Daftar Permohonan" />
            <Sidebar />

            <div className="flex-1 flex flex-col min-w-0 w-full">
                <Topbar title="Daftar Permohonan" />

                <main className="flex-1 p-4 md:p-8 overflow-y-auto">
                    <div className="max-w-7xl mx-auto space-y-6">

                        {/* Header Card */}
                        <div className="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm flex items-center gap-4">
                            <div className="p-3 bg-blue-50 rounded-2xl text-blue-700"><FilePlus2 size={24} /></div>
                            <div>
                                <h2 className="text-lg font-black text-blue-900 uppercase">Pendaftaran Tiket Baharu</h2>
                                <p className="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Sila isi maklumat permohonan.</p>
                            </div>
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-6 w-full">

                            {/* 1. Applicant Details */}
                            <section className="bg-white p-6 md:p-8 rounded-3xl border border-gray-100 shadow-sm">
                                <div className="flex items-center gap-3 mb-6 text-blue-900">
                                    <User size={20} />
                                    <h3 className="font-black uppercase tracking-widest text-sm">1. Butiran Pemohon</h3>
                                </div>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label className="block text-[10px] font-black uppercase text-gray-400 mb-2">Nama Pemohon <span className="text-red-500 font-bold ml-1">*</span></label>
                                        <input type="text" value={data.nama_pemohon} onChange={e => setData('nama_pemohon', e.target.value)} className="w-full h-11 rounded-xl border-gray-200 bg-gray-50 text-sm focus:outline-none focus:border-blue-500" placeholder="Masukkan nama penuh" required />
                                        {errors.nama_pemohon && <p className="text-red-500 text-xs mt-1 font-bold">{errors.nama_pemohon}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-[10px] font-black uppercase text-gray-400 mb-2">No. Telefon Pemohon <span className="text-red-500 font-bold ml-1">*</span></label>
                                        <input type="text" value={data.notel_pemohon} onChange={e => setData('notel_pemohon', e.target.value)} className="w-full h-11 rounded-xl border-gray-200 bg-gray-50 text-sm focus:outline-none focus:border-blue-500" placeholder="Masukkan no. telefon" required />
                                        {errors.notel_pemohon && <p className="text-red-500 text-xs mt-1 font-bold">{errors.notel_pemohon}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-[10px] font-black uppercase text-gray-400 mb-2">Alamat Emel Pemohon <span className="text-red-500 font-bold ml-1">*</span></label>
                                        <input type="email" value={data.emel_pemohon} onChange={e => setData('emel_pemohon', e.target.value)} className="w-full h-11 rounded-xl border-gray-200 bg-gray-50 text-sm focus:outline-none focus:border-blue-500" placeholder="Masukkan alamat emel" required />
                                        {errors.emel_pemohon && <p className="text-red-500 text-xs mt-1 font-bold">{errors.emel_pemohon}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-[10px] font-black uppercase text-gray-400 mb-2">
                                            Agensi Pemohon <span className="text-red-500 font-bold ml-1">*</span>
                                        </label>

                                        {/* Searchable Agency Combobox */}
                                        <div className="relative" id="agensi-combobox">
                                            {/* Selected agency display */}
                                            <div
                                                onClick={() => setIsOpenDropdown(!isOpenDropdown)}
                                                className="w-full h-11 rounded-xl border border-gray-200 bg-gray-50 text-sm px-4 flex items-center justify-between cursor-pointer select-none focus-within:border-blue-500"
                                            >
                                                <span className={data.agensi ? "text-gray-800 font-medium" : "text-gray-400"}>
                                                    {data.agensi || "Pilih agensi"}
                                                </span>
                                                <span className="text-gray-400 text-xs">▼</span>
                                            </div>

                                            {/* Search dropdown menu */}
                                            {isOpenDropdown && (
                                                <div className="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-2xl shadow-lg p-2 space-y-2 max-h-60 flex flex-col animate-in fade-in slide-in-from-top-1 duration-100">

                                                    {/* Search input */}
                                                    <div className="relative flex items-center w-full">
                                                        <Search size={14} className="absolute left-3 text-gray-400" />
                                                        <input
                                                            type="text"
                                                            placeholder="Cari agensi..."
                                                            value={searchTerm}
                                                            onChange={e => setSearchTerm(e.target.value)}
                                                            className="w-full text-xs font-semibold pl-9 pr-4 py-2 bg-gray-50 border border-gray-100 rounded-xl focus:outline-none focus:border-blue-500 focus:bg-white"
                                                            onClick={(e) => e.stopPropagation()} // Prevent closing dropdown when typing
                                                        />
                                                        {searchTerm && (
                                                            <button
                                                                type="button"
                                                                onClick={() => setSearchTerm('')}
                                                                className="absolute right-3 text-gray-400 hover:text-gray-600 text-xs font-bold"
                                                            >
                                                                Clear
                                                            </button>
                                                        )}
                                                    </div>

                                                    {/* Filtered agency list */}
                                                    <div className="overflow-y-auto flex-1 space-y-0.5 max-h-40 pl-0.5">
                                                        {senaraiAgensi
                                                            .filter(a => a.toLowerCase().includes(searchTerm.toLowerCase()))
                                                            .map((a) => (
                                                                <div
                                                                    key={a}
                                                                    onClick={() => {
                                                                        setData('agensi', a);
                                                                        setSearchTerm(''); // Reset search term
                                                                        setIsOpenDropdown(false); // Close dropdown
                                                                    }}
                                                                    className={`p-2.5 rounded-xl text-xs font-bold uppercase cursor-pointer transition-colors ${
                                                                        data.agensi === a
                                                                            ? 'bg-blue-50 text-blue-700 font-black'
                                                                            : 'text-gray-700 hover:bg-gray-50'
                                                                    }`}
                                                                >
                                                                    {a}
                                                                </div>
                                                            ))
                                                        }

                                                        {/* Empty search result message */}
                                                        {senaraiAgensi.filter(a => a.toLowerCase().includes(searchTerm.toLowerCase())).length === 0 && (
                                                            <p className="text-gray-400 text-xs italic p-3 text-center">Tiada agensi sepadan...</p>
                                                        )}
                                                    </div>
                                                </div>
                                            )}
                                        </div>

                                        {errors.agensi && <p className="text-red-500 text-xs mt-1 font-bold">{errors.agensi}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-[10px] font-black uppercase text-gray-400 mb-2">Lokasi </label>
                                        <input type="text" value={data.lokasi} onChange={e => setData('lokasi', e.target.value)} className="w-full h-11 rounded-xl border-gray-200 bg-gray-50 text-sm focus:outline-none focus:border-blue-500" placeholder="Masukkan lokasi spesifik" />
                                        {errors.lokasi && <p className="text-red-500 text-xs mt-1 font-bold">{errors.lokasi}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-[10px] font-black uppercase text-gray-400 mb-2">Daerah <span className="text-red-500 font-bold ml-1">*</span></label>
                                        <select value={data.daerah} onChange={e => setData('daerah', e.target.value)} className="w-full h-11 rounded-xl border-gray-200 bg-gray-50 text-sm cursor-pointer focus:outline-none focus:border-blue-500" required>
                                            <option value="">Pilih daerah</option>
                                            {senaraiDaerah.map(d => <option key={d} value={d}>{d}</option>)}
                                        </select>
                                        {errors.daerah && <p className="text-red-500 text-xs mt-1 font-bold">{errors.daerah}</p>}
                                    </div>
                                </div>
                            </section>

                            {/* 2. Ticket Details */}
                            <section className="bg-white p-6 md:p-8 rounded-3xl border border-gray-100 shadow-sm">
                                <div className="flex items-center gap-3 mb-6 text-blue-900">
                                    <ClipboardList size={20} />
                                    <h3 className="font-black uppercase tracking-widest text-sm">2. Butiran Tiket</h3>
                                </div>
                                <div className="space-y-5">
                                    <div>
                                        <label className="block text-[10px] font-black uppercase text-gray-400 mb-2">Perkara <span className="text-red-500 font-bold ml-1">*</span></label>
                                        <textarea value={data.perkara} onChange={e => setData('perkara', e.target.value)} className="w-full h-24 rounded-xl border-gray-200 bg-gray-50 text-sm p-3 focus:outline-none focus:border-blue-500 resize-none" placeholder="Terangkan perkara aduan..." required />
                                        {errors.perkara && <p className="text-red-500 text-xs mt-1 font-bold">{errors.perkara}</p>}
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label className="block text-[10px] font-black uppercase text-gray-400 mb-2">Kategori <span className="text-red-500 font-bold ml-1">*</span></label>
                                            <select value={data.kategori} onChange={e => setData('kategori', e.target.value)} className="w-full h-11 rounded-xl border-gray-200 bg-gray-50 text-sm cursor-pointer focus:outline-none focus:border-blue-500" required>
                                                <option value="">Pilih kategori</option>
                                                {Object.keys(subKategoriMapping).map(k => <option key={k} value={k}>{k}</option>)}
                                            </select>
                                            {errors.kategori && <p className="text-red-500 text-xs mt-1 font-bold">{errors.kategori}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-[10px] font-black uppercase text-gray-400 mb-2">Sub Kategori <span className="text-red-500 font-bold ml-1">*</span></label>
                                            <select value={data.sub_kategori} onChange={e => setData('sub_kategori', e.target.value)} className="w-full h-11 rounded-xl border-gray-200 bg-gray-50 text-sm cursor-pointer focus:outline-none focus:border-blue-500" required disabled={!data.kategori}>
                                                <option value="">Pilih sub kategori</option>
                                                {data.kategori && subKategoriMapping[data.kategori]?.map(s => <option key={s} value={s}>{s}</option>)}
                                            </select>
                                            {errors.sub_kategori && <p className="text-red-500 text-xs mt-1 font-bold">{errors.sub_kategori}</p>}
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-[10px] font-black uppercase text-gray-400 mb-2">Saluran Terima <span className="text-red-500 font-bold ml-1">*</span></label>
                                        <select value={data.saluran} onChange={e => setData('saluran', e.target.value)} className="w-full h-11 rounded-xl border-gray-200 bg-gray-50 text-sm cursor-pointer focus:outline-none focus:border-blue-500" required>
                                            <option value="">Pilih saluran terima</option>
                                            {senaraiSaluran.map(s => <option key={s} value={s}>{s}</option>)}
                                        </select>
                                        {errors.saluran && <p className="text-red-500 text-xs mt-1 font-bold">{errors.saluran}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-[10px] font-black uppercase text-gray-400 mb-2">Lampiran</label>
                                        {/* Hidden file input trigger */}
                                        <div
                                            onClick={() => fileInputRef.current.click()}
                                            className="border-2 border-dashed border-gray-200 rounded-2xl p-6 flex flex-col items-center justify-center text-center cursor-pointer hover:border-blue-400 hover:bg-blue-50/20 transition-all duration-150"
                                        >
                                            <Upload className="text-gray-400 mb-2" size={24} />
                                            {data.lampiran ? (
                                                <p className="text-xs font-black text-emerald-600 uppercase flex items-center gap-1.5">
                                                    <CheckCircle size={14} /> {data.lampiran.name}
                                                </p>
                                            ) : (
                                                <>
                                                    <p className="text-xs font-bold text-gray-600">Klik untuk muat naik dokumen (PDF, Word, atau imej)</p>

                                                </>
                                            )}
                                            <input
                                                ref={fileInputRef}
                                                type="file"
                                                onChange={e => setData('lampiran', e.target.files[0])}
                                                className="hidden"
                                            />
                                        </div>
                                        {errors.lampiran && <p className="text-red-500 text-xs mt-1 font-bold">{errors.lampiran}</p>}
                                    </div>
                                </div>
                            </section>

                            {/* Submit Button */}
                            <div className="flex justify-end pt-2">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="bg-blue-900 text-white px-8 py-3.5 rounded-xl font-black uppercase text-xs hover:bg-blue-800 disabled:bg-gray-400 transition shadow-md active:scale-[0.99] cursor-pointer"
                                >
                                    {processing ? 'Mendaftar...' : 'Daftar Permohonan'}
                                </button>
                            </div>
                        </form>
                    </div>
                </main>
            </div>
        </div>
    );
}
