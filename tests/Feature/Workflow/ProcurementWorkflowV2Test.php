<?php

namespace Tests\Feature\Workflow;

use App\Models\Pengguna;
use App\Notifications\WorkflowAssignmentNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProcurementWorkflowV2Test extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_procurement_v2_completes_the_full_workflow(): void
    {
        $admin = $this->createUser(
            '880000000001',
            'Admin Ujian',
            'admin'
        );

        $kupp = $this->createUser(
            '880000000002',
            'KUPP Ujian',
            'ketua_upp'
        );

        $kutd = $this->createUser(
            '880000000003',
            'KUTD Ujian',
            'ketua_utd'
        );

        $technician = $this->createUser(
            '880000000004',
            'Juruteknik Ujian',
            'juruteknik'
        );

        $chief = $this->createUser(
            '880000000005',
            'Ketua Wilayah Ujian',
            'ketua_wilayah'
        );

        $ticketId = 'TEST-PROCUREMENT-V2-001';

        $this->createTicket(
            $ticketId,
            $admin,
            'Pembekalan Peralatan ICT',
            'Menunggu Semakan'
        );

        $reviewResponse = $this
            ->actingAs($kupp)
            ->post(
                route(
                    'tickets.workflow.reviewProcurement',
                    ['id_tiket' => $ticketId]
                ),
                $this->procurementReviewPayload()
            );

        $reviewResponse
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route(
                    'tickets.show',
                    ['id_tiket' => $ticketId]
                )
            );

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'status_tiket' => 'Disemak',
            'disemak_oleh_ic' => $kupp->no_ic,
            'workflow_version' => 2,
        ]);

        $this->assertDatabaseHas('laporan', [
            'id_tiket' => $ticketId,
            'pengguna_ic' => $kupp->no_ic,
        ]);

        $this->assertDatabaseHas('jejak_tiket', [
            'id_tiket' => $ticketId,
            'aktiviti' => 'DISEMAK',
        ]);

        $assignmentResponse = $this
            ->actingAs($kutd)
            ->post(
                route(
                    'tickets.workflow.assignProcurement',
                    ['id_tiket' => $ticketId]
                ),
                [
                    'tarikh_lawatan' => now()
                        ->addDay()
                        ->toDateString(),
                    'masa_lawatan' => '10:30',
                    'catatan_lawatan' => 'Lawatan ujian integrasi.',
                    'senarai_pic_ic' => [
                        $technician->no_ic,
                    ],
                ]
            );

        $assignmentResponse
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route(
                    'tickets.show',
                    ['id_tiket' => $ticketId]
                )
            );

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'status_tiket' => 'Dalam Tindakan',
        ]);

        $this->assertDatabaseHas('tugasan_tiket', [
            'id_tiket' => $ticketId,
            'no_ic' => $technician->no_ic,
        ]);

        $this->assertDatabaseHas(
            'transformasi_digital',
            [
                'id_tiket' => $ticketId,
                'tarikh_lawatan' => now()
                    ->addDay()
                    ->toDateString(),
            ]
        );

        $reportResponse = $this
            ->actingAs($technician)
            ->post(
                route(
                    'tickets.workflow.submitProcurementReport',
                    ['id_tiket' => $ticketId]
                ),
                $this->procurementReportPayload()
            );

        $reportResponse
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route(
                    'tickets.show',
                    ['id_tiket' => $ticketId]
                )
            );

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'status_tiket' => 'Menunggu Semakan Laporan',
        ]);

        $this->assertDatabaseHas('laporan', [
            'id_tiket' => $ticketId,
            'pengguna_ic' => $technician->no_ic,
        ]);

        $this->assertDatabaseHas('jejak_tiket', [
            'id_tiket' => $ticketId,
            'aktiviti' => 'DILAKSANA',
        ]);

        $saveResponse = $this
            ->actingAs($kupp)
            ->post(
                route(
                    'tickets.workflow.saveProcurementLkk',
                    ['id_tiket' => $ticketId]
                ),
                $this->procurementCostPayload()
            );

        $saveResponse
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route(
                    'tickets.show',
                    ['id_tiket' => $ticketId]
                )
            );

        $report = DB::table('laporan')
            ->where('id_tiket', $ticketId)
            ->first();

        $this->assertNotNull($report);
        $this->assertNotEmpty($report->pendahuluan);
        $this->assertNotEmpty($report->hasil_kajian);
        $this->assertNotEmpty($report->kos_items);
        $this->assertSame(
            'Rumusan pembekalan untuk ujian integrasi.',
            $report->rumusan
        );
        $this->assertSame(
            $kupp->nama,
            $report->disediakan_oleh
        );

        $verificationResponse = $this
            ->actingAs($kupp)
            ->post(
                route(
                    'tickets.workflow.reviewProcurementLkk',
                    ['id_tiket' => $ticketId]
                ),
                [
                    'tindakan' => 'VERIFIKASI',
                    'ulasan' => null,
                ]
            );

        $verificationResponse
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route(
                    'tickets.show',
                    ['id_tiket' => $ticketId]
                )
            );

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'status_tiket' => 'Menunggu Validasi',
            'disahkan_oleh_ic' => $kupp->no_ic,
        ]);

        $this->assertDatabaseHas('jejak_tiket', [
            'id_tiket' => $ticketId,
            'aktiviti' => 'DIVERIFIKASI',
        ]);

        $validationResponse = $this
            ->actingAs($chief)
            ->post(
                route(
                    'tickets.workflow.validateProcurementLkk',
                    ['id_tiket' => $ticketId]
                ),
                [
                    'tindakan' => 'LULUS',
                    'ulasan' => 'LKK Pembekalan diluluskan.',
                ]
            );

        $validationResponse
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route(
                    'tickets.show',
                    ['id_tiket' => $ticketId]
                )
            );

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'status_tiket' => 'Selesai',
        ]);

        $closedTicket = DB::table('tiket')
            ->where('id_tiket', $ticketId)
            ->first();

        $this->assertNotNull($closedTicket);
        $this->assertNotNull(
            $closedTicket->tarikh_tutup
        );

        $this->assertDatabaseHas('laporan', [
            'id_tiket' => $ticketId,
            'disemak_oleh' => $chief->nama,
        ]);

        $this->assertDatabaseHas('jejak_tiket', [
            'id_tiket' => $ticketId,
            'aktiviti' => 'DIVALIDASI',
        ]);

        $this->assertDatabaseHas('jejak_tiket', [
            'id_tiket' => $ticketId,
            'aktiviti' => 'TIKET DITUTUP',
        ]);

        $this->assertSame(
            1,
            DB::table('laporan')
                ->where('id_tiket', $ticketId)
                ->count()
        );
    }

    public function test_all_assigned_pic_notifications_are_cleared_when_one_pic_acts(): void
    {
        $admin = $this->createUser(
            '882000000001',
            'Admin Notifikasi',
            'admin'
        );

        $kupp = $this->createUser(
            '882000000002',
            'KUPP Notifikasi',
            'ketua_upp'
        );

        $kutd = $this->createUser(
            '882000000003',
            'KUTD Notifikasi',
            'ketua_utd'
        );

        $firstTechnician = $this->createUser(
            '882000000004',
            'Juruteknik Pertama',
            'juruteknik'
        );

        $secondTechnician = $this->createUser(
            '882000000005',
            'Juruteknik Kedua',
            'juruteknik'
        );

        $ticketId = 'TEST-PROCUREMENT-V2-NOTIFICATION';

        $this->createTicket(
            $ticketId,
            $admin,
            'Pembekalan Peralatan ICT',
            'Menunggu Semakan'
        );

        $this->actingAs($kupp)
            ->post(
                route(
                    'tickets.workflow.reviewProcurement',
                    ['id_tiket' => $ticketId]
                ),
                $this->procurementReviewPayload()
            )
            ->assertSessionHasNoErrors();

        $this->actingAs($kutd)
            ->post(
                route(
                    'tickets.workflow.assignProcurement',
                    ['id_tiket' => $ticketId]
                ),
                [
                    'tarikh_lawatan' => now()
                        ->addDay()
                        ->toDateString(),
                    'masa_lawatan' => '10:00',
                    'catatan_lawatan' => 'Ujian notifikasi berbilang PIC.',
                    'senarai_pic_ic' => [
                        $firstTechnician->no_ic,
                        $secondTechnician->no_ic,
                    ],
                ]
            )
            ->assertSessionHasNoErrors();

        Notification::assertSentTo(
            $firstTechnician,
            WorkflowAssignmentNotification::class
        );

        Notification::assertSentTo(
            $secondTechnician,
            WorkflowAssignmentNotification::class
        );

        $notificationIds = [];

        foreach (
            [
                $firstTechnician,
                $secondTechnician,
            ] as $assignedTechnician
        ) {
            $notificationId = (string) Str::uuid();
            $notificationIds[] = $notificationId;

            DB::table('notifications')->insert([
                'id' => $notificationId,
                'type' => WorkflowAssignmentNotification::class,
                'notifiable_type' => $assignedTechnician->getMorphClass(),
                'notifiable_id' => (string) $assignedTechnician->getKey(),
                'data' => json_encode(
                    ['id_tiket' => $ticketId],
                    JSON_THROW_ON_ERROR
                ),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($firstTechnician)
            ->post(
                route(
                    'tickets.workflow.submitProcurementReport',
                    ['id_tiket' => $ticketId]
                ),
                $this->procurementReportPayload()
            )
            ->assertSessionHasNoErrors();

        foreach ($notificationIds as $notificationId) {
            $this->assertNotNull(
                DB::table('notifications')
                    ->where('id', $notificationId)
                    ->value('read_at')
            );
        }

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'status_tiket' => 'Menunggu Semakan Laporan',
        ]);
    }

    public function test_procurement_v2_supports_both_correction_paths(): void
    {
        $admin = $this->createUser(
            '881000000001',
            'Admin Pembetulan',
            'admin'
        );

        $kupp = $this->createUser(
            '881000000002',
            'KUPP Pembetulan',
            'ketua_upp'
        );

        $kutd = $this->createUser(
            '881000000003',
            'KUTD Pembetulan',
            'ketua_utd'
        );

        $technician = $this->createUser(
            '881000000004',
            'Juruteknik Pembetulan',
            'juruteknik'
        );

        $chief = $this->createUser(
            '881000000005',
            'Ketua Wilayah Pembetulan',
            'ketua_wilayah'
        );

        $ticketId = 'TEST-PROCUREMENT-V2-002';

        $this->createTicket(
            $ticketId,
            $admin,
            'Pembekalan Peralatan ICT',
            'Menunggu Semakan'
        );

        $this
            ->actingAs($kupp)
            ->post(
                route(
                    'tickets.workflow.reviewProcurement',
                    ['id_tiket' => $ticketId]
                ),
                $this->procurementReviewPayload()
            )
            ->assertSessionHasNoErrors();

        $this
            ->actingAs($kutd)
            ->post(
                route(
                    'tickets.workflow.assignProcurement',
                    ['id_tiket' => $ticketId]
                ),
                [
                    'tarikh_lawatan' => now()
                        ->addDays(2)
                        ->toDateString(),
                    'masa_lawatan' => '09:00',
                    'catatan_lawatan' => 'Lawatan pembetulan.',
                    'senarai_pic_ic' => [
                        $technician->no_ic,
                    ],
                ]
            )
            ->assertSessionHasNoErrors();

        $this
            ->actingAs($technician)
            ->post(
                route(
                    'tickets.workflow.submitProcurementReport',
                    ['id_tiket' => $ticketId]
                ),
                $this->procurementReportPayload()
            )
            ->assertSessionHasNoErrors();

        $this
            ->actingAs($kupp)
            ->post(
                route(
                    'tickets.workflow.reviewProcurementLkk',
                    ['id_tiket' => $ticketId]
                ),
                [
                    'tindakan' => 'PEMBETULAN',
                    'ulasan' => 'Sila perincikan keadaan semasa.',
                ]
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'status_tiket' => 'Laporan Perlu Pembetulan',
            'ulasan_semakan' => 'Sila perincikan keadaan semasa.',
        ]);

        $this
            ->actingAs($technician)
            ->post(
                route(
                    'tickets.workflow.submitProcurementReport',
                    ['id_tiket' => $ticketId]
                ),
                $this->procurementReportPayload(
                    'Keadaan semasa telah diperincikan.'
                )
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'status_tiket' => 'Menunggu Semakan Laporan',
            'ulasan_semakan' => null,
        ]);

        $this
            ->actingAs($kupp)
            ->post(
                route(
                    'tickets.workflow.saveProcurementLkk',
                    ['id_tiket' => $ticketId]
                ),
                $this->procurementCostPayload()
            )
            ->assertSessionHasNoErrors();

        $this
            ->actingAs($kupp)
            ->post(
                route(
                    'tickets.workflow.reviewProcurementLkk',
                    ['id_tiket' => $ticketId]
                ),
                [
                    'tindakan' => 'VERIFIKASI',
                    'ulasan' => null,
                ]
            )
            ->assertSessionHasNoErrors();

        $this
            ->actingAs($chief)
            ->post(
                route(
                    'tickets.workflow.validateProcurementLkk',
                    ['id_tiket' => $ticketId]
                ),
                [
                    'tindakan' => 'PEMBETULAN',
                    'ulasan' => 'Sila semak semula rumusan kos.',
                ]
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'status_tiket' => 'Pembetulan Laporan',
            'ulasan_semakan' => 'Sila semak semula rumusan kos.',
        ]);

        $this
            ->actingAs($kupp)
            ->post(
                route(
                    'tickets.workflow.saveProcurementLkk',
                    ['id_tiket' => $ticketId]
                ),
                [
                    'kos_items' => [
                        [
                            'jenis_peralatan' => 'Komputer meja dikemaskini',
                            'kuantiti' => 2,
                            'anggaran_kos' => 3500,
                        ],
                    ],
                    'rumusan' => 'Rumusan kos telah dikemaskini.',
                ]
            )
            ->assertSessionHasNoErrors();

        $this
            ->actingAs($kupp)
            ->post(
                route(
                    'tickets.workflow.reviewProcurementLkk',
                    ['id_tiket' => $ticketId]
                ),
                [
                    'tindakan' => 'VERIFIKASI',
                    'ulasan' => null,
                ]
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'status_tiket' => 'Menunggu Validasi',
            'ulasan_semakan' => null,
        ]);

        $this->assertDatabaseMissing('jejak_tiket', [
            'id_tiket' => $ticketId,
            'aktiviti' => 'PEMBETULAN DIMINTA',
        ]);
    }

    public function test_wrong_role_cannot_review_procurement_ticket(): void
    {
        $admin = $this->createUser(
            '883000000001',
            'Admin Autorisasi',
            'admin'
        );

        $kutd = $this->createUser(
            '883000000002',
            'KUTD Autorisasi',
            'ketua_utd'
        );

        $ticketId = 'TEST-PROCUREMENT-AUTH-001';

        $this->createTicket(
            $ticketId,
            $admin,
            'Pembekalan Peralatan ICT',
            'Menunggu Semakan'
        );

        $this
            ->actingAs($kutd)
            ->post(
                route(
                    'tickets.workflow.reviewProcurement',
                    ['id_tiket' => $ticketId]
                ),
                $this->procurementReviewPayload()
            )
            ->assertForbidden();

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'status_tiket' => 'Menunggu Semakan',
        ]);

        $this->assertDatabaseMissing('laporan', [
            'id_tiket' => $ticketId,
        ]);
    }

    public function test_unassigned_technician_cannot_submit_procurement_report(): void
    {
        $admin = $this->createUser(
            '884000000001',
            'Admin Petugas',
            'admin'
        );

        $kupp = $this->createUser(
            '884000000002',
            'KUPP Petugas',
            'ketua_upp'
        );

        $technician = $this->createUser(
            '884000000003',
            'Juruteknik Tidak Dilantik',
            'juruteknik'
        );

        $ticketId = 'TEST-PROCUREMENT-PIC-001';

        $this->createTicket(
            $ticketId,
            $admin,
            'Pembekalan Peralatan ICT',
            'Dalam Tindakan'
        );

        DB::table('laporan')->insert([
            'id_tiket' => $ticketId,
            'pendahuluan' => json_encode(
                $this->procurementReviewPayload()[
                    'pendahuluan'
                ],
                JSON_THROW_ON_ERROR
            ),
            'hasil_kajian' => json_encode(
                $this->procurementReviewPayload()[
                    'hasil_kajian'
                ],
                JSON_THROW_ON_ERROR
            ),
            'pengguna_ic' => $kupp->no_ic,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($technician)
            ->from(
                route(
                    'tickets.show',
                    ['id_tiket' => $ticketId]
                )
            )
            ->post(
                route(
                    'tickets.workflow.submitProcurementReport',
                    ['id_tiket' => $ticketId]
                ),
                $this->procurementReportPayload()
            );

        $response
            ->assertRedirect(
                route(
                    'tickets.show',
                    ['id_tiket' => $ticketId]
                )
            )
            ->assertSessionHasErrors('sistem');

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'status_tiket' => 'Dalam Tindakan',
        ]);
    }

    public function test_procurement_route_rejects_modernization_subcategory(): void
    {
        $admin = $this->createUser(
            '885000000001',
            'Admin Pengasingan',
            'admin'
        );

        $kupp = $this->createUser(
            '885000000002',
            'KUPP Pengasingan',
            'ketua_upp'
        );

        $ticketId = 'TEST-PROCUREMENT-SUB-001';

        $this->createTicket(
            $ticketId,
            $admin,
            'Pemodenan Bilik Mesyuarat',
            'Menunggu Semakan'
        );

        $response = $this
            ->actingAs($kupp)
            ->from(
                route(
                    'tickets.show',
                    ['id_tiket' => $ticketId]
                )
            )
            ->post(
                route(
                    'tickets.workflow.reviewProcurement',
                    ['id_tiket' => $ticketId]
                ),
                $this->procurementReviewPayload()
            );

        $response
            ->assertRedirect(
                route(
                    'tickets.show',
                    ['id_tiket' => $ticketId]
                )
            )
            ->assertSessionHasErrors('sistem');

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'status_tiket' => 'Menunggu Semakan',
        ]);

        $this->assertDatabaseMissing('laporan', [
            'id_tiket' => $ticketId,
        ]);
    }

    private function createUser(
        string $identityNumber,
        string $name,
        string $role
    ): Pengguna {
        DB::table('pengguna')->insert([
            'no_ic' => $identityNumber,
            'nama' => $name,
            'emel' => $identityNumber.'@example.test',
            'kata_laluan' => Hash::make(
                'IntegrationTest1!'
            ),
            'jawatan' => $role,
            'gred' => 'Ujian',
            'no_telefon' => '0100000000',
            'peranan' => $role,
            'status_pengguna' => 'Aktif',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Pengguna::query()
            ->where('no_ic', $identityNumber)
            ->firstOrFail();
    }

    private function createTicket(
        string $ticketId,
        Pengguna $creator,
        string $subCategory,
        string $status
    ): void {
        DB::table('tiket')->insert([
            'id_tiket' => $ticketId,
            'perkara' => 'Ujian Integrasi Pembekalan V2',
            'tarikh_terima' => now(),
            'saluran' => 'Sistem',
            'nama_pemohon' => 'Pemohon Ujian',
            'emel_pemohon' => 'pemohon@example.test',
            'notel_pemohon' => '0101111111',
            'agensi' => 'Agensi Ujian',
            'lokasi' => 'Lokasi Ujian',
            'daerah' => 'Sandakan',
            'kategori' => 'Transformasi Digital',
            'tahap_keutamaan' => 'Sederhana',
            'status_tiket' => $status,
            'pengguna_ic' => $creator->no_ic,
            'workflow_version' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('transformasi_digital')
            ->insert([
                'sub_kategori' => $subCategory,
                'id_tiket' => $ticketId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function procurementReviewPayload(): array
    {
        return [
            'pendahuluan' => [
                'jabatan' => 'Jabatan Ujian',
                'tarikh_terima' => now()
                    ->toDateString(),
                'no_rujukan' => 'RUJ-UJIAN-001',
                'bilangan_kakitangan' => 10,
                'tujuan' => 'Pembekalan komputer untuk kakitangan.',
                'peruntukan' => 'Peruntukan pembangunan',
                'tanggungjawab' => 'Jabatan pemohon',
                'emel_ketua_cawangan' => 'ketua@example.test',
                'pegawai_nama' => 'Pegawai Perhubungan',
                'pegawai_notel' => '0102222222',
                'pegawai_emel' => 'pegawai@example.test',
                'pegawai_jawatan' => 'Pegawai Tadbir',
                'cdo_nama' => 'CDO Ujian',
            ],
            'hasil_kajian' => [
                [
                    'nama_pemohon' => 'Kakitangan Ujian',
                    'jawatan_pemohon' => 'Pembantu Tadbir',
                ],
            ],
        ];
    }

    private function procurementReportPayload(
        string $currentCondition =
            'Komputer lama tidak lagi mencukupi.'
    ): array {
        return [
            'hasil_kajian' => [
                [
                    'nama_pemohon' => 'Kakitangan Ujian',
                    'jawatan_pemohon' => 'Pembantu Tadbir',
                    'keadaan_semasa' => $currentCondition,
                    'justifikasi_cadangan' => 'Komputer baharu diperlukan.',
                ],
            ],
        ];
    }

    private function procurementCostPayload(): array
    {
        return [
            'kos_items' => [
                [
                    'jenis_peralatan' => 'Komputer meja',
                    'kuantiti' => 2,
                    'anggaran_kos' => 3000,
                ],
            ],
            'rumusan' => 'Rumusan pembekalan untuk ujian integrasi.',
        ];
    }
}
