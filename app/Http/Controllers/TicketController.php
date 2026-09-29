<?php

namespace App\Http\Controllers;

use App\Mail\TicketVerificationAlert;
use App\Models\Pengguna;
use App\Models\Tiket;
use App\Notifications\NewTicketNoti;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TicketController extends Controller
{
    /**
     * Display ticket listing with search and filters.
     */
    public function index(Request $request): InertiaResponse
    {
        $kategoriSelected = $request->query('kategori');
        $subKategoriSelected = $request->query('sub_kategori');
        $search = $request->query('search');

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
                'Meja Bantuan' => 'mejaBantuan',
                'Konsultasi Rangkaian' => 'konsultasiRangkaian',
                'Transformasi Digital' => 'transformasiDigital',
                default => null,
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
                    'Menunggu Semakan',
                    'Dalam Tindakan',
                    'Menunggu Semakan Laporan',
                    'Laporan Perlu Pembetulan',
                    'Sedia Diverifikasi',
                    'Pembetulan Ketua',
                ]);
            } elseif ($status === 'belum_tindakan') {
                $query->whereIn('status_tiket', [
                    'Menunggu Klasifikasi',
                    'Menunggu Semakan Dokumen',
                    'Menunggu Kelulusan',
                    'Tugasan UTD',
                    'Tugasan UPP',
                    'Menunggu Semakan',
                ]);
            } else {
                if (! is_array($status)) {
                    $status = [$status];
                }
                $status = array_filter($status);
                if (! empty($status)) {
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
            'filters' => $request->only(['search', 'kategori', 'sub_kategori', 'status']),
        ]);
    }

    /**
     * Store newly created ticket application.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()?->peranan === 'admin',
            403,
            'Hanya Admin dibenarkan mendaftar permohonan.'
        );

        $kategoriWorkflow = config('s2p_workflow.categories', []);

        $subKategoriSah = $kategoriWorkflow[
            $request->input('kategori')
        ] ?? [];

        $validated = $request->validate([
            'perkara' => ['required', 'string', 'max:255'],
            'saluran' => ['required', 'string'],
            'nama_pemohon' => ['required', 'string', 'max:255'],
            'emel_pemohon' => ['required', 'email', 'max:255'],
            'notel_pemohon' => ['required', 'string', 'max:20'],
            'agensi' => ['required', 'string'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'daerah' => ['required', 'string'],
            'kategori' => [
                'required',
                'string',
                Rule::in(array_keys($kategoriWorkflow)),
            ],
            'sub_kategori' => [
                'required',
                'string',
                Rule::in($subKategoriSah),
            ],
            'lampiran' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:5120'],
        ]);

        $enableCurrentWorkflow =
            (bool) config(
                's2p_workflow.enable_new_tickets',
                false
            )
            || (
                $validated['kategori']
                    === 'Konsultasi Rangkaian'
                && (bool) config(
                    's2p_workflow.enable_network_new_tickets',
                    false
                )
            )
            || (
                $validated['kategori']
                    === 'Meja Bantuan'
                && (bool) config(
                    's2p_workflow.enable_helpdesk_new_tickets',
                    false
                )
            );

        $workflowVersion = $enableCurrentWorkflow
            ? (int) config(
                's2p_workflow.version',
                Tiket::WORKFLOW_VERSION_CURRENT
            )
            : Tiket::WORKFLOW_VERSION_LEGACY;

        $prefix = match ($request->kategori) {
            'Meja Bantuan' => 'MB',
            'Transformasi Digital' => 'TD',
            'Konsultasi Rangkaian' => 'KR',
            default => 'GEN',
        };

        $currentYear = now()->format('Y');

        $lastTicket = Tiket::where('id_tiket', 'LIKE', "SDK-{$prefix}-{$currentYear}-%")
            ->orderBy('id_tiket', 'desc')
            ->first();

        $nextSequence = $lastTicket ? ((int) substr($lastTicket->id_tiket, -3)) + 1 : 1;
        $paddedSequence = str_pad($nextSequence, 3, '0', STR_PAD_LEFT);
        $generatedId = "SDK-{$prefix}-{$currentYear}-{$paddedSequence}";

        $filePath = null;
        if ($request->hasFile('lampiran')) {
            $file = $request->file('lampiran');
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $cleanName = str_replace(' ', '_', $originalName);
            $extension = $file->getClientOriginalExtension();
            $filenameToStore = $cleanName.'_'.$generatedId.'.'.$extension;
            $filePath = $file->storeAs('attachments', $filenameToStore, 'public');
            $validated['lampiran'] = $filePath;
        }

        $validated['kategori'] = match ($prefix) {
            'MB' => 'Meja Bantuan',
            'TD' => 'Transformasi Digital',
            'KR' => 'Konsultasi Rangkaian',
            default => 'Umum',
        };

        $validated['id_tiket'] = $generatedId;
        $validated['tarikh_terima'] = now();
        $validated['status_tiket'] = config(
            's2p_workflow.statuses.classification',
            'Menunggu Klasifikasi'
        );
        $validated['pengguna_ic'] = $request->user()->no_ic;
        $validated['workflow_version'] = $workflowVersion;

        $subKategoriValue = $validated['sub_kategori'];
        unset($validated['sub_kategori']);

        DB::beginTransaction();
        try {
            $ticket = Tiket::create($validated);

            $childTable = match ($validated['kategori']) {
                'Meja Bantuan' => 'meja_bantuan',
                'Transformasi Digital' => 'transformasi_digital',
                'Konsultasi Rangkaian' => 'konsultasi_rangkaian',
                default => null,
            };

            if ($childTable) {
                DB::table($childTable)->insert([
                    'id_tiket' => $generatedId,
                    'sub_kategori' => $subKategoriValue,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $aktivitiJejak = $workflowVersion
                === Tiket::WORKFLOW_VERSION_CURRENT
                    ? config(
                        's2p_workflow.trail_events.registered',
                        'DAFTAR TIKET'
                    )
                    : 'Daftar Tiket';

            $ticket->rekodLog(
                $aktivitiJejak,
                'Oleh '.$request->user()->nama,
                'SELESAI'
            );

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            if ($filePath) {
                Storage::disk('public')->delete($filePath);
            }
            Log::error('Ticket Insertion Failed: '.$e->getMessage());

            return back()->withErrors([
                'sistem' => 'Permohonan tidak dapat didaftarkan kerana berlaku ralat sistem. Sila cuba semula.',
            ]);
        }

        try {

            /*
             * Semua peranan pengurusan yang dibenarkan membuat
             * klasifikasi menerima notifikasi pendaftaran tiket.
             */
            $registrationRecipientRoles = [
                'ketua_upp',
                'ketua_utd',
                'ketua_wilayah',
            ];

            $senaraiPengurus = Pengguna::query()
                ->whereIn(
                    'peranan',
                    $registrationRecipientRoles
                )
                ->where('status_pengguna', 'Aktif')
                ->get();
            $targetHeadsEmails = $senaraiPengurus->whereNotNull('emel')->pluck('emel')->toArray();

            if (! empty($targetHeadsEmails)) {
                Mail::to($targetHeadsEmails)->send(new TicketVerificationAlert($ticket, $subKategoriValue));
            }

            if ($senaraiPengurus->isNotEmpty()) {
                Notification::send($senaraiPengurus, new NewTicketNoti($ticket, 'baru'));
            }

        } catch (\Exception $e) {
            Log::error('Ticket Notification Failed: '.$e->getMessage());
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

        if (! in_array($peranan, $kakitanganTeknikal)) {
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
            ! $ticket->petugas->contains('no_ic', $user->no_ic)
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
            'Meja Bantuan' => $ticket->mejaBantuan,
            'Konsultasi Rangkaian' => $ticket->konsultasiRangkaian,
            'Transformasi Digital' => $ticket->transformasiDigital,
            default => null,
        };

        $ticket->sub_kategori = $childData?->sub_kategori;
        $ticket->serial_no = $ticket->mejaBantuan?->serial_no ?? null;
        $ticket->kuantiti_dipinjam = $ticket->mejaBantuan?->kuantiti_dipinjam ?? null;

        $senaraiPengguna = DB::table('pengguna')
            ->whereIn('peranan', ['juruteknik', 'pic', 'ketua_upp', 'kupp', 'ketua upp', 'Ketua UPP', 'ketua_utd', 'kutd', 'ketua utd', 'Ketua UTD', 'ketua_wilayah', 'kw', 'ketua wilayah', 'Ketua Wilayah'])
            ->select(
                'no_ic',
                'nama',
                'peranan',
                'status_pengguna'
            )
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
            'ticket' => $ticket,
            'auditTrail' => $auditTrail,
            'senaraiPengguna' => $senaraiPengguna,
            'senaraiAset' => $senaraiAset,
            'backUrl' => $previousUrl,
            'dataKelulusan' => $dataKos,
        ]);
    }

    /**
     * Process classification and ticket assignment workflow.
     */

    /**
     * Handle PIC closing of regular ticket tasks.
     */

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

    /**
     * Print site information sheet.
     */
    public function cetakMaklumatTapak($id_tiket)
    {
        $ticket = Tiket::with(['petugas', 'konsultasiRangkaian'])
            ->where('id_tiket', $id_tiket)
            ->firstOrFail();

        return Inertia::render('Tickets/CetakMaklumatTapak', [
            'ticket' => $ticket,
        ]);
    }

    /**
     * Submit helpdesk report by PIC.
     */

    /**
     * Confirm and close helpdesk ticket by manager.
     */

    /**
     * Generate available serial asset list for loan.
     */

    /**
     * Store loan approval and assign PIC.
     */

    /**
     * Save loan item delivery details.
     */

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

        if (! $adakahPeminjaman) {
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

        if (! $adakahPengurus && ! $adakahPIC) {
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

        if (! $serialDitemui) {
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
            'pic' => $pic,
        ]);
    }

    /**
     * Submit completed equipment loan form to KUTD.
     */

    /**
     * Process KUTD review decision on asset loan ticket.
     */

    /**
     * Validate and finalize equipment loan ticket.
     */

    /**
     * Store and process Network Consultation LKK report (Filled by KUTD / Reviewed by KW).
     */

    /**
     * Store and process Digital Transformation LKK report workflow.
     */
    public function cetakLKK($id_tiket)
    {
        $ticket = Tiket::query()
            ->with('transformasiDigital')
            ->where('id_tiket', $id_tiket)
            ->firstOrFail();

        $subKategori =
            $ticket->transformasiDigital?->sub_kategori
            ?? '';

        $isModernization =
            $ticket->kategori === 'Transformasi Digital'
            && $subKategori === 'Pemodenan Bilik Mesyuarat';

        $isModernizationV2 =
            $isModernization
            && $ticket->usesCurrentWorkflow();

        $isProcurement =
            $ticket->kategori === 'Transformasi Digital'
            && $subKategori === 'Pembekalan Peralatan ICT';

        $isProcurementV2 =
            $isProcurement
            && $ticket->usesCurrentWorkflow();

        $isDigitalTransformationV2 =
            $isModernizationV2
            || $isProcurementV2;

        if ($isModernization || $isProcurementV2) {
            if (
                $ticket->status_tiket
                !== config(
                    's2p_workflow.statuses.completed',
                    'Selesai'
                )
            ) {
                abort(
                    403,
                    $isProcurementV2
                        ? 'LKK Pembekalan Peralatan ICT hanya boleh dicetak selepas validasi akhir Ketua Wilayah.'
                        : 'LKK Pemodenan Bilik Mesyuarat hanya boleh dicetak selepas validasi akhir Ketua Wilayah.'
                );
            }

            $currentRole = strtolower(
                trim(
                    (string) (
                        Auth::user()?->peranan
                        ?? ''
                    )
                )
            );

            $allowedRoles = $isDigitalTransformationV2
                ? config(
                    's2p_workflow.flows.'.
                    (
                        $isProcurementV2
                            ? 'pembekalan'
                            : 'pemodenan'
                    ).
                    '.report_print_roles',
                    []
                )
                : [
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

            $allowedRoles = array_map(
                static fn ($role): string => strtolower(
                    trim((string) $role)
                ),
                $allowedRoles
            );

            if (
                ! in_array(
                    $currentRole,
                    $allowedRoles,
                    true
                )
            ) {
                abort(
                    403,
                    $isProcurementV2
                        ? 'Hanya KUPP dan Ketua Wilayah dibenarkan mencetak LKK Pembekalan Peralatan ICT V2.'
                        : (
                            $isModernizationV2
                                ? 'Hanya KUPP dan Ketua Wilayah dibenarkan mencetak LKK Pemodenan V2.'
                                : 'Hanya KUPP, KUTD dan Ketua Wilayah dibenarkan mencetak LKK Pemodenan Bilik Mesyuarat.'
                        )
                );
            }
        }

        $laporan = DB::table('laporan')
            ->where('id_tiket', $id_tiket)
            ->first();

        if ($laporan === null) {
            return redirect()
                ->back()
                ->withErrors([
                    'sistem' => 'Laporan LKK belum dijana.',
                ]);
        }

        foreach (
            [
                'gambar_tapak',
                'gambar_cadangan',
            ] as $field
        ) {
            if (! empty($laporan->{$field})) {
                $decoded = json_decode(
                    $laporan->{$field},
                    true
                );

                if (! is_array($decoded)) {
                    $decoded = $decoded
                        ? [$decoded]
                        : [];
                }

                $laporan->{$field} = json_encode(
                    $decoded
                );
            } else {
                $laporan->{$field} = json_encode([]);
            }
        }

        $kosItems = json_decode(
            $laporan->kos_items
        ) ?? [];

        $tarikhDisediakan = null;
        $tarikhDisemak = null;
        $jawatanPenyedia = null;
        $jawatanPenyemak = null;

        if ($isDigitalTransformationV2) {
            $verifiedTrail = DB::table('jejak_tiket')
                ->where('id_tiket', $id_tiket)
                ->where(
                    'aktiviti',
                    config(
                        's2p_workflow.trail_events.verified',
                        'DIVERIFIKASI'
                    )
                )
                ->orderByDesc('id')
                ->first();

            $validatedTrail = DB::table('jejak_tiket')
                ->where('id_tiket', $id_tiket)
                ->where(
                    'aktiviti',
                    config(
                        's2p_workflow.trail_events.validated',
                        'DIVALIDASI'
                    )
                )
                ->orderByDesc('id')
                ->first();

            $tarikhDisediakan =
                $verifiedTrail?->created_at;

            $tarikhDisemak =
                $validatedTrail?->created_at;

            if (
                filled(
                    $laporan->disediakan_oleh
                    ?? null
                )
            ) {
                $jawatanPenyedia = Pengguna::query()
                    ->where(
                        'nama',
                        $laporan->disediakan_oleh
                    )
                    ->value('jawatan');
            }

            if (
                filled(
                    $laporan->disemak_oleh
                    ?? null
                )
            ) {
                $jawatanPenyemak = Pengguna::query()
                    ->where(
                        'nama',
                        $laporan->disemak_oleh
                    )
                    ->value('jawatan');
            }
        }

        if ($ticket->kategori === 'Transformasi Digital') {
            if (
                $subKategori
                === 'Pembekalan Peralatan ICT'
            ) {
                return view(
                    'reports.laporan_lkk_pembekalanICT',
                    compact(
                        'ticket',
                        'laporan',
                        'kosItems',
                        'isProcurementV2',
                        'tarikhDisediakan',
                        'tarikhDisemak',
                        'jawatanPenyedia',
                        'jawatanPenyemak'
                    )
                );
            }

            return view(
                'reports.laporan_lkk_transformasi',
                compact(
                    'ticket',
                    'laporan',
                    'kosItems',
                    'isModernizationV2',
                    'tarikhDisediakan',
                    'tarikhDisemak',
                    'jawatanPenyedia',
                    'jawatanPenyemak'
                )
            );
        }

        return view(
            'reports.laporan_lkk_rangkaian',
            compact(
                'ticket',
                'laporan',
                'kosItems'
            )
        );
    }
}
