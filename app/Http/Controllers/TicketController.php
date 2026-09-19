<?php

namespace App\Http\Controllers;

use App\Models\Tiket;
use App\Models\Pengguna;
use App\Models\Laporan;
use App\Mail\TicketVerificationAlert;
use App\Notifications\NewTicketNoti;
use App\Notifications\PengesahanKetuaNoti;
use App\Notifications\ValidasiKWNoti;
use App\Notifications\LKKPembetulanNoti;
use App\Notifications\TugasanPicNoti;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TicketController extends Controller
{
    /**
     * Display ticket listing with search and filters.
     */
    public function index(Request $request): InertiaResponse
    {
        $kategoriSelected    = $request->query('kategori');
        $subKategoriSelected = $request->query('sub_kategori');
        $search              = $request->query('search');

        $userAktif = Auth::user();

        $query = Tiket::with(['petugas', 'mejaBantuan', 'konsultasiRangkaian', 'transformasiDigital'])->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('id_tiket', 'like', "%{$search}%")
                  ->orWhere('agensi', 'like', "%{$search}%")
                  ->orWhere('perkara', 'like', "%{$search}%");
            });
        }

        if (in_array(strtolower((string) $userAktif->peranan), ['juruteknik', 'pic'], true)) {
            $query->whereHas('petugas', function ($subQuery) use ($userAktif) {
                $subQuery->where('tugasan_tiket.no_ic', $userAktif->no_ic);
            });
        }

        if ($kategoriSelected) {
            $query->where('kategori', $kategoriSelected);
        }

        if ($subKategoriSelected && $kategoriSelected) {
            $relation = match ($kategoriSelected) {
                'Meja Bantuan'         => 'mejaBantuan',
                'Konsultasi Rangkaian' => 'konsultasiRangkaian',
                'Transformasi Digital' => 'transformasiDigital',
                default                => null,
            };

            if ($relation) {
                $query->whereHas($relation, function ($subQuery) use ($subKategoriSelected) {
                    $subQuery->where('sub_kategori', $subKategoriSelected);
                });
            }
        }

        if ($request->has('status')) {
            $status = $request->query('status');

            if ($status === 'aktif') {
                $query->where('status_tiket', '!=', 'Selesai');
            } elseif ($status === 'proses' || $status === 'dalam_tindakan') {
                $query->whereIn('status_tiket', [
                    'Dalam Tindakan Pegawai',
                    'Menunggu Pengesahan',
                    'LKK Perlu Pembetulan',
                    'Menunggu Validasi',
                    'Menunggu Semakan'
                ]);
            } elseif ($status === 'belum_tindakan') {
                $query->whereIn('status_tiket', [
                    'Menunggu Klasifikasi',
                    'Menunggu Semakan Dokumen',
                    'Menunggu Kelulusan',
                    'Tugasan UTD',
                    'Tugasan UPP'
                ]);
            } else {
                if (!is_array($status)) {
                    $status = [$status];
                }
                $status = array_filter($status);
                if (!empty($status)) {
                    $query->whereIn('status_tiket', $status);
                }
            }
        }

        $tickets = $query->paginate(10);

        $tickets->getCollection()->transform(function ($ticket) {
            $pic = DB::table('tugasan_tiket')
                    ->join('pengguna', 'tugasan_tiket.no_ic', '=', 'pengguna.no_ic')
                    ->where('tugasan_tiket.id_tiket', $ticket->id_tiket)
                    ->pluck('pengguna.nama')
                    ->implode(', ');

            $ticket->nama_pic = $pic;
            return $ticket;
        });

        return Inertia::render('Tickets/SenaraiTiket', [
            'tickets' => $tickets,
            'filters' => $request->only(['search', 'kategori', 'sub_kategori', 'status'])
        ]);
    }

    /**
     * Store newly created ticket application.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'perkara'       => ['required', 'string', 'max:255'],
            'saluran'       => ['required', 'string'],
            'nama_pemohon'  => ['required', 'string', 'max:255'],
            'emel_pemohon'  => ['required', 'email', 'max:255'],
            'notel_pemohon' => ['required', 'string', 'max:20'],
            'agensi'        => ['required', 'string'],
            'lokasi'        => ['nullable', 'string', 'max:255'],
            'daerah'        => ['required', 'string'],
            'kategori'      => ['required', 'string', Rule::in(['Meja Bantuan', 'Konsultasi Rangkaian', 'Transformasi Digital'])],
            'sub_kategori'  => ['required', 'string', Rule::in(match ($request->input('kategori')) { 'Meja Bantuan' => ['Penyelenggaraan Komputer', 'Penyelenggaraan Rangkaian', 'Sistem Aplikasi', 'Perkhidmatan E-mel', 'Perkhidmatan Lintas Langsung', 'Peminjaman Peralatan ICT'], 'Konsultasi Rangkaian' => ['Pemasangan Baharu', 'Naiktaraf'], 'Transformasi Digital' => ['Pemodenan Bilik Mesyuarat', 'Pembekalan Peralatan ICT'], default => [] })],
            'lampiran'      => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:5120'],
        ]);

        $prefix = match ($request->kategori) {
            'Meja Bantuan'         => 'MB',
            'Transformasi Digital' => 'TD',
            'Konsultasi Rangkaian' => 'KR',
            default                => 'GEN',
        };

        $currentYear = now()->format('Y');

        $lastTicket = Tiket::where('id_tiket', 'LIKE', "SDK-{$prefix}-{$currentYear}-%")
            ->orderBy('id_tiket', 'desc')
            ->first();

        $nextSequence   = $lastTicket ? ((int) substr($lastTicket->id_tiket, -3)) + 1 : 1;
        $paddedSequence = str_pad($nextSequence, 3, '0', STR_PAD_LEFT);
        $generatedId    = "SDK-{$prefix}-{$currentYear}-{$paddedSequence}";

        $filePath = null;
        if ($request->hasFile('lampiran')) {
            $file = $request->file('lampiran');
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $cleanName = str_replace(' ', '_', $originalName);
            $extension = $file->getClientOriginalExtension();
            $filenameToStore = $cleanName . '_' . $generatedId . '.' . $extension;
            $filePath = $file->storeAs('attachments', $filenameToStore, 'public');
            $validated['lampiran'] = $filePath;
        }

        $validated['kategori'] = match ($prefix) {
            'MB'    => 'Meja Bantuan',
            'TD'    => 'Transformasi Digital',
            'KR'    => 'Konsultasi Rangkaian',
            default => 'Umum',
        };

        $validated['id_tiket']      = $generatedId;
        $validated['tarikh_terima']  = now();
        $validated['status_tiket']  = 'Menunggu Klasifikasi';
        $validated['pengguna_ic']   = $request->user()->no_ic;

        $subKategoriValue = $validated['sub_kategori'];
        unset($validated['sub_kategori']);

        DB::beginTransaction();
        try {
            $ticket = Tiket::create($validated);

            $childTable = match ($validated['kategori']) {
                'Meja Bantuan'         => 'meja_bantuan',
                'Transformasi Digital' => 'transformasi_digital',
                'Konsultasi Rangkaian' => 'konsultasi_rangkaian',
                default                => null,
            };

            if ($childTable) {
                DB::table($childTable)->insert([
                    'id_tiket'     => $generatedId,
                    'sub_kategori' => $subKategoriValue,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }

            $ticket->rekodLog('Daftar Tiket', 'Oleh ' . Auth::user()->nama, 'SELESAI');

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            if ($filePath) Storage::disk('public')->delete($filePath);
            Log::error('Ticket Insertion Failed: ' . $e->getMessage());
            return back()->withErrors([
                'sistem' => 'Permohonan tidak dapat didaftarkan kerana berlaku ralat sistem. Sila cuba semula.'
            ]);
        }

        try {

            $senaraiPengurus = Pengguna::whereIn('peranan', ['ketua_upp', 'kupp', 'ketua upp', 'Ketua UPP', 'ketua_utd', 'kutd', 'ketua utd', 'Ketua UTD', 'ketua_wilayah', 'kw', 'ketua wilayah', 'Ketua Wilayah'])->get();
            $targetHeadsEmails = $senaraiPengurus->whereNotNull('emel')->pluck('emel')->toArray();

            if (!empty($targetHeadsEmails)) {
                Mail::to($targetHeadsEmails)->send(new TicketVerificationAlert($ticket, $subKategoriValue));
            }

            if ($senaraiPengurus->isNotEmpty()) {
                Notification::send($senaraiPengurus, new NewTicketNoti($ticket, 'baru'));
            }

        } catch (\Exception $e) {
            Log::error('Ticket Notification Failed: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', "Permohonan baru berjaya didaftarkan. ID Tiket: {$generatedId}");
    }

    /**
     * Handle redirect from email verification.
     */
    public function handleEmailRedirect(string $id_tiket): RedirectResponse
    {
        $user = Auth::user();
        $peranan = strtolower(trim($user->peranan));
        $kakitanganTeknikal = ['ketua_upp', 'kupp', 'ketua upp', 'ketua_utd', 'kutd', 'ketua utd', 'ketua_wilayah', 'kw', 'ketua wilayah'];

        if (!in_array($peranan, $kakitanganTeknikal)) {
            return redirect()->route('dashboard')->with('error', 'Anda tidak mempunyai akses ke modul pengesahan ini.');
        }

        return redirect()->route('tickets.show', ['id_tiket' => $id_tiket]);
    }

    /**
     * Show ticket details view.
     */
    public function show(string $id_ticket): InertiaResponse
    {
        $ticket = Tiket::with(['petugas', 'mejaBantuan', 'konsultasiRangkaian', 'transformasiDigital', 'laporan'])
            ->where('id_tiket', $id_ticket)
            ->firstOrFail();

        $user = Auth::user();
        $peranan = strtolower(trim((string) $user->peranan));

        if (
            in_array($peranan, ['juruteknik', 'pic'], true) &&
            !$ticket->petugas->contains('no_ic', $user->no_ic)
        ) {
            abort(403, 'Anda tidak mempunyai kebenaran untuk melihat tiket ini.');
        }

        $previousUrl = url()->previous();
        if (str_contains($previousUrl, '/tickets/')) {
            $previousUrl = route('tickets.index');
        }

        $auditTrail = DB::table('jejak_tiket')
            ->where('id_tiket', $id_ticket)
            ->orderBy('id', 'asc')
            ->get();

        $childData = match ($ticket->kategori) {
            'Meja Bantuan'         => $ticket->mejaBantuan,
            'Konsultasi Rangkaian' => $ticket->konsultasiRangkaian,
            'Transformasi Digital' => $ticket->transformasiDigital,
            default                => null,
        };

        $ticket->sub_kategori      = $childData?->sub_kategori;
        $ticket->serial_no          = $ticket->mejaBantuan?->serial_no ?? null;
        $ticket->kuantiti_dipinjam = $ticket->mejaBantuan?->kuantiti_dipinjam ?? null;

        $senaraiPengguna = DB::table('pengguna')
            ->whereIn('peranan', ['juruteknik', 'pic', 'ketua_upp', 'kupp', 'ketua upp', 'Ketua UPP', 'ketua_utd', 'kutd', 'ketua utd', 'Ketua UTD', 'ketua_wilayah', 'kw', 'ketua wilayah', 'Ketua Wilayah'])
            ->select('no_ic', 'nama', 'peranan')
            ->get();

        $senaraiAset = DB::table('aset')
            ->select('nama_aset', DB::raw("SUM(CASE WHEN status = 'Tersedia' THEN 1 ELSE 0 END) as baki_stok"))
            ->groupBy('nama_aset')
            ->get();

        $dataKos = null;
        if (
            $ticket->kategori === 'Meja Bantuan' &&
            $ticket->sub_kategori === 'Peminjaman Peralatan ICT' &&
            $ticket->laporan &&
            $ticket->laporan->kos_items
        ) {
            $dataKos = $ticket->laporan->kos_items;
        }

        $ticket->petugas = $ticket->petugas ?? [];

        return Inertia::render('Tickets/InfoTiket', [
            'ticket'               => $ticket,
            'auditTrail'           => $auditTrail,
            'senaraiPengguna'      => $senaraiPengguna,
            'senaraiAset'          => $senaraiAset,
            'BorangLKKTransformasi' => $ticket,
            'backUrl'              => $previousUrl,
            'dataKelulusan'        => $dataKos
        ]);
    }

    /**
     * Process classification and ticket assignment workflow.
     */
    public function processAction(Request $request, string $id_tiket): RedirectResponse
    {
        $userAktif = Auth::user();
        $ticket = Tiket::where('id_tiket', $id_tiket)->firstOrFail();
        $statusProsesSah = ['Menunggu Klasifikasi', 'Menunggu Semakan Dokumen', 'Tugasan UTD'];
        if (!in_array($ticket->status_tiket, $statusProsesSah, true)) {
            abort(403, 'Status tiket semasa tidak dibenarkan untuk diproses melalui tindakan ini.');
        }

        if ($ticket->status_tiket !== 'Menunggu Klasifikasi') {
            $subKategoriSediaAda = match ($ticket->kategori) {
                'Meja Bantuan'         => $ticket->mejaBantuan?->sub_kategori,
                'Konsultasi Rangkaian' => $ticket->konsultasiRangkaian?->sub_kategori,
                'Transformasi Digital' => $ticket->transformasiDigital?->sub_kategori,
                default                => null,
            };

            $request->merge([
                'kategori'        => $ticket->kategori,
                'sub_kategori'    => $subKategoriSediaAda,
                'tahap_keutamaan' => $ticket->tahap_keutamaan,
            ]);
        }

        $perananAktif = strtolower(trim($userAktif->peranan));

        // Authorize the workflow before validating request data.
        $isKUPP = in_array($perananAktif, ['ketua_upp', 'kupp', 'ketua upp']);
        $isKUTD = in_array($perananAktif, ['ketua_utd', 'kutd', 'ketua utd']);
        $isPengurusanLain = in_array($perananAktif, ['ketua_utd', 'kutd', 'ketua utd', 'ketua_wilayah', 'kw', 'ketua wilayah']);

        if ($ticket->status_tiket === 'Menunggu Klasifikasi' && !($isKUPP || $isPengurusanLain)) {
            abort(403, 'Hanya KUPP, KUTD atau KW dibenarkan memproses tiket berstatus Menunggu Klasifikasi.');
        }

        if ($ticket->status_tiket === 'Tugasan UTD' && !$isKUTD) {
            abort(403, 'Hanya KUTD dibenarkan memproses tiket berstatus Tugasan UTD.');
        }

        if ($ticket->status_tiket === 'Menunggu Semakan Dokumen' && !$isKUPP) {
            abort(403, 'Hanya KUPP dibenarkan memproses tiket berstatus Menunggu Semakan Dokumen.');
        }

        $mesejSukses = "Tiket berjaya dikemaskini.";
        $subKategoriSah = match ($request->input('kategori')) {
            'Meja Bantuan' => ['Penyelenggaraan Komputer', 'Penyelenggaraan Rangkaian', 'Sistem Aplikasi', 'Perkhidmatan E-mel', 'Perkhidmatan Lintas Langsung', 'Peminjaman Peralatan ICT'],
            'Konsultasi Rangkaian' => ['Pemasangan Baharu', 'Naiktaraf'],
            'Transformasi Digital' => ['Pemodenan Bilik Mesyuarat', 'Pembekalan Peralatan ICT'],
            default => [],
        };


        $rules = [
            'kategori'        => ['required', 'string', 'in:Meja Bantuan,Konsultasi Rangkaian,Transformasi Digital'],
            'sub_kategori'    => ['required', 'string', Rule::in($subKategoriSah)],
            'tahap_keutamaan' => ['required', 'string', 'in:Rendah,Sederhana,Tinggi'],
        ];

        // Extra validation if processed by KUPP
        if (in_array($perananAktif, ['ketua_upp', 'kupp', 'ketua upp']) && in_array($ticket->status_tiket, ['Menunggu Klasifikasi', 'Menunggu Semakan Dokumen'], true)) {
            $rules['bisa_kendalikan'] = ['required', 'in:Ya,Tidak'];

            $adakahPeminjaman = $request->input('sub_kategori') === 'Peminjaman Peralatan ICT';

            if ($adakahPeminjaman) {
                $rules['no_ic'] = ['nullable', 'string'];
            } else {
                $rules['no_ic'] = ['required_if:bisa_kendalikan,Ya', 'nullable', 'string', Rule::exists('pengguna', 'no_ic')->where(fn ($query) => $query->where('peranan', 'juruteknik'))];
            }
        }

        if ($ticket->status_tiket === 'Tugasan UTD') {
            $rules['senarai_pic_ic'] = ['required', 'array', 'min:1'];
            $rules['senarai_pic_ic.*'] = ['required', 'string', 'distinct', Rule::exists('pengguna', 'no_ic')->where(fn ($query) => $query->where('peranan', 'juruteknik'))];
            if ($request->input('kategori') === 'Konsultasi Rangkaian') {
                $rules['tarikh_lawatan'] = ['required', 'date'];
                $rules['masa_lawatan'] = ['required', 'date_format:H:i'];
                $rules['catatan_lawatan'] = ['nullable', 'string'];
            }
        }


        $validated = $request->validate($rules);

        $hariSla = match ($validated['tahap_keutamaan']) {
            'Tinggi' => 3, 'Sederhana' => 7, default => 14,
        };

        $statusLama = $ticket->status_tiket;
        $statusBaharu = $ticket->status_tiket;

        $kategoriCek = strtolower($validated['kategori'] ?? $ticket->kategori ?? '');
        $adakahTD = str_contains($kategoriCek, 'transformasi digital');
        $adakahPeminjaman = str_contains(strtolower($validated['sub_kategori'] ?? ''), 'peminjaman');


        // Status transition logic
        if ($statusLama === 'Menunggu Klasifikasi') {
            if ($adakahPeminjaman) {
                $statusBaharu = 'Menunggu Kelulusan';
            }
            else if ($isPengurusanLain) {
                $statusBaharu = 'Menunggu Semakan Dokumen';
            }
            else if ($isKUPP) {
                if ($adakahTD) {
                    $statusBaharu = 'Tugasan UPP';
                } else {
                    $statusBaharu = ($request->bisa_kendalikan === 'Ya') ? 'Dalam Tindakan Pegawai' : 'Tugasan UTD';
                }
            }
        }
        elseif ($statusLama === 'Menunggu Semakan Dokumen') {
            if ($adakahTD) {
                $statusBaharu = 'Tugasan UPP';
            } else {
                $statusBaharu = ($request->bisa_kendalikan === 'Ya' || $isPengurusanLain) ? 'Dalam Tindakan Pegawai' : 'Tugasan UTD';
            }
        }
        elseif ($statusLama === 'Tugasan UTD') {
            $statusBaharu = 'Dalam Tindakan Pegawai';
        }

        $oldKategori = $ticket->kategori;
        $newKategori = $validated['kategori'];
        $newIdTiket = $id_tiket;

        DB::beginTransaction();
        try {
            if ($oldKategori !== $newKategori) {
                $prefix = match ($newKategori) {
                    'Meja Bantuan'         => 'MB',
                    'Konsultasi Rangkaian' => 'KR',
                    'Transformasi Digital' => 'TD',
                    default                => 'GEN',
                };

                $currentYear = now()->format('Y');

                $lastTicket = Tiket::where('id_tiket', 'LIKE', "SDK-{$prefix}-{$currentYear}-%")
                    ->orderBy('id_tiket', 'desc')
                    ->first();

                $nextSequence   = $lastTicket ? ((int) substr($lastTicket->id_tiket, -3)) + 1 : 1;
                $paddedSequence = str_pad($nextSequence, 3, '0', STR_PAD_LEFT);
                $newIdTiket     = "SDK-{$prefix}-{$currentYear}-{$paddedSequence}";

                $oldChildTable = match ($oldKategori) {
                    'Meja Bantuan'         => 'meja_bantuan',
                    'Transformasi Digital' => 'transformasi_digital',
                    'Konsultasi Rangkaian' => 'konsultasi_rangkaian',
                };
                DB::table($oldChildTable)->where('id_tiket', $id_tiket)->delete();

                DB::table('tiket')->where('id_tiket', $id_tiket)->update([
                    'id_tiket'        => $newIdTiket,
                    'status_tiket'    => $statusBaharu,
                    'kategori'        => $newKategori,
                    'tahap_keutamaan' => $validated['tahap_keutamaan'],
                    'sla'             => now()->addDays($hariSla),
                    'updated_at'      => now(),
                ]);

                $newChildTable = match ($newKategori) {
                    'Meja Bantuan'         => 'meja_bantuan',
                    'Transformasi Digital' => 'transformasi_digital',
                    'Konsultasi Rangkaian' => 'konsultasi_rangkaian',
                };

                $insertChildFields = [
                    'id_tiket'     => $newIdTiket,
                    'sub_kategori' => $validated['sub_kategori'],
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];

                if (($newKategori === 'Konsultasi Rangkaian') && $request->filled('tarikh_lawatan')) {
                    $insertChildFields['tarikh_lawatan'] = $validated['tarikh_lawatan'];
                    $insertChildFields['masa_lawatan'] = $validated['masa_lawatan'];
                    $insertChildFields['catatan_lawatan'] = $validated['catatan_lawatan'] ?? null;
                }

                DB::table($newChildTable)->insert($insertChildFields);

                $ticket = Tiket::where('id_tiket', $newIdTiket)->firstOrFail();
                $mesejSukses = "Kategori tiket berjaya ditukar. ID baharu dijana: {$newIdTiket}";

            } else {
                $ticket->update([
                    'status_tiket'    => $statusBaharu,
                    'kategori'        => $validated['kategori'],
                    'tahap_keutamaan' => $validated['tahap_keutamaan'],
                    'sla'             => $statusLama === 'Menunggu Klasifikasi' ? now()->addDays($hariSla) : $ticket->sla,
                ]);

                $childTable = match ($validated['kategori']) {
                    'Meja Bantuan'         => 'meja_bantuan',
                    'Transformasi Digital' => 'transformasi_digital',
                    'Konsultasi Rangkaian' => 'konsultasi_rangkaian',
                    default                => null,
                };

                if ($childTable) {
                    $updateFields = ['sub_kategori' => $validated['sub_kategori']];
                    if (($validated['kategori'] === 'Konsultasi Rangkaian') && $request->filled('tarikh_lawatan')) {
                        $updateFields['tarikh_lawatan'] = $validated['tarikh_lawatan'];
                        $updateFields['masa_lawatan'] = $validated['masa_lawatan'];
                        $updateFields['catatan_lawatan'] = $validated['catatan_lawatan'] ?? null;
                    }

                    DB::table($childTable)->updateOrInsert(['id_tiket' => $id_tiket], array_merge($updateFields, ['updated_at' => now()]));
                }
            }

            if ($isKUPP && $request->filled('no_ic')) {
                $ticket->petugas()->sync([$request->no_ic]);
            } else if ($request->has('senarai_pic_ic') && !empty($request->senarai_pic_ic)) {
                $pics = array_filter($request->senarai_pic_ic, fn($val) => !empty($val));
                if (!empty($pics)) $ticket->petugas()->sync($pics);
            }

            if ($statusLama === 'Menunggu Klasifikasi' || $statusLama === 'Menunggu Semakan Dokumen') {
                $ticket->rekodLog('Disemak', 'Oleh ' . $userAktif->nama, 'INFO');
            }

            if ($statusBaharu === 'Tugasan UTD' && $statusLama !== 'Tugasan UTD') {
                $badgeUtD = ($validated['kategori'] === 'Konsultasi Rangkaian') ? 'FASA 1' : 'INFO';
                $ticket->rekodLog('Dihantar ke UTD', 'Oleh ' . $userAktif->nama, $badgeUtD);
            }

            if ($statusLama === 'Tugasan UTD' && $statusBaharu === 'Dalam Tindakan Pegawai') {
                if ($validated['kategori'] === 'Meja Bantuan') {
                    $ticket->rekodLog('Disemak', 'Oleh ' . $userAktif->nama, 'INFO');
                } elseif ($validated['kategori'] === 'Konsultasi Rangkaian') {
                    $ticket->rekodLog('Dijadual', 'Oleh ' . $userAktif->nama, 'FASA 2');
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processAction: ' . $e->getMessage());
            return back()->withErrors([
                'sistem' => 'Tindakan tiket tidak dapat diproses kerana berlaku ralat sistem. Sila cuba semula.'
            ]);
        }

        try {

            $this->clearTicketNotifications($id_tiket);

            if ($statusBaharu === 'Menunggu Semakan Dokumen') {
                $targetUsers = Pengguna::whereIn('peranan', ['ketua_upp', 'kupp', 'ketua upp', 'Ketua UPP'])->get();
                $targetUsers->isNotEmpty() && Notification::send($targetUsers, new NewTicketNoti($ticket, 'semakan'));
            }
            elseif ($statusBaharu === 'Tugasan UTD') {
                $targetUsers = Pengguna::whereIn('peranan', ['ketua_utd', 'kutd', 'ketua utd', 'Ketua UTD'])->get();
                $targetUsers->isNotEmpty() && Notification::send($targetUsers, new NewTicketNoti($ticket, 'tugasan_utd'));
            }
            elseif ($statusBaharu === 'Menunggu Kelulusan') {
                $paraPengesah = Pengguna::whereIn('peranan', ['ketua_upp', 'kupp', 'ketua upp', 'Ketua UPP', 'ketua_utd', 'kutd', 'ketua utd', 'Ketua UTD', 'ketua_wilayah', 'kw', 'ketua wilayah', 'Ketua Wilayah'])
                    ->whereNotNull('no_ic')
                    ->where('no_ic', '!=', '')
                    ->get()
                    ->unique('no_ic');

                if ($paraPengesah->isNotEmpty()) {
                    Notification::send($paraPengesah, new PengesahanKetuaNoti($ticket, Auth::user()->nama));
                }
            }

            $senaraiPicMahuDihantar = [];
            if ($isKUPP && $request->filled('no_ic') && $request->bisa_kendalikan === 'Ya') {
                $senaraiPicMahuDihantar[] = $request->no_ic;
            } elseif ($request->has('senarai_pic_ic') && !empty($request->senarai_pic_ic)) {
                $senaraiPicMahuDihantar = array_filter($request->senarai_pic_ic, fn($val) => !empty($val));
            }

            if (!empty($senaraiPicMahuDihantar)) {
                $jurutekniks = Pengguna::whereIn('no_ic', $senaraiPicMahuDihantar)->get();
                Notification::send($jurutekniks, new NewTicketNoti($ticket));
            }

        } catch (\Exception $e) {
            Log::error('Ticket Notification Failed in processAction: ' . $e->getMessage());
        }

        if ($oldKategori !== $newKategori) {
            return redirect()->route('tickets.show', ['id_tiket' => $newIdTiket])->with('success', $mesejSukses);
        }

        return back()->with('success', $mesejSukses);
    }

    /**
     * Handle PIC closing of regular ticket tasks.
     */
    public function picUpdate(Request $request, string $id_tiket): RedirectResponse
    {
        $request->validate([
            'catatan_penutupan' => ['nullable', 'string', 'min:10'],
        ], [
            'catatan_penutupan.min' => 'Catatan laporan mestilah mengandungi sekurang-kurangnya 10 aksara.',
        ]);

        $ticket = Tiket::where('id_tiket', $id_tiket)->firstOrFail();

        DB::beginTransaction();
        try {
            $ticket->update([
                'ulasan_semakan' => $request->catatan_penutupan,
                'status_tiket'   => 'Selesai',
                'tarikh_tutup'   => now(),
            ]);

            $ticket->rekodLog('Tindakan diselesaikan', 'Oleh ' . Auth::user()->nama . ' | Catatan: ' . $request->catatan_penutupan, 'SELESAI');
            $ticket->rekodLog('Tiket ditutup', 'Oleh ' . Auth::user()->nama, 'SELESAI');

            DB::commit();

            $this->clearTicketNotifications($id_tiket);

            return redirect()->route('tickets.index')->with('success', "Tugasan bagi Tiket {$id_tiket} berjaya diselesaikan dan ditutup rasmi.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ralat picUpdate: ' . $e->getMessage());
            return back()->withErrors(['catatan_pic' => 'Gagal mengemas kini laporan PIC: ' . $e->getMessage()]);
        }
    }

    /**
     * Get available asset count by category name.
     */
    public function getKuantitiAset(Request $request)
    {
        $namaAset = $request->query('kategori');

        $totalKuantiti = DB::table('aset')
            ->where('nama_aset', $namaAset)
            ->where('status', 'Tersedia')
            ->count();

        return response()->json(['kuantiti' => $totalKuantiti]);
    }

    /**
     * Store site visit report and transition status to Menunggu Pengesahan for KUTD.
     */
    public function storeLaporanTapak(Request $request, string $id_tiket)
    {
        $ticket = Tiket::where('id_tiket', $id_tiket)->firstOrFail();
        $userSemasa = $request->user();

        if ($ticket->kategori !== 'Konsultasi Rangkaian') {
            abort(403, 'Laporan maklumat tapak hanya dibenarkan untuk tiket Konsultasi Rangkaian.');
        }

        $adakahPIC = DB::table('tugasan_tiket')
            ->where('id_tiket', $id_tiket)
            ->where('no_ic', $userSemasa->no_ic)
            ->exists();

        if (!$adakahPIC) {
            abort(403, 'Anda bukan PIC yang ditugaskan untuk tiket ini.');
        }

        if ($ticket->status_tiket !== 'Dalam Tindakan Pegawai') {
            abort(403, 'Status tiket tidak membenarkan PIC mengisi atau menghantar laporan maklumat tapak.');
        }

        $validated = $request->validate([
            'nama_lokasi_bangunan'  => ['nullable', 'string', 'max:255'],
            'jenis_premis'          => ['required', 'string'],
            'bilik_server'          => ['required', 'string'],
            'rack_server'           => ['required', 'string'],
            'sumber_kuasa'          => ['required', 'string'],
            'persekitaran_fizikal'  => ['required', 'string'],
            'liputan'               => ['required', 'string'],
            'jenis_capaian'         => ['required', 'string'],
            'kelajuan'              => ['required', 'string'],
            'lan'                   => ['required', 'string'],
            'ap'                    => ['required', 'string'],
            'firewall'              => ['required', 'string'],
            'rumusan'               => ['required', 'string'],
            'ulasan_teknikal'       => ['required', 'array'],
        ]);

        $ulasanJson = json_encode($request->input('ulasan_teknikal'));

        DB::beginTransaction();
        try {
            DB::table('konsultasi_rangkaian')
                ->where('id_tiket', $id_tiket)
                ->update([
                    'nama_lokasi_bangunan'  => $validated['nama_lokasi_bangunan'],
                    'jenis_premis'          => $validated['jenis_premis'],
                    'bilik_server'          => $validated['bilik_server'],
                    'rack_server'           => $validated['rack_server'],
                    'sumber_kuasa'          => $validated['sumber_kuasa'],
                    'persekitaran_fizikal'  => $validated['persekitaran_fizikal'],
                    'liputan'               => $validated['liputan'],
                    'jenis_capaian'         => $validated['jenis_capaian'],
                    'kelajuan'              => $validated['kelajuan'],
                    'lan'                   => $validated['lan'],
                    'ap'                    => $validated['ap'],
                    'firewall'              => $validated['firewall'],
                    'rumusan'               => $validated['rumusan'],
                    'ulasan_teknikal'       => $ulasanJson,
                    'updated_at'            => now(),
                ]);

            $this->clearTicketNotifications($id_tiket);

            if ($ticket->status_tiket === 'Dalam Tindakan Pegawai') {
                $ticket->update([
                    'status_tiket' => 'Menunggu Pengesahan',
                    'updated_at'   => now()
                ]);

                $alreadyLogged = DB::table('jejak_tiket')
                    ->where('id_tiket', $id_tiket)
                    ->where('aktiviti', 'Pegawai Pelaksana')
                    ->exists();

                if (!$alreadyLogged) {
                    $ticket->rekodLog('Pegawai Pelaksana', 'Oleh ' . Auth::user()->nama, 'FASA 3');
                }

                $senaraiKUTD = Pengguna::whereIn('peranan', ['ketua_utd', 'kutd', 'ketua utd', 'Ketua UTD'])->get();
                if ($senaraiKUTD->isNotEmpty()) {
                    Notification::send($senaraiKUTD, new PengesahanKetuaNoti($ticket, Auth::user()->nama, true));
                }
            }

            DB::commit();
            return back()->with('success', 'Laporan maklumat tapak berjaya dikemaskini dan dihantar ke KUTD.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error storeLaporanTapak: ' . $e->getMessage());
            return back()->withErrors(['sistem' => 'Gagal menyimpan maklumat tapak: ' . $e->getMessage()]);
        }
    }

    /**
     * Print site information sheet.
     */
    public function cetakMaklumatTapak($id_tiket)
    {
        $ticket = Tiket::with(['petugas', 'konsultasiRangkaian'])
            ->where('id_tiket', $id_tiket)
            ->firstOrFail();

        return Inertia::render('Tickets/CetakMaklumatTapak', [
            'ticket' => $ticket
        ]);
    }

    /**
     * Submit helpdesk report by PIC.
     */
    public function hantarLaporanMB(Request $request, string $id_tiket)
    {
        $validated = $request->validate([
            'catatan_penutupan' => ['required', 'string', 'max:2000'],
        ]);

        $ticket = Tiket::where('id_tiket', $id_tiket)->firstOrFail();
        $userSemasa = Auth::user();

        $ticket->update([
            'status_tiket'      => 'Menunggu Pengesahan',
            'catatan_penutupan' => $validated['catatan_penutupan'],
        ]);

        $alreadyLogged = DB::table('jejak_tiket')
            ->where('id_tiket', $id_tiket)
            ->where('aktiviti', 'Pegawai Pelaksana')
            ->exists();

        if (!$alreadyLogged) {
            $ticket->rekodLog('Pegawai Pelaksana', 'Oleh ' . $userSemasa->nama);
        }

        $this->clearTicketNotifications($id_tiket);

        $isHandedOverToUTD = DB::table('jejak_tiket')
            ->where('id_tiket', $id_tiket)
            ->where('aktiviti', 'LIKE', '%Dihantar ke UTD%')
            ->exists();

        if ($isHandedOverToUTD) {
            $targetManagers = Pengguna::whereIn('peranan', ['ketua_utd', 'kutd', 'ketua utd', 'Ketua UTD'])->get();
        } else {
            $targetManagers = Pengguna::whereIn('peranan', ['ketua_upp', 'kupp', 'ketua upp', 'Ketua UPP'])->get();
        }

        if ($targetManagers->isNotEmpty()) {
            Notification::send($targetManagers, new PengesahanKetuaNoti($ticket, $userSemasa->nama));
        }

        return back()->with('success', 'Laporan berjaya dihantar!');
    }

    /**
     * Confirm and close helpdesk ticket by manager.
     */
    public function sahkanTutupMB(Request $request, string $id_tiket)
    {
        $ticket = Tiket::where('id_tiket', $id_tiket)->firstOrFail();
        $userSemasa = Auth::user();

        $ticket->update([
            'status_tiket' => 'Selesai',
            'tarikh_tutup' => now()
        ]);

        $ticket->rekodLog('Disahkan', 'Oleh ' . $userSemasa->nama, 'DISAHKAN');
        $ticket->rekodLog('Tiket Ditutup', 'Oleh ' . $userSemasa->nama, 'SELESAI');

        $this->clearTicketNotifications($id_tiket);

        return back()->with('success', 'Tiket ini berjaya disahkan dan ditutup sepenuhnya!');
    }

    /**
     * Generate available serial asset list for loan.
     */
    public function janaSenaraiAset(Request $request, $id_tiket)
    {
        $request->validate([
            'id_aset'        => 'required|string|exists:aset,nama_aset',
            'kuantiti_lulus' => 'required|integer|min:1',
        ]);

        $userSemasa = Auth::user();
        $perananSemasa = strtolower(trim($userSemasa->peranan ?? ''));

        $perananKUPP = [
            'ketua_upp',
            'ketua upp',
            'kupp',
        ];

        if (!in_array($perananSemasa, $perananKUPP, true)) {
            abort(403, 'Hanya Ketua UPP dibenarkan menjana senarai aset untuk kelulusan peminjaman.');
        }

        $ticket = DB::table('tiket')
            ->where('id_tiket', $id_tiket)
            ->first();

        if (!$ticket) {
            abort(404, 'Tiket peminjaman tidak dijumpai.');
        }

        $adakahPeminjaman = $ticket->kategori === 'Meja Bantuan'
            && DB::table('meja_bantuan')
                ->where('id_tiket', $id_tiket)
                ->where('sub_kategori', 'Peminjaman Peralatan ICT')
                ->exists();

        if (!$adakahPeminjaman) {
            abort(403, 'Tindakan ini hanya sah untuk tiket Meja Bantuan - Peminjaman Peralatan ICT.');
        }

        if ($ticket->status_tiket !== 'Menunggu Kelulusan') {
            abort(403, 'Senarai aset hanya boleh dijana untuk tiket berstatus Menunggu Kelulusan.');
        }

        $senaraiAset = DB::table('aset')
            ->where('nama_aset', $request->id_aset)
            ->where('status', 'Tersedia')
            ->inRandomOrder()
            ->limit($request->kuantiti_lulus)
            ->get(['serial_no', 'nama_aset', 'model']);

        if ($senaraiAset->count() < $request->kuantiti_lulus) {
            return response()->json(['error' => 'Baki stok fizikal tidak mencukupi!'], 400);
        }

        return response()->json([
            'senarai_aset' => $senaraiAset
        ]);
    }

    /**
     * Store loan approval and assign PIC.
     */
    public function storePeminjaman(Request $request, $id_tiket)
    {
        $request->validate([
            'id_aset'                  => 'required|string',
            'kuantiti_lulus'           => 'required|integer|min:1',
            'pic_ic'                   => ['required', 'string', Rule::exists('pengguna', 'no_ic')->where(fn ($query) => $query->where('peranan', 'juruteknik'))],
            'senarai_aset'             => 'required|array|min:1',
            'senarai_aset.*.serial_no' => 'required|string|distinct',
        ]);

        if (count($request->senarai_aset) !== (int) $request->kuantiti_lulus) {
            return back()->withErrors([
                'senarai_aset' => 'Bilangan aset yang dipilih tidak sepadan dengan kuantiti yang diluluskan.'
            ]);
        }

        DB::beginTransaction();
        try {
            $ticket = DB::table('tiket')
                ->where('id_tiket', $id_tiket)
                ->lockForUpdate()
                ->first();

            if (!$ticket) {
                DB::rollBack();
                return back()->withErrors(['sistem' => 'Tiket peminjaman tidak dijumpai.']);
            }

            $userSemasa = Auth::user();
            $perananSemasa = strtolower(trim($userSemasa->peranan ?? ''));

            $perananKUPP = [
                'ketua_upp',
                'ketua upp',
                'kupp',
            ];

            if (!in_array($perananSemasa, $perananKUPP, true)) {
                abort(403, 'Hanya Ketua UPP dibenarkan meluluskan permohonan peminjaman peralatan ICT.');
            }

            $adakahPeminjaman = $ticket->kategori === 'Meja Bantuan'
                && DB::table('meja_bantuan')
                    ->where('id_tiket', $id_tiket)
                    ->where('sub_kategori', 'Peminjaman Peralatan ICT')
                    ->exists();

            if (!$adakahPeminjaman) {
                abort(403, 'Tindakan ini hanya sah untuk tiket Meja Bantuan - Peminjaman Peralatan ICT.');
            }

            if ($ticket->status_tiket !== 'Menunggu Kelulusan') {
                abort(403, 'Tiket peminjaman tidak berada pada status Menunggu Kelulusan.');
            }

            $senaraiSiriAset = collect($request->senarai_aset)
                ->pluck('serial_no')
                ->values()
                ->toArray();

            $asetDipilih = DB::table('aset')
                ->whereIn('serial_no', $senaraiSiriAset)
                ->lockForUpdate()
                ->get();

            if ($asetDipilih->count() !== count($senaraiSiriAset)) {
                DB::rollBack();
                return back()->withErrors([
                    'senarai_aset' => 'Satu atau lebih aset yang dipilih tidak wujud dalam rekod inventori.'
                ]);
            }

            if ($asetDipilih->contains(fn ($aset) => $aset->nama_aset !== $request->id_aset)) {
                DB::rollBack();
                return back()->withErrors([
                    'senarai_aset' => 'Satu atau lebih aset yang dipilih tidak sepadan dengan kategori aset yang diluluskan.'
                ]);
            }

            if ($asetDipilih->contains(fn ($aset) => $aset->status !== 'Tersedia')) {
                DB::rollBack();
                return back()->withErrors([
                    'senarai_aset' => 'Satu atau lebih aset yang dipilih tidak lagi tersedia untuk dipinjam.'
                ]);
            }

            $senaraiAsetDisahkan = $asetDipilih->map(fn ($aset) => [
                'serial_no' => $aset->serial_no,
                'nama_aset' => $aset->nama_aset,
                'model' => $aset->model,
            ])->values()->all();

            DB::table('tiket')->where('id_tiket', $id_tiket)->update([
                'status_tiket'      => 'Dalam Tindakan Pegawai',
                'updated_at'        => now()
            ]);

            $rekodPeminjaman = [
                'nama_aset'    => $request->id_aset,
                'kuantiti'     => $request->kuantiti_lulus,
                'senarai_siri' => $senaraiAsetDisahkan,
                'tarikh_lulus' => now()->toDateTimeString()
            ];

            DB::table('laporan')->updateOrInsert(
                ['id_tiket' => $id_tiket],
                [
                    'kos_items'    => json_encode($rekodPeminjaman),
                    'disemak_oleh' => Auth::user()->nama,
                    'pengguna_ic'  => $ticket->pengguna_ic ?? $ticket->no_ic_pemohon ?? Auth::user()->no_ic,
                    'updated_at'   => now()
                ]
            );

            DB::table('tugasan_tiket')->updateOrInsert(
                ['id_tiket' => $id_tiket],
                [
                    'no_ic'      => $request->pic_ic,
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );

            $jumlahAsetDikemasKini = DB::table('aset')
                ->whereIn('serial_no', $senaraiSiriAset)
                ->where('status', 'Tersedia')
                ->update([
                    'status' => 'Dipinjam'
                ]);

            if ($jumlahAsetDikemasKini !== (int) $request->kuantiti_lulus) {
                DB::rollBack();
                return back()->withErrors([
                    'senarai_aset' => 'Kelulusan dibatalkan kerana status inventori aset telah berubah. Sila jana semula senarai aset.'
                ]);
            }

            DB::table('jejak_tiket')->insert([
                'id_tiket'       => $id_tiket,
                'nama_pelaku'    => Auth::user()->nama,
                'peranan_pelaku' => Auth::user()->peranan,
                'aktiviti'       => 'Disokong',
                'pesanan'        => 'Oleh ' . Auth::user()->nama,
                'status_badge'   => 'LULUS',
                'created_at'     => now(),
                'updated_at'     => now()
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ralat Peminjaman: ' . $e->getMessage());
            return back()->withErrors([
                'sistem' => 'Kelulusan peminjaman tidak dapat diproses kerana berlaku ralat sistem. Sila cuba semula.'
            ]);
        }

        try {
            $this->clearTicketNotifications($id_tiket);

            $picUser = \App\Models\Pengguna::where('no_ic', $request->pic_ic)->first();

            if ($picUser) {
                $picUser->notify(new \App\Notifications\TugasanPicNoti(
                    $ticket,
                    $request->id_aset,
                    Auth::user()->nama
                ));
            }
        } catch (\Exception $e) {
            Log::error('Notifikasi Peminjaman Gagal: ' . $e->getMessage());
        }

        return back()->with('success', 'Kelulusan peminjaman berjaya dihantar kepada pegawai!');
    }

    /**
     * Save loan item delivery details.
     */
    public function simpanBorangPeminjaman(Request $request, $id_tiket)
    {
        $request->validate([
            'serial_no'         => 'required|string',
            'no_harta'          => 'nullable|string',
            'status_perkakasan' => 'required|string',
            'mod_penggunaan'    => 'required|string',
            'jawatan_penerima'  => 'required|string',
            'catatan'           => 'nullable|string',
        ]);

        $adakahPIC = DB::table('tugasan_tiket')
            ->where('id_tiket', $id_tiket)
            ->where('no_ic', Auth::user()->no_ic)
            ->exists();

        if (!$adakahPIC) {
            abort(403, 'Hanya PIC yang ditugaskan boleh mengemaskini borang peminjaman ini.');
        }

        $ticket = DB::table('tiket')
            ->where('id_tiket', $id_tiket)
            ->first();

        if (!$ticket) {
            abort(404, 'Tiket peminjaman tidak dijumpai.');
        }

        $adakahPeminjaman = $ticket->kategori === 'Meja Bantuan'
            && DB::table('meja_bantuan')
                ->where('id_tiket', $id_tiket)
                ->where('sub_kategori', 'Peminjaman Peralatan ICT')
                ->exists();

        if (!$adakahPeminjaman) {
            abort(403, 'Tindakan ini hanya sah untuk tiket Meja Bantuan - Peminjaman Peralatan ICT.');
        }

        if ($ticket->status_tiket !== 'Dalam Tindakan Pegawai') {
            abort(403, 'Borang peminjaman hanya boleh dikemaskini semasa tiket berada dalam status Dalam Tindakan Pegawai.');
        }

        $serial_no = $request->serial_no;

        $laporan = DB::table('laporan')->where('id_tiket', $id_tiket)->first();

        if ($laporan && $laporan->kos_items) {
            $kosItems = json_decode($laporan->kos_items, true);

            $itemDitemui = false;

            if (isset($kosItems['senarai_siri'])) {
                foreach ($kosItems['senarai_siri'] as &$item) {
                    $siriSemasa = $item['serial_no'] ?? $item['no_siri'] ?? null;
                    if ($siriSemasa === $serial_no) {
                        $itemDitemui = true;
                        $item['no_pendaftaran_harta'] = $request->no_harta;
                        $item['status_perkakasan']     = $request->status_perkakasan;
                        $item['mod_penggunaan']        = $request->mod_penggunaan;
                        $item['jawatan_penerima']      = $request->jawatan_penerima;
                        $item['catatan']               = $request->catatan;
                    }
                }
            }

            if (!$itemDitemui) {
                return back()->withErrors(['sistem' => 'Nombor siri aset tidak ditemui dalam rekod peminjaman tiket ini.']);
            }

            DB::table('laporan')->where('id_tiket', $id_tiket)->update([
                'kos_items'  => json_encode($kosItems),
                'updated_at' => now()
            ]);

            $idPemulanganTerakhir = DB::table('jejak_tiket')
                ->where('id_tiket', $id_tiket)
                ->where('aktiviti', 'Tiket Dikembalikan')
                ->max('id');

            if ($idPemulanganTerakhir) {
                $sudahDikemaskini = DB::table('jejak_tiket')
                    ->where('id_tiket', $id_tiket)
                    ->where('aktiviti', 'Tiket Dikemaskini')
                    ->where('id', '>', $idPemulanganTerakhir)
                    ->exists();

                if (!$sudahDikemaskini) {
                    DB::table('jejak_tiket')->insert([
                        'id_tiket'       => $id_tiket,
                        'nama_pelaku'    => Auth::user()->nama,
                        'peranan_pelaku' => Auth::user()->peranan,
                        'aktiviti'       => 'Tiket Dikemaskini',
                        'pesanan'        => 'Oleh ' . Auth::user()->nama,
                        'status_badge'   => 'INFO',
                        'created_at'     => now(),
                        'updated_at'     => now()
                    ]);
                }
            }

            return back()->with('success', 'Maklumat borang peminjaman berjaya disimpan!');
        }

        return back()->withErrors(['sistem' => 'Rekod kelulusan tidak dijumpai.']);
    }

    /**
     * Print equipment loan acknowledgement form.
     */
    public function cetakPeminjaman(Request $request, $id_tiket, $serial_no)
    {
        $ticket = Tiket::with(['laporan'])->where('id_tiket', $id_tiket)->firstOrFail();

        $adakahPeminjaman = $ticket->kategori === 'Meja Bantuan'
            && DB::table('meja_bantuan')
                ->where('id_tiket', $id_tiket)
                ->where('sub_kategori', 'Peminjaman Peralatan ICT')
                ->exists();

        if (!$adakahPeminjaman) {
            abort(403, 'Cetakan ini hanya sah untuk tiket Meja Bantuan - Peminjaman Peralatan ICT.');
        }

        $userSemasa = Auth::user();
        $perananSemasa = strtolower(trim($userSemasa->peranan ?? ''));

        $perananPengurus = [
            'ketua_upp',
            'ketua upp',
            'kupp',
            'ketua_utd',
            'ketua utd',
            'kutd',
            'ketua_wilayah',
            'ketua wilayah',
            'kw',
        ];

        $adakahPengurus = in_array($perananSemasa, $perananPengurus, true);

        $adakahPIC = DB::table('tugasan_tiket')
            ->where('id_tiket', $id_tiket)
            ->where('no_ic', $userSemasa->no_ic)
            ->exists();

        if (!$adakahPengurus && !$adakahPIC) {
            abort(403, 'Anda tidak mempunyai kebenaran untuk melihat atau mencetak borang peminjaman ini.');
        }

        $laporan = $ticket->laporan;
        $kosItems = $laporan && $laporan->kos_items
            ? (is_string($laporan->kos_items) ? json_decode($laporan->kos_items, true) : $laporan->kos_items)
            : [];

        $senaraiSiri = is_array($kosItems['senarai_siri'] ?? null) ? $kosItems['senarai_siri'] : [];
        $serialDitemui = collect($senaraiSiri)->contains(function ($item) use ($serial_no) {
            $siri = $item['serial_no'] ?? $item['no_siri'] ?? null;
            return (string) $siri === (string) $serial_no;
        });

        if (!$serialDitemui) {
            abort(404, 'Nombor siri aset tidak ditemui dalam rekod peminjaman tiket ini.');
        }

        $picRecord = DB::table('tugasan_tiket')->where('id_tiket', $id_tiket)->first();

        $pic = null;
        if ($picRecord) {
            $pic = DB::table('pengguna')
                ->where('no_ic', $picRecord->no_ic)
                ->select('nama', 'peranan')
                ->first();
        }

        return Inertia::render('Tickets/CetakBorangPeminjaman', [
            'ticket' => $ticket,
            'pic' => $pic
        ]);
    }

    /**
     * Submit completed equipment loan form to KUTD.
     */
    public function hantarKeKUTD($id_tiket)
    {
        $ticket = DB::table('tiket')->where('id_tiket', $id_tiket)->first();

        if (!$ticket) {
            abort(404, 'Tiket tidak dijumpai.');
        }

        $adakahPeminjaman = $ticket->kategori === 'Meja Bantuan'
            && DB::table('meja_bantuan')
                ->where('id_tiket', $id_tiket)
                ->where('sub_kategori', 'Peminjaman Peralatan ICT')
                ->exists();

        if (!$adakahPeminjaman) {
            abort(403, 'Tindakan ini hanya sah untuk tiket Peminjaman Peralatan ICT.');
        }

        if ($ticket->status_tiket !== 'Dalam Tindakan Pegawai') {
            abort(403, 'Tiket tidak berada pada status Dalam Tindakan Pegawai.');
        }

        $adakahPIC = DB::table('tugasan_tiket')
            ->where('id_tiket', $id_tiket)
            ->where('no_ic', Auth::user()->no_ic)
            ->exists();

        if (!$adakahPIC) {
            abort(403, 'Hanya PIC yang ditugaskan boleh menghantar tiket untuk pengesahan.');
        }

        $laporan = DB::table('laporan')
            ->where('id_tiket', $id_tiket)
            ->first();

        if (!$laporan || !$laporan->kos_items) {
            return back()->withErrors([
                'sistem' => 'Maklumat kelulusan peminjaman tidak dijumpai.'
            ]);
        }

        $kosItems = json_decode($laporan->kos_items, true);
        $senaraiSiri = $kosItems['senarai_siri'] ?? [];

        if (!is_array($senaraiSiri) || empty($senaraiSiri)) {
            return back()->withErrors([
                'sistem' => 'Senarai aset peminjaman tidak dijumpai.'
            ]);
        }

        $borangBelumLengkap = collect($senaraiSiri)->contains(function ($item) {
            $serialNo = trim((string) ($item['serial_no'] ?? $item['no_siri'] ?? ''));
            $statusPerkakasan = trim((string) ($item['status_perkakasan'] ?? ''));
            $modPenggunaan = trim((string) ($item['mod_penggunaan'] ?? ''));
            $jawatanPenerima = trim((string) ($item['jawatan_penerima'] ?? ''));

            return $serialNo === ''
                || $statusPerkakasan === ''
                || $modPenggunaan === ''
                || $jawatanPenerima === '';
        });

        if ($borangBelumLengkap) {
            return back()->withErrors([
                'sistem' => 'Sila lengkapkan borang peminjaman bagi semua aset sebelum dihantar kepada KUTD.'
            ]);
        }

        DB::beginTransaction();
        try {
            DB::table('tiket')->where('id_tiket', $id_tiket)->update([
                'status_tiket' => 'Menunggu Pengesahan',
                'updated_at'   => now()
            ]);

            $ticket->status_tiket = 'Menunggu Pengesahan';

            $idPemulanganTerakhir = DB::table('jejak_tiket')
                ->where('id_tiket', $id_tiket)
                ->where('aktiviti', 'Tiket Dikembalikan')
                ->max('id');

            if ($idPemulanganTerakhir) {
                $sudahDikemaskini = DB::table('jejak_tiket')
                    ->where('id_tiket', $id_tiket)
                    ->where('aktiviti', 'Tiket Dikemaskini')
                    ->where('id', '>', $idPemulanganTerakhir)
                    ->exists();

                if (!$sudahDikemaskini) {
                    DB::table('jejak_tiket')->insert([
                        'id_tiket'       => $id_tiket,
                        'nama_pelaku'    => Auth::user()->nama,
                        'peranan_pelaku' => Auth::user()->peranan,
                        'aktiviti'       => 'Tiket Dikemaskini',
                        'pesanan'        => 'Oleh ' . Auth::user()->nama,
                        'status_badge'   => 'INFO',
                        'created_at'     => now(),
                        'updated_at'     => now()
                    ]);
                }
            }

            $alreadyLogged = DB::table('jejak_tiket')
                ->where('id_tiket', $id_tiket)
                ->where('aktiviti', 'Pegawai Pelaksana')
                ->exists();

            if (!$alreadyLogged) {
                DB::table('jejak_tiket')->insert([
                    'id_tiket'       => $id_tiket,
                    'nama_pelaku'    => Auth::user()->nama,
                    'peranan_pelaku' => Auth::user()->peranan,
                    'aktiviti'       => 'Pegawai Pelaksana',
                    'pesanan'        => 'Oleh ' . Auth::user()->nama,
                    'status_badge'   => 'INFO',
                    'created_at'     => now(),
                    'updated_at'     => now()
                ]);
            }

            $this->clearTicketNotifications($id_tiket);

            $senaraiPengesah = \App\Models\Pengguna::whereIn('peranan', ['ketua_utd', 'kutd', 'ketua utd', 'Ketua UTD'])
                ->whereNotNull('no_ic')
                ->where('no_ic', '!=', '')
                ->get()
                ->unique('no_ic');

            foreach ($senaraiPengesah as $pengesahUser) {
                $pengesahUser->notify(new \App\Notifications\PengesahanKetuaNoti($ticket, Auth::user()->nama, true));
            }

            DB::commit();
            return back()->with('success', 'Tiket peminjaman peralatan berjaya diserahkan untuk pengesahan!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ralat hantarKeKUTD: ' . $e->getMessage());
            return back()->withErrors(['sistem' => 'Gagal menyerahkan tiket: ' . $e->getMessage()]);
        }
    }

    /**
     * Process KUTD review decision on asset loan ticket.
     */
    public function prosesPengesahanKutd(Request $request, $id_tiket)
    {
        $request->validate([
            'tindakan' => 'required|in:pulang_pic,hantar_kw',
            'ulasan'   => 'nullable|string'
        ]);

        $userSemasa = Auth::user();
        $perananSemasa = strtolower(trim($userSemasa->peranan ?? '' ));

        $perananPengesah = [
            'ketua_utd',
            'ketua utd',
            'kutd',
        ];

        if (!in_array($perananSemasa, $perananPengesah, true)) {
            abort(403, 'Anda tidak mempunyai kebenaran untuk mengesahkan tiket peminjaman.');
        }

        $ticket = DB::table('tiket')->where('id_tiket', $id_tiket)->first();

        if (!$ticket) {
            abort(404, 'Tiket tidak dijumpai.');
        }

        $adakahPeminjaman = $ticket->kategori === 'Meja Bantuan'
            && DB::table('meja_bantuan')
                ->where('id_tiket', $id_tiket)
                ->where('sub_kategori', 'Peminjaman Peralatan ICT')
                ->exists();

        if (!$adakahPeminjaman) {
            abort(403, 'Tindakan ini hanya sah untuk tiket Peminjaman Peralatan ICT.');
        }

        if ($ticket->status_tiket !== 'Menunggu Pengesahan') {
            abort(403, 'Tiket tidak berada pada status Menunggu Pengesahan.');
        }

        $tindakan = $request->tindakan;
        $ulasan   = $request->ulasan ?? 'Tiada ulasan dinyatakan.';

        DB::beginTransaction();
        try {

            if ($tindakan === 'pulang_pic') {
                $statusBaru   = 'Dalam Tindakan Pegawai';
                $aktivitiLog  = 'Tiket Dikembalikan';
                $badgeStatus  = 'INFO';
                $pesananAudit = 'Oleh ' . Auth::user()->nama;
            } else {
                $statusBaru   = 'Menunggu Validasi';
                $aktivitiLog  = 'Disahkan';
                $badgeStatus  = 'LULUS';
                $pesananAudit = 'Oleh ' . Auth::user()->nama ;
            }

            DB::table('tiket')->where('id_tiket', $id_tiket)->update([
                'status_tiket'   => $statusBaru,
                'ulasan_semakan' => $ulasan,
                'updated_at'     => now()
            ]);

            DB::table('jejak_tiket')->insert([
                'id_tiket'       => $id_tiket,
                'nama_pelaku'    => Auth::user()->nama,
                'peranan_pelaku' => Auth::user()->peranan,
                'aktiviti'       => $aktivitiLog,
                'pesanan'        => $pesananAudit,
                'status_badge'   => $badgeStatus,
                'created_at'     => now(),
                'updated_at'     => now()
            ]);

            $this->clearTicketNotifications($id_tiket);

            if ($tindakan === 'pulang_pic') {
                $picRecord = DB::table('tugasan_tiket')
                    ->where('id_tiket', $id_tiket)
                    ->first();

                if ($picRecord && !empty($picRecord->no_ic)) {
                    $picUser = \App\Models\Pengguna::where('no_ic', $picRecord->no_ic)->first();

                    if ($picUser) {
                        $picUser->notify(new \App\Notifications\PeminjamanPembetulanNoti(
                            $ticket,
                            Auth::user()->nama,
                            $ulasan
                        ));
                    }
                }
            }

            if ($tindakan === 'hantar_kw') {
                $senaraiKw = \App\Models\Pengguna::whereIn('peranan', [
                    'ketua_wilayah',
                    'kw',
                    'ketua wilayah',
                    'Ketua Wilayah'
                ])->get();

                foreach ($senaraiKw as $kwUser) {
                    $kwUser->notify(new \App\Notifications\ValidasiKWNoti(
                        $ticket,
                        Auth::user()->nama
                    ));
                }
            }

            DB::commit();
            return back()->with('success', 'Keputusan verifikasi KUTD berjaya diproses!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ralat prosesPengesahanKutd: ' . $e->getMessage());
            return back()->withErrors(['sistem' => 'Gagal memproses verifikasi KUTD: ' . $e->getMessage()]);
        }
    }

    /**
     * Validate and finalize equipment loan ticket.
     */
    public function sahkanTutupPeminjaman(Request $request, $id_tiket)
    {
        $request->validate([
            'ulasan' => 'nullable|string'
        ]);

        $userSemasa = Auth::user();
        $perananSemasa = strtolower(trim($userSemasa->peranan ?? ''));

        $perananPengesah = [
            'ketua_wilayah',
            'ketua wilayah',
            'kw',
        ];

        if (!in_array($perananSemasa, $perananPengesah, true)) {
            abort(403, 'Hanya Ketua Wilayah dibenarkan menutup tiket peminjaman.');
        }

        $ticket = DB::table('tiket')
            ->where('id_tiket', $id_tiket)
            ->first();

        if (!$ticket) {
            abort(404, 'Tiket tidak dijumpai.');
        }

        $adakahPeminjaman = $ticket->kategori === 'Meja Bantuan'
            && DB::table('meja_bantuan')
                ->where('id_tiket', $id_tiket)
                ->where('sub_kategori', 'Peminjaman Peralatan ICT')
                ->exists();

        if (!$adakahPeminjaman) {
            abort(403, 'Tindakan ini hanya sah untuk tiket Peminjaman Peralatan ICT.');
        }

        if ($ticket->status_tiket !== 'Menunggu Validasi') {
            abort(403, 'Tiket tidak berada pada status Menunggu Validasi.');
        }


        DB::beginTransaction();

        try {
            DB::table('tiket')->where('id_tiket', $id_tiket)->update([
                'status_tiket' => 'Selesai',
                'tarikh_tutup' => now(),
                'updated_at'   => now()
            ]);

            DB::table('jejak_tiket')->insert([
                'id_tiket'       => $id_tiket,
                'nama_pelaku'    => $userSemasa->nama,
                'peranan_pelaku' => $userSemasa->peranan,
                'aktiviti'       => 'Diluluskan',
                'pesanan'        => 'Oleh ' . $userSemasa->nama,
                'status_badge'   => 'LULUS',
                'created_at'     => now(),
                'updated_at'     => now()
            ]);

            DB::table('jejak_tiket')->insert([
                'id_tiket'       => $id_tiket,
                'nama_pelaku'    => $userSemasa->nama,
                'peranan_pelaku' => $userSemasa->peranan,
                'aktiviti'       => 'Tiket Ditutup',
                'pesanan'        => 'Oleh ' . $userSemasa->nama,
                'status_badge'   => 'SELESAI',
                'created_at'     => now()->addSecond(),
                'updated_at'     => now()
            ]);

            $this->clearTicketNotifications($id_tiket);

            DB::commit();

            return back()->with('success', 'Tiket peminjaman peralatan berjaya diluluskan dan ditutup secara rasmi!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ralat sahkanTutupPeminjaman: ' . $e->getMessage());

            return back()->withErrors([
                'sistem' => 'Gagal menutup tiket peminjaman: ' . $e->getMessage()
            ]);
        }
    }
    /**
     * Store and process Network Consultation LKK report (Filled by KUTD / Reviewed by KW).
     */
    public function storeLKKRangkaian(Request $request, string $id_tiket)
    {
        $ticket = Tiket::where('id_tiket', $id_tiket)->firstOrFail();

        $tindakan         = $request->input('tindakan');
        $tindakanSah = ['KUTD_SAH_SEMAKAN', 'KW_VALIDASI_SELESAI', 'KW_PEMBETULAN', 'KUTD_PEMBETULAN'];

        if ($tindakan && !in_array($tindakan, $tindakanSah, true)) {
            abort(422, 'Tindakan LKK Rangkaian tidak sah.');
        }
        if ($ticket->kategori !== 'Konsultasi Rangkaian') {
            abort(403, 'LKK Rangkaian hanya dibenarkan untuk tiket Konsultasi Rangkaian.');
        }

        if ($tindakan === 'KUTD_SAH_SEMAKAN') {
            $perananSemasa = strtolower(trim((string) ($request->user()->peranan ?? '')));
            $statusSemasa = strtolower(trim((string) $ticket->status_tiket));

            $perananKUTD = ['ketua_utd', 'ketua utd', 'kutd'];
            $statusKUTDSah = ['menunggu pengesahan', 'menunggu pengesahan lkk', 'menunggu semakan', 'semakan kutd', 'lkk perlu pembetulan'];

            if (!in_array($perananSemasa, $perananKUTD, true)) {
                abort(403, 'Hanya KUTD dibenarkan mengesahkan LKK Rangkaian.');
            }

            if (!in_array($statusSemasa, $statusKUTDSah, true)) {
                abort(403, 'Status tiket tidak membenarkan KUTD mengesahkan LKK Rangkaian.');
            }
        }
        if (in_array($tindakan, ['KW_VALIDASI_SELESAI', 'KW_PEMBETULAN'], true)) {
            $perananSemasa = strtolower(trim((string) ($request->user()->peranan ?? '')));
            $statusSemasa = strtolower(trim((string) $ticket->status_tiket));

            $perananKW = ['ketua_wilayah', 'ketua wilayah', 'kw'];
            $statusKWSah = ['menunggu validasi', 'menunggu validasi kw'];

            if (!in_array($perananSemasa, $perananKW, true)) {
                abort(403, 'Hanya Ketua Wilayah dibenarkan membuat validasi LKK Rangkaian.');
            }

            if (!in_array($statusSemasa, $statusKWSah, true)) {
                abort(403, 'Status tiket tidak membenarkan Ketua Wilayah memproses LKK Rangkaian.');
            }
        }
        if ($tindakan === 'KUTD_PEMBETULAN') {
            $perananSemasa = strtolower(trim((string) ($request->user()->peranan ?? '')));
            $statusSemasa = strtolower(trim((string) $ticket->status_tiket));

            $perananKUTD = ['ketua_utd', 'ketua utd', 'kutd'];
            $statusKUTDPembetulan = ['menunggu pengesahan', 'menunggu pengesahan lkk'];

            if (!in_array($perananSemasa, $perananKUTD, true)) {
                abort(403, 'Hanya KUTD dibenarkan memulangkan LKK Rangkaian untuk pembetulan.');
            }

            if (!in_array($statusSemasa, $statusKUTDPembetulan, true)) {
                abort(403, 'Status tiket tidak membenarkan KUTD memulangkan LKK Rangkaian untuk pembetulan.');
            }
        }
        if ($request->boolean('is_draft') && $tindakan) {
            abort(422, 'Draf LKK Rangkaian tidak boleh mengandungi tindakan workflow.');
        }

        if ($request->boolean('is_draft') && !$tindakan) {
            $perananSemasa = strtolower(trim((string) ($request->user()->peranan ?? '')));
            $statusSemasa = strtolower(trim((string) $ticket->status_tiket));

            $perananKUTD = ['ketua_utd', 'ketua utd', 'kutd'];
            $statusDraftKUTD = ['menunggu pengesahan', 'menunggu pengesahan lkk', 'menunggu semakan', 'semakan kutd', 'lkk perlu pembetulan'];

            if (!in_array($perananSemasa, $perananKUTD, true)) {
                abort(403, 'Hanya KUTD dibenarkan menyimpan draf LKK Rangkaian.');
            }

            if (!in_array($statusSemasa, $statusDraftKUTD, true)) {
                abort(403, 'Status tiket tidak membenarkan KUTD menyimpan draf LKK Rangkaian.');
            }
        }
        if (!$tindakan && !$request->boolean('is_draft')) {
            abort(422, 'Tindakan LKK Rangkaian diperlukan.');
        }

        $isDraft          = $request->boolean('is_draft');
        $isKutdHantar     = $tindakan === 'KUTD_SAH_SEMAKAN';
        $isKwSahkan       = $tindakan === 'KW_VALIDASI_SELESAI';
        $isKwPembetulan   = $tindakan === 'KW_PEMBETULAN';
        $isKutdPembetulan = $tindakan === 'KUTD_PEMBETULAN';

        $isPengesahanSaja = $isKwSahkan || $isKwPembetulan || $isKutdPembetulan;

        if ($isKwPembetulan || $isKutdPembetulan) {
            $request->validate(['ulasan_ketua' => 'required|string']);
        } elseif ($isPengesahanSaja) {
            $request->validate(['ulasan_ketua' => 'nullable|string', 'ulasan_semakan' => 'nullable|string']);
        } else {
            $laporanSediaAda = DB::table('laporan')->where('id_tiket', $id_tiket)->first();
            $request->validate([
                'pendahuluan'             => [$isDraft ? 'nullable' : 'required', 'string'],
                'cadangan_penambahbaikan' => [$isDraft ? 'nullable' : 'required', 'array'],
                'objektif'                => [$isDraft ? 'nullable' : 'required', 'array'],
                'rumusan'                 => [$isDraft ? 'nullable' : 'required', 'string'],
                'kos_items'               => ['required', 'array'],
                'disediakan_oleh'         => ['nullable', 'string'],
                'disemak_oleh'            => ['nullable', 'string'],
                'logical_diagram'         => [($isDraft || ($laporanSediaAda && $laporanSediaAda->logical_diagram)) ? 'nullable' : 'required', 'file', 'mimes:png,jpg,jpeg,pdf', 'max:5120'],
                'physical_diagram'        => [($isDraft || ($laporanSediaAda && $laporanSediaAda->physical_diagram)) ? 'nullable' : 'required', 'file', 'mimes:png,jpg,jpeg,pdf', 'max:5120'],
            ]);
        }

        DB::beginTransaction();
        try {
            if (!$isPengesahanSaja) {
                $laporanSediaAda = DB::table('laporan')->where('id_tiket', $id_tiket)->first();
                $logicalPath = $laporanSediaAda ? $laporanSediaAda->logical_diagram : null;
                $physicalPath = $laporanSediaAda ? $laporanSediaAda->physical_diagram : null;

                if ($request->hasFile('logical_diagram')) {
                    if ($logicalPath) Storage::disk('public')->delete($logicalPath);
                    $logicalPath = $request->file('logical_diagram')->store('diagrams', 'public');
                }
                if ($request->hasFile('physical_diagram')) {
                    if ($physicalPath) Storage::disk('public')->delete($physicalPath);
                    $physicalPath = $request->file('physical_diagram')->store('diagrams', 'public');
                }

                $ulasanTeknikalAsal = DB::table('konsultasi_rangkaian')->where('id_tiket', $id_tiket)->value('ulasan_teknikal');

                Laporan::updateOrCreate(
                    ['id_tiket' => $id_tiket],
                    [
                        'pendahuluan'             => $request->input('pendahuluan', ''),
                        'ulasan_teknikal'         => $ulasanTeknikalAsal,
                        'cadangan_penambahbaikan' => $request->has('cadangan_penambahbaikan') ? json_encode($request->input('cadangan_penambahbaikan')) : null,
                        'objektif'                => $request->has('objektif') ? json_encode($request->input('objektif')) : null,
                        'kos_items'               => $request->has('kos_items') ? json_encode($request->input('kos_items')) : null,
                        'rumusan'                 => $request->input('rumusan', ''),
                        'logical_diagram'         => $logicalPath,
                        'physical_diagram'        => $physicalPath,
                        'disediakan_oleh'         => $request->input('disediakan_oleh', ''),
                        'disemak_oleh'            => $request->input('disemak_oleh', ''),
                        'pengguna_ic'             => Auth::user()->no_ic,
                    ]
                );
            }

            if ($request->filled('disediakan_oleh') && $request->input('disediakan_oleh') !== 'n/a' && !$isPengesahanSaja) {
                DB::table('laporan')->updateOrInsert(
                    ['id_tiket' => $id_tiket],
                    [
                        'disediakan_oleh' => $request->input('disediakan_oleh'),
                        'disemak_oleh'    => $request->input('disemak_oleh'),
                        'updated_at'      => now()
                    ]
                );
            }

            $ulasanInput = $request->input('ulasan_ketua') ?? $request->input('ulasan_semakan');

            if ($isDraft) {
                $statusBaru = $ticket->status_tiket;
            } elseif ($isKutdHantar) {
                $statusBaru = 'Menunggu Validasi';
                $labelAktiviti = 'Disahkan';
                $labelBadge = 'DISAHKAN';
            } elseif ($isKwSahkan) {
                $statusBaru = 'Selesai';
                $labelAktiviti = 'Divalidasi';
                $labelBadge = 'LULUS';
            } elseif ($isKwPembetulan || $isKutdPembetulan) {
                $statusBaru = 'LKK Perlu Pembetulan';
                $labelAktiviti = 'LKK Perlu Pembetulan';
                $labelBadge = 'PEMBETULAN';
            } else {
                $statusBaru = 'Menunggu Pengesahan';
                $labelAktiviti = 'Pegawai Pelaksana';
                $labelBadge = 'FASA 3';
            }

            $updateTiketData = [
                'status_tiket' => $statusBaru,
                'updated_at'   => now()
            ];

            if ($isKwSahkan) {
                $updateTiketData['tarikh_tutup'] = now();
            }

            if (($isKwPembetulan || $isKutdPembetulan) && !empty($ulasanInput)) {
                $updateTiketData['ulasan_semakan'] = $ulasanInput;
            }

            $ticket->update($updateTiketData);

            if ($isKwSahkan) {
                $ticket->rekodLog('Divalidasi', 'Oleh ' . $request->user()->nama, 'LULUS');
                $ticket->rekodLog('Tiket ditutup', 'Oleh ' . $request->user()->nama, 'SELESAI');
            } elseif (!$isDraft && !($isKwPembetulan || $isKutdPembetulan)) {
                $alreadyLogged = DB::table('jejak_tiket')
                    ->where('id_tiket', $id_tiket)
                    ->where('aktiviti', $labelAktiviti)
                    ->exists();

                if (!$alreadyLogged) {
                    $pesanan = $ulasanInput ? 'Ulasan: ' . $ulasanInput : 'Oleh ' . $request->user()->nama;
                    $ticket->rekodLog($labelAktiviti, $pesanan, $labelBadge);
                }
            }

            $this->clearTicketNotifications($id_tiket);

            $hantarNotiTanpaBertindih = function($targetUsersCollection, $notificationInstance) use ($id_tiket) {
                $penerimaSah = $targetUsersCollection->filter(function($u) use ($id_tiket) {
                    $userKey = $u->no_ic ?? $u->id;
                    $terimaBaruSahaja = DB::table('notifications')
                        ->where('notifiable_id', $userKey)
                        ->where('data', 'LIKE', "%{$id_tiket}%")
                        ->where('created_at', '>=', now()->subSeconds(15))
                        ->exists();
                    return !$terimaBaruSahaja;
                });

                if ($penerimaSah->isNotEmpty()) {
                    Notification::send($penerimaSah, $notificationInstance);
                }
            };

            if ($isKutdHantar) {
                $paraKW = \App\Models\Pengguna::whereIn('peranan', ['ketua_wilayah', 'kw', 'ketua wilayah', 'Ketua Wilayah'])->get();
                if ($paraKW->isNotEmpty()) {
                    $hantarNotiTanpaBertindih($paraKW, new ValidasiKWNoti($ticket, Auth::user()->nama));
                }
            } elseif ($isKwPembetulan || $isKutdPembetulan) {
                $petugasList = $ticket->petugas;
                if ($petugasList->isNotEmpty()) {
                    $hantarNotiTanpaBertindih($petugasList, new LKKPembetulanNoti($ticket, Auth::user()->nama));
                }
            }

            DB::commit();
            return back()->with('success', 'Laporan LKK Rangkaian berjaya diproses!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ralat storeLKKRangkaian: ' . $e->getMessage());
            return back()->withErrors(['sistem' => 'Gagal menyimpan laporan LKK Rangkaian: ' . $e->getMessage()]);
        }
    }


    /**
     * Store and process Digital Transformation LKK report workflow.
     */
    public function storeLKKTransformasiDigital(Request $request, $id_tiket)
    {
        $ticket = \App\Models\Tiket::with('transformasiDigital')->where('id_tiket', $id_tiket)->first();

        if (!$ticket) return back()->withErrors(['sistem' => 'Tiket tidak dijumpai.']);
        $kategoriSemasa = strtolower(trim((string) $ticket->kategori));

        $isPeminjaman = $kategoriSemasa === 'meja bantuan' && DB::table('meja_bantuan')
            ->where('id_tiket', $id_tiket)
            ->where('sub_kategori', 'Peminjaman Peralatan ICT')
            ->exists();

        if ($kategoriSemasa !== 'transformasi digital' && !$isPeminjaman) {
            abort(403, 'Tindakan LKK tidak dibenarkan untuk kategori tiket ini.');
        }

        $subKategori = $isPeminjaman
            ? 'Peminjaman Peralatan ICT'
            : ($ticket->transformasiDigital?->sub_kategori
                ?? $ticket->transformasi_digital?->sub_kategori
                ?? '');

        $isPembekalan = str_contains(strtolower($subKategori), 'pembekalan');

        $tindakan = $request->input('tindakan', $request->query('tindakan'));
        $hasTindakan = !empty($tindakan);

        $tindakanSah = [
            'HANTAR_KE_KUTD',
            'AGIH_KE_PIC',
            'PIC_HANTAR_SEMAKAN',
            'SAHKAN_PEMINJAMAN_SELESAI',
            'KUTD_SAH_SEMAKAN',
            'KUPP_HANTAR_VALIDASI',
            'KW_PEMBETULAN',
            'KUTD_PEMBETULAN',
            'KW_VALIDASI_SELESAI',
        ];

        if ($hasTindakan && !in_array($tindakan, $tindakanSah, true)) {
            abort(422, 'Tindakan LKK tidak sah.');
        }
        if ($tindakan === 'HANTAR_KE_KUTD') {
            $userSemasa = $request->user();
            $perananSemasa = strtolower(trim((string) ($userSemasa->peranan ?? '')));
            $statusSemasa = strtolower(trim((string) $ticket->status_tiket));

            $perananKupp = ['ketua_upp', 'ketua upp', 'kupp'];
            $statusFasa1 = ['tugasan upp'];

            if (!in_array($perananSemasa, $perananKupp, true)) {
                abort(403, 'Hanya KUPP dibenarkan menghantar LKK kepada KUTD.');
            }

            if (!in_array($statusSemasa, $statusFasa1, true)) {
                abort(403, 'Status tiket tidak membenarkan KUPP menghantar LKK kepada KUTD.');
            }
        }


        if ($tindakan === 'SAHKAN_PEMINJAMAN_SELESAI') {
            $userSemasa = $request->user();
            $perananSemasa = strtolower(trim((string) ($userSemasa->peranan ?? '')));
            $statusSemasa = strtolower(trim((string) $ticket->status_tiket));

            $perananPengesah = [
                'ketua_upp', 'ketua upp', 'kupp',
                'ketua_utd', 'ketua utd', 'kutd',
                'ketua_wilayah', 'ketua wilayah', 'kw',
            ];

            $statusPeminjamanSah = [
                'menunggu pengesahan',
                'menunggu pengesahan lkk',
                'menunggu semakan',
                'semakan kutd',
                'menunggu validasi',
                'validasi kw',
                'menunggu validasi kw',
            ];

            if (!$isPeminjaman) {
                abort(403, 'Tindakan ini hanya dibenarkan untuk Peminjaman Peralatan ICT.');
            }

            if (!in_array($perananSemasa, $perananPengesah, true)) {
                abort(403, 'Anda tidak dibenarkan mengesahkan penutupan tiket peminjaman ini.');
            }

            if (!in_array($statusSemasa, $statusPeminjamanSah, true)) {
                abort(403, 'Status tiket tidak membenarkan pengesahan penutupan peminjaman.');
            }
        }

        if ($tindakan === 'KUTD_PEMBETULAN') {
            $userSemasa = $request->user();
            $perananSemasa = strtolower(trim((string) ($userSemasa->peranan ?? '')));
            $statusSemasa = strtolower(trim((string) $ticket->status_tiket));

            $perananKupp = ['ketua_upp', 'ketua upp', 'kupp'];
            $perananKutd = ['ketua_utd', 'ketua utd', 'kutd'];
            $perananKw = ['ketua_wilayah', 'ketua wilayah', 'kw'];

            $statusPeminjamanSah = [
                'menunggu pengesahan',
                'menunggu pengesahan lkk',
                'menunggu semakan',
                'semakan kutd',
                'menunggu validasi',
                'validasi kw',
                'menunggu validasi kw',
            ];

            $statusSemakanKutd = [
                'menunggu semakan',
                'semakan kutd',
                'lkk perlu pembetulan',
            ];

            $statusSemakanKupp = [
                'menunggu pengesahan',
                'menunggu pengesahan lkk',
            ];

            if ($isPeminjaman) {
                $perananPeminjamanSah = array_merge($perananKupp, $perananKutd, $perananKw);

                if (!in_array($perananSemasa, $perananPeminjamanSah, true)) {
                    abort(403, 'Anda tidak dibenarkan mengembalikan LKK peminjaman untuk pembetulan.');
                }

                if (!in_array($statusSemasa, $statusPeminjamanSah, true)) {
                    abort(403, 'Status tiket peminjaman tidak membenarkan LKK dikembalikan untuk pembetulan.');
                }
            } elseif (in_array($statusSemasa, $statusSemakanKutd, true)) {
                if (!in_array($perananSemasa, $perananKutd, true)) {
                    abort(403, 'Hanya KUTD dibenarkan mengembalikan LKK pada peringkat semakan teknikal.');
                }
            } elseif (in_array($statusSemasa, $statusSemakanKupp, true)) {
                if (!in_array($perananSemasa, $perananKupp, true)) {
                    abort(403, 'Hanya KUPP dibenarkan mengembalikan LKK pada peringkat pengesahan.');
                }
            } else {
                abort(403, 'Status tiket tidak membenarkan LKK dikembalikan untuk pembetulan.');
            }
        }

        if ($tindakan === 'AGIH_KE_PIC') {
            $userSemasa = $request->user();
            $perananSemasa = strtolower(trim((string) ($userSemasa->peranan ?? '')));
            $statusSemasa = strtolower(trim((string) $ticket->status_tiket));

            $perananKutd = ['ketua_utd', 'ketua utd', 'kutd'];

            $statusAgihanKutd = [
                'tugasan utd',
                'tindakan kutd (agihan)',
            ];

            if (!in_array($perananSemasa, $perananKutd, true)) {
                abort(403, 'Hanya KUTD dibenarkan mengagihkan tugasan kepada PIC.');
            }

            if (!in_array($statusSemasa, $statusAgihanKutd, true)) {
                abort(403, 'Status tiket tidak membenarkan KUTD mengagihkan tugasan kepada PIC.');
            }

            $picInput = $request->input('pic_ic', []);
            $picInput = is_array($picInput) ? $picInput : [$picInput];

            $picInput = array_values(array_filter(
                array_map(fn($ic) => trim((string) $ic), $picInput),
                fn($ic) => $ic !== ''
            ));

            if (empty($picInput)) {
                abort(422, 'Sekurang-kurangnya seorang PIC mesti dipilih.');
            }

            if (count($picInput) !== count(array_unique($picInput))) {
                abort(422, 'PIC yang sama tidak boleh dipilih lebih daripada sekali.');
            }

            $pegawaiPIC = Pengguna::whereIn('no_ic', $picInput)
                ->whereNotNull('no_ic')
                ->where('no_ic', '!=', '')
                ->get();

            if ($pegawaiPIC->count() !== count($picInput)) {
                abort(422, 'Terdapat PIC yang tidak sah atau tidak wujud dalam sistem.');
            }

            $perananPICDibenarkan = [
                'pic',
                'juruteknik',
            ];

            $picTidakSah = $pegawaiPIC->first(function ($pegawai) use ($perananPICDibenarkan) {
                $peranan = strtolower(trim((string) ($pegawai->peranan ?? '')));
                return !in_array($peranan, $perananPICDibenarkan, true);
            });

            if ($picTidakSah) {
                abort(403, 'Hanya pegawai berperanan PIC atau Juruteknik boleh ditugaskan.');
            }
            $request->merge(['pic_ic' => $picInput]);
        }

        if ($tindakan === 'PIC_HANTAR_SEMAKAN') {
            $userSemasa = $request->user();
            $statusSemasa = strtolower(trim((string) $ticket->status_tiket));


            $statusBolehHantarPic = [
                'dalam tindakan pegawai',
                'tindakan pic',
                'lkk perlu pembetulan',
            ];

            $adakahPIC = DB::table('tugasan_tiket')
                ->where('id_tiket', $id_tiket)
                ->where('no_ic', $userSemasa->no_ic)
                ->exists();


            if (!$adakahPIC) {
                abort(403, 'Anda bukan PIC yang ditugaskan untuk tiket ini.');
            }

            if (!in_array($statusSemasa, $statusBolehHantarPic, true)) {
                abort(403, 'Status tiket tidak membenarkan PIC menghantar laporan teknikal untuk semakan.');
            }
        }

        if ($tindakan === 'KUTD_SAH_SEMAKAN') {
            $userSemasa = $request->user();
            $perananSemasa = strtolower(trim((string) ($userSemasa->peranan ?? '')));
            $statusSemasa = strtolower(trim((string) $ticket->status_tiket));

            $perananKutd = ['ketua_utd', 'ketua utd', 'kutd'];
            $statusSemakanKutd = [
                'menunggu semakan',
                'semakan kutd',
                'lkk perlu pembetulan',
            ];

            if (!in_array($perananSemasa, $perananKutd, true)) {
                abort(403, 'Hanya KUTD dibenarkan mengesahkan semakan LKK.');
            }

            if (!in_array($statusSemasa, $statusSemakanKutd, true)) {
                abort(403, 'Status tiket tidak membenarkan pengesahan semakan LKK oleh KUTD.');
            }
        }

        if ($tindakan === 'KUPP_HANTAR_VALIDASI') {
            $userSemasa = $request->user();
            $perananSemasa = strtolower(trim((string) ($userSemasa->peranan ?? '')));
            $statusSemasa = strtolower(trim((string) $ticket->status_tiket));

            $perananKupp = ['ketua_upp', 'ketua upp', 'kupp'];
            $statusPengesahanKupp = ['menunggu pengesahan', 'menunggu pengesahan lkk'];

            if (!in_array($perananSemasa, $perananKupp, true)) {
                abort(403, 'Hanya KUPP dibenarkan mengesahkan LKK untuk dihantar kepada Ketua Wilayah.');
            }

            if (!in_array($statusSemasa, $statusPengesahanKupp, true)) {
                abort(403, 'Status tiket tidak membenarkan pengesahan LKK oleh KUPP.');
            }
        }

        if ($tindakan === 'KW_PEMBETULAN') {
            $userSemasa = $request->user();
            $perananSemasa = strtolower(trim((string) ($userSemasa->peranan ?? '')));
            $statusSemasa = strtolower(trim((string) $ticket->status_tiket));

            $perananKw = ['ketua_wilayah', 'ketua wilayah', 'kw'];
            $statusPembetulanKw = ['menunggu validasi', 'menunggu validasi kw'];

            if ($isPeminjaman) {
                abort(403, 'Tindakan pembetulan KW ini tidak dibenarkan untuk LKK peminjaman.');
            }

            if (!in_array($perananSemasa, $perananKw, true)) {
                abort(403, 'Hanya Ketua Wilayah dibenarkan mengembalikan LKK untuk pembetulan.');
            }

            if (!in_array($statusSemasa, $statusPembetulanKw, true)) {
                abort(403, 'Status tiket tidak membenarkan LKK dikembalikan untuk pembetulan.');
            }
        }

        if ($tindakan === 'KW_VALIDASI_SELESAI') {
            $userSemasa = $request->user();
            $perananSemasa = strtolower(trim((string) ($userSemasa->peranan ?? '')));
            $statusSemasa = strtolower(trim((string) $ticket->status_tiket));

            $perananKw = ['ketua_wilayah', 'ketua wilayah', 'kw'];
            $statusValidasiKw = ['menunggu validasi', 'validasi kw', 'menunggu validasi kw'];

            if (!in_array($perananSemasa, $perananKw, true)) {
                abort(403, 'Hanya Ketua Wilayah dibenarkan membuat validasi akhir LKK.');
            }

            if (!in_array($statusSemasa, $statusValidasiKw, true)) {
                abort(403, 'Status tiket tidak membenarkan validasi akhir LKK.');
            }
        }

        $isDraft = filter_var($request->input('is_draft', $request->query('is_draft')), FILTER_VALIDATE_BOOLEAN);
        $isKuppSahkan     = $tindakan === 'KUPP_HANTAR_VALIDASI';
        $isKutdHantar     = $tindakan === 'KUTD_SAH_SEMAKAN';
        $isKwSahkan       = $tindakan === 'KW_VALIDASI_SELESAI';
        $isKwPembetulan   = $tindakan === 'KW_PEMBETULAN';
        $isKutdPembetulan = $tindakan === 'KUTD_PEMBETULAN';

        $isPengesahanSaja = $isKwSahkan || $isKwPembetulan || $isKutdPembetulan;

        $rules = [
            'gambar_tapak' => ['nullable', 'array'],
            'gambar_tapak.*' => ['file', 'image', 'max:5120'],
            'gambar_cadangan' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];

        if ($isPengesahanSaja) {
            $rules = array_merge($rules, [
                'ulasan_ketua' => ['nullable', 'string'],
                'ulasan_semakan' => ['nullable', 'string']
            ]);
        } elseif ($isDraft || $hasTindakan) {
            $rules = array_merge($rules, [
                'pendahuluan' => ['nullable'], 'objektif' => ['nullable'], 'skop_kajian' => ['nullable'],
                'keadaan_semasa' => ['nullable'], 'hasil_kajian' => ['nullable'], 'cadangan_penambahbaikan' => ['nullable'],
                'kos_items' => ['nullable'], 'rumusan' => ['nullable', 'string'], 'disediakan_oleh' => ['nullable', 'string'], 'disemak_oleh' => ['nullable', 'string'],
            ]);
        } else {
            $rules = array_merge($rules, [
                'pendahuluan'             => ['required'],
                'kos_items'               => ['required'],
                'disediakan_oleh'         => ['nullable', 'string'],
                'disemak_oleh'            => ['nullable', 'string'],
                'cadangan_penambahbaikan' => ['nullable'],
            ]);

            if ($isPembekalan) {
                $rules['hasil_kajian'] = ['required'];
                $rules['rumusan'] = ['nullable', 'string'];
            } else {
                $rules['objektif'] = ['required'];
                $rules['skop_kajian'] = ['required'];
                $rules['keadaan_semasa'] = ['required'];
                $rules['rumusan'] = ['required', 'string'];
            }
        }

        if ($tindakan === 'AGIH_KE_PIC') {
            $rules['tarikh_lawatan'] = ['required', 'date'];
            $rules['masa_lawatan'] = ['required', 'date_format:H:i'];
        }

        $validated = $request->validate($rules);

        DB::beginTransaction();
        try {
            if (!$isPengesahanSaja) {
                $laporanLama = DB::table('laporan')->where('id_tiket', $id_tiket)->first();

                $gambarTapakPaths = [];
                if ($laporanLama && !empty($laporanLama->gambar_tapak)) {
                    $decoded = json_decode($laporanLama->gambar_tapak, true);
                    $gambarTapakPaths = is_array($decoded) ? $decoded : [$laporanLama->gambar_tapak];
                }

                if ($request->hasFile('gambar_tapak')) {
                    $files = $request->file('gambar_tapak');
                    $filesArray = is_array($files) ? $files : [$files];

                    foreach ($gambarTapakPaths as $oldPath) {
                        $cleanPath = is_string($oldPath) ? trim($oldPath, '"\'\\') : '';
                        if ($cleanPath) Storage::disk('public')->delete($cleanPath);
                    }
                    $gambarTapakPaths = [];

                    foreach ($filesArray as $fileItem) {
                        if ($fileItem && $fileItem->isValid()) {
                            $gambarTapakPaths[] = $fileItem->store('diagrams', 'public');
                        }
                    }
                }

                $gambarCadanganPaths = [];
                if ($laporanLama && !empty($laporanLama->gambar_cadangan)) {
                    $decoded = json_decode($laporanLama->gambar_cadangan, true);
                    $gambarCadanganPaths = is_array($decoded) ? $decoded : [$laporanLama->gambar_cadangan];
                }

                if ($request->hasFile('gambar_cadangan')) {
                    $files = $request->file('gambar_cadangan');
                    $filesArray = is_array($files) ? $files : [$files];

                    foreach ($gambarCadanganPaths as $oldPath) {
                        $cleanPath = is_string($oldPath) ? trim($oldPath, '"\'\\') : '';
                        if ($cleanPath) Storage::disk('public')->delete($cleanPath);
                    }
                    $gambarCadanganPaths = [];

                    foreach ($filesArray as $fileItem) {
                        if ($fileItem && $fileItem->isValid()) {
                            $gambarCadanganPaths[] = $fileItem->store('diagrams', 'public');
                        }
                    }
                }

                $getData = function($field, $isJson = false) use ($request, $laporanLama) {
                    $val = $request->input($field);
                    if ($val !== null) {
                        return $isJson ? (is_array($val) ? json_encode($val) : $val) : $val;
                    }
                    return $laporanLama ? $laporanLama->{$field} : null;
                };

                $pendahuluanData = $request->input('pendahuluan');
                if ($isKuppSahkan && is_array($pendahuluanData)) {
                    $pendahuluanData['kupp_sah'] = true;
                }
                $finalPendahuluan = is_array($pendahuluanData) ? json_encode($pendahuluanData) : ($pendahuluanData ?? ($laporanLama->pendahuluan ?? null));

                DB::table('laporan')->updateOrInsert(
                    ['id_tiket' => $id_tiket],
                    [
                        'pengguna_ic'             => $request->user()->no_ic,
                        'pendahuluan'             => $finalPendahuluan,
                        'objektif'                => $getData('objektif', true),
                        'skop_kajian'             => $getData('skop_kajian', true),
                        'keadaan_semasa'          => $getData('keadaan_semasa', true),
                        'hasil_kajian'            => $getData('hasil_kajian', true),
                        'kos_items'               => $getData('kos_items', true),
                        'cadangan_penambahbaikan' => $getData('cadangan_penambahbaikan', true),
                        'rumusan'                 => $getData('rumusan'),
                        'disediakan_oleh'         => $getData('disediakan_oleh'),
                        'disemak_oleh'            => $getData('disemak_oleh'),
                        'gambar_tapak'            => !empty($gambarTapakPaths) ? json_encode(array_values($gambarTapakPaths)) : ($laporanLama->gambar_tapak ?? null),
                        'gambar_cadangan'         => !empty($gambarCadanganPaths) ? json_encode(array_values($gambarCadanganPaths)) : ($laporanLama->gambar_cadangan ?? null),
                        'updated_at'              => now()
                    ]
                );
            }

            if ($request->filled('disediakan_oleh') && $request->input('disediakan_oleh') !== 'n/a') {
                DB::table('laporan')->updateOrInsert(
                    ['id_tiket' => $id_tiket],
                    [
                        'disediakan_oleh' => $request->input('disediakan_oleh'),
                        'disemak_oleh'    => $request->input('disemak_oleh'),
                        'updated_at'      => now()
                    ]
                );
            }

            $ulasanInput = $request->input('ulasan_ketua') ?? $request->input('ulasan_semakan');

            if ($isDraft) {
                $statusBaru = $ticket->status_tiket;
            }
            elseif ($hasTindakan) {
                if ($tindakan === 'HANTAR_KE_KUTD') {
                    $statusBaru = 'Tugasan UTD';
                    $ticket->rekodLog('Dihantar ke UTD', 'Oleh ' . $request->user()->nama, 'FASA 1');

                } elseif ($tindakan === 'AGIH_KE_PIC') {
                    $statusBaru = 'Dalam Tindakan Pegawai';
                    $ticket->rekodLog('Dijadual', 'Oleh ' . $request->user()->nama, 'FASA 2');

                    DB::table('transformasi_digital')->where('id_tiket', $id_tiket)->update([
                        'tarikh_lawatan' => $request->input('tarikh_lawatan'),
                        'masa_lawatan'   => $request->input('masa_lawatan'),
                        'updated_at'     => now()
                    ]);

                    if ($request->filled('pic_ic')) {
                        $pics = (array) $request->input('pic_ic');
                        $picsFiltered = array_filter($pics, fn($ic) => !empty($ic));

                        DB::table('tugasan_tiket')->where('id_tiket', $id_tiket)->delete();

                        foreach ($picsFiltered as $ic) {
                            DB::table('tugasan_tiket')->insert([
                                'id_tiket'   => $id_tiket,
                                'no_ic'      => $ic,
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                        }
                    }
                } elseif ($tindakan === 'PIC_HANTAR_SEMAKAN') {
                    $isHandedOverToUTD = DB::table('jejak_tiket')
                        ->where('id_tiket', $id_tiket)
                        ->where('aktiviti', 'LIKE', '%Dihantar ke UTD%')
                        ->exists();

                    if ($isPeminjaman) {
                        $statusBaru = 'Menunggu Pengesahan';
                        $alreadyLogged = DB::table('jejak_tiket')
                            ->where('id_tiket', $id_tiket)
                            ->where('aktiviti', 'Dihantar untuk Pengesahan')
                            ->exists();
                        if (!$alreadyLogged) {
                            $ticket->rekodLog('Dihantar untuk Pengesahan', 'Oleh ' . $request->user()->nama, 'FASA 3');
                        }
                    } else {
                        $statusBaru = $isHandedOverToUTD ? 'Menunggu Semakan' : 'Menunggu Pengesahan';
                        $alreadyLogged = DB::table('jejak_tiket')
                            ->where('id_tiket', $id_tiket)
                            ->where('aktiviti', 'Pegawai Pelaksana')
                            ->exists();
                        if (!$alreadyLogged) {
                            $ticket->rekodLog('Pegawai Pelaksana', 'Oleh ' . $request->user()->nama, 'FASA 3');
                        }
                    }

                } elseif ($tindakan === 'SAHKAN_PEMINJAMAN_SELESAI') {
                    $statusBaru = 'Selesai';
                    $ticket->rekodLog('Disahkan & Ditutup', 'Oleh ' . $request->user()->nama, 'SELESAI');
                    $ticket->rekodLog('Tiket Ditutup', 'Oleh ' . $request->user()->nama, 'SELESAI');

                } elseif ($tindakan === 'KUTD_SAH_SEMAKAN') {
                    if ($isPembekalan) {
                        $statusBaru = 'Menunggu Validasi';
                        $ticket->rekodLog('Disemak', 'Oleh ' . $request->user()->nama, 'FASA 4');
                    } else {
                        $statusBaru = 'Menunggu Pengesahan';
                        $ticket->rekodLog('Disemak', 'Oleh ' . $request->user()->nama, 'FASA 4');
                        $ticket->rekodLog('Dihantar ke UPP', 'Oleh ' . $request->user()->nama, 'FASA 4');
                    }

                } elseif ($tindakan === 'KUPP_HANTAR_VALIDASI') {
                    $statusBaru = 'Menunggu Validasi';
                    $ticket->rekodLog('Disahkan', 'Oleh ' . $request->user()->nama, 'FASA 5');

                } elseif (in_array($tindakan, ['KW_PEMBETULAN', 'KUTD_PEMBETULAN'])) {
                    $statusBaru = 'LKK Perlu Pembetulan';

                } elseif ($tindakan === 'KW_VALIDASI_SELESAI') {
                    $statusBaru = 'Selesai';
                    $ticket->rekodLog('Divalidasi', 'Oleh ' . $request->user()->nama, 'LULUS');
                    $ticket->rekodLog('Tiket Ditutup', 'Oleh ' . $request->user()->nama, 'SELESAI');

                } else {
                    $statusBaru = $ticket->status_tiket;
                }
            } else {
                $statusBaru = $ticket->status_tiket;
            }

            $updateTiketData = [
                'status_tiket' => $statusBaru,
                'updated_at'   => now()
            ];

            if ($statusBaru === 'Selesai') {
                $updateTiketData['tarikh_tutup'] = now();
            }

            if (in_array($tindakan, ['KW_PEMBETULAN', 'KUTD_PEMBETULAN']) && !empty($ulasanInput)) {
                $updateTiketData['ulasan_semakan'] = $ulasanInput;
            }

            $ticket->update($updateTiketData);

            if ($hasTindakan && !$isDraft) $this->clearTicketNotifications($id_tiket);

            if ($hasTindakan && !$isDraft) {
                $hantarNotiTanpaBertindih = function($targetUsersCollection, $notificationInstance) use ($id_tiket) {
                    $penerimaSah = $targetUsersCollection->filter(function($u) use ($id_tiket) {
                        $userKey = $u->no_ic ?? $u->id;
                        $terimaBaruSahaja = DB::table('notifications')
                            ->where('notifiable_id', $userKey)
                            ->where('data', 'LIKE', "%{$id_tiket}%")
                            ->where('created_at', '>=', now()->subSeconds(15))
                            ->exists();
                        return !$terimaBaruSahaja;
                    });

                    if ($penerimaSah->isNotEmpty()) {
                        Notification::send($penerimaSah, $notificationInstance);
                    }
                };

                if ($tindakan === 'HANTAR_KE_KUTD') {
                    $senaraiKUTD = Pengguna::whereIn('peranan', ['ketua_utd', 'kutd', 'ketua utd', 'Ketua UTD'])
                        ->whereNotNull('no_ic')
                        ->where('no_ic', '!=', '')
                        ->get()
                        ->unique('no_ic');

                    if ($senaraiKUTD->isNotEmpty()) {
                        $hantarNotiTanpaBertindih($senaraiKUTD, new NewTicketNoti($ticket, 'tugasan_utd'));
                    }
                }
                elseif ($tindakan === 'AGIH_KE_PIC') {
                    if ($request->filled('pic_ic')) {
                        $pics = (array) $request->input('pic_ic');
                        $picsFiltered = array_filter($pics, fn($ic) => !empty($ic));
                        $pegawaiTugasan = Pengguna::whereIn('no_ic', $picsFiltered)
                            ->whereNotNull('no_ic')
                            ->where('no_ic', '!=', '')
                            ->get()
                            ->unique('no_ic');

                        if ($pegawaiTugasan->isNotEmpty()) {
                            $hantarNotiTanpaBertindih($pegawaiTugasan, new NewTicketNoti($ticket, 'tindakan_pic'));
                        }
                    }
                }
                elseif ($tindakan === 'PIC_HANTAR_SEMAKAN') {
                    $isHandedOverToUTD = DB::table('jejak_tiket')
                        ->where('id_tiket', $id_tiket)
                        ->where('aktiviti', 'LIKE', '%Dihantar ke UTD%')
                        ->exists();

                    if ($isPeminjaman) {
                        $paraPengesah = Pengguna::whereIn('peranan', ['ketua_upp', 'kupp', 'ketua upp', 'Ketua UPP', 'ketua_utd', 'kutd', 'ketua utd', 'Ketua UTD', 'ketua_wilayah', 'kw', 'ketua wilayah', 'Ketua Wilayah'])
                            ->whereNotNull('no_ic')
                            ->where('no_ic', '!=', '')
                            ->get()
                            ->unique('no_ic');

                        if ($paraPengesah->isNotEmpty()) {
                            $hantarNotiTanpaBertindih($paraPengesah, new PengesahanKetuaNoti($ticket, Auth::user()->nama, true));
                        }
                    } else {
                        if ($isHandedOverToUTD) {
                            $paraKUTD = Pengguna::whereIn('peranan', ['ketua_utd', 'kutd', 'ketua utd', 'Ketua UTD'])
                                ->whereNotNull('no_ic')
                                ->where('no_ic', '!=', '')
                                ->get()
                                ->unique('no_ic');

                            if ($paraKUTD->isNotEmpty()) {
                                $hantarNotiTanpaBertindih($paraKUTD, new NewTicketNoti($ticket, 'semakan_kutd')); // FIXED
                            }
                        } else {
                            $paraKUPP = Pengguna::whereIn('peranan', ['ketua_upp', 'kupp', 'ketua upp', 'Ketua UPP'])
                                ->whereNotNull('no_ic')
                                ->where('no_ic', '!=', '')
                                ->get()
                                ->unique('no_ic');

                            if ($paraKUPP->isNotEmpty()) {
                                $hantarNotiTanpaBertindih($paraKUPP, new PengesahanKetuaNoti($ticket, Auth::user()->nama));
                            }
                        }
                    }
                }
                elseif ($tindakan === 'KUTD_SAH_SEMAKAN') {
                    if ($isPembekalan) {
                        $paraKW = Pengguna::whereIn('peranan', ['ketua_wilayah', 'kw', 'ketua wilayah', 'Ketua Wilayah'])
                            ->whereNotNull('no_ic')
                            ->where('no_ic', '!=', '')
                            ->get()
                            ->unique('no_ic');

                        if ($paraKW->isNotEmpty()) {
                            $hantarNotiTanpaBertindih($paraKW, new ValidasiKWNoti($ticket, Auth::user()->nama));
                        }
                    } else {
                        $paraKUPP = Pengguna::whereIn('peranan', ['ketua_upp', 'kupp', 'ketua upp', 'Ketua UPP'])
                            ->whereNotNull('no_ic')
                            ->where('no_ic', '!=', '')
                            ->get()
                            ->unique('no_ic');

                        if ($paraKUPP->isNotEmpty()) {
                            $hantarNotiTanpaBertindih($paraKUPP, new PengesahanKetuaNoti($ticket, Auth::user()->nama));
                        }
                    }
                }
                elseif ($tindakan === 'KUPP_HANTAR_VALIDASI') {
                    $paraKW = Pengguna::whereIn('peranan', ['ketua_wilayah', 'kw', 'ketua wilayah', 'Ketua Wilayah'])
                        ->whereNotNull('no_ic')
                        ->where('no_ic', '!=', '')
                        ->get()
                        ->unique('no_ic');

                    if ($paraKW->isNotEmpty()) {
                        $hantarNotiTanpaBertindih($paraKW, new ValidasiKWNoti($ticket, Auth::user()->nama));
                    }
                }
                elseif (in_array($tindakan, ['KW_PEMBETULAN', 'KUTD_PEMBETULAN'])) {
                    $petugasList = $ticket->petugas
                        ->filter(fn($p) => !empty($p->no_ic))
                        ->unique('no_ic');

                    if ($petugasList->isNotEmpty()) {
                        $hantarNotiTanpaBertindih($petugasList, new LKKPembetulanNoti($ticket, Auth::user()->nama));
                    }
                }
            }

            if ($statusBaru === 'Menunggu Kelulusan') {
                $paraPengesah = Pengguna::whereIn('peranan', ['ketua_upp', 'kupp', 'ketua upp', 'Ketua UPP', 'ketua_utd', 'kutd', 'ketua utd', 'Ketua UTD', 'ketua_wilayah', 'kw', 'ketua wilayah', 'Ketua Wilayah'])
                    ->whereNotNull('no_ic')
                    ->where('no_ic', '!=', '')
                    ->get()
                    ->unique('no_ic');

                if ($paraPengesah->isNotEmpty()) {
                    Notification::send($paraPengesah, new PengesahanKetuaNoti($ticket, Auth::user()->nama));
                }
            }

            DB::commit();
            return back()->with('success', 'Proses LKK Berjaya!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ralat storeLKK: ' . $e->getMessage());
            return back()->withErrors(['sistem' => $e->getMessage()]);
        }
    }

    public function cetakLKK($id_tiket)
    {
        $ticket = Tiket::with('transformasiDigital')->where('id_tiket', $id_tiket)->firstOrFail();
        $laporan = DB::table('laporan')->where('id_tiket', $id_tiket)->first();

        if (!$laporan) return redirect()->back()->withErrors(['sistem' => 'Laporan LKK belum dijana.']);

        if ($laporan) {
            foreach (['gambar_tapak', 'gambar_cadangan'] as $field) {
                if (!empty($laporan->{$field})) {
                    $decoded = json_decode($laporan->{$field}, true);

                    if (!is_array($decoded)) {
                        $decoded = $decoded ? [$decoded] : [];
                    }
                    $laporan->{$field} = json_encode($decoded);
                } else {
                    $laporan->{$field} = json_encode([]);
                }
            }
        }

        $kosItems = json_decode($laporan->kos_items) ?? [];

        if ($ticket->kategori === 'Transformasi Digital') {
            $subKategori = $ticket->transformasiDigital->sub_kategori ?? '';
            if ($subKategori === 'Pembekalan Peralatan ICT') return view('reports.laporan_lkk_pembekalanICT', compact('ticket', 'laporan', 'kosItems'));
            return view('reports.laporan_lkk_transformasi', compact('ticket', 'laporan', 'kosItems'));
        }
        return view('reports.laporan_lkk_rangkaian', compact('ticket', 'laporan', 'kosItems'));
    }

    public function verifikasiLKK(Request $request, string $id_tiket)
    {
        $ticket = Tiket::where('id_tiket', $id_tiket)->firstOrFail();
        $laporan = Laporan::where('id_tiket', $id_tiket)->firstOrFail();

        $validated = $request->validate([
            'aksi' => ['required', 'in:pulang_pic,hantar_kw,pulang_semak,lulus_tutup'],
            'ulasan_ketua' => ['nullable', 'string', 'max:2000']
        ]);

        $aksi = $validated['aksi'];
        $userSemasa = Auth::user();
        $ulasan = $validated['ulasan_ketua'] ?? null;

        DB::beginTransaction();
        try {
            switch ($aksi) {
                case 'hantar_kw':
                    $ticket->update(['status_tiket' => 'Menunggu Validasi']);
                    $ticket->rekodLog('Disahkan', 'Oleh ' . $userSemasa->nama);
                    break;
                case 'lulus_tutup':
                    $ticket->update([
                        'status_tiket' => 'Selesai',
                        'tarikh_tutup' => now()
                    ]);
                    $ticket->rekodLog('Divalidasi', 'Oleh ' . $userSemasa->nama, 'LULUS');
                    $ticket->rekodLog('Tiket Ditutup', 'Oleh ' . $userSemasa->nama, 'SELESAI');
                    break;
                case 'pulang_pic':
                    $updateData = ['status_tiket' => 'LKK Perlu Pembetulan'];
                    if ($ulasan) $updateData['ulasan_semakan'] = $ulasan;
                    $ticket->update($updateData);

                    $kupp = Pengguna::where('no_ic', $ticket->pengguna_ic)->first();
                    if ($kupp) {
                        $kupp->notify(new LKKPembetulanNoti($ticket, $userSemasa->nama));
                    }
                    break;
                case 'pulang_semak':
                    $ticket->update(['status_tiket' => 'Menunggu Pengesahan']);
                    $ticket->rekodLog('Laporan dikembalikan', 'Oleh ' . $userSemasa->nama);
                    break;
            }

            $this->clearTicketNotifications($id_tiket);

            DB::commit();
            return back()->with('success', 'Tindakan berjaya!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['sistem' => $e->getMessage()]);
        }
    }


    private function clearTicketNotifications(string $id_tiket)
    {
        try {
            DB::table('notifications')
                ->whereNull('read_at')
                ->where('data->id_tiket', $id_tiket)
                ->update(['read_at' => now()]);
        } catch (\Exception $e) {}

        try {
            DB::table('notifications')
                ->whereNull('read_at')
                ->whereRaw("CAST(data AS CHAR) LIKE ?", ['%' . $id_tiket . '%'])
                ->update(['read_at' => now()]);
        } catch (\Exception $e) {}

        $unreadNotis = DB::table('notifications')->whereNull('read_at')->get();
        $notisToClear = [];
        foreach ($unreadNotis as $noti) {
            if (str_contains((string) $noti->data, $id_tiket)) {
                $notisToClear[] = $noti->id;
            }
        }
        if (!empty($notisToClear)) {
            DB::table('notifications')->whereIn('id', $notisToClear)->update(['read_at' => now()]);
        }
    }
}
