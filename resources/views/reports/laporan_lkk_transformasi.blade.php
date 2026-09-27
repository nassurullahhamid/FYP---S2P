<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Cetak LKK TD - {{ $ticket->id_tiket }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111; line-height: 1.6; margin: 0; padding: 40px; }
        @media print {
            @page { size: A4 portrait; margin: 0; }
            body { padding: 2cm !important; margin: 0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            .avoid-break { page-break-inside: avoid; }
        }

        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        .header-title { text-align: center; font-weight: bold; font-size: 15px; text-transform: uppercase; margin-top: 10px; line-height: 1.3; }
        .header-dept { font-weight: bold; font-size: 12px; text-transform: uppercase; text-align: center; color: #000; letter-spacing: 0.5px; }
        .doc-code { text-align: right; font-size: 10px; font-weight: bold; color: #444; }

        .section-title { font-weight: bold; text-transform: uppercase; font-size: 12px; margin-top: 25px; margin-bottom: 5px; }
        .content-text { text-align: justify; margin-top: 5px; font-style: normal !important; }

        /* Lower-alpha list format */
        ol.alpha-list { list-style-type: lower-alpha; padding-left: 30px; margin-top: 5px; margin-bottom: 15px; }
        ol.alpha-list li { margin-bottom: 5px; font-style: normal !important; }

        table.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.data-table th, table.data-table td { border: 1px solid #000; padding: 8px; vertical-align: top; }
        table.data-table th { background-color: #f9fafb; font-size: 10px; text-transform: uppercase; text-align: left; }

        .image-grid { display: flex; flex-wrap: wrap; gap: 20px; width: 100%; margin-top: 15px; margin-bottom: 15px; justify-content: center; }
        .image-box { flex: 1 1 45%; box-sizing: border-box; min-width: 280px; display: flex; justify-content: center; align-items: center; }
        .image-box img { max-width: 100%; height: auto; max-height: 400px; object-fit: contain; display: block; background: #fff; }

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
        function parseToList($data) {
            if (!$data) return [];
            if (is_string($data) && str_starts_with(trim($data), '[')) {
                $decoded = json_decode($data, true);
                return is_array($decoded) ? array_map(fn($i) => is_array($i) ? ($i['teks'] ?? '') : $i, $decoded) : [];
            }
            return array_values(array_filter(array_map('trim', explode("\n", $data))));
        }

        $objektif = parseToList($laporan->objektif ?? '');
        $skop = parseToList($laporan->skop_kajian ?? '');
        $pemerhatian = json_decode($laporan->keadaan_semasa ?? '[]', true) ?? [];

        // Image sanitization
        $gambarTapakRaw = json_decode($laporan->gambar_tapak ?? '[]', true) ?? [];
        if (!is_array($gambarTapakRaw)) { $gambarTapakRaw = $gambarTapakRaw ? [$gambarTapakRaw] : []; }
        $gambarTapak = array_map(fn($img) => trim(str_replace(['"', '\\'], '', $img)), $gambarTapakRaw);

        $gambarCadanganRaw = json_decode($laporan->gambar_cadangan ?? '[]', true) ?? [];
        if (!is_array($gambarCadanganRaw)) { $gambarCadanganRaw = $gambarCadanganRaw ? [$gambarCadanganRaw] : []; }
        $gambarCadangan = array_map(fn($img) => trim(str_replace(['"', '\\'], '', $img)), $gambarCadanganRaw);

        // Cost items array
        $kosItemsRaw = $laporan->kos_items ?? '[]';
        $kosItems = is_string($kosItemsRaw) ? json_decode($kosItemsRaw, true) : $kosItemsRaw;
        $kosItems = is_array($kosItems) ? $kosItems : [];

        // Workflow lama mengekalkan tarikh laporan sedia ada.
        $tarikhLaporan = ($laporan->updated_at ?? false)
            ? \Carbon\Carbon::parse($laporan->updated_at)->format('d/m/Y')
            : (($ticket->updated_at ?? false) ? \Carbon\Carbon::parse($ticket->updated_at)->format('d/m/Y') : '');

        /*
         * Workflow V2 menggunakan masa sebenar daripada
         * jejak DIVERIFIKASI dan DIVALIDASI.
         *
         * Workflow 1 mengekalkan $tarikhLaporan.
         */
        $tarikhPenyedia =
            ($isModernizationV2 ?? false)
            && !empty($tarikhDisediakan ?? null)
                ? \Carbon\Carbon::parse(
                    $tarikhDisediakan
                )->format('d/m/Y')
                : $tarikhLaporan;

        $tarikhPenyemak =
            ($isModernizationV2 ?? false)
            && !empty($tarikhDisemak ?? null)
                ? \Carbon\Carbon::parse(
                    $tarikhDisemak
                )->format('d/m/Y')
                : $tarikhLaporan;

        $jawatanPenyediaPaparan =
            ($isModernizationV2 ?? false)
            && !empty($jawatanPenyedia ?? null)
                ? $jawatanPenyedia
                : 'Penolong Pegawai Teknologi Maklumat';

        $jawatanPenyemakPaparan =
            ($isModernizationV2 ?? false)
            && !empty($jawatanPenyemak ?? null)
                ? $jawatanPenyemak
                : 'Pegawai Teknologi Maklumat';
    @endphp

    <div class="no-print" style="text-align: right; margin-bottom: 20px;">
        <button onclick="window.print()" style="background: #2563eb; color: white; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: bold; border: none;">🖨️ Cetak</button>
    </div>

    <table class="header-table">
        <tr>
            <td style="text-align: center;">
                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; margin-bottom: 15px; text-align: center;">
                    <img src="{{ asset('images/logo_jtdi.png') }}" style="height: 80px; object-fit: contain; margin-bottom: 6px;" alt="Logo JTDI" />
                    <div class="header-dept">JABATAN TEKNOLOGI DIGITAL DAN INOVASI NEGERI SABAH</div>
                </div>
                @if($isModernizationV2 ?? false)
                    <div style="margin-bottom: 4px; text-align: right; font-size: 8px; font-style: italic; font-weight: bold;">
                        BPI/B01v1.3
                    </div>
                @endif
                <div class="header-title">LAPORAN KAJIAN KESAURAN<br>PEMODENAN BILIK MESYUARAT</div>
            </td>
        </tr>
    </table>

    <div class="section-title">1. Pendahuluan / Latar Belakang</div>
    <p class="content-text">{{ $laporan->pendahuluan ?? 'Tiada maklumat.' }}</p>

    <div class="section-title">2. Objektif</div>
    <div style="margin-top: 5px; margin-bottom: 8px; font-weight: 500;">
         Objektif laporan ini adalah seperti berikut:
    </div>
    <ol class="alpha-list">
        @foreach($objektif as $o) <li>{{ $o }}</li> @endforeach
    </ol>

    <div class="section-title">3. Skop Kajian</div>
    <div style="margin-top: 5px; margin-bottom: 8px; font-weight: 500;">
         Skop kajian kesauran ini meliputi perkara berikut:
    </div>
    <ol class="alpha-list">
        @foreach($skop as $s) <li>{{ $s }}</li> @endforeach
    </ol>

    <div class="section-title">4. Pemerhatian Keadaan Semasa</div>
    @if(count($gambarTapak) > 0)
    <div class="image-grid">
        @foreach($gambarTapak as $img)
            @if(!empty($img))
                <div class="image-box"><img src="{{ asset('storage/'.$img) }}" alt="Gambar Tapak" /></div>
            @endif
        @endforeach
    </div>
    @endif

    <table class="data-table">
        <thead><tr><th>Aspek</th><th>Ulasan & Analisis</th></tr></thead>
        <tbody>
            @foreach($pemerhatian as $item)
                <tr><td>{{ $item['aspek'] ?? '-' }}</td><td>{{ $item['ulasan'] ?? '-' }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-title">5. Cadangan Susun Atur</div>
    @if(count($gambarCadangan) > 0)
    <div class="image-grid">
        @foreach($gambarCadangan as $img)
            @if(!empty($img))
                <div class="image-box"><img src="{{ asset('storage/'.$img) }}" alt="Gambar Cadangan" /></div>
            @endif
        @endforeach
    </div>
    @endif

    <div class="section-title">6. Anggaran Kos</div>
    <div style="margin-top: 5px; margin-bottom: 8px; font-weight: 500;">
        Anggaran kos bagi pemasangan ini adalah seperti berikut:
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">NO</th>
                <th style="width: 45%; text-align: left; padding-left: 10px;">ITEM / DESKRIPSI SPESIFIKASI</th>
                <th style="width: 15%; text-align: right; padding-right: 15px;">ANGGARAN (RM)</th>
                <th style="width: 15%; text-align: center;">KTT</th>
                <th style="width: 20%; text-align: right; padding-right: 15px;">JUMLAH (RM)</th>
            </tr>
        </thead>
        <tbody>
            @php $grandTotal = 0; @endphp
            @if(count($kosItems) > 0)
                @foreach($kosItems as $index => $row)
                    @php
                        $isArr = is_array($row);
                        $namaItem = $isArr ? ($row['item'] ?? '-') : ($row->item ?? '-');
                        $qty = $isArr ? intval($row['kuantiti'] ?? 0) : intval($row->kuantiti ?? 0);

                        $hargaKos = $isArr
                            ? floatval($row['harga_seunit'] ?? $row['anggaran'] ?? 0)
                            : floatval($row->harga_seunit ?? $row->anggaran ?? 0);

                        $subtotal = $qty * $hargaKos;
                        $grandTotal += $subtotal;
                    @endphp
                    <tr>
                        <td style="text-align: center;">{{ $index + 1 }}</td>
                        <td style="text-align: left; padding-left: 10px;">{{ $namaItem }}</td>
                        <td style="text-align: right; padding-right: 15px;">{{ number_format($hargaKos, 2) }}</td>
                        <td style="text-align: center;">{{ $qty }}</td>
                        <td style="text-align: right; padding-right: 15px; font-weight: bold;">{{ number_format($subtotal, 2) }}</td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="5" style="text-align: center; padding: 20px; color: #777;">Tiada item direkodkan.</td>
                </tr>
            @endif
        </tbody>
        @if(count($kosItems) > 0)
            <tfoot>
                <tr style="background-color: #f9fafb; font-weight: bold;">
                    <td colspan="4" style="text-align: right; text-transform: uppercase; font-size: 11px; padding-right: 15px;">JUMLAH KESELURUHAN (RM):</td>
                    <td style="text-align: right; font-size: 12px; padding-right: 15px;">{{ number_format($grandTotal, 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="section-title">7. Rumusan</div>
    <p class="content-text">{{ $laporan->rumusan ?? 'Tiada rumusan.' }}</p>

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
                                <td style="border: none; padding: 2px 0;"><span style="text-transform: uppercase;">{{ $jawatanPenyediaPaparan }}</span></td>
                            </tr>
                            <tr>
                                <td style="width: 1%; white-space: nowrap; border: none; padding: 2px 0;">Tarikh</td>
                                <td style="width: 1%; white-space: nowrap; border: none; padding: 2px 10px;">:</td>
                                <td style="border: none; padding: 2px 0;">{{ $tarikhPenyedia }}</td>
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
                                <td style="border: none; padding: 2px 0;"><span style="text-transform: uppercase;">{{ $jawatanPenyemakPaparan }}</span></td>
                            </tr>
                            <tr>
                                <td style="width: 1%; white-space: nowrap; border: none; padding: 2px 0;">Tarikh</td>
                                <td style="width: 1%; white-space: nowrap; border: none; padding: 2px 10px;">:</td>
                                <td style="border: none; padding: 2px 0;">{{ $tarikhPenyemak }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>

    <script>
        window.onload = function() {
            setTimeout(function() { window.print(); }, 500);
        }
    </script>
</body>
</html>
