<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Laporan Pembekalan ICT - {{ $ticket->id_tiket }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background-color: #f1f5f9; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

        .a4-paper {
            max-width: 297mm;
            margin: 2rem auto;
            background: white;
            padding: 2.5rem;
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            border-radius: 0.5rem;
        }

        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; border-bottom: 2px solid #1f2937; padding-bottom: 15px; }
        .doc-code { text-align: right; font-size: 10px; font-weight: bold; padding-bottom: 10px; }
        .header-logo img { height: 65px; width: auto; margin: 0 auto 10px auto; }
        .header-dept { font-size: 12px; font-weight: bold; margin-bottom: 5px; text-transform: uppercase; }
        .header-title { font-size: 15px; font-weight: 900; line-height: 1.3; text-transform: uppercase; }

        @media print {
            @page {
                size: A4 landscape;
                margin: 0;
            }

            body {
                background-color: white;
                padding: 10mm 15mm;
            }
            .a4-paper { margin: 0; padding: 0; box-shadow: none; border-radius: 0; width: 100%; max-width: 100%; }
            .no-print { display: none !important; }
            .page-break-avoid { page-break-inside: avoid; }
            .break-before-page { page-break-before: always; }
        }
    </style>
</head>
<body class="font-sans text-gray-800 text-[11px] leading-relaxed">

    @php
        $dataLaporan = $laporan ?? $ticket->laporan;

        $parseJsonSafe = function($data) {
            if (is_array($data)) return $data;
            if (is_string($data)) {
                $decoded = json_decode($data, true);
                return is_array($decoded) ? $decoded : [];
            }
            return [];
        };

        $pendahuluan = $parseJsonSafe($dataLaporan->pendahuluan ?? null);
        $hasilKajian = $parseJsonSafe($dataLaporan->hasil_kajian ?? null);
        $kosItems = $parseJsonSafe($dataLaporan->kos_items ?? null);

        $grandTotalKos = 0;
        foreach($kosItems as $item) {
            $ktt = (int)($item['kuantiti'] ?? 0);
            $harga = (float)($item['anggaran_kos'] ?? $item['anggaran'] ?? $item['harga_seunit'] ?? $item['jumlah'] ?? 0);
            if(isset($item['jumlah']) && !isset($item['anggaran_kos']) && !isset($item['anggaran'])) {
                $grandTotalKos += (float)$item['jumlah'];
            } else {
                $grandTotalKos += ($ktt * $harga);
            }
        }

        $grandTotalKajian = 0;
        foreach($hasilKajian as $kajian) {
            $grandTotalKajian += (float)($kajian['anggaran_kos'] ?? 0);
        }

        $tarikhPengkaji = '-';
        if (!empty($dataLaporan->created_at)) {
            $tarikhPengkaji = \Carbon\Carbon::parse($dataLaporan->created_at)->format('d/m/Y');
        } elseif (!empty($dataLaporan->updated_at)) {
            $tarikhPengkaji = \Carbon\Carbon::parse($dataLaporan->updated_at)->format('d/m/Y');
        } elseif (!empty($ticket->created_at)) {
            $tarikhPengkaji = \Carbon\Carbon::parse($ticket->created_at)->format('d/m/Y');
        }

        $tarikhVerifikasi = '-';
        if (!empty($dataLaporan->updated_at)) {
            $tarikhVerifikasi = \Carbon\Carbon::parse($dataLaporan->updated_at)->format('d/m/Y');
        } elseif (!empty($ticket->updated_at)) {
            $tarikhVerifikasi = \Carbon\Carbon::parse($ticket->updated_at)->format('d/m/Y');
        }
    @endphp

    <div class="no-print max-w-4xl mx-auto mt-6" style="text-align: right; margin-bottom: 20px;">
        <button onclick="window.print()" style="background: #2563eb; color: white; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
             Cetak Laporan
        </button>
    </div>

    <div class="a4-paper">

        <table style="width: 100%; margin-bottom: 20px;">
            <tr>
                <td colspan="2" style="text-align: right; font-size: 8px; font-style: italic; font-weight: bold; padding-bottom: 15px;">
                    BPI/BP02v1.1
                </td>
            </tr>

            <tr>
                <td style="width: 120px; text-align: center; vertical-align: middle;">
                    <img src="{{ asset('images/logo_jtdi.png') }}" alt="Logo JTDI" style="height: 38px; width: auto; display: block; margin: 0 auto 3px auto;" />
                    <div style="width: 100%; text-align: center; font-size: 6px; font-weight: bold; text-transform: uppercase; line-height: 1.1; color: #000; font-family: Arial, sans-serif;">
                        JABATAN TEKNOLOGI DIGITAL<br>DAN INOVASI NEGERI SABAH
                    </div>
                </td>

                <td style="vertical-align: middle; padding-left: 15px;">
                    <h1 style="font-size: 12px; font-weight: 900; text-transform: uppercase; margin: 0; color: #000; letter-spacing: 0.5px;">
                        LAPORAN KAJIAN KEPERLUAN (BPI/BP02 &ndash; {{ date('Y') }} / {{ $pendahuluan['jabatan'] ?? $ticket->agensi ?? '<Agensi>' }} / 001 )
                    </h1>
                </td>
            </tr>
        </table>

        <div class="space-y-6">

            <div class="page-break-avoid">
                <h3 class="text-xs font-black text-black-800 tracking-wider mb-2">BUTIRAN PERMOHONAN</h3>
                <div class="border border-gray-300 rounded-lg overflow-hidden flex flex-wrap bg-white">
                    <div class="w-3/4 p-3 border-b border-r border-gray-200">
                        <span class="block text-[9px] font-bold text-gray-500 mb-0.5">Kementerian / Jabatan:</span>
                        <span class="block text-xs font-bold text-gray-900 capitalize">{{ $pendahuluan['jabatan'] ?? '-' }}</span>
                    </div>
                    <div class="w-1/4 p-3 border-b border-gray-200">
                        <span class="block text-[9px] font-bold text-gray-500 mb-0.5">Bilangan Kakitangan:</span>
                        <span class="block text-xs font-bold text-gray-900">{{ $pendahuluan['bilangan_kakitangan'] ?? '-' }}</span>
                    </div>

                    <div class="w-1/2 p-3 border-b border-r border-gray-200">
                        <span class="block text-[9px] font-bold text-gray-500 mb-0.5">Tarikh Permohonan Diterima:</span>
                        <span class="block text-xs font-bold text-gray-900">{{ $pendahuluan['tarikh_terima'] ?? '-' }}</span>
                    </div>
                    <div class="w-1/2 p-3 border-b border-gray-200">
                        <span class="block text-[9px] font-bold text-gray-500 mb-0.5">No. Rujukan Surat:</span>
                        <span class="block text-xs font-bold text-gray-900 uppercase">{{ $pendahuluan['no_rujukan'] ?? '-' }}</span>
                    </div>

                    <div class="w-full p-3 border-b border-gray-200">
                        <span class="block text-[9px] font-bold text-gray-500 mb-0.5">Tujuan Permohonan:</span>
                        <span class="block text-xs font-bold text-gray-900 capitalize whitespace-pre-wrap">{{ $pendahuluan['tujuan'] ?? '-' }}</span>
                    </div>

                    <div class="w-full p-3 border-b border-gray-200">
                        <span class="block text-[9px] font-bold text-gray-500 mb-0.5">Peruntukan Daripada:</span>
                        <span class="block text-xs font-bold text-gray-900 capitalize">{{ $pendahuluan['peruntukan'] ?? '-' }}</span>
                    </div>

                    <div class="w-1/2 p-3 border-r border-gray-200">
                        <span class="block text-[9px] font-bold text-gray-500 mb-0.5">Tanggungjawab:</span>
                        <span class="block text-xs font-bold text-gray-900 capitalize">{{ $pendahuluan['tanggungjawab'] ?? '-' }}</span>
                    </div>
                    <div class="w-1/2 p-3">
                        <span class="block text-[9px] font-bold text-gray-500 mb-0.5">E-mel Ketua Cawangan / PID:</span>
                        <span class="block text-xs font-bold text-gray-900">{{ $pendahuluan['emel_ketua_cawangan'] ?? '-' }}</span>
                    </div>
                </div>
            </div>

            <div class="page-break-avoid">
                <h3 class="text-xs font-black text-black-800 tracking-wider mb-2">BUTIRAN PEGAWAI YANG DIHUBUNGI (AGENSI)</h3>
                <div class="border border-gray-300 rounded-lg overflow-hidden flex flex-wrap bg-white">
                    <div class="w-1/2 p-3 border-b border-r border-gray-200">
                        <span class="block text-[9px] font-bold text-gray-500 mb-0.5">Nama Pegawai:</span>
                        <span class="block text-xs font-bold text-gray-900 capitalize">{{ $pendahuluan['pegawai_nama'] ?? '-' }}</span>
                    </div>
                    <div class="w-1/2 p-3 border-b border-gray-200">
                        <span class="block text-[9px] font-bold text-gray-500 mb-0.5">Jawatan:</span>
                        <span class="block text-xs font-bold text-gray-900 capitalize">{{ $pendahuluan['pegawai_jawatan'] ?? '-' }}</span>
                    </div>
                    <div class="w-1/2 p-3 border-b border-r border-gray-200">
                        <span class="block text-[9px] font-bold text-gray-500 mb-0.5">No. Telefon:</span>
                        <span class="block text-xs font-bold text-gray-900">{{ $pendahuluan['pegawai_notel'] ?? '-' }}</span>
                    </div>
                    <div class="w-1/2 p-3 border-b border-gray-200">
                        <span class="block text-[9px] font-bold text-gray-500 mb-0.5">E-mel:</span>
                        <span class="block text-xs font-bold text-gray-900">{{ $pendahuluan['pegawai_emel'] ?? '-' }}</span>
                    </div>
                    <div class="w-full p-3 bg-gray-50/50">
                        <span class="block text-[9px] font-bold text-gray-500 mb-0.5">CDO:</span>
                        <span class="block text-xs font-bold text-gray-800 capitalize">{{ $pendahuluan['cdo_nama'] ?? '-' }}</span>
                    </div>
                </div>
            </div>

            <div class="break-before-page">
                <h3 class="text-xs font-black text-black-800 tracking-wider mb-2">BUTIRAN HASIL KAJIAN</h3>
                <table class="w-full border-collapse border border-gray-400">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="border border-gray-400 p-2 text-center text-[10px] w-10">Bil.</th>
                            <th class="border border-gray-400 p-2 text-left text-[10px]">Butiran Permohonan</th>
                            <th class="border border-gray-400 p-2 text-left text-[10px] w-1/4">Keadaan Semasa</th>
                            <th class="border border-gray-400 p-2 text-left text-[10px] w-1/3">Justifikasi & Cadangan</th>
                            <th class="border border-gray-400 p-2 text-right text-[10px] w-24">Anggaran Kos (RM)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($hasilKajian as $row)
                            <tr class="page-break-avoid">
                                <td class="border border-gray-400 p-2 align-top text-center font-bold">{{ $loop->iteration }}</td>
                                <td class="border border-gray-400 p-2 align-top">
                                    <span class="font-bold text-gray-900 capitalize">{{ $row['nama_pemohon'] ?? '-' }}</span><br>
                                    <span class="text-[9px] text-gray-500 capitalize">({{ $row['jawatan_pemohon'] ?? 'Tiada Jawatan' }})</span>
                                </td>
                                <td class="border border-gray-400 p-2 align-top whitespace-pre-wrap">{{ $row['keadaan_semasa'] ?? '-' }}</td>
                                <td class="border border-gray-400 p-2 align-top whitespace-pre-wrap">{{ $row['justifikasi_cadangan'] ?? '-' }}</td>
                                <td class="border border-gray-400 p-2 align-top text-right font-bold">
                                    {{ number_format((float)($row['anggaran_kos'] ?? 0), 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="border border-gray-400 p-3 text-center italic">Tiada data direkodkan.</td></tr>
                        @endforelse

                        <tr class="bg-gray-100 font-black text-xs">
                            <td colspan="4" class="border border-gray-400 p-2 text-right">Jumlah Anggaran Kos:</td>
                            <td class="border border-gray-400 p-2 text-right text-slate-900">RM {{ number_format($grandTotalKajian, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="page-break-avoid">
                <h3 class="text-xs font-black text-black-800 tracking-wider mb-2">RUMUSAN</h3>
                <table class="w-full border-collapse border border-gray-400">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="border border-gray-400 p-2 text-center text-[10px] w-10">Bil.</th>
                            <th class="border border-gray-400 p-2 text-left text-[10px]">Jenis Peralatan (PC/NB/Pencetak/Pengimbas)</th>
                            <th class="border border-gray-400 p-2 text-center text-[10px] w-20">Kuantiti</th>
                            <th class="border border-gray-400 p-2 text-right text-[10px] w-28">Anggaran Kos (RM)</th>
                            <th class="border border-gray-400 p-2 text-right text-[10px] w-28">Jumlah Anggaran Kos (RM)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($kosItems as $index => $row)
                            @php
                                $harga = (float)($row['anggaran_kos'] ?? $row['anggaran'] ?? $row['harga_seunit'] ?? 0);
                                $ktt = (int)($row['kuantiti'] ?? 0);
                                $subtotal = $ktt * $harga;
                            @endphp
                            <tr>
                                <td class="border border-gray-400 p-2 text-center font-bold">{{ $loop->iteration }}</td>
                                <td class="border border-gray-400 p-2 font-bold capitalize">{{ $row['jenis_peralatan'] ?? $row['item'] ?? '-' }}</td>
                                <td class="border border-gray-400 p-2 text-center">{{ $ktt }}</td>
                                <td class="border border-gray-400 p-2 text-right">{{ number_format($harga, 2) }}</td>
                                <td class="border border-gray-400 p-2 text-right font-bold">{{ number_format($subtotal, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="border border-gray-400 p-3 text-center italic">Tiada data direkodkan.</td></tr>
                        @endforelse
                        <tr class="bg-gray-100 font-black text-xs">
                            <td colspan="4" class="border border-gray-400 p-2 text-right">Jumlah Keseluruhan Anggaran Kos:</td>
                            <td class="border border-gray-400 p-2 text-right text-slate-900">RM {{ number_format($grandTotalKos, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-8 pt-8 page-break-avoid">
                <div class="text-xs font-black tracking-wide text-black mb-1.5 uppercase">
                    PENGESAHAN
                </div>

                <div class="border border-black rounded-sm overflow-hidden bg-white text-[11px] text-black">

                    <div class="flex border-b border-black bg-gray-100 font-bold">
                        <div class="w-1/2 p-2 border-r border-black">Pegawai Pengkaji</div>
                        <div class="w-1/2 p-2">Pegawai Verifikasi</div>
                    </div>

                    <div class="flex border-b border-black min-h-[140px]">
                        <div class="w-1/2 p-3 border-r border-black flex flex-col justify-between bg-white">
                            <div class="space-y-4 pt-4">
                                <div class="flex"><div class="w-[85px]">Tandatangan</div><div class="w-4 text-center">:</div><div class="flex-1"></div></div>
                                <div class="flex"><div class="w-[85px]">Nama</div><div class="w-4 text-center">:</div><div class="flex-1 font-bold capitalize">{{ $dataLaporan->disediakan_oleh ?? '-' }}</div></div>
                                <div class="flex"><div class="w-[85px]">Jawatan</div><div class="w-4 text-center">:</div><div class="flex-1 font-semibold capitalize">{{ $pendahuluan['jawatan_penyedia'] ?? $dataLaporan->jawatan ?? 'Penolong Pegawai Teknologi Maklumat' }}</div></div>
                                <div class="flex"><div class="w-[85px]">Tarikh</div><div class="w-4 text-center">:</div><div class="flex-1 font-semibold">{{ $tarikhPengkaji }}</div></div>
                            </div>
                            <div class="italic text-[10px] text-black pt-4 font-medium">
                                Nota: Pegawai Pengkaji tidak boleh sama dengan Pegawai Verifikasi
                            </div>
                        </div>

                        <div class="w-1/2 p-3 flex flex-col justify-between bg-white">
                            <div class="space-y-4 pt-4">
                                <div class="flex"><div class="w-[85px]">Tandatangan</div><div class="w-4 text-center">:</div><div class="flex-1"></div></div>
                                <div class="flex"><div class="w-[85px]">Nama</div><div class="w-4 text-center">:</div><div class="flex-1 font-bold capitalize">{{ $dataLaporan->disemak_oleh ?? '-' }}</div></div>
                                <div class="flex"><div class="w-[85px]">Jawatan</div><div class="w-4 text-center">:</div><div class="flex-1 font-semibold capitalize">{{ $pendahuluan['jawatan_penyemak'] ?? $dataLaporan->jawatan_pengesah ?? 'Pegawai Teknologi Maklumat' }}</div></div>
                                <div class="flex"><div class="w-[85px]">Tarikh</div><div class="w-4 text-center">:</div><div class="flex-1 font-semibold">{{ $tarikhVerifikasi }}</div></div>
                            </div>
                            <div></div>
                        </div>
                    </div>

                    <div class="bg-gray-100 border-b border-black p-2 text-center font-bold">
                        Laporan dipersetujui oleh:
                    </div>
                    <div class="border-b border-black p-3 min-h-[130px] bg-white">
                        <div class="text-center italic text-gray-700 font-medium mb-4">
                            (Ruangan ini perlu disahkan oleh pegawai di jabatan pelanggan)
                        </div>
                        <div class="space-y-4 pt-2">
                            <div class="flex"><div class="w-[85px]">Tandatangan</div><div class="w-4 text-center">:</div><div class="flex-1"></div></div>
                            <div class="flex"><div class="w-[85px]">Nama</div><div class="w-4 text-center">:</div><div class="flex-1"></div></div>
                            <div class="flex"><div class="w-[85px]">Jawatan</div><div class="w-4 text-center">:</div><div class="flex-1"></div></div>
                            <div class="flex"><div class="w-[85px]">Tarikh</div><div class="w-4 text-center">:</div><div class="flex-1"></div></div>
                        </div>
                    </div>

                    <div class="bg-gray-100 border-b border-black p-2 text-center font-bold">
                        Untuk Pengesahan Ibu Pejabat, Jabatan Teknologi Digital dan Inovasi
                    </div>
                    <div class="p-3 min-h-[120px] bg-white">
                        <div class="space-y-4 pt-4">
                            <div class="flex"><div class="w-[85px]">Tandatangan</div><div class="w-4 text-center">:</div><div class="flex-1"></div></div>
                            <div class="flex"><div class="w-[85px]">Nama</div><div class="w-4 text-center">:</div><div class="flex-1"></div></div>
                            <div class="flex"><div class="w-[85px]">Jawatan</div><div class="w-4 text-center">:</div><div class="flex-1"></div></div>
                            <div class="flex"><div class="w-[85px]">Tarikh</div><div class="w-4 text-center">:</div><div class="flex-1"></div></div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <script>
        window.onload = function() {
            setTimeout(() => {
                window.print();
            }, 500);
        }
    </script>
</body>
</html>
