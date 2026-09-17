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
        $statusFilter        = $request->query('status');

        $userAktif = Auth::user();

        $query = Tiket::with(['petugas', 'mejaBantuan', 'konsultasiRangkaian', 'transformasiDigital'])->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('id_tiket', 'like', "%{$search}%")
                  ->orWhere('agensi', 'like', "%{$search}%")
                  ->orWhere('perkara', 'like', "%{$search}%");
            });
        }

        if ($userAktif->peranan === 'juruteknik') {
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
            'kategori'      => ['required', 'string'],
            'sub_kategori'  => ['required', 'string'],
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

            $senaraiPengurus = Pengguna::whereIn('peranan', ['ketua_upp', 'kupp', 'ketua upp', 'Ketua UPP', 'ketua_utd', 'kutd', 'ketua utd', 'Ketua UTD', 'ketua_wilayah', 'kw', 'ketua wilayah', 'Ketua Wilayah'])->get();
            $targetHeadsEmails = $senaraiPengurus->whereNotNull('emel')->pluck('emel')->toArray();

            if (!empty($targetHeadsEmails)) {
                Mail::to($targetHeadsEmails)->send(new TicketVerificationAlert($ticket, $subKategoriValue));
            }

            if ($senaraiPengurus->isNotEmpty()) {
                Notification::send($senaraiPengurus, new NewTicketNoti($ticket, 'baru'));
            }

            return redirect()->back()->with('success', "Permohonan baru berjaya didaftarkan. ID Tiket: {$generatedId}");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ticket Insertion Failed: ' . $e->getMessage());
            return back()->withErrors([
                'sistem' => 'Pangkalan data menolak kemasukan data: ' . $e->getMessage()
            ]);
        }
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
        if ($ticket->laporan && $ticket->laporan->kos_items) {
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
        $perananAktif = strtolower(trim($userAktif->peranan));

        $mesejSukses = "Tiket berjaya dikemaskini.";

        $rules = [
            'kategori'        => ['required', 'string'],
            'sub_kategori'    => ['required', 'string'],
            'tahap_keutamaan' => ['required', 'string'],
        ];

        // Extra validation if processed by KUPP
        if (in_array($perananAktif, ['ketua_upp', 'kupp', 'ketua upp']) && $ticket->status_tiket === 'Menunggu Klasifikasi') {
            $rules['bisa_kendalikan'] = ['required', 'in:Ya,Tidak'];

            $adakahPeminjaman = ($request->input('sub_kategori') === 'Peminjaman Peralatan ICT' || $ticket->sub_kategori === 'Peminjaman Peralatan ICT');

            if ($adakahPeminjaman) {
                $rules['no_ic'] = ['nullable', 'string'];
            } else {
                $rules['no_ic'] = ['required_if:bisa_kendalikan,Ya', 'nullable', 'string'];
            }
        }

        if ($request->input('sub_kategori') === 'Peminjaman Peralatan ICT' && $ticket->status_tiket !== 'Menunggu Klasifikasi') {
            $rules['serial_no'] = ['required', 'string'];
            $rules['kuantiti_dipinjam'] = ['required', 'integer', 'min:1'];
        }

        $validated = $request->validate($rules);

        $hariSla = match ($validated['tahap_keutamaan']) {
            'Tinggi' => 3, 'Sederhana' => 7, default => 14,
        };

        $statusLama = $ticket->status_tiket;
        $statusBaharu = $ticket->status_tiket;

        $kategoriCek = strtolower($validated['kategori'] ?? $ticket->kategori ?? '');
        $adakahTD = str_contains($kategoriCek, 'transformasi digital');
        $adakahPeminjaman = str_contains(strtolower($validated['sub_kategori'] ?? $ticket->sub_kategori ?? ''), 'peminjaman');

        // Check user role
        $isKUPP = in_array($perananAktif, ['ketua_upp', 'kupp', 'ketua upp']);
        $isPengurusanLain = in_array($perananAktif, ['ketua_utd', 'kutd', 'ketua utd', 'ketua_wilayah', 'kw', 'ketua wilayah']);

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

                if (in_array($newKategori, ['Konsultasi Rangkaian', 'Transformasi Digital']) && $request->filled('tarikh_lawatan')) {
                    $insertChildFields['tarikh_lawatan'] = $request->tarikh_lawatan;
                    $insertChildFields['masa_lawatan']   = $request->masa_lawatan;
                    $insertChildFields['catatan_lawatan'] = $request->catatan_lawatan;
                }

                DB::table($newChildTable)->insert($insertChildFields);
                DB::table('jejak_tiket')->where('id_tiket', $id_tiket)->update(['id_tiket' => $newIdTiket]);
                DB::table('tugasan_tiket')->where('id_tiket', $id_tiket)->update(['id_tiket' => $newIdTiket]);

                $ticket = Tiket::where('id_tiket', $newIdTiket)->firstOrFail();
                $mesejSukses = "Kategori tiket berjaya ditukar. ID baharu dijana: {$newIdTiket}";

            } else {
                $ticket->update([
                    'status_tiket'    => $statusBaharu,
                    'kategori'        => $validated['kategori'],
                    'tahap_keutamaan' => $validated['tahap_keutamaan'],
                    'sla'             => now()->addDays($hariSla),
                ]);

                $childTable = match ($validated['kategori']) {
                    'Meja Bantuan'         => 'meja_bantuan',
                    'Transformasi Digital' => 'transformasi_digital',
                    'Konsultasi Rangkaian' => 'konsultasi_rangkaian',
                    default                => null,
                };

                if ($childTable) {
                    $updateFields = ['sub_kategori' => $validated['sub_kategori']];
                    if (in_array($validated['kategori'], ['Konsultasi Rangkaian', 'Transformasi Digital']) && $request->filled('tarikh_lawatan')) {
                        $updateFields['tarikh_lawatan']  = $request->tarikh_lawatan;
                        $updateFields['masa_lawatan']    = $request->masa_lawatan;
                        $updateFields['catatan_lawatan'] = $request->catatan_lawatan;
                    }

                    if ($statusLama !== 'Menunggu Klasifikasi' && $validated['kategori'] === 'Meja Bantuan' && $validated['sub_kategori'] === 'Peminjaman Peralatan ICT') {
                        $updateFields['kuantiti_dipinjam'] = $validated['kuantiti_dipinjam'];
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

            if ($oldKategori !== $newKategori) {
                return redirect()->route('tickets.show', ['id_tiket' => $newIdTiket])->with('success', $mesejSukses);
            }

            return back()->with('success', $mesejSukses);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processAction: ' . $e->getMessage());
            return back()->withErrors(['sistem' => $e->getMessage()]);
        }
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

            $ticket = Tiket::where('id_tiket', $id_tiket)->firstOrFail();

            $this->clearTicketNotifications($id_tiket);

            $hantarKeKutd = $request->boolean('hantar_ke_kutd', true);
            if ($hantarKeKutd && in_array($ticket->status_tiket, ['Dalam Tindakan Pegawai', 'Tugasan UTD'])) {
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
            'id_aset'        => 'required',
            'kuantiti_lulus' => 'required|integer|min:1',
        ]);

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
            'pic_ic'                   => 'required|string|exists:pengguna,no_ic',
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
                'aktiviti'       => 'Permohonan diluluskan',
                'pesanan'        => 'Oleh ' . Auth::user()->nama,
                'status_badge'   => 'LULUS',
                'created_at'     => now(),
                'updated_at'     => now()
            ]);

            $this->clearTicketNotifications($id_tiket);

            $picUser = \App\Models\Pengguna::where('no_ic', $request->pic_ic)->first();

            if ($picUser) {
                $picUser->notify(new \App\Notifications\TugasanPicNoti(
                    $ticket,
                    $request->id_aset,
                    Auth::user()->nama
                ));
            }

            DB::commit();
            return back()->with('success', 'Kelulusan peminjaman berjaya dihantar kepada pegawai!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ralat Peminjaman: ' . $e->getMessage());
            return back()->withErrors(['sistem' => 'Gagal memproses kelulusan: ' . $e->getMessage()]);
        }
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

        $adakahPeminjaman = DB::table('meja_bantuan')
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

        DB::beginTransaction();
        try {
            DB::table('tiket')->where('id_tiket', $id_tiket)->update([
                'status_tiket' => 'Menunggu Pengesahan',
                'updated_at'   => now()
            ]);

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

            $senaraiPengesah = \App\Models\Pengguna::whereIn('peranan', ['ketua_upp', 'kupp', 'ketua upp', 'Ketua UPP', 'ketua_utd', 'kutd', 'ketua utd', 'Ketua UTD', 'ketua_wilayah', 'kw', 'ketua wilayah', 'Ketua Wilayah'])
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

        if (!in_array($perananSemasa, $perananPengesah, true)) {
            abort(403, 'Anda tidak mempunyai kebenaran untuk mengesahkan tiket peminjaman.');
        }

        $ticket = DB::table('tiket')->where('id_tiket', $id_tiket)->first();

        if (!$ticket) {
            abort(404, 'Tiket tidak dijumpai.');
        }

        $adakahPeminjaman = DB::table('meja_bantuan')
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
                $aktivitiLog  = 'Tiket dipulangkan';
                $badgeStatus  = 'INFO';
                $pesananAudit = 'Tiket dikembalikan kepada petugas untuk pembetulan';
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

            if ($tindakan === 'hantar_kw') {
                $senaraiKw = \App\Models\Pengguna::whereIn('peranan', ['ketua_wilayah', 'kw', 'ketua wilayah', 'Ketua Wilayah'])->get();
                foreach ($senaraiKw as $kwUser) {
                    $kwUser->notify(new \App\Notifications\ValidasiKWNoti($ticket, Auth::user()->nama));
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

        DB::beginTransaction();
        try {
            DB::table('tiket')->where('id_tiket', $id_tiket)->update([
                'status_tiket' => 'Selesai',
                'tarikh_tutup' => now(),
                'updated_at'   => now()
            ]);

            DB::table('jejak_tiket')->insert([
                'id_tiket'       => $id_tiket,
                'nama_pelaku'    => Auth::user()->nama,
                'peranan_pelaku' => Auth::user()->peranan,
                'aktiviti'       => 'Divalidasi',
                'pesanan'        => 'Oleh ' . Auth::user()->nama,
                'status_badge'   => 'LULUS',
                'created_at'     => now(),
                'updated_at'     => now()
            ]);

            DB::table('jejak_tiket')->insert([
                'id_tiket'       => $id_tiket,
                'nama_pelaku'    => Auth::user()->nama,
                'peranan_pelaku' => Auth::user()->peranan,
                'aktiviti'       => 'Tiket Ditutup',
                'pesanan'        => 'Oleh ' . Auth::user()->nama,
                'status_badge'   => 'SELESAI',
                'created_at'     => now()->addSecond(),
                'updated_at'     => now()
            ]);

            $this->clearTicketNotifications($id_tiket);

            DB::commit();
            return back()->with('success', 'Tiket peminjaman peralatan berjaya disahkan, divalidasi, dan ditutup secara rasmi!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ralat sahkanTutupPeminjaman: ' . $e->getMessage());
            return back()->withErrors(['sistem' => 'Gagal menutup tiket peminjaman: ' . $e->getMessage()]);
        }
    }

    /**
     * Store and process Network Consultation LKK report (Filled by KUTD / Reviewed by KW).
     */
    public function storeLKKRangkaian(Request $request, string $id_tiket)
    {
        $ticket = Tiket::where('id_tiket', $id_tiket)->firstOrFail();

        $tindakan         = $request->input('tindakan');
        $isDraft          = $request->boolean('is_draft');
        $isKutdHantar     = $request->boolean('is_kutd_hantar') || $tindakan === 'KUTD_SAH_SEMAKAN';
        $isKwSahkan       = $request->boolean('is_kw_sahkan') || $tindakan === 'KW_VALIDASI_SELESAI';
        $isKwPembetulan   = $request->boolean('is_kw_pembetulan') || $tindakan === 'KW_PEMBETULAN';
        $isKutdPembetulan = $request->boolean('is_kutd_pembetulan') || $tindakan === 'KUTD_PEMBETULAN';

        $isPengesahanSaja = $isKwSahkan || $isKwPembetulan || $isKutdPembetulan || in_array($tindakan, ['KW_VALIDASI_SELESAI', 'KW_PEMBETULAN', 'KUTD_PEMBETULAN', 'SAHKAN_PEMINJAMAN_SELESAI']);

        if ($isPengesahanSaja) {
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

        $subKategori = $ticket->transformasiDigital?->sub_kategori
            ?? $ticket->transformasi_digital?->sub_kategori
            ?? $ticket->sub_kategori
            ?? '';

        $isPembekalan = str_contains(strtolower($subKategori), 'pembekalan');
        $isPeminjaman = str_contains(strtolower($subKategori), 'peminjaman');

        $tindakan = $request->input('tindakan', $request->query('tindakan'));
        $hasTindakan = !empty($tindakan);

        $isDraft = filter_var($request->input('is_draft', $request->query('is_draft')), FILTER_VALIDATE_BOOLEAN);
        $isKuppSahkan = filter_var($request->input('is_kupp_sahkan', $request->query('is_kupp_sahkan')), FILTER_VALIDATE_BOOLEAN) || $tindakan === 'KUPP_HANTAR_VALIDASI';
        $isKutdHantar = filter_var($request->input('is_kutd_hantar', $request->query('is_kutd_hantar')), FILTER_VALIDATE_BOOLEAN) || $tindakan === 'KUTD_SAH_SEMAKAN';
        $isKwSahkan = filter_var($request->input('is_kw_sahkan', $request->query('is_kw_sahkan')), FILTER_VALIDATE_BOOLEAN) || $tindakan === 'KW_VALIDASI_SELESAI';
        $isKwPembetulan = filter_var($request->input('is_kw_pembetulan', $request->query('is_kw_pembetulan')), FILTER_VALIDATE_BOOLEAN) || $tindakan === 'KW_PEMBETULAN';
        $isKutdPembetulan = $tindakan === 'KUTD_PEMBETULAN';

        $isPengesahanSaja = $isKwSahkan || $isKwPembetulan || $isKutdPembetulan || in_array($tindakan, ['KW_VALIDASI_SELESAI', 'KW_PEMBETULAN', 'KUTD_PEMBETULAN']);

        $rules = [
            'gambar_tapak' => ['nullable'],
            'gambar_cadangan' => ['nullable'],
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

            $this->clearTicketNotifications($id_tiket);

            if ($hasTindakan) {
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
