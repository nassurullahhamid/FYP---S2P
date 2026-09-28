<?php

namespace Tests\Feature\Workflow;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class HelpdeskWorkflowV2Test extends TestCase
{
    use DatabaseTransactions;

    public function test_helpdesk_v2_completes_the_full_workflow(): void
    {
        Notification::fake();

        $kutd = $this->createUser(
            '810101120001',
            'KUTD Meja Bantuan',
            'ketua_utd'
        );

        $technician = $this->createUser(
            '820202120002',
            'Juruteknik Meja Bantuan',
            'juruteknik'
        );

        $ticketId = 'TEST-MB-V2-001';

        $this->createHelpdeskTicket(
            $ticketId,
            2,
            'Penyelenggaraan Komputer'
        );

        $this->actingAs($kutd)
            ->post(
                route(
                    'tickets.workflow.reviewHelpdesk',
                    ['id_tiket' => $ticketId]
                ),
                [
                    'pic_ic' => $technician->no_ic,
                ]
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'workflow_version' => 2,
            'status_tiket' => 'Dalam Tindakan',
            'disemak_oleh_ic' => $kutd->no_ic,
        ]);

        $this->assertDatabaseHas('tugasan_tiket', [
            'id_tiket' => $ticketId,
            'no_ic' => $technician->no_ic,
        ]);

        $this->assertDatabaseHas('jejak_tiket', [
            'id_tiket' => $ticketId,
            'aktiviti' => 'DISEMAK',
        ]);

        $this->actingAs($technician)
            ->post(
                route(
                    'tickets.workflow.submitHelpdesk',
                    ['id_tiket' => $ticketId]
                ),
                [
                    'catatan_penutupan' => 'Pemeriksaan dan pembaikan komputer telah selesai.',
                ]
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'status_tiket' => 'Menunggu Pengesahan',
            'catatan_penutupan' => 'Pemeriksaan dan pembaikan komputer telah selesai.',
        ]);

        $this->assertDatabaseHas('jejak_tiket', [
            'id_tiket' => $ticketId,
            'aktiviti' => 'DILAKSANA',
        ]);

        $this->actingAs($kutd)
            ->post(
                route(
                    'tickets.workflow.confirmHelpdesk',
                    ['id_tiket' => $ticketId]
                ),
                [
                    'ulasan' => 'Tindakan Juruteknik telah disemak dan disahkan.',
                ]
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'status_tiket' => 'Selesai',
            'ulasan_semakan' => 'Tindakan Juruteknik telah disemak dan disahkan.',
            'disahkan_oleh_ic' => $kutd->no_ic,
        ]);

        $ticket = DB::table('tiket')
            ->where('id_tiket', $ticketId)
            ->first();

        $this->assertNotNull($ticket);
        $this->assertNotNull($ticket->tarikh_tutup);

        $this->assertDatabaseHas('jejak_tiket', [
            'id_tiket' => $ticketId,
            'aktiviti' => 'DISAHKAN',
        ]);

        $this->assertDatabaseHas('jejak_tiket', [
            'id_tiket' => $ticketId,
            'aktiviti' => 'TIKET DITUTUP',
        ]);
    }

    public function test_helpdesk_endpoint_rejects_workflow_one_ticket(): void
    {
        $kutd = $this->createUser(
            '810101120011',
            'KUTD Workflow Satu',
            'ketua_utd'
        );

        $technician = $this->createUser(
            '820202120012',
            'Juruteknik Workflow Satu',
            'juruteknik'
        );

        $ticketId = 'TEST-MB-V1-001';

        $this->createHelpdeskTicket(
            $ticketId,
            1,
            'Penyelenggaraan Komputer'
        );

        $this->actingAs($kutd)
            ->post(
                route(
                    'tickets.workflow.reviewHelpdesk',
                    ['id_tiket' => $ticketId]
                ),
                [
                    'pic_ic' => $technician->no_ic,
                ]
            )
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'workflow_version' => 1,
            'status_tiket' => 'Menunggu Semakan',
        ]);

        $this->assertDatabaseMissing('tugasan_tiket', [
            'id_tiket' => $ticketId,
            'no_ic' => $technician->no_ic,
        ]);
    }

    public function test_wrong_role_cannot_review_helpdesk_ticket(): void
    {
        $technician = $this->createUser(
            '820202120021',
            'Juruteknik Tidak Dibenarkan',
            'juruteknik'
        );

        $assignedTechnician = $this->createUser(
            '820202120022',
            'Juruteknik Pilihan',
            'juruteknik'
        );

        $ticketId = 'TEST-MB-V2-ROLE';

        $this->createHelpdeskTicket(
            $ticketId,
            2,
            'Penyelenggaraan Komputer'
        );

        $this->actingAs($technician)
            ->post(
                route(
                    'tickets.workflow.reviewHelpdesk',
                    ['id_tiket' => $ticketId]
                ),
                [
                    'pic_ic' => $assignedTechnician->no_ic,
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'status_tiket' => 'Menunggu Semakan',
        ]);

        $this->assertDatabaseMissing('tugasan_tiket', [
            'id_tiket' => $ticketId,
        ]);
    }

    public function test_unassigned_technician_cannot_submit_helpdesk_action(): void
    {
        $kutd = $this->createUser(
            '810101120031',
            'KUTD Agihan',
            'ketua_utd'
        );

        $assignedTechnician = $this->createUser(
            '820202120032',
            'Juruteknik Dilantik',
            'juruteknik'
        );

        $unassignedTechnician = $this->createUser(
            '820202120033',
            'Juruteknik Tidak Dilantik',
            'juruteknik'
        );

        $ticketId = 'TEST-MB-V2-PIC';

        $this->createHelpdeskTicket(
            $ticketId,
            2,
            'Penyelenggaraan Komputer'
        );

        $this->actingAs($kutd)
            ->post(
                route(
                    'tickets.workflow.reviewHelpdesk',
                    ['id_tiket' => $ticketId]
                ),
                [
                    'pic_ic' => $assignedTechnician->no_ic,
                ]
            )
            ->assertSessionHasNoErrors();

        $this->actingAs($unassignedTechnician)
            ->post(
                route(
                    'tickets.workflow.submitHelpdesk',
                    ['id_tiket' => $ticketId]
                ),
                [
                    'catatan_penutupan' => 'Catatan daripada Juruteknik yang tidak dilantik.',
                ]
            )
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'status_tiket' => 'Dalam Tindakan',
        ]);

        $ticket = DB::table('tiket')
            ->where('id_tiket', $ticketId)
            ->first();

        $this->assertNotNull($ticket);
        $this->assertNull($ticket->catatan_penutupan);
    }

    public function test_helpdesk_route_rejects_loan_subcategory(): void
    {
        $kutd = $this->createUser(
            '810101120041',
            'KUTD Pengasingan',
            'ketua_utd'
        );

        $technician = $this->createUser(
            '820202120042',
            'Juruteknik Pengasingan',
            'juruteknik'
        );

        $ticketId = 'TEST-MB-V2-LOAN';

        $this->createHelpdeskTicket(
            $ticketId,
            2,
            'Peminjaman Peralatan ICT'
        );

        $this->actingAs($kutd)
            ->post(
                route(
                    'tickets.workflow.reviewHelpdesk',
                    ['id_tiket' => $ticketId]
                ),
                [
                    'pic_ic' => $technician->no_ic,
                ]
            )
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'status_tiket' => 'Menunggu Semakan',
        ]);

        $this->assertDatabaseMissing('tugasan_tiket', [
            'id_tiket' => $ticketId,
        ]);
    }

    public function test_unassigned_kutd_cannot_confirm_before_pic_submission(): void
    {
        $kutd = $this->createUser(
            '810101120051',
            'KUTD Pengesahan Awal',
            'ketua_utd'
        );

        $technician = $this->createUser(
            '820202120052',
            'Juruteknik Pengesahan Awal',
            'juruteknik'
        );

        $ticketId = 'TEST-MB-V2-EARLY';

        $this->createHelpdeskTicket(
            $ticketId,
            2,
            'Penyelenggaraan Komputer'
        );

        $this->actingAs($kutd)
            ->post(
                route(
                    'tickets.workflow.reviewHelpdesk',
                    ['id_tiket' => $ticketId]
                ),
                [
                    'pic_ic' => $technician->no_ic,
                ]
            )
            ->assertSessionHasNoErrors();

        $this->actingAs($kutd)
            ->post(
                route(
                    'tickets.workflow.confirmHelpdesk',
                    ['id_tiket' => $ticketId]
                ),
                [
                    'ulasan' => 'Pengesahan terlalu awal.',
                ]
            )
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'status_tiket' => 'Dalam Tindakan',
        ]);
    }

    private function createUser(
        string $identityNumber,
        string $name,
        string $role
    ): Pengguna {
        return Pengguna::query()->create([
            'no_ic' => $identityNumber,
            'nama' => $name,
            'emel' => strtolower(
                str_replace(' ', '.', $name)
            ).'@example.test',
            'kata_laluan' => Hash::make('Password123!'),
            'jawatan' => match ($role) {
                'ketua_utd' => 'Ketua Unit Teknikal dan DRC',
                'juruteknik' => 'Juruteknik Komputer',
                default => 'Pegawai',
            },
            'gred' => 'FA29',
            'no_telefon' => '0123456789',
            'peranan' => $role,
            'status_pengguna' => 'Aktif',
        ]);
    }

    private function createHelpdeskTicket(
        string $ticketId,
        int $workflowVersion,
        string $subCategory
    ): void {
        $ownerIdentityNumber = Pengguna::query()
            ->orderBy('no_ic')
            ->value('no_ic');

        $this->assertNotNull(
            $ownerIdentityNumber,
            'Fixture tiket memerlukan pengguna pemilik.'
        );

        DB::table('tiket')->insert([
            'id_tiket' => $ticketId,
            'perkara' => 'Ujian integrasi Meja Bantuan V2',
            'tarikh_terima' => now(),
            'saluran' => 'Sistem',
            'nama_pemohon' => 'Pemohon Ujian',
            'emel_pemohon' => 'pemohon@example.test',
            'notel_pemohon' => '0123456789',
            'agensi' => 'Jabatan Ujian',
            'lokasi' => 'Makmal Komputer',
            'daerah' => 'Sandakan',
            'kategori' => 'Meja Bantuan',
            'tahap_keutamaan' => 'Sederhana',
            'status_tiket' => 'Menunggu Semakan',
            'workflow_version' => $workflowVersion,
            'pengguna_ic' => $ownerIdentityNumber,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('meja_bantuan')->insert([
            'id_tiket' => $ticketId,
            'sub_kategori' => $subCategory,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
