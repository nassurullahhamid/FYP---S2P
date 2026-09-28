<?php

namespace Tests\Feature\Workflow;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LoanWorkflowV2Test extends TestCase
{
    use DatabaseTransactions;

    public function test_loan_v2_completes_the_full_workflow(): void
    {
        Notification::fake();

        [$kupp, $kutd, $technician] =
            $this->createWorkflowUsers('01');

        $ticketId = 'TEST-LOAN-V2-001';
        $serialNumber = 'TEST-ASSET-001';

        $this->createLoanTicket($ticketId, 2, $kupp->no_ic);
        $this->createAsset($serialNumber);

        $this->actingAs($kupp)
            ->postJson(
                route(
                    'tickets.workflow.generateLoanAssets',
                    ['id_tiket' => $ticketId]
                ),
                [
                    'id_aset' => 'Komputer Riba',
                    'kuantiti_lulus' => 1,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'senarai_aset.0.serial_no',
                $serialNumber
            );

        $this->actingAs($kupp)
            ->post(
                route(
                    'tickets.workflow.reviewLoan',
                    ['id_tiket' => $ticketId]
                ),
                $this->reviewPayload(
                    $technician,
                    $serialNumber
                )
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'status_tiket' => 'Dalam Tindakan',
        ]);

        $this->assertDatabaseHas('aset', [
            'serial_no' => $serialNumber,
            'status' => 'Dipinjam',
        ]);

        $this->assertDatabaseHas('tugasan_tiket', [
            'id_tiket' => $ticketId,
            'no_ic' => $technician->no_ic,
        ]);

        $this->assertDatabaseHas('jejak_tiket', [
            'id_tiket' => $ticketId,
            'aktiviti' => 'DISEMAK',
        ]);

        $report = DB::table('laporan')
            ->where('id_tiket', $ticketId)
            ->first();

        $this->assertNotNull($report);

        $loanInformation = json_decode(
            $report->kos_items,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame(
            $serialNumber,
            $loanInformation['senarai_siri'][0]['serial_no']
        );

        $this->actingAs($technician)
            ->post(
                route(
                    'tickets.workflow.saveLoanForm',
                    ['id_tiket' => $ticketId]
                ),
                $this->loanFormPayload($serialNumber)
            )
            ->assertSessionHasNoErrors();

        $report = DB::table('laporan')
            ->where('id_tiket', $ticketId)
            ->first();

        $loanInformation = json_decode(
            $report->kos_items,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $assetItem = $loanInformation['senarai_siri'][0];

        $this->assertSame(
            'HARTA-TEST-001',
            $assetItem['no_pendaftaran_harta']
        );
        $this->assertSame(
            'Terpakai',
            $assetItem['status_perkakasan']
        );
        $this->assertSame(
            'Dipinjamkan',
            $assetItem['mod_penggunaan']
        );
        $this->assertSame(
            'Pegawai Teknologi Maklumat',
            $assetItem['jawatan_penerima']
        );

        $this->actingAs($technician)
            ->post(
                route(
                    'tickets.workflow.submitLoan',
                    ['id_tiket' => $ticketId]
                )
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'status_tiket' => 'Menunggu Pengesahan',
        ]);

        $this->assertDatabaseHas('jejak_tiket', [
            'id_tiket' => $ticketId,
            'aktiviti' => 'DILAKSANA',
        ]);

        $this->actingAs($kutd)
            ->post(
                route(
                    'tickets.workflow.confirmLoan',
                    ['id_tiket' => $ticketId]
                ),
                [
                    'ulasan' => 'Borang peminjaman lengkap dan disahkan.',
                ]
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'status_tiket' => 'Selesai',
            'ulasan_semakan' => 'Borang peminjaman lengkap dan disahkan.',
        ]);

        $ticket = DB::table('tiket')
            ->where('id_tiket', $ticketId)
            ->first();

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

    public function test_loan_endpoint_rejects_workflow_one_ticket(): void
    {
        [$kupp, , $technician] =
            $this->createWorkflowUsers('02');

        $ticketId = 'TEST-LOAN-V1-001';
        $serialNumber = 'TEST-ASSET-002';

        $this->createLoanTicket($ticketId, 1, $kupp->no_ic);
        $this->createAsset($serialNumber);

        $this->actingAs($kupp)
            ->post(
                route(
                    'tickets.workflow.reviewLoan',
                    ['id_tiket' => $ticketId]
                ),
                $this->reviewPayload(
                    $technician,
                    $serialNumber
                )
            )
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'workflow_version' => 1,
            'status_tiket' => 'Menunggu Semakan',
        ]);

        $this->assertDatabaseHas('aset', [
            'serial_no' => $serialNumber,
            'status' => 'Tersedia',
        ]);
    }

    public function test_wrong_role_cannot_review_loan_ticket(): void
    {
        [$kupp, $kutd, $technician] =
            $this->createWorkflowUsers('03');

        $ticketId = 'TEST-LOAN-V2-ROLE';
        $serialNumber = 'TEST-ASSET-003';

        $this->createLoanTicket($ticketId, 2, $kupp->no_ic);
        $this->createAsset($serialNumber);

        $this->actingAs($kutd)
            ->post(
                route(
                    'tickets.workflow.reviewLoan',
                    ['id_tiket' => $ticketId]
                ),
                $this->reviewPayload(
                    $technician,
                    $serialNumber
                )
            )
            ->assertForbidden();

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'status_tiket' => 'Menunggu Semakan',
        ]);

        $this->assertDatabaseHas('aset', [
            'serial_no' => $serialNumber,
            'status' => 'Tersedia',
        ]);
    }

    public function test_unassigned_technician_cannot_save_loan_form(): void
    {
        [$kupp, , $assignedTechnician] =
            $this->createWorkflowUsers('04');

        $unassignedTechnician = $this->createUser(
            '840404120049',
            'Juruteknik Tidak Dilantik',
            'juruteknik'
        );

        $ticketId = 'TEST-LOAN-V2-PIC';
        $serialNumber = 'TEST-ASSET-004';

        $this->createLoanTicket($ticketId, 2, $kupp->no_ic);
        $this->createAsset($serialNumber);

        $this->actingAs($kupp)
            ->post(
                route(
                    'tickets.workflow.reviewLoan',
                    ['id_tiket' => $ticketId]
                ),
                $this->reviewPayload(
                    $assignedTechnician,
                    $serialNumber
                )
            )
            ->assertSessionHasNoErrors();

        $this->actingAs($unassignedTechnician)
            ->post(
                route(
                    'tickets.workflow.saveLoanForm',
                    ['id_tiket' => $ticketId]
                ),
                $this->loanFormPayload($serialNumber)
            )
            ->assertSessionHasErrors();

        $report = DB::table('laporan')
            ->where('id_tiket', $ticketId)
            ->first();

        $loanInformation = json_decode(
            $report->kos_items,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertArrayNotHasKey(
            'status_perkakasan',
            $loanInformation['senarai_siri'][0]
        );
    }

    public function test_pic_cannot_submit_incomplete_loan_form(): void
    {
        [$kupp, , $technician] =
            $this->createWorkflowUsers('05');

        $ticketId = 'TEST-LOAN-V2-INCOMPLETE';
        $serialNumber = 'TEST-ASSET-005';

        $this->createLoanTicket($ticketId, 2, $kupp->no_ic);
        $this->createAsset($serialNumber);

        $this->actingAs($kupp)
            ->post(
                route(
                    'tickets.workflow.reviewLoan',
                    ['id_tiket' => $ticketId]
                ),
                $this->reviewPayload(
                    $technician,
                    $serialNumber
                )
            )
            ->assertSessionHasNoErrors();

        $this->actingAs($technician)
            ->post(
                route(
                    'tickets.workflow.submitLoan',
                    ['id_tiket' => $ticketId]
                )
            )
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'status_tiket' => 'Dalam Tindakan',
        ]);
    }

    public function test_loan_route_rejects_non_loan_subcategory(): void
    {
        [$kupp, , $technician] =
            $this->createWorkflowUsers('06');

        $ticketId = 'TEST-LOAN-V2-SUBCATEGORY';
        $serialNumber = 'TEST-ASSET-006';

        $this->createLoanTicket(
            $ticketId,
            2,
            $kupp->no_ic,
            'Penyelenggaraan Komputer'
        );

        $this->createAsset($serialNumber);

        $this->actingAs($kupp)
            ->post(
                route(
                    'tickets.workflow.reviewLoan',
                    ['id_tiket' => $ticketId]
                ),
                $this->reviewPayload(
                    $technician,
                    $serialNumber
                )
            )
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('aset', [
            'serial_no' => $serialNumber,
            'status' => 'Tersedia',
        ]);

        $this->assertDatabaseMissing('tugasan_tiket', [
            'id_tiket' => $ticketId,
        ]);
    }

    public function test_generate_loan_assets_rejects_insufficient_stock(): void
    {
        [$kupp] = $this->createWorkflowUsers('07');

        $ticketId = 'TEST-LOAN-V2-STOCK';
        $serialNumber = 'TEST-ASSET-007';

        $this->createLoanTicket($ticketId, 2, $kupp->no_ic);
        $this->createAsset($serialNumber);

        $this->actingAs($kupp)
            ->postJson(
                route(
                    'tickets.workflow.generateLoanAssets',
                    ['id_tiket' => $ticketId]
                ),
                [
                    'id_aset' => 'Komputer Riba',
                    'kuantiti_lulus' => 2,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'kuantiti_lulus',
            ]);
    }

    private function createWorkflowUsers(
        string $suffix
    ): array {
        $kupp = $this->createUser(
            "81010112{$suffix}01",
            "KUPP Peminjaman {$suffix}",
            'ketua_upp'
        );

        $kutd = $this->createUser(
            "82020212{$suffix}02",
            "KUTD Peminjaman {$suffix}",
            'ketua_utd'
        );

        $technician = $this->createUser(
            "83030312{$suffix}03",
            "Juruteknik Peminjaman {$suffix}",
            'juruteknik'
        );

        return [$kupp, $kutd, $technician];
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
                'ketua_upp' => 'Ketua Unit Penyelarasan Perkhidmatan',
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

    private function createLoanTicket(
        string $ticketId,
        int $workflowVersion,
        string $ownerIdentityNumber,
        string $subCategory = 'Peminjaman Peralatan ICT'
    ): void {
        DB::table('tiket')->insert([
            'id_tiket' => $ticketId,
            'perkara' => 'Ujian integrasi Peminjaman V2',
            'tarikh_terima' => now(),
            'saluran' => 'Sistem',
            'nama_pemohon' => 'Pemohon Ujian',
            'emel_pemohon' => 'pemohon@example.test',
            'notel_pemohon' => '0123456789',
            'agensi' => 'Jabatan Ujian',
            'lokasi' => 'Pejabat Ujian',
            'daerah' => 'Sandakan',
            'kategori' => 'Meja Bantuan',
            'tahap_keutamaan' => 'Sederhana',
            'status_tiket' => 'Menunggu Semakan',
            'pengguna_ic' => $ownerIdentityNumber,
            'workflow_version' => $workflowVersion,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('meja_bantuan')->insert([
            'id_tiket' => $ticketId,
            'sub_kategori' => $subCategory,
            'kuantiti_dipinjam' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createAsset(
        string $serialNumber
    ): void {
        DB::table('aset')->insert([
            'serial_no' => $serialNumber,
            'nama_aset' => 'Komputer Riba',
            'model' => 'Model Ujian',
            'cpu' => 'Intel Core i5',
            'ram' => '16 GB',
            'hard_disk' => '512 GB SSD',
            'os' => 'Windows 11',
            'status' => 'Tersedia',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function reviewPayload(
        Pengguna $technician,
        string $serialNumber
    ): array {
        return [
            'id_aset' => 'Komputer Riba',
            'kuantiti_lulus' => 1,
            'pic_ic' => $technician->no_ic,
            'senarai_aset' => [
                [
                    'serial_no' => $serialNumber,
                ],
            ],
        ];
    }

    private function loanFormPayload(
        string $serialNumber
    ): array {
        return [
            'serial_no' => $serialNumber,
            'no_harta' => 'HARTA-TEST-001',
            'status_perkakasan' => 'Terpakai',
            'mod_penggunaan' => 'Dipinjamkan',
            'jawatan_penerima' => 'Pegawai Teknologi Maklumat',
            'catatan' => 'Peralatan berada dalam keadaan baik.',
        ];
    }
}
