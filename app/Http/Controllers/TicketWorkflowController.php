<?php

namespace App\Http\Controllers;

use App\Http\Requests\Workflow\AssignModernizationTicketRequest;
use App\Http\Requests\Workflow\ClassifyTicketRequest;
use App\Http\Requests\Workflow\ConfirmHelpdeskTicketRequest;
use App\Http\Requests\Workflow\ConfirmLoanTicketRequest;
use App\Http\Requests\Workflow\GenerateLoanAssetsRequest;
use App\Http\Requests\Workflow\ReviewHelpdeskTicketRequest;
use App\Http\Requests\Workflow\ReviewLoanTicketRequest;
use App\Http\Requests\Workflow\ReviewModernizationLkkRequest;
use App\Http\Requests\Workflow\ReviewModernizationTicketRequest;
use App\Http\Requests\Workflow\ReviewNetworkSiteReportRequest;
use App\Http\Requests\Workflow\ReviewNetworkTicketRequest;
use App\Http\Requests\Workflow\ReviewProcurementTicketRequest;
use App\Http\Requests\Workflow\SaveLoanFormRequest;
use App\Http\Requests\Workflow\SaveModernizationLkkRequest;
use App\Http\Requests\Workflow\SaveNetworkLkkRequest;
use App\Http\Requests\Workflow\SaveProcurementLkkRequest;
use App\Http\Requests\Workflow\SubmitHelpdeskTicketRequest;
use App\Http\Requests\Workflow\SubmitModernizationReportRequest;
use App\Http\Requests\Workflow\SubmitNetworkSiteReportRequest;
use App\Http\Requests\Workflow\SubmitProcurementReportRequest;
use App\Http\Requests\Workflow\ValidateModernizationLkkRequest;
use App\Http\Requests\Workflow\ValidateNetworkLkkRequest;
use App\Models\Laporan;
use App\Models\Pengguna;
use App\Models\Tiket;
use App\Notifications\LKKPembetulanNoti;
use App\Notifications\NewTicketNoti;
use App\Notifications\PengesahanKetuaNoti;
use App\Notifications\TugasanPicNoti;
use App\Notifications\WorkflowAssignmentNotification;
use App\Notifications\WorkflowChiefCorrectionNotification;
use App\Notifications\WorkflowConfirmationNotification;
use App\Notifications\WorkflowModernizationChiefCorrectionNotification;
use App\Notifications\WorkflowModernizationValidationNotification;
use App\Notifications\WorkflowNetworkCorrectionNotification;
use App\Notifications\WorkflowProcurementChiefCorrectionNotification;
use App\Notifications\WorkflowProcurementReviewNotification;
use App\Notifications\WorkflowProcurementValidationNotification;
use App\Notifications\WorkflowReportReviewNotification;
use App\Notifications\WorkflowReviewNotification;
use App\Notifications\WorkflowValidationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TicketWorkflowController extends Controller
{
    public function classify(
        ClassifyTicketRequest $request,
        string $id_tiket
    ): RedirectResponse {
        $validated = $request->validated();

        [$ticket, $reviewRole] = DB::transaction(
            function () use (
                $request,
                $id_tiket,
                $validated
            ): array {
                $ticket = Tiket::query()
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->firstOrFail();

                $classificationStatus = config(
                    's2p_workflow.statuses.classification',
                    'Menunggu Klasifikasi'
                );

                if ($ticket->status_tiket !== $classificationStatus) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tiket ini telah diklasifikasikan atau diproses oleh pengguna lain.',
                    ]);
                }

                $flowKey = $this->resolveFlowKey(
                    $validated['kategori'],
                    $validated['sub_kategori']
                );

                if ($flowKey === null) {
                    throw ValidationException::withMessages([
                        'sub_kategori' => 'Aliran kerja bagi kategori dan subkategori ini tidak ditemui.',
                    ]);
                }

                $flow = config(
                    "s2p_workflow.flows.{$flowKey}",
                    []
                );

                $reviewRole = $flow['review_role'] ?? null;

                if (! is_string($reviewRole) || $reviewRole === '') {
                    throw ValidationException::withMessages([
                        'sistem' => 'Pegawai semakan bagi aliran tiket ini belum ditetapkan.',
                    ]);
                }

                $slaDays = config(
                    "s2p_workflow.sla_days.{$validated['tahap_keutamaan']}"
                );

                if (! is_int($slaDays)) {
                    throw ValidationException::withMessages([
                        'tahap_keutamaan' => 'Tempoh SLA bagi tahap keutamaan ini tidak sah.',
                    ]);
                }

                $this->synchronizeCategoryRecord(
                    $ticket,
                    $validated['kategori'],
                    $validated['sub_kategori']
                );

                $ticket->update([
                    'kategori' => $validated['kategori'],
                    'tahap_keutamaan' => $validated['tahap_keutamaan'],
                    'sla' => now()->addDays($slaDays),
                    'status_tiket' => config(
                        's2p_workflow.statuses.initial_review',
                        'Menunggu Semakan'
                    ),
                ]);

                $ticket->rekodLog(
                    config(
                        's2p_workflow.trail_events.classified',
                        'DIKLASIFIKASI'
                    ),
                    'Oleh '.$request->user()->nama,
                    'INFO'
                );

                /*
                 * Apabila seorang pegawai pengurusan mengambil
                 * tindakan klasifikasi, tutup notifikasi pendaftaran
                 * tiket yang sama bagi KUPP, KUTD dan KW.
                 */
                $classificationRecipientIds =
                    Pengguna::query()
                        ->whereIn(
                            'peranan',
                            config(
                                's2p_workflow.classification_roles',
                                []
                            )
                        )
                        ->where(
                            'status_pengguna',
                            'Aktif'
                        )
                        ->pluck('no_ic')
                        ->map(
                            fn ($id): string => (string) $id
                        )
                        ->all();

                if (
                    $classificationRecipientIds !== []
                ) {
                    DB::table('notifications')
                        ->where(
                            'type',
                            NewTicketNoti::class
                        )
                        ->where(
                            'notifiable_type',
                            $request->user()->getMorphClass()
                        )
                        ->whereIn(
                            'notifiable_id',
                            $classificationRecipientIds
                        )
                        ->whereNull('read_at')
                        ->where(
                            'data',
                            'LIKE',
                            '%'.$id_tiket.'%'
                        )
                        ->update([
                            'read_at' => now(),
                            'updated_at' => now(),
                        ]);
                }

                return [
                    $ticket->fresh(),
                    $reviewRole,
                ];
            },
            3
        );

        try {
            $reviewers = Pengguna::query()
                ->where('peranan', $reviewRole)
                ->where('status_pengguna', 'Aktif')
                ->get();

            if ($reviewers->isEmpty()) {
                Log::warning(
                    'Tiada pegawai aktif untuk semakan workflow tiket.',
                    [
                        'id_tiket' => $ticket->id_tiket,
                        'review_role' => $reviewRole,
                    ]
                );
            } else {
                Notification::send(
                    $reviewers,
                    new WorkflowReviewNotification(
                        $ticket,
                        $reviewRole
                    )
                );
            }
        } catch (\Throwable $exception) {
            Log::error(
                'Klasifikasi berjaya tetapi notifikasi semakan gagal.',
                [
                    'id_tiket' => $ticket->id_tiket,
                    'review_role' => $reviewRole,
                    'message' => $exception->getMessage(),
                ]
            );
        }

        return redirect()
            ->route(
                'tickets.show',
                ['id_tiket' => $ticket->id_tiket]
            )
            ->with(
                'success',
                'Tiket berjaya diklasifikasikan dan dihantar untuk semakan.'
            );
    }

    public function reviewNetwork(
        ReviewNetworkTicketRequest $request,
        string $id_tiket
    ): RedirectResponse {
        $validated = $request->validated();
        $reviewer = $request->user();

        [$ticket, $technicians, $subCategory] = DB::transaction(
            function () use (
                $id_tiket,
                $reviewer,
                $validated
            ): array {
                $ticket = Tiket::query()
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->firstOrFail();

                $expectedStatus = config(
                    's2p_workflow.statuses.initial_review',
                    'Menunggu Semakan'
                );

                if ($ticket->status_tiket !== $expectedStatus) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tiket ini bukan lagi dalam peringkat semakan.',
                    ]);
                }

                if ($ticket->kategori !== 'Konsultasi Rangkaian') {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk tiket Konsultasi Rangkaian.',
                    ]);
                }

                $networkRecord = DB::table(
                    'konsultasi_rangkaian'
                )
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->first();

                if ($networkRecord === null) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Rekod Konsultasi Rangkaian tidak ditemui.',
                    ]);
                }

                $subCategory = trim(
                    (string) $networkRecord->sub_kategori
                );

                $allowedSubCategories = config(
                    's2p_workflow.flows.rangkaian.sub_kategori',
                    []
                );

                if (
                    ! in_array(
                        $subCategory,
                        $allowedSubCategories,
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Subkategori tiket tidak menggunakan aliran Konsultasi Rangkaian.',
                    ]);
                }

                $technicianIds = array_values(
                    array_unique(
                        $validated['senarai_pic_ic']
                    )
                );

                $technicians = Pengguna::query()
                    ->whereIn('no_ic', $technicianIds)
                    ->where('peranan', 'juruteknik')
                    ->where('status_pengguna', 'Aktif')
                    ->lockForUpdate()
                    ->get();

                if (
                    $technicians->count()
                    !== count($technicianIds)
                ) {
                    throw ValidationException::withMessages([
                        'senarai_pic_ic' => 'Satu atau lebih Juruteknik tidak sah atau tidak aktif.',
                    ]);
                }

                DB::table('konsultasi_rangkaian')
                    ->where('id_tiket', $id_tiket)
                    ->update([
                        'tarikh_lawatan' => $validated['tarikh_lawatan'],
                        'masa_lawatan' => $validated['masa_lawatan'],
                        'catatan_lawatan' => $validated['catatan_lawatan']
                            ?? null,
                        'updated_at' => now(),
                    ]);

                Laporan::query()->updateOrCreate(
                    [
                        'id_tiket' => $id_tiket,
                    ],
                    [
                        'pendahuluan' => $validated['pendahuluan'],
                        'objektif' => json_encode(
                            $validated['objektif'],
                            JSON_THROW_ON_ERROR
                            | JSON_UNESCAPED_UNICODE
                        ),
                        'pengguna_ic' => $reviewer->no_ic,
                    ]
                );

                DB::table('tugasan_tiket')
                    ->where('id_tiket', $id_tiket)
                    ->delete();

                $assignmentTime = now();

                DB::table('tugasan_tiket')->insert(
                    $technicians
                        ->map(
                            fn (Pengguna $technician): array => [
                                'id_tiket' => $id_tiket,
                                'no_ic' => $technician->no_ic,
                                'created_at' => $assignmentTime,
                                'updated_at' => $assignmentTime,
                            ]
                        )
                        ->all()
                );

                $ticket->update([
                    'status_tiket' => config(
                        's2p_workflow.statuses.in_progress',
                        'Dalam Tindakan'
                    ),
                    'disemak_oleh_ic' => $reviewer->no_ic,
                    'tarikh_semakan' => now(),
                ]);

                $ticket->rekodLog(
                    config(
                        's2p_workflow.trail_events.reviewed',
                        'DISEMAK'
                    ),
                    'Oleh '.$reviewer->nama,
                    'INFO'
                );

                return [
                    $ticket->fresh(),
                    $technicians,
                    $subCategory,
                ];
            },
            3
        );

        try {
            Notification::send(
                $technicians,
                new WorkflowAssignmentNotification(
                    $ticket,
                    $reviewer->nama,
                    $subCategory
                )
            );
        } catch (\Throwable $exception) {
            Log::error(
                'Semakan Rangkaian berjaya tetapi notifikasi Juruteknik gagal.',
                [
                    'id_tiket' => $ticket->id_tiket,
                    'technician_ids' => $technicians->pluck('no_ic')->all(),
                    'message' => $exception->getMessage(),
                ]
            );
        }

        return redirect()
            ->route(
                'tickets.show',
                ['id_tiket' => $ticket->id_tiket]
            )
            ->with(
                'success',
                'Tiket Rangkaian berjaya disemak, lawatan dijadualkan dan Juruteknik telah dilantik.'
            );
    }

    public function reviewHelpdesk(
        ReviewHelpdeskTicketRequest $request,
        string $id_tiket
    ): RedirectResponse {
        $validated = $request->validated();
        $reviewer = $request->user();

        [$ticket, $pic, $subCategory] = DB::transaction(
            function () use (
                $id_tiket,
                $reviewer,
                $validated
            ): array {
                $ticket = Tiket::query()
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->firstOrFail();

                $expectedStatus = config(
                    's2p_workflow.statuses.initial_review',
                    'Menunggu Semakan'
                );

                if ($ticket->status_tiket !== $expectedStatus) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tiket ini bukan lagi dalam peringkat semakan.',
                    ]);
                }

                if ($ticket->kategori !== 'Meja Bantuan') {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk tiket Meja Bantuan.',
                    ]);
                }

                $helpdeskRecord = DB::table('meja_bantuan')
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->first();

                if ($helpdeskRecord === null) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Rekod kategori Meja Bantuan tidak ditemui.',
                    ]);
                }

                $subCategory = trim(
                    (string) $helpdeskRecord->sub_kategori
                );

                $allowedSubCategories = config(
                    's2p_workflow.flows.meja_bantuan.sub_kategori',
                    []
                );

                if (
                    $subCategory === 'Peminjaman Peralatan ICT'
                    || ! in_array(
                        $subCategory,
                        $allowedSubCategories,
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Subkategori tiket tidak menggunakan aliran Meja Bantuan biasa.',
                    ]);
                }

                $pic = Pengguna::query()
                    ->where('no_ic', $validated['pic_ic'])
                    ->where('peranan', 'juruteknik')
                    ->where('status_pengguna', 'Aktif')
                    ->lockForUpdate()
                    ->first();

                if ($pic === null) {
                    throw ValidationException::withMessages([
                        'pic_ic' => 'Petugas tidak sah atau tidak aktif.',
                    ]);
                }

                DB::table('tugasan_tiket')
                    ->where('id_tiket', $id_tiket)
                    ->delete();

                DB::table('tugasan_tiket')->insert([
                    'id_tiket' => $id_tiket,
                    'no_ic' => $pic->no_ic,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $ticket->update([
                    'status_tiket' => config(
                        's2p_workflow.statuses.in_progress',
                        'Dalam Tindakan'
                    ),
                    'disemak_oleh_ic' => $reviewer->no_ic,
                    'tarikh_semakan' => now(),
                ]);

                $ticket->rekodLog(
                    config(
                        's2p_workflow.trail_events.reviewed',
                        'DISEMAK'
                    ),
                    'Oleh '.$reviewer->nama,
                    'INFO'
                );

                return [
                    $ticket->fresh(),
                    $pic,
                    $subCategory,
                ];
            },
            3
        );

        try {
            $pic->notify(
                new WorkflowAssignmentNotification(
                    $ticket,
                    $reviewer->nama,
                    $subCategory
                )
            );
        } catch (\Throwable $exception) {
            Log::error(
                'Semakan Meja Bantuan berjaya tetapi notifikasi petugas gagal.',
                [
                    'id_tiket' => $ticket->id_tiket,
                    'pic_ic' => $pic->no_ic,
                    'message' => $exception->getMessage(),
                ]
            );
        }

        return redirect()
            ->route(
                'tickets.show',
                ['id_tiket' => $ticket->id_tiket]
            )
            ->with(
                'success',
                'Tiket berjaya disemak dan petugas telah dilantik.'
            );
    }

    public function generateLoanAssets(
        GenerateLoanAssetsRequest $request,
        string $id_tiket
    ): JsonResponse {
        $validated = $request->validated();

        $ticket = Tiket::query()
            ->where('id_tiket', $id_tiket)
            ->firstOrFail();

        $expectedStatus = config(
            's2p_workflow.statuses.initial_review',
            'Menunggu Semakan'
        );

        if ($ticket->status_tiket !== $expectedStatus) {
            abort(
                403,
                'Senarai peralatan hanya boleh dijana semasa peringkat semakan.'
            );
        }

        $isLoanTicket = $ticket->kategori === 'Meja Bantuan'
            && DB::table('meja_bantuan')
                ->where('id_tiket', $id_tiket)
                ->where(
                    'sub_kategori',
                    'Peminjaman Peralatan ICT'
                )
                ->exists();

        if (! $isLoanTicket) {
            abort(
                403,
                'Tindakan ini hanya dibenarkan untuk Peminjaman Peralatan ICT.'
            );
        }

        $assets = DB::table('aset')
            ->where('nama_aset', $validated['id_aset'])
            ->where('status', 'Tersedia')
            ->orderBy('serial_no')
            ->limit((int) $validated['kuantiti_lulus'])
            ->get([
                'serial_no',
                'nama_aset',
                'model',
            ]);

        if (
            $assets->count()
            < (int) $validated['kuantiti_lulus']
        ) {
            return response()->json(
                [
                    'message' => 'Baki stok fizikal tidak mencukupi.',
                    'errors' => [
                        'kuantiti_lulus' => [
                            'Baki stok fizikal tidak mencukupi.',
                        ],
                    ],
                ],
                422
            );
        }

        return response()->json([
            'senarai_aset' => $assets,
        ]);
    }

    public function reviewLoan(
        ReviewLoanTicketRequest $request,
        string $id_tiket
    ): RedirectResponse {
        $validated = $request->validated();

        [$ticket, $pic, $assetName] = DB::transaction(
            function () use (
                $request,
                $id_tiket,
                $validated
            ): array {
                $ticket = Tiket::query()
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->firstOrFail();

                $expectedStatus = config(
                    's2p_workflow.statuses.initial_review',
                    'Menunggu Semakan'
                );

                if ($ticket->status_tiket !== $expectedStatus) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tiket ini bukan lagi dalam peringkat semakan.',
                    ]);
                }

                $isLoanTicket = $ticket->kategori === 'Meja Bantuan'
                    && DB::table('meja_bantuan')
                        ->where('id_tiket', $id_tiket)
                        ->where(
                            'sub_kategori',
                            'Peminjaman Peralatan ICT'
                        )
                        ->exists();

                if (! $isLoanTicket) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk Peminjaman Peralatan ICT.',
                    ]);
                }

                $serialNumbers = collect(
                    $validated['senarai_aset']
                )
                    ->pluck('serial_no')
                    ->values()
                    ->all();

                $assets = DB::table('aset')
                    ->whereIn('serial_no', $serialNumbers)
                    ->orderBy('serial_no')
                    ->lockForUpdate()
                    ->get();

                if ($assets->count() !== count($serialNumbers)) {
                    throw ValidationException::withMessages([
                        'senarai_aset' => 'Satu atau lebih peralatan tidak ditemui.',
                    ]);
                }

                if (
                    $assets->contains(
                        fn ($asset) => $asset->nama_aset
                            !== $validated['id_aset']
                    )
                ) {
                    throw ValidationException::withMessages([
                        'senarai_aset' => 'Peralatan yang dipilih tidak sepadan dengan jenis peralatan.',
                    ]);
                }

                if (
                    $assets->contains(
                        fn ($asset) => $asset->status !== 'Tersedia'
                    )
                ) {
                    throw ValidationException::withMessages([
                        'senarai_aset' => 'Satu atau lebih peralatan tidak lagi tersedia.',
                    ]);
                }

                $pic = Pengguna::query()
                    ->where('no_ic', $validated['pic_ic'])
                    ->where('peranan', 'juruteknik')
                    ->where('status_pengguna', 'Aktif')
                    ->lockForUpdate()
                    ->first();

                if ($pic === null) {
                    throw ValidationException::withMessages([
                        'pic_ic' => 'Petugas tidak sah atau tidak aktif.',
                    ]);
                }

                $approvedAssets = $assets
                    ->map(
                        fn ($asset): array => [
                            'serial_no' => $asset->serial_no,
                            'nama_aset' => $asset->nama_aset,
                            'model' => $asset->model,
                        ]
                    )
                    ->values()
                    ->all();

                $loanInformation = [
                    'nama_aset' => $validated['id_aset'],
                    'kuantiti' => $validated['kuantiti_lulus'],
                    'senarai_siri' => $approvedAssets,
                    'tarikh_lulus' => now()->toDateTimeString(),
                ];

                DB::table('laporan')->updateOrInsert(
                    ['id_tiket' => $id_tiket],
                    [
                        'kos_items' => json_encode(
                            $loanInformation,
                            JSON_THROW_ON_ERROR
                                | JSON_UNESCAPED_UNICODE
                        ),
                        'disemak_oleh' => $request->user()->nama,
                        'pengguna_ic' => $ticket->pengguna_ic,
                        'updated_at' => now(),
                    ]
                );

                DB::table('tugasan_tiket')
                    ->where('id_tiket', $id_tiket)
                    ->delete();

                DB::table('tugasan_tiket')->insert([
                    'id_tiket' => $id_tiket,
                    'no_ic' => $pic->no_ic,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $updatedAssetCount = DB::table('aset')
                    ->whereIn('serial_no', $serialNumbers)
                    ->where('status', 'Tersedia')
                    ->update([
                        'status' => 'Dipinjam',
                        'updated_at' => now(),
                    ]);

                if (
                    $updatedAssetCount
                    !== (int) $validated['kuantiti_lulus']
                ) {
                    throw ValidationException::withMessages([
                        'senarai_aset' => 'Status inventori telah berubah. Sila jana semula senarai peralatan.',
                    ]);
                }

                $ticket->update([
                    'status_tiket' => config(
                        's2p_workflow.statuses.in_progress',
                        'Dalam Tindakan'
                    ),
                ]);

                $ticket->rekodLog(
                    config(
                        's2p_workflow.trail_events.reviewed',
                        'DISEMAK'
                    ),
                    'Oleh '.$request->user()->nama,
                    'INFO'
                );

                return [
                    $ticket->fresh(),
                    $pic,
                    $validated['id_aset'],
                ];
            },
            3
        );

        try {
            $pic->notify(
                new TugasanPicNoti(
                    $ticket,
                    $assetName,
                    $request->user()->nama
                )
            );
        } catch (\Throwable $exception) {
            Log::error(
                'Semakan peminjaman berjaya tetapi notifikasi PIC gagal.',
                [
                    'id_tiket' => $ticket->id_tiket,
                    'pic_ic' => $pic->no_ic,
                    'message' => $exception->getMessage(),
                ]
            );
        }

        return redirect()
            ->route(
                'tickets.show',
                ['id_tiket' => $ticket->id_tiket]
            )
            ->with(
                'success',
                'Maklumat peralatan berjaya disimpan dan petugas telah dilantik.'
            );
    }

    public function validateNetworkLkk(
        ValidateNetworkLkkRequest $request,
        string $id_tiket
    ): RedirectResponse {
        $validated = $request->validated();
        $validator = $request->user();

        $isCorrection =
            $validated['tindakan'] === 'PEMBETULAN';

        [
            $ticket,
            $reviewers,
            $subCategory,
        ] = DB::transaction(
            function () use (
                $id_tiket,
                $validator,
                $validated,
                $isCorrection
            ): array {
                $ticket = Tiket::query()
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->firstOrFail();

                $expectedStatus = config(
                    's2p_workflow.statuses.validation',
                    'Menunggu Validasi'
                );

                if (
                    $ticket->status_tiket
                    !== $expectedStatus
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'LKK ini bukan lagi dalam peringkat validasi.',
                    ]);
                }

                if (
                    $ticket->kategori
                    !== 'Konsultasi Rangkaian'
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk tiket Konsultasi Rangkaian.',
                    ]);
                }

                $networkRecord = DB::table(
                    'konsultasi_rangkaian'
                )
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->first();

                if ($networkRecord === null) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Rekod Konsultasi Rangkaian tidak ditemui.',
                    ]);
                }

                $subCategory = trim(
                    (string) $networkRecord->sub_kategori
                );

                $allowedSubCategories = config(
                    's2p_workflow.flows.rangkaian.sub_kategori',
                    []
                );

                if (
                    ! in_array(
                        $subCategory,
                        $allowedSubCategories,
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Subkategori tiket tidak menggunakan aliran Konsultasi Rangkaian.',
                    ]);
                }

                $report = Laporan::query()
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->first();

                if (
                    $report === null
                    || trim(
                        (string) $report->pendahuluan
                    ) === ''
                    || trim(
                        (string) $report->rumusan
                    ) === ''
                    || empty($report->logical_diagram)
                    || empty($report->physical_diagram)
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'LKK belum lengkap dan tidak boleh divalidasi.',
                    ]);
                }

                $reviewers = Pengguna::query()
                    ->where(
                        'peranan',
                        config(
                            's2p_workflow.flows.rangkaian.verification_role',
                            'ketua_utd'
                        )
                    )
                    ->where('status_pengguna', 'Aktif')
                    ->lockForUpdate()
                    ->get();

                if (
                    $isCorrection
                    && $reviewers->isEmpty()
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tiada KUTD aktif untuk menerima arahan pembetulan.',
                    ]);
                }

                if ($isCorrection) {
                    $ticket->update([
                        'status_tiket' => config(
                            's2p_workflow.statuses.chief_correction',
                            'Pembetulan Ketua'
                        ),
                        'ulasan_semakan' => $validated['ulasan'],
                        'tarikh_tutup' => null,
                    ]);

                    $ticket->rekodLog(
                        config(
                            's2p_workflow.trail_events.correction_requested',
                            'PEMBETULAN DIMINTA'
                        ),
                        'Oleh '.$validator->nama,
                        'PEMBETULAN'
                    );
                } else {
                    $report->update([
                        'disemak_oleh' => $validator->nama,
                    ]);

                    $ticket->update([
                        'status_tiket' => config(
                            's2p_workflow.statuses.completed',
                            'Selesai'
                        ),
                        'ulasan_semakan' => null,
                        'tarikh_tutup' => now(),
                    ]);

                    $ticket->rekodLog(
                        config(
                            's2p_workflow.trail_events.validated',
                            'DIVALIDASI'
                        ),
                        'Oleh '.$validator->nama,
                        'LULUS'
                    );

                    $ticket->rekodLog(
                        config(
                            's2p_workflow.trail_events.closed',
                            'TIKET DITUTUP'
                        ),
                        'Oleh '.$validator->nama,
                        'SELESAI'
                    );
                }

                DB::table('notifications')
                    ->where(
                        'notifiable_type',
                        $validator->getMorphClass()
                    )
                    ->where(
                        'notifiable_id',
                        (string) $validator->getKey()
                    )
                    ->whereNull('read_at')
                    ->where(
                        'data',
                        'LIKE',
                        '%'.$id_tiket.'%'
                    )
                    ->update([
                        'read_at' => now(),
                        'updated_at' => now(),
                    ]);

                return [
                    $ticket->fresh(),
                    $reviewers,
                    $subCategory,
                ];
            },
            3
        );

        if ($isCorrection) {
            try {
                Notification::send(
                    $reviewers,
                    new WorkflowChiefCorrectionNotification(
                        $ticket,
                        $validator->nama,
                        $validated['ulasan'],
                        $subCategory
                    )
                );
            } catch (\Throwable $exception) {
                Log::error(
                    'LKK Rangkaian dipulangkan tetapi notifikasi KUTD gagal.',
                    [
                        'id_tiket' => $ticket->id_tiket,
                        'reviewer_ids' => $reviewers
                            ->pluck('no_ic')
                            ->all(),
                        'message' => $exception->getMessage(),
                    ]
                );
            }
        }

        return redirect()
            ->route(
                'tickets.show',
                ['id_tiket' => $ticket->id_tiket]
            )
            ->with(
                'success',
                $isCorrection
                    ? 'LKK dipulangkan kepada KUTD untuk pembetulan.'
                    : 'LKK berjaya divalidasi dan tiket telah ditutup.'
            );
    }

    public function saveNetworkLkk(
        SaveNetworkLkkRequest $request,
        string $id_tiket
    ): RedirectResponse {
        $validated = $request->validated();
        $reviewer = $request->user();
        $isDraft = (bool) $validated['is_draft'];

        $newLogicalPath = null;
        $newPhysicalPath = null;
        $oldLogicalPath = null;
        $oldPhysicalPath = null;

        try {
            if ($request->hasFile('logical_diagram')) {
                $newLogicalPath = $request
                    ->file('logical_diagram')
                    ->store('diagrams', 'public');
            }

            if ($request->hasFile('physical_diagram')) {
                $newPhysicalPath = $request
                    ->file('physical_diagram')
                    ->store('diagrams', 'public');
            }

            [
                $ticket,
                $subCategory,
                $oldLogicalPath,
                $oldPhysicalPath,
            ] = DB::transaction(
                function () use (
                    $id_tiket,
                    $reviewer,
                    $validated,
                    $isDraft,
                    $newLogicalPath,
                    $newPhysicalPath
                ): array {
                    $ticket = Tiket::query()
                        ->where('id_tiket', $id_tiket)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (
                        $ticket->kategori
                        !== 'Konsultasi Rangkaian'
                    ) {
                        throw ValidationException::withMessages([
                            'sistem' => 'Tindakan ini hanya dibenarkan untuk tiket Konsultasi Rangkaian.',
                        ]);
                    }

                    $allowedStatuses = [
                        config(
                            's2p_workflow.statuses.ready_for_verification',
                            'Sedia Diverifikasi'
                        ),
                        config(
                            's2p_workflow.statuses.chief_correction',
                            'Pembetulan Ketua'
                        ),
                    ];

                    if (
                        ! in_array(
                            $ticket->status_tiket,
                            $allowedStatuses,
                            true
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'sistem' => 'Status tiket tidak membenarkan LKK disimpan atau dihantar.',
                        ]);
                    }

                    $networkRecord = DB::table(
                        'konsultasi_rangkaian'
                    )
                        ->where('id_tiket', $id_tiket)
                        ->lockForUpdate()
                        ->first();

                    if ($networkRecord === null) {
                        throw ValidationException::withMessages([
                            'sistem' => 'Rekod Konsultasi Rangkaian tidak ditemui.',
                        ]);
                    }

                    $subCategory = trim(
                        (string) $networkRecord->sub_kategori
                    );

                    $allowedSubCategories = config(
                        's2p_workflow.flows.rangkaian.sub_kategori',
                        []
                    );

                    if (
                        ! in_array(
                            $subCategory,
                            $allowedSubCategories,
                            true
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'sistem' => 'Subkategori tiket tidak menggunakan aliran Konsultasi Rangkaian.',
                        ]);
                    }

                    $existingReport = Laporan::query()
                        ->where('id_tiket', $id_tiket)
                        ->lockForUpdate()
                        ->first();

                    $oldLogicalPath =
                        $existingReport?->logical_diagram;

                    $oldPhysicalPath =
                        $existingReport?->physical_diagram;

                    $logicalPath =
                        $newLogicalPath
                        ?? $oldLogicalPath;

                    $physicalPath =
                        $newPhysicalPath
                        ?? $oldPhysicalPath;

                    if (
                        ! $isDraft
                        && (
                            empty($logicalPath)
                            || empty($physicalPath)
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'sistem' => 'Logical diagram dan physical diagram mesti disediakan sebelum LKK dihantar.',
                        ]);
                    }

                    $reportData = [
                        'pendahuluan' => $validated['pendahuluan']
                            ?? '',
                        'ulasan_teknikal' => $networkRecord->ulasan_teknikal,
                        'objektif' => json_encode(
                            $validated['objektif']
                            ?? [],
                            JSON_THROW_ON_ERROR
                            | JSON_UNESCAPED_UNICODE
                        ),
                        'cadangan_penambahbaikan' => json_encode(
                            $validated[
                                'cadangan_penambahbaikan'
                            ] ?? [],
                            JSON_THROW_ON_ERROR
                            | JSON_UNESCAPED_UNICODE
                        ),
                        'kos_items' => json_encode(
                            $validated['kos_items']
                            ?? [],
                            JSON_THROW_ON_ERROR
                            | JSON_UNESCAPED_UNICODE
                        ),
                        'rumusan' => $validated['rumusan']
                            ?? '',
                        'logical_diagram' => $logicalPath,
                        'physical_diagram' => $physicalPath,
                        'disediakan_oleh' => $reviewer->nama,
                        'disemak_oleh' => $existingReport?->disemak_oleh,
                        'pengguna_ic' => $reviewer->no_ic,
                    ];

                    Laporan::query()->updateOrCreate(
                        ['id_tiket' => $id_tiket],
                        $reportData
                    );

                    if (! $isDraft) {
                        $ticket->update([
                            'status_tiket' => config(
                                's2p_workflow.statuses.validation',
                                'Menunggu Validasi'
                            ),
                            'ulasan_semakan' => null,
                            'disahkan_oleh_ic' => $reviewer->no_ic,
                        ]);

                        $ticket->rekodLog(
                            config(
                                's2p_workflow.trail_events.verified',
                                'DIVERIFIKASI'
                            ),
                            'Oleh '.$reviewer->nama,
                            'DIVERIFIKASI'
                        );

                        DB::table('notifications')
                            ->where(
                                'notifiable_type',
                                $reviewer->getMorphClass()
                            )
                            ->where(
                                'notifiable_id',
                                (string) $reviewer->getKey()
                            )
                            ->whereNull('read_at')
                            ->where(
                                'data',
                                'LIKE',
                                '%'.$id_tiket.'%'
                            )
                            ->update([
                                'read_at' => now(),
                                'updated_at' => now(),
                            ]);
                    }

                    return [
                        $ticket->fresh(),
                        $subCategory,
                        $oldLogicalPath,
                        $oldPhysicalPath,
                    ];
                },
                3
            );
        } catch (\Throwable $exception) {
            if ($newLogicalPath !== null) {
                Storage::disk('public')->delete(
                    $newLogicalPath
                );
            }

            if ($newPhysicalPath !== null) {
                Storage::disk('public')->delete(
                    $newPhysicalPath
                );
            }

            throw $exception;
        }

        try {
            if (
                $newLogicalPath !== null
                && $oldLogicalPath !== null
                && $oldLogicalPath !== $newLogicalPath
            ) {
                Storage::disk('public')->delete(
                    $oldLogicalPath
                );
            }

            if (
                $newPhysicalPath !== null
                && $oldPhysicalPath !== null
                && $oldPhysicalPath !== $newPhysicalPath
            ) {
                Storage::disk('public')->delete(
                    $oldPhysicalPath
                );
            }
        } catch (\Throwable $exception) {
            Log::warning(
                'LKK Rangkaian disimpan tetapi fail diagram lama gagal dipadam.',
                [
                    'id_tiket' => $ticket->id_tiket,
                    'message' => $exception->getMessage(),
                ]
            );
        }

        if (! $isDraft) {
            $validators = Pengguna::query()
                ->where(
                    'peranan',
                    config(
                        's2p_workflow.flows.rangkaian.validation_role',
                        'ketua_wilayah'
                    )
                )
                ->where('status_pengguna', 'Aktif')
                ->get();

            try {
                if ($validators->isNotEmpty()) {
                    Notification::send(
                        $validators,
                        new WorkflowValidationNotification(
                            $ticket,
                            $reviewer->nama,
                            $subCategory
                        )
                    );
                }
            } catch (\Throwable $exception) {
                Log::error(
                    'LKK Rangkaian berjaya dihantar tetapi notifikasi Ketua Wilayah gagal.',
                    [
                        'id_tiket' => $ticket->id_tiket,
                        'validator_ids' => $validators
                            ->pluck('no_ic')
                            ->all(),
                        'message' => $exception->getMessage(),
                    ]
                );
            }
        }

        return redirect()
            ->route(
                'tickets.show',
                ['id_tiket' => $ticket->id_tiket]
            )
            ->with(
                'success',
                $isDraft
                    ? 'Draf LKK Rangkaian berjaya disimpan.'
                    : 'LKK Rangkaian berjaya dihantar untuk validasi Ketua Wilayah.'
            );
    }

    public function reviewNetworkSiteReport(
        ReviewNetworkSiteReportRequest $request,
        string $id_tiket
    ): RedirectResponse {
        $validated = $request->validated();
        $reviewer = $request->user();

        $isCorrection =
            $validated['tindakan'] === 'PEMBETULAN';

        [
            $ticket,
            $technicians,
            $subCategory,
        ] = DB::transaction(
            function () use (
                $id_tiket,
                $reviewer,
                $validated,
                $isCorrection
            ): array {
                $ticket = Tiket::query()
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->firstOrFail();

                $expectedStatus = config(
                    's2p_workflow.statuses.report_review',
                    'Menunggu Semakan Laporan'
                );

                if (
                    $ticket->status_tiket
                    !== $expectedStatus
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Laporan tapak ini bukan lagi dalam peringkat semakan.',
                    ]);
                }

                if (
                    $ticket->kategori
                    !== 'Konsultasi Rangkaian'
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk tiket Konsultasi Rangkaian.',
                    ]);
                }

                $networkRecord = DB::table(
                    'konsultasi_rangkaian'
                )
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->first();

                if ($networkRecord === null) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Rekod laporan tapak Rangkaian tidak ditemui.',
                    ]);
                }

                $subCategory = trim(
                    (string) $networkRecord->sub_kategori
                );

                $allowedSubCategories = config(
                    's2p_workflow.flows.rangkaian.sub_kategori',
                    []
                );

                if (
                    ! in_array(
                        $subCategory,
                        $allowedSubCategories,
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Subkategori tiket tidak menggunakan aliran Konsultasi Rangkaian.',
                    ]);
                }

                if (
                    trim(
                        (string) $networkRecord->rumusan
                    ) === ''
                    || trim(
                        (string) $networkRecord->ulasan_teknikal
                    ) === ''
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Laporan tapak belum lengkap dan tidak boleh disemak.',
                    ]);
                }

                $assignedIds = DB::table(
                    'tugasan_tiket'
                )
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->pluck('no_ic')
                    ->all();

                $technicians = Pengguna::query()
                    ->whereIn('no_ic', $assignedIds)
                    ->where('peranan', 'juruteknik')
                    ->where('status_pengguna', 'Aktif')
                    ->lockForUpdate()
                    ->get();

                if (
                    $isCorrection
                    && $technicians->isEmpty()
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tiada Juruteknik aktif ditugaskan untuk menerima arahan pembetulan.',
                    ]);
                }

                $newStatus = $isCorrection
                    ? config(
                        's2p_workflow.statuses.pic_correction',
                        'Laporan Perlu Pembetulan'
                    )
                    : config(
                        's2p_workflow.statuses.ready_for_verification',
                        'Sedia Diverifikasi'
                    );

                $ticket->update([
                    'status_tiket' => $newStatus,
                    'ulasan_semakan' => $isCorrection
                        ? $validated['ulasan']
                        : null,
                ]);

                $ticket->rekodLog(
                    $isCorrection
                        ? config(
                            's2p_workflow.trail_events.correction_requested',
                            'PEMBETULAN DIMINTA'
                        )
                        : config(
                            's2p_workflow.trail_events.confirmed',
                            'DISAHKAN'
                        ),
                    'Oleh '.$reviewer->nama,
                    $isCorrection
                        ? 'PEMBETULAN'
                        : 'LULUS'
                );

                DB::table('notifications')
                    ->where(
                        'notifiable_type',
                        $reviewer->getMorphClass()
                    )
                    ->where(
                        'notifiable_id',
                        (string) $reviewer->getKey()
                    )
                    ->whereNull('read_at')
                    ->where(
                        'data',
                        'LIKE',
                        '%'.$id_tiket.'%'
                    )
                    ->update([
                        'read_at' => now(),
                        'updated_at' => now(),
                    ]);

                return [
                    $ticket->fresh(),
                    $technicians,
                    $subCategory,
                ];
            },
            3
        );

        if ($isCorrection) {
            try {
                Notification::send(
                    $technicians,
                    new WorkflowNetworkCorrectionNotification(
                        $ticket,
                        $reviewer->nama,
                        $validated['ulasan'],
                        $subCategory
                    )
                );
            } catch (\Throwable $exception) {
                Log::error(
                    'Laporan tapak Rangkaian dipulangkan tetapi notifikasi Juruteknik gagal.',
                    [
                        'id_tiket' => $ticket->id_tiket,
                        'technician_ids' => $technicians
                            ->pluck('no_ic')
                            ->all(),
                        'message' => $exception->getMessage(),
                    ]
                );
            }
        }

        return redirect()
            ->route(
                'tickets.show',
                ['id_tiket' => $ticket->id_tiket]
            )
            ->with(
                'success',
                $isCorrection
                    ? 'Laporan tapak dipulangkan kepada Juruteknik untuk pembetulan.'
                    : 'Laporan tapak diterima. Penyediaan LKK boleh diteruskan.'
            );
    }

    public function submitNetworkSiteReport(
        SubmitNetworkSiteReportRequest $request,
        string $id_tiket
    ): RedirectResponse {
        $validated = $request->validated();
        $technician = $request->user();

        $newLogicalPath = null;
        $newPhysicalPath = null;
        $oldLogicalPath = null;
        $oldPhysicalPath = null;

        try {
            if ($request->hasFile('logical_diagram')) {
                $newLogicalPath = $request
                    ->file('logical_diagram')
                    ->store('diagrams', 'public');
            }

            if ($request->hasFile('physical_diagram')) {
                $newPhysicalPath = $request
                    ->file('physical_diagram')
                    ->store('diagrams', 'public');
            }

            [
                $ticket,
                $subCategory,
                $oldLogicalPath,
                $oldPhysicalPath,
            ] = DB::transaction(
                function () use (
                    $id_tiket,
                    $technician,
                    $validated,
                    $newLogicalPath,
                    $newPhysicalPath
                ): array {
                    $ticket = Tiket::query()
                        ->where('id_tiket', $id_tiket)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (
                        $ticket->kategori
                        !== 'Konsultasi Rangkaian'
                    ) {
                        throw ValidationException::withMessages([
                            'sistem' => 'Tindakan ini hanya dibenarkan untuk tiket Konsultasi Rangkaian.',
                        ]);
                    }

                    $allowedStatuses = [
                        config(
                            's2p_workflow.statuses.in_progress',
                            'Dalam Tindakan'
                        ),
                        config(
                            's2p_workflow.statuses.pic_correction',
                            'Laporan Perlu Pembetulan'
                        ),
                    ];

                    if (
                        ! in_array(
                            $ticket->status_tiket,
                            $allowedStatuses,
                            true
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'sistem' => 'Status tiket tidak membenarkan laporan tapak dihantar.',
                        ]);
                    }

                    $isAssigned = DB::table('tugasan_tiket')
                        ->where('id_tiket', $id_tiket)
                        ->where('no_ic', $technician->no_ic)
                        ->lockForUpdate()
                        ->exists();

                    if (! $isAssigned) {
                        throw ValidationException::withMessages([
                            'sistem' => 'Anda bukan Juruteknik yang dilantik untuk tiket ini.',
                        ]);
                    }

                    $networkRecord = DB::table(
                        'konsultasi_rangkaian'
                    )
                        ->where('id_tiket', $id_tiket)
                        ->lockForUpdate()
                        ->first();

                    if ($networkRecord === null) {
                        throw ValidationException::withMessages([
                            'sistem' => 'Rekod Konsultasi Rangkaian tidak ditemui.',
                        ]);
                    }

                    $subCategory = trim(
                        (string) $networkRecord->sub_kategori
                    );

                    $allowedSubCategories = config(
                        's2p_workflow.flows.rangkaian.sub_kategori',
                        []
                    );

                    if (
                        ! in_array(
                            $subCategory,
                            $allowedSubCategories,
                            true
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'sistem' => 'Subkategori tiket tidak menggunakan aliran Konsultasi Rangkaian.',
                        ]);
                    }

                    $technicalReviews = json_encode(
                        $validated['ulasan_teknikal'],
                        JSON_THROW_ON_ERROR
                        | JSON_UNESCAPED_UNICODE
                    );

                    $recommendations = json_encode(
                        $validated['cadangan_penambahbaikan'],
                        JSON_THROW_ON_ERROR
                        | JSON_UNESCAPED_UNICODE
                    );

                    $existingReport = Laporan::query()
                        ->where('id_tiket', $id_tiket)
                        ->lockForUpdate()
                        ->first();

                    $oldLogicalPath =
                        $existingReport?->logical_diagram;

                    $oldPhysicalPath =
                        $existingReport?->physical_diagram;

                    $logicalPath =
                        $newLogicalPath
                        ?? $oldLogicalPath;

                    $physicalPath =
                        $newPhysicalPath
                        ?? $oldPhysicalPath;

                    if (
                        empty($logicalPath)
                        || empty($physicalPath)
                    ) {
                        throw ValidationException::withMessages([
                            'sistem' => 'Logical diagram dan physical diagram mesti disediakan sebelum laporan dihantar.',
                        ]);
                    }

                    DB::table('konsultasi_rangkaian')
                        ->where('id_tiket', $id_tiket)
                        ->update([
                            'nama_lokasi_bangunan' => $validated['nama_lokasi_bangunan']
                                ?? null,
                            'jenis_premis' => $validated['jenis_premis'],
                            'bilik_server' => $validated['bilik_server'],
                            'rack_server' => $validated['rack_server'],
                            'sumber_kuasa' => $validated['sumber_kuasa'],
                            'persekitaran_fizikal' => $validated['persekitaran_fizikal'],
                            'liputan' => $validated['liputan'],
                            'jenis_capaian' => $validated['jenis_capaian'],
                            'kelajuan' => $validated['kelajuan'],
                            'lan' => $validated['lan'],
                            'ap' => $validated['ap'],
                            'firewall' => $validated['firewall'],
                            'rumusan' => $validated['rumusan'],
                            'ulasan_teknikal' => $technicalReviews,
                            'updated_at' => now(),
                        ]);

                    Laporan::query()->updateOrCreate(
                        [
                            'id_tiket' => $id_tiket,
                        ],
                        [
                            'ulasan_teknikal' => $technicalReviews,
                            'cadangan_penambahbaikan' => $recommendations,
                            'logical_diagram' => $logicalPath,
                            'physical_diagram' => $physicalPath,
                            'pengguna_ic' => $technician->no_ic,
                        ]
                    );

                    $ticket->update([
                        'status_tiket' => config(
                            's2p_workflow.statuses.report_review',
                            'Menunggu Semakan Laporan'
                        ),
                        'ulasan_semakan' => null,
                    ]);

                    $ticket->rekodLog(
                        config(
                            's2p_workflow.trail_events.performed',
                            'DILAKSANA'
                        ),
                        'Oleh '.$technician->nama,
                        'INFO'
                    );

                    DB::table('notifications')
                        ->where(
                            'notifiable_type',
                            $technician->getMorphClass()
                        )
                        ->where(
                            'notifiable_id',
                            (string) $technician->getKey()
                        )
                        ->whereNull('read_at')
                        ->where(
                            'data',
                            'LIKE',
                            '%'.$id_tiket.'%'
                        )
                        ->update([
                            'read_at' => now(),
                            'updated_at' => now(),
                        ]);

                    return [
                        $ticket->fresh(),
                        $subCategory,
                        $oldLogicalPath,
                        $oldPhysicalPath,
                    ];
                },
                3
            );
        } catch (\Throwable $exception) {
            if ($newLogicalPath !== null) {
                Storage::disk('public')->delete(
                    $newLogicalPath
                );
            }

            if ($newPhysicalPath !== null) {
                Storage::disk('public')->delete(
                    $newPhysicalPath
                );
            }

            throw $exception;
        }

        try {
            if (
                $newLogicalPath !== null
                && $oldLogicalPath !== null
                && $oldLogicalPath !== $newLogicalPath
            ) {
                Storage::disk('public')->delete(
                    $oldLogicalPath
                );
            }

            if (
                $newPhysicalPath !== null
                && $oldPhysicalPath !== null
                && $oldPhysicalPath !== $newPhysicalPath
            ) {
                Storage::disk('public')->delete(
                    $oldPhysicalPath
                );
            }
        } catch (\Throwable $exception) {
            Log::warning(
                'Laporan tapak disimpan tetapi diagram lama gagal dipadam.',
                [
                    'id_tiket' => $ticket->id_tiket,
                    'message' => $exception->getMessage(),
                ]
            );
        }

        $reviewers = Pengguna::query()
            ->where(
                'peranan',
                config(
                    's2p_workflow.flows.rangkaian.verification_role',
                    'ketua_utd'
                )
            )
            ->where('status_pengguna', 'Aktif')
            ->get();

        try {
            if ($reviewers->isNotEmpty()) {
                Notification::send(
                    $reviewers,
                    new WorkflowReportReviewNotification(
                        $ticket,
                        $technician->nama,
                        $subCategory
                    )
                );
            }
        } catch (\Throwable $exception) {
            Log::error(
                'Laporan tapak Rangkaian berjaya dihantar tetapi notifikasi KUTD gagal.',
                [
                    'id_tiket' => $ticket->id_tiket,
                    'reviewer_ids' => $reviewers->pluck('no_ic')->all(),
                    'message' => $exception->getMessage(),
                ]
            );
        }

        return redirect()
            ->route(
                'tickets.show',
                ['id_tiket' => $ticket->id_tiket]
            )
            ->with(
                'success',
                'Laporan tapak berjaya dihantar untuk semakan KUTD.'
            );
    }

    public function submitHelpdeskByPic(
        SubmitHelpdeskTicketRequest $request,
        string $id_tiket
    ): RedirectResponse {
        $validated = $request->validated();
        $pic = $request->user();

        [$ticket, $subCategory] = DB::transaction(
            function () use (
                $id_tiket,
                $pic,
                $validated
            ): array {
                $ticket = Tiket::query()
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->firstOrFail();

                $expectedStatus = config(
                    's2p_workflow.statuses.in_progress',
                    'Dalam Tindakan'
                );

                if ($ticket->status_tiket !== $expectedStatus) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tiket ini tidak berada dalam status Dalam Tindakan.',
                    ]);
                }

                if ($ticket->kategori !== 'Meja Bantuan') {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk tiket Meja Bantuan.',
                    ]);
                }

                $helpdeskRecord = DB::table('meja_bantuan')
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->first();

                if ($helpdeskRecord === null) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Rekod kategori Meja Bantuan tidak ditemui.',
                    ]);
                }

                $subCategory = trim(
                    (string) $helpdeskRecord->sub_kategori
                );

                $allowedSubCategories = config(
                    's2p_workflow.flows.meja_bantuan.sub_kategori',
                    []
                );

                if (
                    $subCategory === 'Peminjaman Peralatan ICT'
                    || ! in_array(
                        $subCategory,
                        $allowedSubCategories,
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Subkategori tiket tidak menggunakan aliran Meja Bantuan biasa.',
                    ]);
                }

                $isAssignedPic = DB::table('tugasan_tiket')
                    ->where('id_tiket', $id_tiket)
                    ->where('no_ic', $pic->no_ic)
                    ->exists();

                if (! $isAssignedPic) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Anda bukan petugas yang dilantik untuk tiket ini.',
                    ]);
                }

                $ticket->update([
                    'catatan_penutupan' => $validated[
                        'catatan_penutupan'
                    ],
                    'status_tiket' => config(
                        's2p_workflow.statuses.confirmation',
                        'Menunggu Pengesahan'
                    ),
                ]);

                $ticket->rekodLog(
                    config(
                        's2p_workflow.trail_events.performed',
                        'DILAKSANA'
                    ),
                    'Oleh '.$pic->nama,
                    'INFO'
                );

                return [
                    $ticket->fresh(),
                    $subCategory,
                ];
            },
            3
        );

        try {
            $reviewers = Pengguna::query()
                ->where('peranan', 'ketua_utd')
                ->where('status_pengguna', 'Aktif')
                ->get();

            if ($reviewers->isEmpty()) {
                Log::warning(
                    'Tiada KUTD aktif untuk pengesahan Meja Bantuan.',
                    ['id_tiket' => $ticket->id_tiket]
                );
            } else {
                Notification::send(
                    $reviewers,
                    new WorkflowConfirmationNotification(
                        $ticket,
                        $pic->nama,
                        $subCategory
                    )
                );
            }
        } catch (\Throwable $exception) {
            Log::error(
                'Penghantaran Meja Bantuan berjaya tetapi notifikasi KUTD gagal.',
                [
                    'id_tiket' => $ticket->id_tiket,
                    'message' => $exception->getMessage(),
                ]
            );
        }

        return redirect()
            ->route(
                'tickets.show',
                ['id_tiket' => $ticket->id_tiket]
            )
            ->with(
                'success',
                'Catatan tindakan berjaya dihantar kepada KUTD.'
            );
    }

    public function confirmHelpdesk(
        ConfirmHelpdeskTicketRequest $request,
        string $id_tiket
    ): RedirectResponse {
        $validated = $request->validated();
        $reviewer = $request->user();

        $ticket = DB::transaction(
            function () use (
                $id_tiket,
                $reviewer,
                $validated
            ): Tiket {
                $ticket = Tiket::query()
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->firstOrFail();

                $expectedStatus = config(
                    's2p_workflow.statuses.confirmation',
                    'Menunggu Pengesahan'
                );

                if ($ticket->status_tiket !== $expectedStatus) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tiket ini bukan lagi dalam peringkat pengesahan.',
                    ]);
                }

                if ($ticket->kategori !== 'Meja Bantuan') {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk tiket Meja Bantuan.',
                    ]);
                }

                $helpdeskRecord = DB::table('meja_bantuan')
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->first();

                if ($helpdeskRecord === null) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Rekod kategori Meja Bantuan tidak ditemui.',
                    ]);
                }

                $subCategory = trim(
                    (string) $helpdeskRecord->sub_kategori
                );

                $allowedSubCategories = config(
                    's2p_workflow.flows.meja_bantuan.sub_kategori',
                    []
                );

                if (
                    $subCategory === 'Peminjaman Peralatan ICT'
                    || ! in_array(
                        $subCategory,
                        $allowedSubCategories,
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Subkategori tiket tidak menggunakan aliran Meja Bantuan biasa.',
                    ]);
                }

                if (
                    trim(
                        (string) $ticket->catatan_penutupan
                    ) === ''
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Catatan tindakan petugas belum dilengkapkan.',
                    ]);
                }

                $ticket->update([
                    'status_tiket' => config(
                        's2p_workflow.statuses.completed',
                        'Selesai'
                    ),
                    'tarikh_tutup' => now(),
                    'ulasan_semakan' => $validated['ulasan'] ?? null,
                    'disahkan_oleh_ic' => $reviewer->no_ic,
                ]);

                $ticket->rekodLog(
                    config(
                        's2p_workflow.trail_events.confirmed',
                        'DISAHKAN'
                    ),
                    'Oleh '.$reviewer->nama,
                    'DISAHKAN'
                );

                $ticket->rekodLog(
                    config(
                        's2p_workflow.trail_events.closed',
                        'TIKET DITUTUP'
                    ),
                    'Oleh '.$reviewer->nama,
                    'SELESAI'
                );

                return $ticket->fresh();
            },
            3
        );

        return redirect()
            ->route(
                'tickets.show',
                ['id_tiket' => $ticket->id_tiket]
            )
            ->with(
                'success',
                'Tiket Meja Bantuan berjaya disahkan dan ditutup.'
            );
    }

    public function confirmLoan(
        ConfirmLoanTicketRequest $request,
        string $id_tiket
    ): RedirectResponse {
        $validated = $request->validated();
        $user = $request->user();

        $ticket = DB::transaction(
            function () use (
                $id_tiket,
                $user,
                $validated
            ): Tiket {
                $ticket = Tiket::query()
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->firstOrFail();

                $expectedStatus = config(
                    's2p_workflow.statuses.confirmation',
                    'Menunggu Pengesahan'
                );

                if ($ticket->status_tiket !== $expectedStatus) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tiket ini bukan lagi dalam peringkat pengesahan.',
                    ]);
                }

                $isLoanTicket = $ticket->kategori === 'Meja Bantuan'
                    && DB::table('meja_bantuan')
                        ->where('id_tiket', $id_tiket)
                        ->where(
                            'sub_kategori',
                            'Peminjaman Peralatan ICT'
                        )
                        ->exists();

                if (! $isLoanTicket) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk Peminjaman Peralatan ICT.',
                    ]);
                }

                $report = DB::table('laporan')
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->first();

                if ($report === null || empty($report->kos_items)) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Maklumat peminjaman tidak ditemui.',
                    ]);
                }

                try {
                    $loanInformation = json_decode(
                        $report->kos_items,
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    );
                } catch (\JsonException) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Struktur rekod peminjaman tidak sah.',
                    ]);
                }

                $assetItems = $loanInformation['senarai_siri']
                    ?? null;

                if (! is_array($assetItems) || $assetItems === []) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Senarai peralatan peminjaman tidak sah.',
                    ]);
                }

                $hasIncompleteForm = collect($assetItems)
                    ->contains(
                        function ($item): bool {
                            if (! is_array($item)) {
                                return true;
                            }

                            $serialNumber = trim(
                                (string) (
                                    $item['serial_no']
                                    ?? $item['no_siri']
                                    ?? ''
                                )
                            );

                            $hardwareStatus = trim(
                                (string) (
                                    $item['status_perkakasan']
                                    ?? ''
                                )
                            );

                            $usageMode = trim(
                                (string) (
                                    $item['mod_penggunaan']
                                    ?? ''
                                )
                            );

                            $recipientPosition = trim(
                                (string) (
                                    $item['jawatan_penerima']
                                    ?? ''
                                )
                            );

                            return $serialNumber === ''
                                || ! in_array(
                                    $hardwareStatus,
                                    ['Baru', 'Terpakai'],
                                    true
                                )
                                || ! in_array(
                                    $usageMode,
                                    ['Dipinjamkan', 'Diserahkan'],
                                    true
                                )
                                || $recipientPosition === '';
                        }
                    );

                if ($hasIncompleteForm) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Borang peminjaman belum lengkap dan tidak boleh disahkan.',
                    ]);
                }

                $closedAt = now();

                $ticket->update([
                    'status_tiket' => config(
                        's2p_workflow.statuses.completed',
                        'Selesai'
                    ),
                    'tarikh_tutup' => $closedAt,
                    'ulasan_semakan' => $validated['ulasan']
                        ?? null,
                ]);

                $ticket->rekodLog(
                    config(
                        's2p_workflow.trail_events.confirmed',
                        'DISAHKAN'
                    ),
                    'Oleh '.$user->nama,
                    'DISAHKAN'
                );

                $ticket->rekodLog(
                    config(
                        's2p_workflow.trail_events.closed',
                        'TIKET DITUTUP'
                    ),
                    'Oleh '.$user->nama,
                    'SELESAI'
                );

                return $ticket->fresh();
            },
            3
        );

        return redirect()
            ->route(
                'tickets.show',
                ['id_tiket' => $ticket->id_tiket]
            )
            ->with(
                'success',
                'Tiket peminjaman berjaya disahkan dan ditutup.'
            );
    }

    public function saveLoanForm(
        SaveLoanFormRequest $request,
        string $id_tiket
    ): RedirectResponse {
        $validated = $request->validated();

        DB::transaction(
            function () use (
                $request,
                $id_tiket,
                $validated
            ): void {
                $ticket = Tiket::query()
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureLoanPicCanAct(
                    $ticket,
                    $request->user()->no_ic
                );

                $report = DB::table('laporan')
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->first();

                if ($report === null || empty($report->kos_items)) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Rekod kelulusan peminjaman tidak ditemui.',
                    ]);
                }

                $loanInformation = json_decode(
                    $report->kos_items,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                if (
                    ! is_array($loanInformation) ||
                    ! is_array(
                        $loanInformation['senarai_siri'] ?? null
                    )
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Struktur rekod peminjaman tidak sah.',
                    ]);
                }

                $itemFound = false;

                foreach (
                    $loanInformation['senarai_siri'] as &$assetItem
                ) {
                    $serialNumber = $assetItem['serial_no']
                        ?? $assetItem['no_siri']
                        ?? null;

                    if (
                        (string) $serialNumber
                        !== (string) $validated['serial_no']
                    ) {
                        continue;
                    }

                    $itemFound = true;
                    $assetItem['no_pendaftaran_harta']
                        = $validated['no_harta'] ?? null;
                    $assetItem['status_perkakasan']
                        = $validated['status_perkakasan'];
                    $assetItem['mod_penggunaan']
                        = $validated['mod_penggunaan'];
                    $assetItem['jawatan_penerima']
                        = $validated['jawatan_penerima'];
                    $assetItem['catatan']
                        = $validated['catatan'] ?? null;

                    break;
                }

                unset($assetItem);

                if (! $itemFound) {
                    throw ValidationException::withMessages([
                        'serial_no' => 'Nombor siri tidak termasuk dalam peminjaman tiket ini.',
                    ]);
                }

                DB::table('laporan')
                    ->where('id_tiket', $id_tiket)
                    ->update([
                        'kos_items' => json_encode(
                            $loanInformation,
                            JSON_THROW_ON_ERROR
                                | JSON_UNESCAPED_UNICODE
                        ),
                        'updated_at' => now(),
                    ]);
            },
            3
        );

        return back()->with(
            'success',
            'Maklumat borang peminjaman berjaya disimpan.'
        );
    }

    public function submitLoanByPic(
        Request $request,
        string $id_tiket
    ): RedirectResponse {
        $user = $request->user();

        abort_unless(
            $user !== null
                && $user->status_pengguna === 'Aktif'
                && $user->peranan === 'juruteknik',
            403,
            'Hanya Juruteknik aktif dibenarkan menghantar borang peminjaman.'
        );

        $ticket = DB::transaction(
            function () use (
                $user,
                $id_tiket
            ): Tiket {
                $ticket = Tiket::query()
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureLoanPicCanAct(
                    $ticket,
                    $user->no_ic
                );

                $report = DB::table('laporan')
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->first();

                if ($report === null || empty($report->kos_items)) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Maklumat peminjaman tidak ditemui.',
                    ]);
                }

                $loanInformation = json_decode(
                    $report->kos_items,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                $assetItems = $loanInformation['senarai_siri']
                    ?? null;

                if (! is_array($assetItems) || $assetItems === []) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Senarai peralatan peminjaman tidak sah.',
                    ]);
                }

                $hasIncompleteForm = collect($assetItems)
                    ->contains(
                        function (array $item): bool {
                            $serialNumber = trim(
                                (string) (
                                    $item['serial_no']
                                    ?? $item['no_siri']
                                    ?? ''
                                )
                            );

                            $hardwareStatus = trim(
                                (string) (
                                    $item['status_perkakasan']
                                    ?? ''
                                )
                            );

                            $usageMode = trim(
                                (string) (
                                    $item['mod_penggunaan']
                                    ?? ''
                                )
                            );

                            $recipientPosition = trim(
                                (string) (
                                    $item['jawatan_penerima']
                                    ?? ''
                                )
                            );

                            return $serialNumber === ''
                                || ! in_array(
                                    $hardwareStatus,
                                    ['Baru', 'Terpakai'],
                                    true
                                )
                                || ! in_array(
                                    $usageMode,
                                    ['Dipinjamkan', 'Diserahkan'],
                                    true
                                )
                                || $recipientPosition === '';
                        }
                    );

                if ($hasIncompleteForm) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Sila lengkapkan borang bagi semua peralatan sebelum dihantar kepada KUTD.',
                    ]);
                }

                $ticket->update([
                    'status_tiket' => config(
                        's2p_workflow.statuses.confirmation',
                        'Menunggu Pengesahan'
                    ),
                ]);

                $ticket->rekodLog(
                    config(
                        's2p_workflow.trail_events.performed',
                        'DILAKSANA'
                    ),
                    'Oleh '.$user->nama,
                    'INFO'
                );

                return $ticket->fresh();
            },
            3
        );

        try {
            $reviewers = Pengguna::query()
                ->where('peranan', 'ketua_utd')
                ->where('status_pengguna', 'Aktif')
                ->get();

            if ($reviewers->isEmpty()) {
                Log::warning(
                    'Tiada KUTD aktif untuk pengesahan peminjaman.',
                    ['id_tiket' => $ticket->id_tiket]
                );
            } else {
                Notification::send(
                    $reviewers,
                    new PengesahanKetuaNoti(
                        $ticket,
                        $user->nama,
                        true
                    )
                );
            }
        } catch (\Throwable $exception) {
            Log::error(
                'Penghantaran PIC berjaya tetapi notifikasi KUTD gagal.',
                [
                    'id_tiket' => $ticket->id_tiket,
                    'message' => $exception->getMessage(),
                ]
            );
        }

        return redirect()
            ->route(
                'tickets.show',
                ['id_tiket' => $ticket->id_tiket]
            )
            ->with(
                'success',
                'Borang peminjaman berjaya dihantar kepada KUTD.'
            );
    }

    public function submitProcurementReport(
        SubmitProcurementReportRequest $request,
        string $id_tiket
    ): RedirectResponse {
        $validated = $request->validated();
        $technician = $request->user();

        [
            $ticket,
            $reviewers,
        ] = DB::transaction(
            function () use (
                $id_tiket,
                $validated,
                $technician
            ): array {
                $ticket = Tiket::query()
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->firstOrFail();

                $allowedStatuses = [
                    config(
                        's2p_workflow.statuses.in_progress',
                        'Dalam Tindakan'
                    ),
                    config(
                        's2p_workflow.statuses.pic_correction',
                        'Laporan Perlu Pembetulan'
                    ),
                ];

                if (
                    ! in_array(
                        $ticket->status_tiket,
                        $allowedStatuses,
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Status tiket tidak membenarkan laporan Pembekalan dihantar.',
                    ]);
                }

                if (
                    $ticket->kategori
                    !== 'Transformasi Digital'
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk tiket Transformasi Digital.',
                    ]);
                }

                $digitalRecord = DB::table(
                    'transformasi_digital'
                )
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->first();

                if (
                    $digitalRecord === null
                    || trim(
                        (string) $digitalRecord->sub_kategori
                    ) !== 'Pembekalan Peralatan ICT'
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk Pembekalan Peralatan ICT.',
                    ]);
                }

                $isAssigned = DB::table('tugasan_tiket')
                    ->where('id_tiket', $id_tiket)
                    ->where(
                        'no_ic',
                        $technician->no_ic
                    )
                    ->exists();

                if (! $isAssigned) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Anda bukan Juruteknik yang dilantik untuk tiket ini.',
                    ]);
                }

                $reportExists = DB::table('laporan')
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->exists();

                if (! $reportExists) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Maklumat awal Pembekalan oleh KUPP tidak ditemui.',
                    ]);
                }

                DB::table('laporan')
                    ->where('id_tiket', $id_tiket)
                    ->update([
                        'hasil_kajian' => json_encode(
                            $validated['hasil_kajian'],
                            JSON_THROW_ON_ERROR
                            | JSON_UNESCAPED_UNICODE
                        ),
                        'pengguna_ic' => $technician->no_ic,
                        'updated_at' => now(),
                    ]);

                $ticket->update([
                    'status_tiket' => config(
                        's2p_workflow.statuses.report_review',
                        'Menunggu Semakan Laporan'
                    ),
                    'ulasan_semakan' => null,
                ]);

                $ticket->rekodLog(
                    config(
                        's2p_workflow.trail_events.performed',
                        'DILAKSANA'
                    ),
                    'Oleh '.$technician->nama,
                    'INFO'
                );

                DB::table('notifications')
                    ->where(
                        'notifiable_type',
                        $technician->getMorphClass()
                    )
                    ->where(
                        'notifiable_id',
                        (string) $technician->getKey()
                    )
                    ->whereNull('read_at')
                    ->where(
                        'data',
                        'LIKE',
                        '%'.$id_tiket.'%'
                    )
                    ->update([
                        'read_at' => now(),
                        'updated_at' => now(),
                    ]);

                $reviewers = Pengguna::query()
                    ->where(
                        'peranan',
                        config(
                            's2p_workflow.flows.pembekalan.pic_submission_role',
                            'ketua_upp'
                        )
                    )
                    ->where('status_pengguna', 'Aktif')
                    ->get();

                return [
                    $ticket->fresh(),
                    $reviewers,
                ];
            },
            3
        );

        try {
            if ($reviewers->isEmpty()) {
                Log::warning(
                    'Tiada KUPP aktif untuk semakan laporan Pembekalan.',
                    ['id_tiket' => $ticket->id_tiket]
                );
            } else {
                Notification::send(
                    $reviewers,
                    new WorkflowProcurementReviewNotification(
                        $ticket,
                        $technician->nama
                    )
                );
            }
        } catch (\Throwable $exception) {
            Log::error(
                'Laporan Pembekalan berjaya dihantar tetapi notifikasi KUPP gagal.',
                [
                    'id_tiket' => $ticket->id_tiket,
                    'message' => $exception->getMessage(),
                ]
            );
        }

        return redirect()
            ->route(
                'tickets.show',
                ['id_tiket' => $ticket->id_tiket]
            )
            ->with(
                'success',
                'Laporan hasil kajian berjaya dihantar kepada KUPP.'
            );
    }

    public function saveProcurementLkk(
        SaveProcurementLkkRequest $request,
        string $id_tiket
    ): RedirectResponse {
        $validated = $request->validated();
        $preparer = $request->user();

        $ticket = DB::transaction(
            function () use (
                $id_tiket,
                $validated,
                $preparer
            ): Tiket {
                $ticket = Tiket::query()
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->firstOrFail();

                $allowedStatuses = [
                    config(
                        's2p_workflow.statuses.report_review',
                        'Menunggu Semakan Laporan'
                    ),
                    config(
                        's2p_workflow.statuses.chief_correction',
                        'Pembetulan Ketua'
                    ),
                ];

                if (
                    ! in_array(
                        $ticket->status_tiket,
                        $allowedStatuses,
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Status tiket tidak membenarkan LKK Pembekalan dikemaskini.',
                    ]);
                }

                if (
                    $ticket->kategori
                    !== 'Transformasi Digital'
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk tiket Transformasi Digital.',
                    ]);
                }

                $digitalRecord = DB::table(
                    'transformasi_digital'
                )
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->first();

                if (
                    $digitalRecord === null
                    || trim(
                        (string) $digitalRecord->sub_kategori
                    ) !== 'Pembekalan Peralatan ICT'
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk Pembekalan Peralatan ICT.',
                    ]);
                }

                $reportExists = DB::table('laporan')
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->exists();

                if (! $reportExists) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Laporan Pembekalan tidak ditemui.',
                    ]);
                }

                DB::table('laporan')
                    ->where('id_tiket', $id_tiket)
                    ->update([
                        'kos_items' => json_encode(
                            $validated['kos_items'],
                            JSON_THROW_ON_ERROR
                            | JSON_UNESCAPED_UNICODE
                        ),
                        'rumusan' => $validated['rumusan'],
                        'disediakan_oleh' => $preparer->nama,
                        'pengguna_ic' => $preparer->no_ic,
                        'updated_at' => now(),
                    ]);

                return $ticket->fresh();
            },
            3
        );

        return redirect()
            ->route(
                'tickets.show',
                ['id_tiket' => $ticket->id_tiket]
            )
            ->with(
                'success',
                'Anggaran kos dan rumusan Pembekalan berjaya dikemaskini.'
            );
    }

    public function submitModernizationReport(
        SubmitModernizationReportRequest $request,
        string $id_tiket
    ): RedirectResponse {
        $validated = $request->validated();
        $technician = $request->user();

        $newPaths = [];
        $oldProposalPaths = [];

        try {
            [
                $ticket,
                $reviewers,
                $subCategory,
            ] = DB::transaction(
                function () use (
                    $request,
                    $id_tiket,
                    $validated,
                    $technician,
                    &$newPaths,
                    &$oldProposalPaths
                ): array {
                    $ticket = Tiket::query()
                        ->where('id_tiket', $id_tiket)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $allowedStatuses = [
                        config(
                            's2p_workflow.statuses.in_progress',
                            'Dalam Tindakan'
                        ),
                        config(
                            's2p_workflow.statuses.pic_correction',
                            'Laporan Perlu Pembetulan'
                        ),
                    ];

                    if (
                        ! in_array(
                            $ticket->status_tiket,
                            $allowedStatuses,
                            true
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'sistem' => 'Status tiket tidak membenarkan laporan Pemodenan dihantar.',
                        ]);
                    }

                    if (
                        $ticket->kategori
                        !== 'Transformasi Digital'
                    ) {
                        throw ValidationException::withMessages([
                            'sistem' => 'Tindakan ini hanya dibenarkan untuk tiket Transformasi Digital.',
                        ]);
                    }

                    $digitalRecord = DB::table(
                        'transformasi_digital'
                    )
                        ->where('id_tiket', $id_tiket)
                        ->lockForUpdate()
                        ->first();

                    if ($digitalRecord === null) {
                        throw ValidationException::withMessages([
                            'sistem' => 'Rekod Transformasi Digital tidak ditemui.',
                        ]);
                    }

                    $subCategory = trim(
                        (string) $digitalRecord->sub_kategori
                    );

                    if (
                        $subCategory
                        !== 'Pemodenan Bilik Mesyuarat'
                    ) {
                        throw ValidationException::withMessages([
                            'sistem' => 'Tindakan ini hanya dibenarkan untuk Pemodenan Bilik Mesyuarat.',
                        ]);
                    }

                    $isAssigned = DB::table('tugasan_tiket')
                        ->where('id_tiket', $id_tiket)
                        ->where(
                            'no_ic',
                            $technician->no_ic
                        )
                        ->exists();

                    if (! $isAssigned) {
                        throw ValidationException::withMessages([
                            'sistem' => 'Anda bukan Juruteknik yang dilantik untuk tiket ini.',
                        ]);
                    }

                    $report = DB::table('laporan')
                        ->where('id_tiket', $id_tiket)
                        ->lockForUpdate()
                        ->first();

                    if ($report === null) {
                        throw ValidationException::withMessages([
                            'sistem' => 'Maklumat awal laporan oleh KUPP tidak ditemui.',
                        ]);
                    }

                    $decodePaths = static function (
                        mixed $value
                    ): array {
                        if (
                            ! is_string($value)
                            || trim($value) === ''
                        ) {
                            return [];
                        }

                        $decoded = json_decode(
                            $value,
                            true
                        );

                        if (is_array($decoded)) {
                            return array_values(
                                array_filter(
                                    $decoded,
                                    fn (mixed $path): bool => is_string($path)
                                        && trim($path) !== ''
                                )
                            );
                        }

                        return [trim($value)];
                    };

                    $sitePaths = $decodePaths(
                        $report->gambar_tapak
                    );

                    $proposalPaths = $decodePaths(
                        $report->gambar_cadangan
                    );

                    if ($request->hasFile('gambar_tapak')) {
                        foreach (
                            $request->file(
                                'gambar_tapak',
                                []
                            ) as $index => $file
                        ) {
                            $extension = strtolower(
                                $file->getClientOriginalExtension()
                            );

                            $fileName = sprintf(
                                'tapak_%s_%s_%d.%s',
                                $id_tiket,
                                now()->format('YmdHis'),
                                $index + 1,
                                $extension
                            );

                            $path = $file->storeAs(
                                'laporan_td/'.
                                    $id_tiket.
                                    '/gambar_tapak',
                                $fileName,
                                'public'
                            );

                            $sitePaths[] = $path;
                            $newPaths[] = $path;
                        }
                    }

                    if (
                        $request->hasFile(
                            'gambar_cadangan'
                        )
                    ) {
                        $file = $request->file(
                            'gambar_cadangan'
                        );

                        $extension = strtolower(
                            $file->getClientOriginalExtension()
                        );

                        $fileName = sprintf(
                            'cadangan_%s_%s.%s',
                            $id_tiket,
                            now()->format('YmdHis'),
                            $extension
                        );

                        $path = $file->storeAs(
                            'laporan_td/'.
                                $id_tiket.
                                '/gambar_cadangan',
                            $fileName,
                            'public'
                        );

                        $oldProposalPaths = $proposalPaths;
                        $proposalPaths = [$path];
                        $newPaths[] = $path;
                    }

                    if (empty($proposalPaths)) {
                        throw ValidationException::withMessages([
                            'gambar_cadangan' => 'Cadangan susun atur pemasangan mesti dimuat naik.',
                        ]);
                    }

                    DB::table('laporan')
                        ->where('id_tiket', $id_tiket)
                        ->update([
                            'keadaan_semasa' => json_encode(
                                $validated[
                                    'keadaan_semasa'
                                ],
                                JSON_THROW_ON_ERROR
                                | JSON_UNESCAPED_UNICODE
                            ),
                            'cadangan_penambahbaikan' => is_array(
                                $validated[
                                    'cadangan_penambahbaikan'
                                ] ?? null
                            )
                                    ? json_encode(
                                        $validated[
                                            'cadangan_penambahbaikan'
                                        ],
                                        JSON_THROW_ON_ERROR
                                        | JSON_UNESCAPED_UNICODE
                                    )
                                    : (
                                        $validated[
                                            'cadangan_penambahbaikan'
                                        ] ?? null
                                    ),
                            'gambar_tapak' => empty($sitePaths)
                                    ? null
                                    : json_encode(
                                        array_values(
                                            array_unique(
                                                $sitePaths
                                            )
                                        ),
                                        JSON_THROW_ON_ERROR
                                        | JSON_UNESCAPED_SLASHES
                                    ),
                            'gambar_cadangan' => json_encode(
                                $proposalPaths,
                                JSON_THROW_ON_ERROR
                                | JSON_UNESCAPED_SLASHES
                            ),
                            'pengguna_ic' => $technician->no_ic,
                            'updated_at' => now(),
                        ]);

                    $ticket->update([
                        'status_tiket' => config(
                            's2p_workflow.statuses.report_review',
                            'Menunggu Semakan Laporan'
                        ),
                        'ulasan_semakan' => null,
                    ]);

                    $ticket->rekodLog(
                        config(
                            's2p_workflow.trail_events.performed',
                            'DILAKSANA'
                        ),
                        'Oleh '.$technician->nama,
                        'INFO'
                    );

                    DB::table('notifications')
                        ->where(
                            'notifiable_type',
                            $technician->getMorphClass()
                        )
                        ->where(
                            'notifiable_id',
                            (string) $technician->getKey()
                        )
                        ->whereNull('read_at')
                        ->where(
                            'data',
                            'LIKE',
                            '%'.$id_tiket.'%'
                        )
                        ->update([
                            'read_at' => now(),
                            'updated_at' => now(),
                        ]);

                    $reviewers = Pengguna::query()
                        ->where(
                            'peranan',
                            config(
                                's2p_workflow.flows.pemodenan.pic_submission_role',
                                'ketua_upp'
                            )
                        )
                        ->where(
                            'status_pengguna',
                            'Aktif'
                        )
                        ->get();

                    return [
                        $ticket->fresh(),
                        $reviewers,
                        $subCategory,
                    ];
                },
                3
            );
        } catch (\Throwable $exception) {
            foreach (
                array_unique($newPaths) as $newPath
            ) {
                Storage::disk('public')->delete(
                    $newPath
                );
            }

            throw $exception;
        }

        foreach (
            array_unique($oldProposalPaths) as $oldPath
        ) {
            if (
                $oldPath !== ''
                && ! in_array(
                    $oldPath,
                    $newPaths,
                    true
                )
            ) {
                Storage::disk('public')->delete(
                    $oldPath
                );
            }
        }

        try {
            if ($reviewers->isEmpty()) {
                Log::warning(
                    'Tiada KUPP aktif untuk semakan laporan Pemodenan.',
                    ['id_tiket' => $ticket->id_tiket]
                );
            } else {
                Notification::send(
                    $reviewers,
                    new WorkflowReportReviewNotification(
                        $ticket,
                        $technician->nama,
                        $subCategory
                    )
                );
            }
        } catch (\Throwable $exception) {
            Log::error(
                'Laporan Pemodenan berjaya dihantar tetapi notifikasi KUPP gagal.',
                [
                    'id_tiket' => $ticket->id_tiket,
                    'reviewer_ids' => $reviewers
                        ->pluck('no_ic')
                        ->all(),
                    'message' => $exception->getMessage(),
                ]
            );
        }

        return redirect()
            ->route(
                'tickets.show',
                ['id_tiket' => $ticket->id_tiket]
            )
            ->with(
                'success',
                'Laporan Pemodenan berjaya dihantar kepada KUPP.'
            );
    }

    public function saveModernizationLkk(
        SaveModernizationLkkRequest $request,
        string $id_tiket
    ): RedirectResponse {
        $validated = $request->validated();
        $preparer = $request->user();

        $ticket = DB::transaction(
            function () use (
                $id_tiket,
                $validated,
                $preparer
            ): Tiket {
                $ticket = Tiket::query()
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->firstOrFail();

                $allowedStatuses = [
                    config(
                        's2p_workflow.statuses.report_review',
                        'Menunggu Semakan Laporan'
                    ),
                    config(
                        's2p_workflow.statuses.chief_correction',
                        'Pembetulan Ketua'
                    ),
                ];

                if (
                    ! in_array(
                        $ticket->status_tiket,
                        $allowedStatuses,
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Status tiket tidak membenarkan LKK Pemodenan dikemaskini.',
                    ]);
                }

                if (
                    $ticket->kategori
                    !== 'Transformasi Digital'
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk tiket Transformasi Digital.',
                    ]);
                }

                $digitalRecord = DB::table(
                    'transformasi_digital'
                )
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->first();

                if (
                    $digitalRecord === null
                    || trim(
                        (string) $digitalRecord->sub_kategori
                    ) !== 'Pemodenan Bilik Mesyuarat'
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk Pemodenan Bilik Mesyuarat.',
                    ]);
                }

                $report = DB::table('laporan')
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->first();

                if ($report === null) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Laporan Pemodenan tidak ditemui.',
                    ]);
                }

                if (
                    blank($report->keadaan_semasa)
                    || blank($report->gambar_cadangan)
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Laporan Juruteknik belum lengkap dan tidak boleh dikemaskini oleh KUPP.',
                    ]);
                }

                DB::table('laporan')
                    ->where('id_tiket', $id_tiket)
                    ->update([
                        'kos_items' => json_encode(
                            $validated['kos_items'],
                            JSON_THROW_ON_ERROR
                            | JSON_UNESCAPED_UNICODE
                        ),
                        'rumusan' => $validated['rumusan'],
                        'disediakan_oleh' => $preparer->nama,
                        'pengguna_ic' => $preparer->no_ic,
                        'updated_at' => now(),
                    ]);

                DB::table('notifications')
                    ->where(
                        'notifiable_type',
                        $preparer->getMorphClass()
                    )
                    ->where(
                        'notifiable_id',
                        (string) $preparer->getKey()
                    )
                    ->whereNull('read_at')
                    ->where(
                        'data',
                        'LIKE',
                        '%'.$id_tiket.'%'
                    )
                    ->update([
                        'read_at' => now(),
                        'updated_at' => now(),
                    ]);

                return $ticket->fresh();
            },
            3
        );

        return redirect()
            ->route(
                'tickets.show',
                ['id_tiket' => $ticket->id_tiket]
            )
            ->with(
                'success',
                'Anggaran kos dan rumusan laporan berjaya dikemaskini.'
            );
    }

    public function reviewProcurementLkk(
        ReviewModernizationLkkRequest $request,
        string $id_tiket
    ): RedirectResponse {
        $validated = $request->validated();
        $reviewer = $request->user();

        $isCorrection =
            $validated['tindakan'] === 'PEMBETULAN';

        [
            $ticket,
            $recipients,
            $subCategory,
        ] = DB::transaction(
            function () use (
                $id_tiket,
                $validated,
                $reviewer,
                $isCorrection
            ): array {
                $ticket = Tiket::query()
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $ticket->kategori
                    !== 'Transformasi Digital'
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk tiket Transformasi Digital.',
                    ]);
                }

                $digitalRecord = DB::table(
                    'transformasi_digital'
                )
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->first();

                if ($digitalRecord === null) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Rekod Transformasi Digital tidak ditemui.',
                    ]);
                }

                $subCategory = trim(
                    (string) $digitalRecord->sub_kategori
                );

                if (
                    $subCategory
                    !== 'Pembekalan Peralatan ICT'
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk Pembekalan Peralatan ICT.',
                    ]);
                }

                $reportReviewStatus = config(
                    's2p_workflow.statuses.report_review',
                    'Menunggu Semakan Laporan'
                );

                $chiefCorrectionStatus = config(
                    's2p_workflow.statuses.chief_correction',
                    'Pembetulan Ketua'
                );

                $allowedStatuses = $isCorrection
                    ? [$reportReviewStatus]
                    : [
                        $reportReviewStatus,
                        $chiefCorrectionStatus,
                    ];

                if (
                    ! in_array(
                        $ticket->status_tiket,
                        $allowedStatuses,
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Status tiket tidak membenarkan tindakan semakan atau verifikasi.',
                    ]);
                }

                $report = DB::table('laporan')
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->first();

                if ($report === null) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Laporan Pembekalan Peralatan ICT tidak ditemui.',
                    ]);
                }

                if ($isCorrection) {
                    $recipients = Pengguna::query()
                        ->whereIn(
                            'no_ic',
                            DB::table('tugasan_tiket')
                                ->where(
                                    'id_tiket',
                                    $id_tiket
                                )
                                ->pluck('no_ic')
                        )
                        ->whereIn(
                            'peranan',
                            ['juruteknik', 'pic']
                        )
                        ->where(
                            'status_pengguna',
                            'Aktif'
                        )
                        ->get();

                    if ($recipients->isEmpty()) {
                        throw ValidationException::withMessages([
                            'sistem' => 'Tiada Juruteknik aktif untuk menerima arahan pembetulan.',
                        ]);
                    }

                    $ticket->update([
                        'status_tiket' => config(
                            's2p_workflow.statuses.pic_correction',
                            'Laporan Perlu Pembetulan'
                        ),
                        'ulasan_semakan' => $validated['ulasan'],
                    ]);

                    $ticket->rekodLog(
                        config(
                            's2p_workflow.trail_events.correction_requested',
                            'PEMBETULAN DIMINTA'
                        ),
                        'Oleh '.$reviewer->nama,
                        'PEMBETULAN'
                    );
                } else {
                    /*
                     * Struktur wajib borang BPI/B02v1.3:
                     * - butiran permohonan dan pegawai;
                     * - hasil kajian setiap pemohon;
                     * - rumusan peralatan dan anggaran kos.
                     */
                    $requiredFields = [
                        'pendahuluan',
                        'hasil_kajian',
                        'kos_items',
                        'rumusan',
                    ];

                    foreach ($requiredFields as $field) {
                        if (blank($report->{$field})) {
                            throw ValidationException::withMessages([
                                'sistem' => 'LKK Pembekalan Peralatan ICT belum lengkap. Medan '.$field.' masih kosong.',
                            ]);
                        }
                    }

                    DB::table('laporan')
                        ->where('id_tiket', $id_tiket)
                        ->update([
                            'disediakan_oleh' => $reviewer->nama,
                            'pengguna_ic' => $reviewer->no_ic,
                            'updated_at' => now(),
                        ]);

                    $ticket->update([
                        'status_tiket' => config(
                            's2p_workflow.statuses.validation',
                            'Menunggu Validasi'
                        ),
                        'ulasan_semakan' => null,
                        'disahkan_oleh_ic' => $reviewer->no_ic,
                    ]);

                    $ticket->rekodLog(
                        config(
                            's2p_workflow.trail_events.verified',
                            'DIVERIFIKASI'
                        ),
                        'Oleh '.$reviewer->nama,
                        'DIVERIFIKASI'
                    );

                    $recipients = Pengguna::query()
                        ->where(
                            'peranan',
                            config(
                                's2p_workflow.flows.pembekalan.validation_role',
                                'ketua_wilayah'
                            )
                        )
                        ->where(
                            'status_pengguna',
                            'Aktif'
                        )
                        ->get();

                    if ($recipients->isEmpty()) {
                        throw ValidationException::withMessages([
                            'sistem' => 'Tiada Ketua Wilayah aktif untuk menerima validasi.',
                        ]);
                    }
                }

                DB::table('notifications')
                    ->where(
                        'notifiable_type',
                        $reviewer->getMorphClass()
                    )
                    ->where(
                        'notifiable_id',
                        (string) $reviewer->getKey()
                    )
                    ->whereNull('read_at')
                    ->where(
                        'data',
                        'LIKE',
                        '%'.$id_tiket.'%'
                    )
                    ->update([
                        'read_at' => now(),
                        'updated_at' => now(),
                    ]);

                return [
                    $ticket->fresh(),
                    $recipients,
                    $subCategory,
                ];
            },
            3
        );

        try {
            if ($isCorrection) {
                Notification::send(
                    $recipients,
                    new LKKPembetulanNoti(
                        $ticket,
                        $reviewer->nama
                    )
                );
            } else {
                Notification::send(
                    $recipients,
                    new WorkflowProcurementValidationNotification(
                        $ticket,
                        $reviewer->nama,
                        $subCategory
                    )
                );
            }
        } catch (\Throwable $exception) {
            Log::error(
                $isCorrection
                    ? 'LKK Pembekalan Peralatan ICT dipulangkan tetapi notifikasi Juruteknik gagal.'
                    : 'LKK Pembekalan Peralatan ICT diverifikasi tetapi notifikasi Ketua Wilayah gagal.',
                [
                    'id_tiket' => $ticket->id_tiket,
                    'recipient_ids' => $recipients
                        ->pluck('no_ic')
                        ->all(),
                    'message' => $exception->getMessage(),
                ]
            );
        }

        return redirect()
            ->route(
                'tickets.show',
                ['id_tiket' => $ticket->id_tiket]
            )
            ->with(
                'success',
                $isCorrection
                    ? 'Laporan dikembalikan kepada Juruteknik untuk pembetulan.'
                    : 'LKK berjaya diverifikasi dan dihantar kepada Ketua Wilayah.'
            );
    }

    public function reviewModernizationLkk(
        ReviewModernizationLkkRequest $request,
        string $id_tiket
    ): RedirectResponse {
        $validated = $request->validated();
        $reviewer = $request->user();

        $isCorrection =
            $validated['tindakan'] === 'PEMBETULAN';

        [
            $ticket,
            $recipients,
            $subCategory,
        ] = DB::transaction(
            function () use (
                $id_tiket,
                $validated,
                $reviewer,
                $isCorrection
            ): array {
                $ticket = Tiket::query()
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $ticket->kategori
                    !== 'Transformasi Digital'
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk tiket Transformasi Digital.',
                    ]);
                }

                $digitalRecord = DB::table(
                    'transformasi_digital'
                )
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->first();

                if ($digitalRecord === null) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Rekod Transformasi Digital tidak ditemui.',
                    ]);
                }

                $subCategory = trim(
                    (string) $digitalRecord->sub_kategori
                );

                if (
                    $subCategory
                    !== 'Pemodenan Bilik Mesyuarat'
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk Pemodenan Bilik Mesyuarat.',
                    ]);
                }

                $reportReviewStatus = config(
                    's2p_workflow.statuses.report_review',
                    'Menunggu Semakan Laporan'
                );

                $chiefCorrectionStatus = config(
                    's2p_workflow.statuses.chief_correction',
                    'Pembetulan Ketua'
                );

                $allowedStatuses = $isCorrection
                    ? [$reportReviewStatus]
                    : [
                        $reportReviewStatus,
                        $chiefCorrectionStatus,
                    ];

                if (
                    ! in_array(
                        $ticket->status_tiket,
                        $allowedStatuses,
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Status tiket tidak membenarkan tindakan semakan atau verifikasi.',
                    ]);
                }

                $report = DB::table('laporan')
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->first();

                if ($report === null) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Laporan Pemodenan tidak ditemui.',
                    ]);
                }

                if ($isCorrection) {
                    $recipients = Pengguna::query()
                        ->whereIn(
                            'no_ic',
                            DB::table('tugasan_tiket')
                                ->where(
                                    'id_tiket',
                                    $id_tiket
                                )
                                ->pluck('no_ic')
                        )
                        ->whereIn(
                            'peranan',
                            ['juruteknik', 'pic']
                        )
                        ->where(
                            'status_pengguna',
                            'Aktif'
                        )
                        ->get();

                    if ($recipients->isEmpty()) {
                        throw ValidationException::withMessages([
                            'sistem' => 'Tiada Juruteknik aktif untuk menerima arahan pembetulan.',
                        ]);
                    }

                    $ticket->update([
                        'status_tiket' => config(
                            's2p_workflow.statuses.pic_correction',
                            'Laporan Perlu Pembetulan'
                        ),
                        'ulasan_semakan' => $validated['ulasan'],
                    ]);

                    $ticket->rekodLog(
                        config(
                            's2p_workflow.trail_events.correction_requested',
                            'PEMBETULAN DIMINTA'
                        ),
                        'Oleh '.$reviewer->nama,
                        'PEMBETULAN'
                    );
                } else {
                    $requiredFields = [
                        'pendahuluan',
                        'objektif',
                        'skop_kajian',
                        'keadaan_semasa',
                        'gambar_cadangan',
                        'kos_items',
                        'rumusan',
                    ];

                    foreach ($requiredFields as $field) {
                        if (blank($report->{$field})) {
                            throw ValidationException::withMessages([
                                'sistem' => 'LKK Pemodenan belum lengkap. Medan '.$field.' masih kosong.',
                            ]);
                        }
                    }

                    DB::table('laporan')
                        ->where('id_tiket', $id_tiket)
                        ->update([
                            'disediakan_oleh' => $reviewer->nama,
                            'pengguna_ic' => $reviewer->no_ic,
                            'updated_at' => now(),
                        ]);

                    $ticket->update([
                        'status_tiket' => config(
                            's2p_workflow.statuses.validation',
                            'Menunggu Validasi'
                        ),
                        'ulasan_semakan' => null,
                        'disahkan_oleh_ic' => $reviewer->no_ic,
                    ]);

                    $ticket->rekodLog(
                        config(
                            's2p_workflow.trail_events.verified',
                            'DIVERIFIKASI'
                        ),
                        'Oleh '.$reviewer->nama,
                        'DIVERIFIKASI'
                    );

                    $recipients = Pengguna::query()
                        ->where(
                            'peranan',
                            config(
                                's2p_workflow.flows.pemodenan.validation_role',
                                'ketua_wilayah'
                            )
                        )
                        ->where(
                            'status_pengguna',
                            'Aktif'
                        )
                        ->get();

                    if ($recipients->isEmpty()) {
                        throw ValidationException::withMessages([
                            'sistem' => 'Tiada Ketua Wilayah aktif untuk menerima validasi.',
                        ]);
                    }
                }

                DB::table('notifications')
                    ->where(
                        'notifiable_type',
                        $reviewer->getMorphClass()
                    )
                    ->where(
                        'notifiable_id',
                        (string) $reviewer->getKey()
                    )
                    ->whereNull('read_at')
                    ->where(
                        'data',
                        'LIKE',
                        '%'.$id_tiket.'%'
                    )
                    ->update([
                        'read_at' => now(),
                        'updated_at' => now(),
                    ]);

                return [
                    $ticket->fresh(),
                    $recipients,
                    $subCategory,
                ];
            },
            3
        );

        try {
            if ($isCorrection) {
                Notification::send(
                    $recipients,
                    new LKKPembetulanNoti(
                        $ticket,
                        $reviewer->nama
                    )
                );
            } else {
                Notification::send(
                    $recipients,
                    new WorkflowModernizationValidationNotification(
                        $ticket,
                        $reviewer->nama,
                        $subCategory
                    )
                );
            }
        } catch (\Throwable $exception) {
            Log::error(
                $isCorrection
                    ? 'LKK Pemodenan dipulangkan tetapi notifikasi Juruteknik gagal.'
                    : 'LKK Pemodenan diverifikasi tetapi notifikasi Ketua Wilayah gagal.',
                [
                    'id_tiket' => $ticket->id_tiket,
                    'recipient_ids' => $recipients
                        ->pluck('no_ic')
                        ->all(),
                    'message' => $exception->getMessage(),
                ]
            );
        }

        return redirect()
            ->route(
                'tickets.show',
                ['id_tiket' => $ticket->id_tiket]
            )
            ->with(
                'success',
                $isCorrection
                    ? 'Laporan dikembalikan kepada Juruteknik untuk pembetulan.'
                    : 'LKK berjaya diverifikasi dan dihantar kepada Ketua Wilayah.'
            );
    }

    public function validateProcurementLkk(
        ValidateModernizationLkkRequest $request,
        string $id_tiket
    ): RedirectResponse {
        $validated = $request->validated();
        $validator = $request->user();

        $isCorrection =
            $validated['tindakan'] === 'PEMBETULAN';

        [
            $ticket,
            $reviewers,
            $subCategory,
        ] = DB::transaction(
            function () use (
                $id_tiket,
                $validated,
                $validator,
                $isCorrection
            ): array {
                $ticket = Tiket::query()
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->firstOrFail();

                $expectedStatus = config(
                    's2p_workflow.statuses.validation',
                    'Menunggu Validasi'
                );

                if (
                    $ticket->status_tiket
                    !== $expectedStatus
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'LKK ini bukan lagi dalam peringkat validasi.',
                    ]);
                }

                if (
                    $ticket->kategori
                    !== 'Transformasi Digital'
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk tiket Transformasi Digital.',
                    ]);
                }

                $digitalRecord = DB::table(
                    'transformasi_digital'
                )
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->first();

                if ($digitalRecord === null) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Rekod Transformasi Digital tidak ditemui.',
                    ]);
                }

                $subCategory = trim(
                    (string) $digitalRecord->sub_kategori
                );

                if (
                    $subCategory
                    !== 'Pembekalan Peralatan ICT'
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk Pembekalan Peralatan ICT.',
                    ]);
                }

                $report = DB::table('laporan')
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->first();

                if ($report === null) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Laporan Pembekalan Peralatan ICT tidak ditemui.',
                    ]);
                }

                $reviewers = Pengguna::query()
                    ->where(
                        'peranan',
                        config(
                            's2p_workflow.flows.pembekalan.verification_role',
                            'ketua_upp'
                        )
                    )
                    ->where('status_pengguna', 'Aktif')
                    ->lockForUpdate()
                    ->get();

                if (
                    $isCorrection
                    && $reviewers->isEmpty()
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tiada KUPP aktif untuk menerima arahan pembetulan.',
                    ]);
                }

                if ($isCorrection) {
                    $ticket->update([
                        'status_tiket' => config(
                            's2p_workflow.statuses.chief_correction',
                            'Pembetulan Ketua'
                        ),
                        'ulasan_semakan' => $validated['ulasan'],
                        'tarikh_tutup' => null,
                    ]);

                    $ticket->rekodLog(
                        config(
                            's2p_workflow.trail_events.correction_requested',
                            'PEMBETULAN DIMINTA'
                        ),
                        'Oleh '.$validator->nama,
                        'PEMBETULAN'
                    );
                } else {
                    DB::table('laporan')
                        ->where('id_tiket', $id_tiket)
                        ->update([
                            'disemak_oleh' => $validator->nama,
                            'updated_at' => now(),
                        ]);

                    $ticket->update([
                        'status_tiket' => config(
                            's2p_workflow.statuses.completed',
                            'Selesai'
                        ),
                        'ulasan_semakan' => $validated['ulasan'] ?? null,
                        'tarikh_tutup' => now(),
                    ]);

                    $ticket->rekodLog(
                        config(
                            's2p_workflow.trail_events.validated',
                            'DIVALIDASI'
                        ),
                        'Oleh '.$validator->nama,
                        'LULUS'
                    );

                    $ticket->rekodLog(
                        config(
                            's2p_workflow.trail_events.closed',
                            'TIKET DITUTUP'
                        ),
                        'Oleh '.$validator->nama,
                        'SELESAI'
                    );
                }

                DB::table('notifications')
                    ->where(
                        'notifiable_type',
                        $validator->getMorphClass()
                    )
                    ->where(
                        'notifiable_id',
                        (string) $validator->getKey()
                    )
                    ->whereNull('read_at')
                    ->where(
                        'data',
                        'LIKE',
                        '%'.$id_tiket.'%'
                    )
                    ->update([
                        'read_at' => now(),
                        'updated_at' => now(),
                    ]);

                return [
                    $ticket->fresh(),
                    $reviewers,
                    $subCategory,
                ];
            },
            3
        );

        if ($isCorrection) {
            try {
                Notification::send(
                    $reviewers,
                    new WorkflowProcurementChiefCorrectionNotification(
                        $ticket,
                        $validator->nama,
                        $validated['ulasan'],
                        $subCategory
                    )
                );
            } catch (\Throwable $exception) {
                Log::error(
                    'LKK Pembekalan Peralatan ICT dipulangkan tetapi notifikasi KUPP gagal.',
                    [
                        'id_tiket' => $ticket->id_tiket,
                        'reviewer_ids' => $reviewers
                            ->pluck('no_ic')
                            ->all(),
                        'message' => $exception->getMessage(),
                    ]
                );
            }
        }

        return redirect()
            ->route(
                'tickets.show',
                ['id_tiket' => $ticket->id_tiket]
            )
            ->with(
                'success',
                $isCorrection
                    ? 'LKK dipulangkan kepada KUPP untuk pembetulan.'
                    : 'LKK berjaya divalidasi dan tiket telah ditutup.'
            );
    }

    public function validateModernizationLkk(
        ValidateModernizationLkkRequest $request,
        string $id_tiket
    ): RedirectResponse {
        $validated = $request->validated();
        $validator = $request->user();

        $isCorrection =
            $validated['tindakan'] === 'PEMBETULAN';

        [
            $ticket,
            $reviewers,
            $subCategory,
        ] = DB::transaction(
            function () use (
                $id_tiket,
                $validated,
                $validator,
                $isCorrection
            ): array {
                $ticket = Tiket::query()
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->firstOrFail();

                $expectedStatus = config(
                    's2p_workflow.statuses.validation',
                    'Menunggu Validasi'
                );

                if (
                    $ticket->status_tiket
                    !== $expectedStatus
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'LKK ini bukan lagi dalam peringkat validasi.',
                    ]);
                }

                if (
                    $ticket->kategori
                    !== 'Transformasi Digital'
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk tiket Transformasi Digital.',
                    ]);
                }

                $digitalRecord = DB::table(
                    'transformasi_digital'
                )
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->first();

                if ($digitalRecord === null) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Rekod Transformasi Digital tidak ditemui.',
                    ]);
                }

                $subCategory = trim(
                    (string) $digitalRecord->sub_kategori
                );

                if (
                    $subCategory
                    !== 'Pemodenan Bilik Mesyuarat'
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk Pemodenan Bilik Mesyuarat.',
                    ]);
                }

                $report = DB::table('laporan')
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->first();

                if ($report === null) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Laporan Pemodenan tidak ditemui.',
                    ]);
                }

                $reviewers = Pengguna::query()
                    ->where(
                        'peranan',
                        config(
                            's2p_workflow.flows.pemodenan.verification_role',
                            'ketua_upp'
                        )
                    )
                    ->where('status_pengguna', 'Aktif')
                    ->lockForUpdate()
                    ->get();

                if (
                    $isCorrection
                    && $reviewers->isEmpty()
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tiada KUPP aktif untuk menerima arahan pembetulan.',
                    ]);
                }

                if ($isCorrection) {
                    $ticket->update([
                        'status_tiket' => config(
                            's2p_workflow.statuses.chief_correction',
                            'Pembetulan Ketua'
                        ),
                        'ulasan_semakan' => $validated['ulasan'],
                        'tarikh_tutup' => null,
                    ]);

                    $ticket->rekodLog(
                        config(
                            's2p_workflow.trail_events.correction_requested',
                            'PEMBETULAN DIMINTA'
                        ),
                        'Oleh '.$validator->nama,
                        'PEMBETULAN'
                    );
                } else {
                    DB::table('laporan')
                        ->where('id_tiket', $id_tiket)
                        ->update([
                            'disemak_oleh' => $validator->nama,
                            'updated_at' => now(),
                        ]);

                    $ticket->update([
                        'status_tiket' => config(
                            's2p_workflow.statuses.completed',
                            'Selesai'
                        ),
                        'ulasan_semakan' => $validated['ulasan'] ?? null,
                        'tarikh_tutup' => now(),
                    ]);

                    $ticket->rekodLog(
                        config(
                            's2p_workflow.trail_events.validated',
                            'DIVALIDASI'
                        ),
                        'Oleh '.$validator->nama,
                        'LULUS'
                    );

                    $ticket->rekodLog(
                        config(
                            's2p_workflow.trail_events.closed',
                            'TIKET DITUTUP'
                        ),
                        'Oleh '.$validator->nama,
                        'SELESAI'
                    );
                }

                DB::table('notifications')
                    ->where(
                        'notifiable_type',
                        $validator->getMorphClass()
                    )
                    ->where(
                        'notifiable_id',
                        (string) $validator->getKey()
                    )
                    ->whereNull('read_at')
                    ->where(
                        'data',
                        'LIKE',
                        '%'.$id_tiket.'%'
                    )
                    ->update([
                        'read_at' => now(),
                        'updated_at' => now(),
                    ]);

                return [
                    $ticket->fresh(),
                    $reviewers,
                    $subCategory,
                ];
            },
            3
        );

        if ($isCorrection) {
            try {
                Notification::send(
                    $reviewers,
                    new WorkflowModernizationChiefCorrectionNotification(
                        $ticket,
                        $validator->nama,
                        $validated['ulasan'],
                        $subCategory
                    )
                );
            } catch (\Throwable $exception) {
                Log::error(
                    'LKK Pemodenan dipulangkan tetapi notifikasi KUPP gagal.',
                    [
                        'id_tiket' => $ticket->id_tiket,
                        'reviewer_ids' => $reviewers
                            ->pluck('no_ic')
                            ->all(),
                        'message' => $exception->getMessage(),
                    ]
                );
            }
        }

        return redirect()
            ->route(
                'tickets.show',
                ['id_tiket' => $ticket->id_tiket]
            )
            ->with(
                'success',
                $isCorrection
                    ? 'LKK dipulangkan kepada KUPP untuk pembetulan.'
                    : 'LKK berjaya divalidasi dan tiket telah ditutup.'
            );
    }

    public function reviewProcurementTicket(
        ReviewProcurementTicketRequest $request,
        string $id_tiket
    ): RedirectResponse {
        $validated = $request->validated();
        $reviewer = $request->user();

        $ticket = DB::transaction(
            function () use (
                $id_tiket,
                $validated,
                $reviewer
            ): Tiket {
                $ticket = Tiket::query()
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $ticket->status_tiket
                    !== config(
                        's2p_workflow.statuses.initial_review',
                        'Menunggu Semakan'
                    )
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tiket ini bukan lagi dalam peringkat semakan KUPP.',
                    ]);
                }

                if (
                    $ticket->kategori
                    !== 'Transformasi Digital'
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk tiket Transformasi Digital.',
                    ]);
                }

                $digitalRecord = DB::table(
                    'transformasi_digital'
                )
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->first();

                if (
                    $digitalRecord === null
                    || trim(
                        (string) $digitalRecord->sub_kategori
                    ) !== 'Pembekalan Peralatan ICT'
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk Pembekalan Peralatan ICT.',
                    ]);
                }

                $reportData = [
                    'pendahuluan' => json_encode(
                        $validated['pendahuluan'],
                        JSON_THROW_ON_ERROR
                        | JSON_UNESCAPED_UNICODE
                    ),
                    'hasil_kajian' => json_encode(
                        $validated['hasil_kajian'],
                        JSON_THROW_ON_ERROR
                        | JSON_UNESCAPED_UNICODE
                    ),
                    'pengguna_ic' => $reviewer->no_ic,
                    'updated_at' => now(),
                ];

                $reportExists = DB::table('laporan')
                    ->where('id_tiket', $id_tiket)
                    ->exists();

                if ($reportExists) {
                    DB::table('laporan')
                        ->where('id_tiket', $id_tiket)
                        ->update($reportData);
                } else {
                    DB::table('laporan')->insert([
                        ...$reportData,
                        'id_tiket' => $id_tiket,
                        'created_at' => now(),
                    ]);
                }

                $ticket->update([
                    'status_tiket' => config(
                        's2p_workflow.statuses.reviewed',
                        'Disemak'
                    ),
                    'disemak_oleh_ic' => $reviewer->no_ic,
                    'tarikh_semakan' => now(),
                    'ulasan_semakan' => null,
                ]);

                $ticket->rekodLog(
                    config(
                        's2p_workflow.trail_events.reviewed',
                        'DISEMAK'
                    ),
                    'Oleh '.$reviewer->nama,
                    'INFO'
                );

                DB::table('notifications')
                    ->where(
                        'notifiable_type',
                        $reviewer->getMorphClass()
                    )
                    ->where(
                        'notifiable_id',
                        (string) $reviewer->getKey()
                    )
                    ->whereNull('read_at')
                    ->where(
                        'data',
                        'LIKE',
                        '%'.$id_tiket.'%'
                    )
                    ->update([
                        'read_at' => now(),
                        'updated_at' => now(),
                    ]);

                return $ticket->fresh();
            },
            3
        );

        try {
            $assigners = Pengguna::query()
                ->where(
                    'peranan',
                    config(
                        's2p_workflow.flows.pembekalan.assignment_role',
                        'ketua_utd'
                    )
                )
                ->where('status_pengguna', 'Aktif')
                ->get();

            if ($assigners->isEmpty()) {
                Log::warning(
                    'Tiada KUTD aktif untuk tugasan Pembekalan.',
                    ['id_tiket' => $ticket->id_tiket]
                );
            } else {
                Notification::send(
                    $assigners,
                    new WorkflowReviewNotification(
                        $ticket,
                        'ketua_utd'
                    )
                );
            }
        } catch (\Throwable $exception) {
            Log::error(
                'Semakan KUPP Pembekalan berjaya tetapi notifikasi KUTD gagal.',
                [
                    'id_tiket' => $ticket->id_tiket,
                    'message' => $exception->getMessage(),
                ]
            );
        }

        return redirect()
            ->route(
                'tickets.show',
                ['id_tiket' => $ticket->id_tiket]
            )
            ->with(
                'success',
                'Butiran Pembekalan berjaya dikemaskini dan dihantar kepada KUTD.'
            );
    }

    public function assignProcurementTicket(
        AssignModernizationTicketRequest $request,
        string $id_tiket
    ): RedirectResponse {
        $validated = $request->validated();
        $assigner = $request->user();

        [
            $ticket,
            $technicians,
            $subCategory,
        ] = DB::transaction(
            function () use (
                $id_tiket,
                $validated,
                $assigner
            ): array {
                $ticket = Tiket::query()
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $ticket->status_tiket
                    !== config(
                        's2p_workflow.statuses.reviewed',
                        'Disemak'
                    )
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tiket ini bukan lagi dalam peringkat tugasan KUTD.',
                    ]);
                }

                if (
                    $ticket->kategori
                    !== 'Transformasi Digital'
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk tiket Transformasi Digital.',
                    ]);
                }

                $digitalRecord = DB::table(
                    'transformasi_digital'
                )
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->first();

                if (
                    $digitalRecord === null
                    || trim(
                        (string) $digitalRecord->sub_kategori
                    ) !== 'Pembekalan Peralatan ICT'
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk Pembekalan Peralatan ICT.',
                    ]);
                }

                $technicianIds = array_values(
                    array_unique(
                        $validated['senarai_pic_ic']
                    )
                );

                $technicians = Pengguna::query()
                    ->whereIn('no_ic', $technicianIds)
                    ->where('peranan', 'juruteknik')
                    ->where('status_pengguna', 'Aktif')
                    ->lockForUpdate()
                    ->get();

                if (
                    $technicians->count()
                    !== count($technicianIds)
                ) {
                    throw ValidationException::withMessages([
                        'senarai_pic_ic' => 'Satu atau lebih Juruteknik tidak sah atau tidak aktif.',
                    ]);
                }

                DB::table('transformasi_digital')
                    ->where('id_tiket', $id_tiket)
                    ->update([
                        'tarikh_lawatan' => $validated['tarikh_lawatan'],
                        'masa_lawatan' => $validated['masa_lawatan'],
                        'catatan_lawatan' => $validated['catatan_lawatan']
                            ?? null,
                        'updated_at' => now(),
                    ]);

                DB::table('tugasan_tiket')
                    ->where('id_tiket', $id_tiket)
                    ->delete();

                $assignmentTime = now();

                DB::table('tugasan_tiket')->insert(
                    $technicians
                        ->map(
                            fn (Pengguna $technician): array => [
                                'id_tiket' => $id_tiket,
                                'no_ic' => $technician->no_ic,
                                'created_at' => $assignmentTime,
                                'updated_at' => $assignmentTime,
                            ]
                        )
                        ->all()
                );

                $ticket->update([
                    'status_tiket' => config(
                        's2p_workflow.statuses.in_progress',
                        'Dalam Tindakan'
                    ),
                    'ulasan_semakan' => null,
                ]);

                DB::table('notifications')
                    ->where(
                        'notifiable_type',
                        $assigner->getMorphClass()
                    )
                    ->where(
                        'notifiable_id',
                        (string) $assigner->getKey()
                    )
                    ->whereNull('read_at')
                    ->where(
                        'data',
                        'LIKE',
                        '%'.$id_tiket.'%'
                    )
                    ->update([
                        'read_at' => now(),
                        'updated_at' => now(),
                    ]);

                return [
                    $ticket->fresh(),
                    $technicians,
                    trim(
                        (string) $digitalRecord->sub_kategori
                    ),
                ];
            },
            3
        );

        try {
            Notification::send(
                $technicians,
                new WorkflowAssignmentNotification(
                    $ticket,
                    $assigner->nama,
                    $subCategory
                )
            );
        } catch (\Throwable $exception) {
            Log::error(
                'Tugasan Pembekalan berjaya tetapi notifikasi Juruteknik gagal.',
                [
                    'id_tiket' => $ticket->id_tiket,
                    'technician_ids' => $technicians
                        ->pluck('no_ic')
                        ->all(),
                    'message' => $exception->getMessage(),
                ]
            );
        }

        return redirect()
            ->route(
                'tickets.show',
                ['id_tiket' => $ticket->id_tiket]
            )
            ->with(
                'success',
                'Lawatan Pembekalan berjaya dijadualkan dan Juruteknik telah dilantik.'
            );
    }

    public function reviewModernizationTicket(
        ReviewModernizationTicketRequest $request,
        string $id_tiket
    ): RedirectResponse {
        $validated = $request->validated();
        $reviewer = $request->user();

        $ticket = DB::transaction(
            function () use (
                $id_tiket,
                $validated,
                $reviewer
            ): Tiket {
                $ticket = Tiket::query()
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->firstOrFail();

                $expectedStatus = config(
                    's2p_workflow.statuses.initial_review',
                    'Menunggu Semakan'
                );

                if ($ticket->status_tiket !== $expectedStatus) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tiket ini bukan lagi dalam peringkat semakan KUPP.',
                    ]);
                }

                if (
                    $ticket->kategori
                    !== 'Transformasi Digital'
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk tiket Transformasi Digital.',
                    ]);
                }

                $digitalRecord = DB::table(
                    'transformasi_digital'
                )
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->first();

                if (
                    $digitalRecord === null
                    || trim(
                        (string) $digitalRecord->sub_kategori
                    ) !== 'Pemodenan Bilik Mesyuarat'
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk Pemodenan Bilik Mesyuarat.',
                    ]);
                }

                $reportData = [
                    'pendahuluan' => $validated['pendahuluan'],
                    'objektif' => json_encode(
                        $validated['objektif'],
                        JSON_THROW_ON_ERROR
                        | JSON_UNESCAPED_UNICODE
                    ),
                    'skop_kajian' => json_encode(
                        $validated['skop_kajian'],
                        JSON_THROW_ON_ERROR
                        | JSON_UNESCAPED_UNICODE
                    ),
                    'pengguna_ic' => $reviewer->no_ic,
                    'updated_at' => now(),
                ];

                $reportExists = DB::table('laporan')
                    ->where('id_tiket', $id_tiket)
                    ->exists();

                if ($reportExists) {
                    DB::table('laporan')
                        ->where('id_tiket', $id_tiket)
                        ->update($reportData);
                } else {
                    DB::table('laporan')->insert([
                        ...$reportData,
                        'id_tiket' => $id_tiket,
                        'created_at' => now(),
                    ]);
                }

                $ticket->update([
                    'status_tiket' => config(
                        's2p_workflow.statuses.reviewed',
                        'Disemak'
                    ),
                    'disemak_oleh_ic' => $reviewer->no_ic,
                    'tarikh_semakan' => now(),
                    'ulasan_semakan' => null,
                ]);

                $ticket->rekodLog(
                    config(
                        's2p_workflow.trail_events.reviewed',
                        'DISEMAK'
                    ),
                    'Oleh '.$reviewer->nama,
                    'INFO'
                );

                /*
                 * Tandakan notifikasi berkaitan tiket yang diterima
                 * oleh KUPP sebagai telah dibaca.
                 */
                DB::table('notifications')
                    ->where(
                        'notifiable_type',
                        $reviewer->getMorphClass()
                    )
                    ->where(
                        'notifiable_id',
                        (string) $reviewer->getKey()
                    )
                    ->whereNull('read_at')
                    ->where(
                        'data',
                        'LIKE',
                        '%'.$id_tiket.'%'
                    )
                    ->update([
                        'read_at' => now(),
                        'updated_at' => now(),
                    ]);

                return $ticket->fresh();
            },
            3
        );

        try {
            $assigners = Pengguna::query()
                ->where(
                    'peranan',
                    config(
                        's2p_workflow.flows.pemodenan.assignment_role',
                        'ketua_utd'
                    )
                )
                ->where('status_pengguna', 'Aktif')
                ->get();

            if ($assigners->isEmpty()) {
                Log::warning(
                    'Tiada KUTD aktif untuk tugasan Pemodenan.',
                    ['id_tiket' => $ticket->id_tiket]
                );
            } else {
                Notification::send(
                    $assigners,
                    new WorkflowReviewNotification(
                        $ticket,
                        'ketua_utd'
                    )
                );
            }
        } catch (\Throwable $exception) {
            Log::error(
                'Semakan KUPP berjaya tetapi notifikasi KUTD gagal.',
                [
                    'id_tiket' => $ticket->id_tiket,
                    'message' => $exception->getMessage(),
                ]
            );
        }

        return redirect()
            ->route(
                'tickets.show',
                ['id_tiket' => $ticket->id_tiket]
            )
            ->with(
                'success',
                'Maklumat kajian berjaya dikemaskini dan dihantar kepada KUTD.'
            );
    }

    public function assignModernizationTicket(
        AssignModernizationTicketRequest $request,
        string $id_tiket
    ): RedirectResponse {
        $validated = $request->validated();
        $assigner = $request->user();

        [
            $ticket,
            $technicians,
            $subCategory,
        ] = DB::transaction(
            function () use (
                $id_tiket,
                $validated,
                $assigner
            ): array {
                $ticket = Tiket::query()
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->firstOrFail();

                $expectedStatus = config(
                    's2p_workflow.statuses.reviewed',
                    'Disemak'
                );

                if ($ticket->status_tiket !== $expectedStatus) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tiket ini bukan lagi dalam peringkat tugasan KUTD.',
                    ]);
                }

                if (
                    $ticket->kategori
                    !== 'Transformasi Digital'
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk tiket Transformasi Digital.',
                    ]);
                }

                $digitalRecord = DB::table(
                    'transformasi_digital'
                )
                    ->where('id_tiket', $id_tiket)
                    ->lockForUpdate()
                    ->first();

                if ($digitalRecord === null) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Rekod Transformasi Digital tidak ditemui.',
                    ]);
                }

                $subCategory = trim(
                    (string) $digitalRecord->sub_kategori
                );

                if (
                    $subCategory
                    !== 'Pemodenan Bilik Mesyuarat'
                ) {
                    throw ValidationException::withMessages([
                        'sistem' => 'Tindakan ini hanya dibenarkan untuk Pemodenan Bilik Mesyuarat.',
                    ]);
                }

                $technicianIds = array_values(
                    array_unique(
                        $validated['senarai_pic_ic']
                    )
                );

                $technicians = Pengguna::query()
                    ->whereIn('no_ic', $technicianIds)
                    ->where('peranan', 'juruteknik')
                    ->where('status_pengguna', 'Aktif')
                    ->lockForUpdate()
                    ->get();

                if (
                    $technicians->count()
                    !== count($technicianIds)
                ) {
                    throw ValidationException::withMessages([
                        'senarai_pic_ic' => 'Satu atau lebih Juruteknik tidak sah atau tidak aktif.',
                    ]);
                }

                DB::table('transformasi_digital')
                    ->where('id_tiket', $id_tiket)
                    ->update([
                        'tarikh_lawatan' => $validated['tarikh_lawatan'],
                        'masa_lawatan' => $validated['masa_lawatan'],
                        'catatan_lawatan' => $validated['catatan_lawatan']
                            ?? null,
                        'updated_at' => now(),
                    ]);

                DB::table('tugasan_tiket')
                    ->where('id_tiket', $id_tiket)
                    ->delete();

                $assignmentTime = now();

                DB::table('tugasan_tiket')->insert(
                    $technicians
                        ->map(
                            fn (Pengguna $technician): array => [
                                'id_tiket' => $id_tiket,
                                'no_ic' => $technician->no_ic,
                                'created_at' => $assignmentTime,
                                'updated_at' => $assignmentTime,
                            ]
                        )
                        ->all()
                );

                $ticket->update([
                    'status_tiket' => config(
                        's2p_workflow.statuses.in_progress',
                        'Dalam Tindakan'
                    ),
                    'ulasan_semakan' => null,
                ]);

                /*
                 * Peringkat KUTD tidak menghasilkan DISEMAK kedua.
                 * Jejak DISEMAK telah direkod ketika tindakan KUPP.
                 */
                DB::table('notifications')
                    ->where(
                        'notifiable_type',
                        $assigner->getMorphClass()
                    )
                    ->where(
                        'notifiable_id',
                        (string) $assigner->getKey()
                    )
                    ->whereNull('read_at')
                    ->where(
                        'data',
                        'LIKE',
                        '%'.$id_tiket.'%'
                    )
                    ->update([
                        'read_at' => now(),
                        'updated_at' => now(),
                    ]);

                return [
                    $ticket->fresh(),
                    $technicians,
                    $subCategory,
                ];
            },
            3
        );

        try {
            Notification::send(
                $technicians,
                new WorkflowAssignmentNotification(
                    $ticket,
                    $assigner->nama,
                    $subCategory
                )
            );
        } catch (\Throwable $exception) {
            Log::error(
                'Tugasan Pemodenan berjaya tetapi notifikasi Juruteknik gagal.',
                [
                    'id_tiket' => $ticket->id_tiket,
                    'technician_ids' => $technicians
                        ->pluck('no_ic')
                        ->all(),
                    'message' => $exception->getMessage(),
                ]
            );
        }

        return redirect()
            ->route(
                'tickets.show',
                ['id_tiket' => $ticket->id_tiket]
            )
            ->with(
                'success',
                'Lawatan berjaya dijadualkan dan Juruteknik telah dilantik.'
            );
    }

    private function ensureLoanPicCanAct(
        Tiket $ticket,
        string $userIc
    ): void {

        $expectedStatus = config(
            's2p_workflow.statuses.in_progress',
            'Dalam Tindakan'
        );

        if ($ticket->status_tiket !== $expectedStatus) {
            throw ValidationException::withMessages([
                'sistem' => 'Tiket ini tidak berada dalam status Dalam Tindakan.',
            ]);
        }

        $isLoanTicket = $ticket->kategori === 'Meja Bantuan'
            && DB::table('meja_bantuan')
                ->where('id_tiket', $ticket->id_tiket)
                ->where(
                    'sub_kategori',
                    'Peminjaman Peralatan ICT'
                )
                ->exists();

        if (! $isLoanTicket) {
            throw ValidationException::withMessages([
                'sistem' => 'Tindakan ini hanya dibenarkan untuk Peminjaman Peralatan ICT.',
            ]);
        }

        $isAssignedPic = DB::table('tugasan_tiket')
            ->where('id_tiket', $ticket->id_tiket)
            ->where('no_ic', $userIc)
            ->exists();

        if (! $isAssignedPic) {
            throw ValidationException::withMessages([
                'sistem' => 'Anda bukan petugas yang dilantik untuk tiket ini.',
            ]);
        }
    }

    private function resolveFlowKey(
        string $category,
        string $subcategory
    ): ?string {
        foreach (
            config('s2p_workflow.flows', []) as $flowKey => $flow
        ) {
            if (($flow['kategori'] ?? null) !== $category) {
                continue;
            }

            if (
                in_array(
                    $subcategory,
                    $flow['sub_kategori'] ?? [],
                    true
                )
            ) {
                return $flowKey;
            }
        }

        return null;
    }

    private function synchronizeCategoryRecord(
        Tiket $ticket,
        string $category,
        string $subcategory
    ): void {
        $childTables = [
            'Meja Bantuan' => 'meja_bantuan',
            'Konsultasi Rangkaian' => 'konsultasi_rangkaian',
            'Transformasi Digital' => 'transformasi_digital',
        ];

        $selectedTable = $childTables[$category] ?? null;

        if ($selectedTable === null) {
            throw ValidationException::withMessages([
                'kategori' => 'Jadual kategori tiket tidak ditemui.',
            ]);
        }

        /*
         * Semasa status Menunggu Klasifikasi, rekod kategori belum
         * mempunyai data operasi. Rekod lama dibuang jika kategori
         * diubah ketika klasifikasi.
         */
        foreach ($childTables as $table) {
            if ($table === $selectedTable) {
                continue;
            }

            DB::table($table)
                ->where('id_tiket', $ticket->id_tiket)
                ->delete();
        }

        $existingRecord = DB::table($selectedTable)
            ->where('id_tiket', $ticket->id_tiket)
            ->exists();

        if ($existingRecord) {
            DB::table($selectedTable)
                ->where('id_tiket', $ticket->id_tiket)
                ->update([
                    'sub_kategori' => $subcategory,
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table($selectedTable)->insert([
            'id_tiket' => $ticket->id_tiket,
            'sub_kategori' => $subcategory,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
