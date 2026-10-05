<?php

namespace Tests\Feature\Workflow;

use App\Models\Pengguna;
use App\Notifications\WorkflowAssignmentNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class NetworkWorkflowV2Test extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Storage::fake('public');
    }

    public function test_network_v2_completes_the_full_workflow(): void
    {
        $users = $this->createWorkflowUsers('91');
        $ticketId = $this->createTicket(
            '001',
            'Pemasangan Baharu'
        );

        $this->reviewTicket(
            $users['kutd'],
            $ticketId,
            $users['technician']
        );

        $this->assertTicketStatus(
            $ticketId,
            'Dalam Tindakan'
        );

        $this->assertDatabaseHas('tugasan_tiket', [
            'id_tiket' => $ticketId,
            'no_ic' => $users['technician']->no_ic,
        ]);

        $this->submitSiteReport(
            $users['technician'],
            $ticketId
        );

        $this->assertTicketStatus(
            $ticketId,
            'Menunggu Semakan Laporan'
        );

        $this->actingAs($users['kutd'])
            ->post(
                route(
                    'tickets.workflow.reviewNetworkSiteReport',
                    $ticketId
                ),
                [
                    'tindakan' => 'TERIMA',
                ]
            )
            ->assertSessionHasNoErrors();

        $this->assertTicketStatus(
            $ticketId,
            'Sedia Diverifikasi'
        );

        $this->actingAs($users['kutd'])
            ->post(
                route(
                    'tickets.workflow.saveNetworkLkk',
                    $ticketId
                ),
                $this->lkkPayload()
            )
            ->assertSessionHasNoErrors();

        $this->assertTicketStatus(
            $ticketId,
            'Menunggu Validasi'
        );

        $this->actingAs($users['kw'])
            ->post(
                route(
                    'tickets.workflow.validateNetworkLkk',
                    $ticketId
                ),
                [
                    'tindakan' => 'LULUS',
                ]
            )
            ->assertSessionHasNoErrors();

        $this->assertTicketStatus(
            $ticketId,
            'Selesai'
        );

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'disahkan_oleh_ic' => $users['kutd']->no_ic,
        ]);

        $this->assertDatabaseHas('jejak_tiket', [
            'id_tiket' => $ticketId,
            'aktiviti' => 'DISEMAK',
        ]);

        $this->assertDatabaseHas('jejak_tiket', [
            'id_tiket' => $ticketId,
            'aktiviti' => 'DILAKSANA',
        ]);

        $this->assertDatabaseHas('jejak_tiket', [
            'id_tiket' => $ticketId,
            'aktiviti' => 'DISAHKAN',
        ]);

        $this->assertDatabaseHas('jejak_tiket', [
            'id_tiket' => $ticketId,
            'aktiviti' => 'DIVERIFIKASI',
        ]);

        $this->assertDatabaseHas('jejak_tiket', [
            'id_tiket' => $ticketId,
            'aktiviti' => 'DIVALIDASI',
        ]);

        $this->assertDatabaseHas('jejak_tiket', [
            'id_tiket' => $ticketId,
            'aktiviti' => 'TIKET DITUTUP',
        ]);

        $closedAt = DB::table('tiket')
            ->where('id_tiket', $ticketId)
            ->value('tarikh_tutup');

        $this->assertNotNull($closedAt);

        $network = DB::table('konsultasi_rangkaian')
            ->where('id_tiket', $ticketId)
            ->first();

        $this->assertNotNull($network);
        $this->assertSame(
            'Premis kerajaan',
            $network->jenis_premis
        );
        $this->assertSame(
            'Liputan keseluruhan bangunan',
            $network->liputan
        );

        $report = DB::table('laporan')
            ->where('id_tiket', $ticketId)
            ->first();

        $this->assertNotNull($report);
        $this->assertNotEmpty($report->logical_diagram);
        $this->assertNotEmpty($report->physical_diagram);
        $this->assertSame(
            $users['kutd']->nama,
            $report->disediakan_oleh
        );
        $this->assertSame(
            $users['kw']->nama,
            $report->disemak_oleh
        );
    }

    public function test_wrong_role_cannot_review_network_ticket(): void
    {
        $users = $this->createWorkflowUsers('93');
        $ticketId = $this->createTicket(
            '003',
            'Pemasangan Baharu'
        );

        $this->actingAs($users['kupp'])
            ->post(
                route(
                    'tickets.workflow.reviewNetwork',
                    $ticketId
                ),
                $this->reviewPayload(
                    $users['technician']
                )
            )
            ->assertForbidden();

        $this->assertTicketStatus(
            $ticketId,
            'Menunggu Semakan'
        );
    }

    public function test_unassigned_technician_cannot_submit_network_report(): void
    {
        $users = $this->createWorkflowUsers('94');

        $unassigned = $this->createUser(
            '949999999999',
            'Juruteknik Tidak Dilantik 94',
            'juruteknik'
        );

        $ticketId = $this->createTicket(
            '004',
            'Pemasangan Baharu'
        );

        $this->reviewTicket(
            $users['kutd'],
            $ticketId,
            $users['technician']
        );

        $this->actingAs($unassigned)
            ->post(
                route(
                    'tickets.workflow.submitNetworkSiteReport',
                    $ticketId
                ),
                $this->siteReportPayload()
            )
            ->assertSessionHasErrors('sistem');

        $this->assertTicketStatus(
            $ticketId,
            'Dalam Tindakan'
        );
    }

    public function test_network_route_rejects_helpdesk_category(): void
    {
        $users = $this->createWorkflowUsers('95');
        $ticketId = $this->createHelpdeskTicket(
            '005',
            'Aduan Perkakasan'
        );

        $this->actingAs($users['kutd'])
            ->post(
                route(
                    'tickets.workflow.reviewNetwork',
                    $ticketId
                ),
                $this->reviewPayload(
                    $users['technician']
                )
            )
            ->assertSessionHasErrors('sistem');

        $this->assertTicketStatus(
            $ticketId,
            'Menunggu Semakan'
        );
    }

    public function test_all_assigned_pic_notifications_are_cleared_when_one_pic_acts(): void
    {
        $users = $this->createWorkflowUsers('92');
        $secondTechnician = $this->createUser(
            '920000000005',
            'Juruteknik Kedua 92',
            'juruteknik'
        );
        $ticketId = $this->createTicket(
            '002',
            'Naiktaraf'
        );

        $payload = $this->reviewPayload(
            $users['technician']
        );
        $payload['senarai_pic_ic'] = [
            $users['technician']->no_ic,
            $secondTechnician->no_ic,
        ];

        $this->actingAs($users['kutd'])
            ->post(
                route(
                    'tickets.workflow.reviewNetwork',
                    $ticketId
                ),
                $payload
            )
            ->assertSessionHasNoErrors();

        Notification::assertSentTo(
            $users['technician'],
            WorkflowAssignmentNotification::class
        );
        Notification::assertSentTo(
            $secondTechnician,
            WorkflowAssignmentNotification::class
        );

        $notificationIds = [];

        foreach (
            [$users['technician'], $secondTechnician] as $assignedTechnician
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

        $this->submitSiteReport(
            $users['technician'],
            $ticketId
        );

        foreach ($notificationIds as $notificationId) {
            $this->assertNotNull(
                DB::table('notifications')
                    ->where('id', $notificationId)
                    ->value('read_at')
            );
        }
    }

    public function test_network_correction_workflow_does_not_add_correction_requested_trail(): void
    {
        $users = $this->createWorkflowUsers('98');
        $ticketId = $this->createTicket(
            '006',
            'Pemasangan Baharu'
        );

        $this->reviewTicket(
            $users['kutd'],
            $ticketId,
            $users['technician']
        );
        $this->submitSiteReport(
            $users['technician'],
            $ticketId
        );

        $this->actingAs($users['kutd'])
            ->post(
                route(
                    'tickets.workflow.reviewNetworkSiteReport',
                    $ticketId
                ),
                ['tindakan' => 'TERIMA']
            )
            ->assertSessionHasNoErrors();

        $this->actingAs($users['kutd'])
            ->post(
                route(
                    'tickets.workflow.saveNetworkLkk',
                    $ticketId
                ),
                $this->lkkPayload()
            )
            ->assertSessionHasNoErrors();

        $this->actingAs($users['kw'])
            ->post(
                route(
                    'tickets.workflow.validateNetworkLkk',
                    $ticketId
                ),
                [
                    'tindakan' => 'PEMBETULAN',
                    'ulasan' => 'Sila kemas kini laporan akhir.',
                ]
            )
            ->assertSessionHasNoErrors();

        $this->assertTicketStatus(
            $ticketId,
            'Pembetulan Laporan'
        );
        $this->assertDatabaseMissing('jejak_tiket', [
            'id_tiket' => $ticketId,
            'aktiviti' => 'PEMBETULAN DIMINTA',
        ]);
    }

    private function reviewTicket(
        Pengguna $kutd,
        string $ticketId,
        Pengguna $technician
    ): void {
        $this->actingAs($kutd)
            ->post(
                route(
                    'tickets.workflow.reviewNetwork',
                    $ticketId
                ),
                $this->reviewPayload($technician)
            )
            ->assertSessionHasNoErrors();
    }

    private function submitSiteReport(
        Pengguna $technician,
        string $ticketId
    ): void {
        $this->actingAs($technician)
            ->post(
                route(
                    'tickets.workflow.submitNetworkSiteReport',
                    $ticketId
                ),
                $this->siteReportPayload()
            )
            ->assertSessionHasNoErrors();
    }

    private function reviewPayload(
        Pengguna $technician
    ): array {
        return [
            'pendahuluan' => 'Kajian teknikal keperluan rangkaian.',
            'objektif' => [
                [
                    'teks' => 'Meningkatkan kestabilan rangkaian.',
                ],
            ],
            'senarai_pic_ic' => [
                $technician->no_ic,
            ],
            'tarikh_lawatan' => now()->addDay()->format('Y-m-d'),
            'masa_lawatan' => '09:30',
            'catatan_lawatan' => 'Lawatan integrasi Rangkaian V2.',
        ];
    }

    private function siteReportPayload(): array
    {
        return [
            'nama_lokasi_bangunan' => 'Bangunan Ujian Rangkaian',
            'jenis_premis' => 'Premis kerajaan',
            'bilik_server' => 'Bilik server tersedia',
            'rack_server' => 'Rak server 42U',
            'sumber_kuasa' => 'UPS dan bekalan utama',
            'persekitaran_fizikal' => 'Suhu dan pengudaraan sesuai',
            'liputan' => 'Liputan keseluruhan bangunan',
            'jenis_capaian' => 'Fiber dan LAN',
            'kelajuan' => '1 Gbps',
            'lan' => 'LAN berstruktur',
            'ap' => 'Empat access point',
            'firewall' => 'Firewall tersedia',
            'rumusan' => 'Infrastruktur sesuai untuk dinaik taraf.',
            'ulasan_teknikal' => [
                [
                    'teks' => 'Konfigurasi rangkaian perlu dikemas kini.',
                ],
            ],
            'cadangan_penambahbaikan' => [
                [
                    'teks' => 'Naik taraf switch dan access point.',
                ],
            ],
            'logical_diagram' => UploadedFile::fake()->create(
                'logical-network.pdf',
                32,
                'application/pdf'
            ),
            'physical_diagram' => UploadedFile::fake()->create(
                'physical-network.jpg',
                32,
                'image/jpeg'
            ),
        ];
    }

    private function lkkPayload(): array
    {
        return [
            'is_draft' => false,
            'pendahuluan' => 'Laporan kerja rangkaian selepas lawatan.',
            'objektif' => [
                [
                    'teks' => 'Menambah baik kestabilan rangkaian.',
                ],
            ],
            'cadangan_penambahbaikan' => [
                [
                    'teks' => 'Menggantikan switch dan access point.',
                ],
            ],
            'kos_items' => [
                [
                    'item' => 'Managed network switch',
                    'kuantiti' => 2,
                    'anggaran' => 3500,
                ],
            ],
            'rumusan' => 'Naik taraf rangkaian disyorkan.',
        ];
    }

    private function createWorkflowUsers(
        string $prefix
    ): array {
        return [
            'kupp' => $this->createUser(
                $prefix.'0000000001',
                'KUPP '.$prefix,
                'ketua_upp'
            ),
            'kutd' => $this->createUser(
                $prefix.'0000000002',
                'KUTD '.$prefix,
                'ketua_utd'
            ),
            'technician' => $this->createUser(
                $prefix.'0000000003',
                'Juruteknik '.$prefix,
                'juruteknik'
            ),
            'kw' => $this->createUser(
                $prefix.'0000000004',
                'Ketua Wilayah '.$prefix,
                'ketua_wilayah'
            ),
        ];
    }

    private function createUser(
        string $identity,
        string $name,
        string $role
    ): Pengguna {
        return Pengguna::query()->create([
            'no_ic' => $identity,
            'nama' => $name,
            'emel' => strtolower(
                str_replace(' ', '.', $name)
            ).'@example.test',
            'kata_laluan' => Hash::make('password-test'),
            'jawatan' => 'Pegawai Ujian',
            'gred' => 'F9',
            'no_telefon' => '088000000',
            'peranan' => $role,
            'status_pengguna' => 'Aktif',
        ]);
    }

    private function createTicket(
        string $suffix,
        string $subCategory
    ): string {
        $applicant = $this->createUser(
            '96'.str_pad(
                $suffix,
                10,
                '0',
                STR_PAD_LEFT
            ),
            'Pemohon Rangkaian '.$suffix,
            'juruteknik'
        );

        $ticketId = 'TEST-KR-V2-'.$suffix;

        DB::table('tiket')->insert([
            'id_tiket' => $ticketId,
            'perkara' => 'Ujian integrasi Rangkaian V2',
            'tarikh_terima' => now(),
            'saluran' => 'Sistem',
            'nama_pemohon' => 'Pemohon Rangkaian '.$suffix,
            'emel_pemohon' => 'network'.$suffix.'@example.test',
            'notel_pemohon' => '088111111',
            'agensi' => 'Agensi Ujian',
            'lokasi' => 'Bangunan Rangkaian',
            'daerah' => 'Kota Kinabalu',
            'kategori' => 'Konsultasi Rangkaian',
            'status_tiket' => 'Menunggu Semakan',
            'pengguna_ic' => $applicant->no_ic,
            'workflow_version' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('konsultasi_rangkaian')->insert([
            'id_tiket' => $ticketId,
            'sub_kategori' => $subCategory,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $ticketId;
    }

    private function createHelpdeskTicket(
        string $suffix,
        string $subCategory
    ): string {
        $applicant = $this->createUser(
            '97'.str_pad(
                $suffix,
                10,
                '0',
                STR_PAD_LEFT
            ),
            'Pemohon Meja Bantuan '.$suffix,
            'juruteknik'
        );

        $ticketId = 'TEST-MB-NET-'.$suffix;

        DB::table('tiket')->insert([
            'id_tiket' => $ticketId,
            'perkara' => 'Ujian pengasingan kategori',
            'tarikh_terima' => now(),
            'saluran' => 'Sistem',
            'nama_pemohon' => 'Pemohon Meja Bantuan '.$suffix,
            'emel_pemohon' => 'helpdesk'.$suffix.'@example.test',
            'notel_pemohon' => '088222222',
            'agensi' => 'Agensi Ujian',
            'lokasi' => 'Lokasi Ujian',
            'daerah' => 'Kota Kinabalu',
            'kategori' => 'Meja Bantuan',
            'status_tiket' => 'Menunggu Semakan',
            'pengguna_ic' => $applicant->no_ic,
            'workflow_version' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('meja_bantuan')->insert([
            'id_tiket' => $ticketId,
            'sub_kategori' => $subCategory,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $ticketId;
    }

    private function assertTicketStatus(
        string $ticketId,
        string $status
    ): void {
        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'status_tiket' => $status,
        ]);
    }
}
