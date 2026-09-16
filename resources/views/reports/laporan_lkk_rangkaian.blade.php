<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Cetak LKK - {{ $ticket->id_tiket }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #111;
            line-height: 1.6;
            margin: 0;
        }

        /* Screen view styling (virtual A4 sheet) */
        @media screen {
            body {
                background-color: #e5e7eb;
                padding: 40px 0;
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            .no-print {
                width: 210mm;
                text-align: right;
                margin-bottom: 20px;
            }
            .a4-container {
                background-color: white;
                width: 210mm;
                min-height: 297mm;
                padding: 40px 50px;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            }
        }

        /* Printer setup */
        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }
            body {
                background-color: white;
                padding: 2cm !important;
                margin: 0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                display: block;
            }
            .a4-container {
                width: 100%;
                padding: 0;
                box-shadow: none;
            }
            .no-print { display: none !important; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            .avoid-break { page-break-inside: avoid; }
        }

        /* Front page layout styling */
        .front-page {
            page-break-after: always;
            width: 100%;
        }
        .fp-doc-code { text-align: right; font-weight: bold; font-size: 11px; margin-bottom: 10px; }
        .fp-header-logo { text-align: center; margin-bottom: 10px; }
        .fp-header-logo img { height: 60px; width: auto; object-fit: contain; }
        .fp-dept { text-align: center; font-size: 13px; margin-bottom: 25px; }
        .fp-title { text-align: center; font-weight: bold; font-size: 13px; text-transform: uppercase; margin-bottom: 30px; line-height: 1.4; }

        .fp-info-table {
            width: 75%;
            margin: 0 auto 25px auto;
            font-size: 13px;
            border: none;
        }
        .fp-info-table td { padding: 6px 0; vertical-align: bottom; }
        .fp-info-label { width: 140px; }
        .fp-info-colon { width: 20px; text-align: center; }
        .fp-info-line { border-bottom: 1px solid #000; padding-left: 10px; text-transform: uppercase; }

        .fp-legend { text-align: right; margin-bottom: 10px; display: flex; justify-content: flex-end; gap: 15px; }
        .fp-icon-box { border: 1px solid #000; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; font-size: 16px; font-weight: bold; }

        .fp-checklist { width: 100%; border-collapse: collapse; border: 1px solid #000; font-size: 12px; }
        .fp-checklist th, .fp-checklist td { border: 1px solid #000; padding: 12px; vertical-align: top; }
        .fp-checklist th { font-weight: normal; text-align: center; }
        .fp-checklist .bg-grey { background-color: #d1d5db !important; }

        .fp-ul { list-style-type: none; margin: 10px 0 0 0; padding: 0; }
        .fp-li { display: flex; align-items: end; margin-bottom: 8px; }
        .fp-bullet { margin-right: 8px; font-size: 14px; line-height: 1; }
        .fp-dotted-line { flex-grow: 1; border-bottom: 1px solid #000; margin: 0 10px; }
        .fp-cross-circle { border: 1px solid #000; border-radius: 50%; width: 14px; height: 14px; display: inline-flex; align-items: center; justify-content: center; font-size: 10px; line-height: 1; }

        .fp-check-item { margin-bottom: 10px; display: flex; justify-content: center; font-size: 14px; }
        .fp-check-item:last-child { margin-bottom: 0; }

        /* Report content body styling */
        .section-title { font-weight: bold; text-transform: uppercase; font-size: 12px; margin-top: 10px; margin-bottom: 10px; background-color: transparent !important; border: none !important; padding: 0 !important; }
        .sub-title { font-weight: bold; text-decoration: none; margin-top: 15px; margin-bottom: 5px; font-style: normal; }
        .diagram-label { font-size: 12px; font-weight: bold; margin-top: 20px; margin-bottom: 10px; font-style: normal !important; }
        .content-text { text-align: justify; margin-top: 5px; font-style: normal !important; }

        ol { margin-top: 5px; margin-bottom: 15px; padding-left: 20px; }
        ol li { margin-bottom: 5px; padding-left: 5px; font-style: normal !important; }

        .diagram-container { width: 100%; margin-top: 5px; display: table; margin-bottom: 15px; }

        table.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.data-table th { background-color: #f9fafb; font-weight: bold; text-transform: uppercase; font-size: 10px; border: 1px solid #000; padding: 8px; text-align: left; }
        table.data-table td { border: 1px solid #000; padding: 8px; vertical-align: top; }

        /* Sign-off box styling */
        .sign-box-table { width: 100%; border-collapse: collapse; border: 1px solid #000; margin-top: 40px; page-break-inside: avoid; }
        .sign-box-table th { border: 1px solid #000; padding: 8px; font-size: 11px; font-weight: bold; text-transform: uppercase; background-color: #f3f4f6; text-align: center; width: 50%; }
        .sign-box-table td { border: 1px solid #000; padding: 12px; width: 50%; vertical-align: top; font-size: 11px; }
        .sign-space { height: 45px; }
        .sign-line { border-bottom: 1px solid #000; margin-bottom: 10px; }

        .sign-details { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .sign-details td { border: none !important; padding: 2px 0 !important; vertical-align: top; }
        .sign-details .col-label { width: 60px; }
        .sign-details .col-colon { width: 15px; text-align: left; }
        .sign-details .col-value { text-align: left; }
    </style>
</head>
<body>

    @php
        // Helper to parse arrays or line breaks safely
        function parseToList($data) {
            if (!$data) return [];
            if (is_string($data) && str_starts_with(trim($data), '[')) {
                $decoded = json_decode($data, true);
                if (is_array($decoded)) {
                    return array_map(function($item) {
                        return is_array($item) && isset($item['teks']) ? $item['teks'] : $item;
                    }, $decoded);
                }
            }
            $lines = explode("\n", $data);
            return array_values(array_filter(array_map('trim', $lines)));
        }

        $ulasanTeknikal = parseToList($laporan->ulasan_teknikal ?? '');
        $cadanganTeknikal = parseToList($laporan->cadangan_penambahbaikan ?? '');
        $objektifProjek = parseToList($laporan->objektif ?? '');

        $kosItemsRaw = $kosItems ?? $laporan->kos_items ?? '[]';
        if (is_string($kosItemsRaw)) {
            $kosItems = json_decode($kosItemsRaw, false) ?? [];
        } else {
            $kosItems = $kosItemsRaw;
        }

        // Format dates
        $tarikhCetak = $ticket->tarikh_terima ? \Carbon\Carbon::parse($ticket->tarikh_terima)->format('d / m / Y') : '____ / ____ / ________';
        $tarikhLaporan = ($laporan->updated_at ?? false)
            ? \Carbon\Carbon::parse($laporan->updated_at)->format('d/m/Y')
            : (($ticket->updated_at ?? false) ? \Carbon\Carbon::parse($ticket->updated_at)->format('d/m/Y') : '');
    @endphp

    <div class="no-print">
        <button onclick="window.print()" style="background: #2563eb; color: white; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: bold; border: none; font-size: 14px;">🖨️ Cetak</button>
    </div>

    <!-- Virtual A4 Container -->
    <div class="a4-container">

        <!-- Front Page Checklist Form -->
        <div class="front-page">
            <div class="fp-doc-code">JPKN-BRK-01/B1</div>

            <div class="fp-header-logo">
                <img src="{{ asset('images/logo_jtdi.png') }}" alt="Logo JPKN" />
            </div>

            <div class="fp-dept">JABATAN TEKNOLOGI DIGITAL & INOVASI (JTDI) NEGERI SABAH</div>

            <div class="fp-title">
                BORANG SENARAI SEMAK DAN LAPORAN<br>
                KAJIAN KEPERLUAN PEMASANGAN/ PENAIKTARAFAN<br>
                INFRASTRUKTUR RANGKAIAN KOMPUTER
            </div>

            <table class="fp-info-table">
                <tr>
                    <td class="fp-info-label">Tarikh</td>
                    <td class="fp-info-colon">:</td>
                    <td class="fp-info-line">{{ $tarikhCetak }}</td>
                </tr>
                <tr>
                    <td class="fp-info-label">Nama Pegawai</td>
                    <td class="fp-info-colon">:</td>
                    <td class="fp-info-line">{{ $laporan->disediakan_oleh ?? '' }}</td>
                </tr>
                <tr>
                    <td class="fp-info-label">Cawangan/PID/KSIT</td>
                    <td class="fp-info-colon">:</td>
                    <td class="fp-info-line">JABATAN TEKNOLOGI DIGITAL & INOVASI WILAYAH SANDAKAN</td>
                </tr>
                <tr>
                    <td class="fp-info-label">Lokasi Kajian</td>
                    <td class="fp-info-colon">:</td>
                    <td class="fp-info-line">{{ $ticket->agensi ?? '' }}</td>
                </tr>
            </table>

            <div class="fp-legend">
                <div class="fp-icon-box">&#10003;</div>
                <div class="fp-icon-box">&#10005;</div>
            </div>

            <table class="fp-checklist">
                <thead>
                    <tr>
                        <th rowspan="2" class="bg-grey" style="width: 50%;">Maklumat</th>
                        <th colspan="2" class="bg-grey" style="width: 50%;">
                            Senarai Semak <span style="margin: 0 10px;">] atau [</span>
                        </th>
                    </tr>
                    <tr>
                        <th style="width: 25%;">Pemohon</th>
                        <th style="width: 25%;">Pegawai BRK</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong>Menyediakan Rajah Rangkaian</strong>
                            <ul class="fp-ul">
                                <li class="fp-li">
                                    <span class="fp-bullet">&#8226;</span> Rajah Pelan Lantai <span class="fp-dotted-line"></span> <span class="fp-cross-circle">x</span>
                                </li>
                                <li class="fp-li">
                                    <span class="fp-bullet">&#8226;</span> Rajah Rangkaian Logik <span class="fp-dotted-line"></span>
                                </li>
                                <li class="fp-li">
                                    <span class="fp-bullet">&#8226;</span> Rajah Rangkaian Fizikal <span class="fp-dotted-line"></span>
                                </li>
                            </ul>
                        </td>
                        <td>
                            <div class="fp-check-item">[ &#10003; ]</div>
                            <div class="fp-check-item">[ &#10003; ]</div>
                            <div class="fp-check-item">[ &#10003; ]</div>
                        </td>
                        <td>
                            <div class="fp-check-item">[ &nbsp;&nbsp;&nbsp; ]</div>
                            <div class="fp-check-item">[ &nbsp;&nbsp;&nbsp; ]</div>
                            <div class="fp-check-item">[ &nbsp;&nbsp;&nbsp; ]</div>
                        </td>
                    </tr>

                    <tr>
                        <td>
                            <strong>Menyediakan Laporan Ringkas</strong>
                            <ul class="fp-ul">
                                <li class="fp-li">
                                    <span class="fp-bullet">&#8226;</span> Pendahuluan/ Latar Belakang <span class="fp-dotted-line"></span>
                                </li>
                                <li class="fp-li">
                                    <span class="fp-bullet">&#8226;</span> Objektif <span class="fp-dotted-line"></span>
                                </li>
                                <li class="fp-li">
                                    <span class="fp-bullet">&#8226;</span> Anggaran Kos <span class="fp-dotted-line"></span>
                                </li>
                                <li class="fp-li">
                                    <span class="fp-bullet">&#8226;</span> Rumusan Pegawai Penilai dan Pegawai Verifikasi <span class="fp-dotted-line"></span>
                                </li>
                                <li class="fp-li">
                                    <span class="fp-bullet">&#8226;</span> Tandatangan Pegawai Penilai dan Verifikasi <span class="fp-dotted-line"></span>
                                </li>
                            </ul>
                        </td>
                        <td>
                            <div class="fp-check-item">[ &#10003; ]</div>
                            <div class="fp-check-item">[ &#10003; ]</div>
                            <div class="fp-check-item">[ &#10003; ]</div>
                            <div class="fp-check-item">[ &#10003; ]</div>
                            <div class="fp-check-item">[ &#10003; ]</div>
                        </td>
                        <td>
                            <div class="fp-check-item">[ &nbsp;&nbsp;&nbsp; ]</div>
                            <div class="fp-check-item">[ &nbsp;&nbsp;&nbsp; ]</div>
                            <div class="fp-check-item">[ &nbsp;&nbsp;&nbsp; ]</div>
                            <div class="fp-check-item">[ &nbsp;&nbsp;&nbsp; ]</div>
                            <div class="fp-check-item">[ &nbsp;&nbsp;&nbsp; ]</div>
                        </td>
                    </tr>

                    <tr>
                        <td>
                            Melengkapkan Borang Maklumat Peralatan Rangkaian <span class="fp-dotted-line" style="display:inline-block; width: 30px;"></span><br>
                            JPKN-BRK-01/B2 (sekiranya cadangan penaiktarafan)
                        </td>
                        <td style="vertical-align: middle; text-align: center;">[ &#10003; ]</td>
                        <td style="vertical-align: middle; text-align: center;">[ &nbsp;&nbsp;&nbsp; ]</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Report Content Pages -->
        <div style="text-align: right; font-size: 11px; font-weight: bold; margin-bottom: 20px; padding-top: 40px;">
            JPKN-BRK-02/B1
        </div>

        <div class="section-title">1. Pendahuluan / Latar Belakang</div>
        <div class="content-text">
            <p style="margin-top: 0;">{{ $laporan->pendahuluan ?? ($ticket->perkara ?? 'Tiada maklumat direkodkan.') }}</p>

            <div class="sub-title">Ulasan Teknikal:</div>
            @if(count($ulasanTeknikal) > 0)
                <ol>
                    @foreach($ulasanTeknikal as $ulasan)
                        <li>{{ $ulasan }}</li>
                    @endforeach
                </ol>
            @else
                <p class="content-text" style="color: #555;">Tiada ulasan direkodkan.</p>
            @endif

            <div class="sub-title">Cadangan Penambahbaikan:</div>
            @if(count($cadanganTeknikal) > 0)
                <ol>
                    @foreach($cadanganTeknikal as $cadangan)
                        <li>{{ $cadangan }}</li>
                    @endforeach
                </ol>
            @else
                <p class="content-text" style="color: #555;">Tiada cadangan direkodkan.</p>
            @endif

            <div class="diagram-label">1.1 Logical Diagram</div>
            <div class="diagram-container">
                <div style="border: 1px solid #ccc; padding: 10px; text-align: center; background: #f9fafb;">
                    @if($laporan->logical_diagram ?? false)
                        <img src="{{ asset('storage/' . $laporan->logical_diagram) }}" alt="Logical Diagram" style="max-height: 250px; max-width: 100%;">
                    @else
                        <div style="height: 100px; padding-top:40px; color: #888;">Rajah tidak disertakan</div>
                    @endif
                </div>
            </div>

            <div class="diagram-label">1.2 Physical Diagram </div>
            <div class="diagram-container">
                <div style="border: 1px solid #ccc; padding: 10px; text-align: center; background: #f9fafb;">
                    @if($laporan->physical_diagram ?? false)
                        <img src="{{ asset('storage/' . $laporan->physical_diagram) }}" alt="Physical Diagram" style="max-height: 250px; max-width: 100%;">
                    @else
                        <div style="height: 100px; padding-top:40px; color: #888;">Rajah tidak disertakan</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="section-title">2. Objektif </div>
        <div class="content-text">
            @php
                $objektifData = is_array($objektifProjek) ? $objektifProjek : (json_decode($laporan->objektif ?? '[]', true) ?? []);
            @endphp

            @if(count($objektifData) > 0)
                <ol>
                    @foreach($objektifData as $objektif)
                        <li>{{ is_array($objektif) ? ($objektif['teks'] ?? '') : $objektif }}</li>
                    @endforeach
                </ol>
            @else
                <p class="content-text" style="color: #555;">Tiada objektif direkodkan.</p>
            @endif
        </div>

        <div class="section-title">3. Anggaran Kos </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%; text-align: center;">NO</th>
                    <th style="width: 45%;">ITEM</th>
                     <th style="width: 15%; text-align: center;">KTT</th>
                    <th style="width: 15%; text-align: center;">ANGGARAN KOS (RM)</th>
                    <th style="width: 20%; text-align: center;">JUMLAH (RM)</th>
                </tr>
            </thead>
            <tbody>
                @php $grandTotal = 0; @endphp
                @if(count($kosItems ?? []) > 0)
                    @foreach($kosItems as $index => $row)
                        @php
                            $qty = intval($row->kuantiti ?? 0);
                            $anggaran = floatval($row->anggaran ?? 0);
                            $subtotal = $qty * $anggaran;
                            $grandTotal += $subtotal;
                        @endphp
                        <tr>
                            <td style="text-align: center;">{{ $index + 1 }}</td>
                            <td>{{ $row->item }}</td>

                            <td style="text-align: center;">{{ $qty }}</td>

                            <td style="text-align: center;">{{ number_format($anggaran, 2) }}</td>

                            <td style="text-align: center; font-weight: bold;">{{ number_format($subtotal, 2) }}</td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 20px; color: #777;">Tiada item direkodkan.</td>
                    </tr>
                @endif
            </tbody>
            @if(count($kosItems ?? []) > 0)
                <tfoot>
                    <tr style="background-color: #f9fafb; font-weight: bold;">
                        <td colspan="4" style="text-align: right; text-transform: uppercase; font-size: 11px; padding-right: 15px;">JUMLAH KESELURUHAN (RM):</td>
                        <td style="text-align: center; font-size: 12px;">{{ number_format($grandTotal, 2) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>

        <div class="avoid-break">
            <div class="section-title">4. Rumusan</div>
            <p class="content-text" style="margin-top: 5px;">{{ $laporan->rumusan ?? 'Tiada rumusan akhir direkodkan.' }}</p>
        </div>

        <!-- Clean Sign-off Section with tight 3-column layout -->
        <table class="sign-box-table">
            <thead>
                <tr>
                    <th>Disediakan Oleh</th>
                    <th>Disemak Oleh</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="sign-space"></div>
                        <div class="sign-line"></div>
                        <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                            <tr>
                                <td style="width: 1%; white-space: nowrap; border: none; padding: 2px 0;">Nama</td>
                                <td style="width: 1%; white-space: nowrap; border: none; padding: 2px 10px;">:</td>
                                <td style="border: none; padding: 2px 0;"><span style="text-transform: uppercase; font-weight: bold;">{{ $laporan->disediakan_oleh ?? '_________________________' }}</span></td>
                            </tr>
                            <tr>
                                <td style="width: 1%; white-space: nowrap; border: none; padding: 2px 0;">Jawatan</td>
                                <td style="width: 1%; white-space: nowrap; border: none; padding: 2px 10px;">:</td>
                                <td style="border: none; padding: 2px 0;"><span style="text-transform: uppercase;">Penolong Pegawai Teknologi Maklumat</span></td>
                            </tr>
                            <tr>
                                <td style="width: 1%; white-space: nowrap; border: none; padding: 2px 0;">Tarikh</td>
                                <td style="width: 1%; white-space: nowrap; border: none; padding: 2px 10px;">:</td>
                                <td style="border: none; padding: 2px 0;">{{ $tarikhLaporan }}</td>
                            </tr>
                        </table>
                    </td>
                    <td>
                        <div class="sign-space"></div>
                        <div class="sign-line"></div>
                        <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                            <tr>
                                <td style="width: 1%; white-space: nowrap; border: none; padding: 2px 0;">Nama</td>
                                <td style="width: 1%; white-space: nowrap; border: none; padding: 2px 10px;">:</td>
                                <td style="border: none; padding: 2px 0;"><span style="text-transform: uppercase; font-weight: bold;">{{ $laporan->disemak_oleh ?? '_________________________' }}</span></td>
                            </tr>
                            <tr>
                                <td style="width: 1%; white-space: nowrap; border: none; padding: 2px 0;">Jawatan</td>
                                <td style="width: 1%; white-space: nowrap; border: none; padding: 2px 10px;">:</td>
                                <td style="border: none; padding: 2px 0;"><span style="text-transform: uppercase;">Pegawai Teknologi Maklumat</span></td>
                            </tr>
                            <tr>
                                <td style="width: 1%; white-space: nowrap; border: none; padding: 2px 0;">Tarikh</td>
                                <td style="width: 1%; white-space: nowrap; border: none; padding: 2px 10px;">:</td>
                                <td style="border: none; padding: 2px 0;">{{ $tarikhLaporan }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>

    </div> <!-- Close A4 Container -->

    <script>
        window.onload = function() {
            setTimeout(function() { window.print(); }, 500);
        }
    </script>
</body>
</html>
