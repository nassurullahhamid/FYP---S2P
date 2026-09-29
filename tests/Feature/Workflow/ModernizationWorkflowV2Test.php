<?php

namespace Tests\Feature\Workflow;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ModernizationWorkflowV2Test extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Storage::fake('public');
    }

    public function test_modernization_v2_completes_the_full_workflow(): void
    {
        $users = $this->createWorkflowUsers('81');
        $ticketId = $this->createTicket('001', 'Pemodenan Bilik Mesyuarat');

        $this->reviewTicket($users['kupp'], $ticketId);
        $this->assertTicketStatus($ticketId, 'Disemak');

        $this->assignTicket($users['kutd'], $ticketId, $users['technician']);
        $this->assertTicketStatus($ticketId, 'Dalam Tindakan');

        $this->submitReport($users['technician'], $ticketId, 'Keadaan awal lengkap.');
        $this->assertTicketStatus($ticketId, 'Menunggu Semakan Laporan');

        $this->saveLkk($users['kupp'], $ticketId);
        $this->verifyLkk($users['kupp'], $ticketId);
        $this->assertTicketStatus($ticketId, 'Menunggu Validasi');

        $this->actingAs($users['kw'])
            ->post(route('tickets.workflow.validateModernizationLkk', $ticketId), [
                'tindakan' => 'LULUS',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTicketStatus($ticketId, 'Selesai');
        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'disahkan_oleh_ic' => $users['kupp']->no_ic,
        ]);
        $this->assertNotNull(DB::table('tiket')->where('id_tiket', $ticketId)->value('tarikh_tutup'));
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

        $report = DB::table('laporan')->where('id_tiket', $ticketId)->first();
        $this->assertNotNull($report);
        $this->assertSame($users['kupp']->nama, $report->disediakan_oleh);
        $this->assertSame($users['kw']->nama, $report->disemak_oleh);
        $this->assertNotEmpty(json_decode((string) $report->gambar_cadangan, true));
    }

    public function test_modernization_v2_supports_both_correction_paths(): void
    {
        $users = $this->createWorkflowUsers('82');
        $ticketId = $this->createTicket('002', 'Pemodenan Bilik Mesyuarat');

        $this->reviewTicket($users['kupp'], $ticketId);
        $this->assignTicket($users['kutd'], $ticketId, $users['technician']);
        $this->submitReport($users['technician'], $ticketId, 'Versi pertama.');

        $this->actingAs($users['kupp'])
            ->post(route('tickets.workflow.reviewModernizationLkk', $ticketId), [
                'tindakan' => 'PEMBETULAN',
                'ulasan' => 'Sila lengkapkan pemerhatian teknikal.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTicketStatus($ticketId, 'Laporan Perlu Pembetulan');

        $this->submitReport($users['technician'], $ticketId, 'Versi juruteknik diperbetulkan.');
        $this->saveLkk($users['kupp'], $ticketId);
        $this->verifyLkk($users['kupp'], $ticketId);

        $this->actingAs($users['kw'])
            ->post(route('tickets.workflow.validateModernizationLkk', $ticketId), [
                'tindakan' => 'PEMBETULAN',
                'ulasan' => 'Sila perincikan rumusan kos.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTicketStatus($ticketId, 'Pembetulan Ketua');

        $this->saveLkk($users['kupp'], $ticketId, 'Rumusan selepas pembetulan Ketua Wilayah.');
        $this->verifyLkk($users['kupp'], $ticketId);
        $this->assertTicketStatus($ticketId, 'Menunggu Validasi');

        $this->assertSame(
            2,
            DB::table('jejak_tiket')
                ->where('id_tiket', $ticketId)
                ->where('aktiviti', 'PEMBETULAN DIMINTA')
                ->count()
        );
    }

    public function test_wrong_role_cannot_review_modernization_ticket(): void
    {
        $users = $this->createWorkflowUsers('84');
        $ticketId = $this->createTicket('004', 'Pemodenan Bilik Mesyuarat');

        $this->actingAs($users['kutd'])
            ->post(route('tickets.workflow.reviewModernization', $ticketId), $this->reviewPayload())
            ->assertForbidden();

        $this->assertTicketStatus($ticketId, 'Menunggu Semakan');
    }

    public function test_unassigned_technician_cannot_submit_modernization_report(): void
    {
        $users = $this->createWorkflowUsers('85');
        $unassigned = $this->createUser('859999999999', 'Juruteknik Tidak Dilantik 85', 'juruteknik');
        $ticketId = $this->createTicket('005', 'Pemodenan Bilik Mesyuarat');

        $this->reviewTicket($users['kupp'], $ticketId);
        $this->assignTicket($users['kutd'], $ticketId, $users['technician']);

        $this->actingAs($unassigned)
            ->post(route('tickets.workflow.submitModernizationReport', $ticketId), $this->reportPayload('Tidak dibenarkan.'))
            ->assertSessionHasErrors('sistem');

        $this->assertTicketStatus($ticketId, 'Dalam Tindakan');
    }

    public function test_modernization_route_rejects_procurement_subcategory(): void
    {
        $users = $this->createWorkflowUsers('86');
        $ticketId = $this->createTicket('006', 'Pembekalan Peralatan ICT');

        $this->actingAs($users['kupp'])
            ->post(route('tickets.workflow.reviewModernization', $ticketId), $this->reviewPayload())
            ->assertSessionHasErrors('sistem');

        $this->assertTicketStatus($ticketId, 'Menunggu Semakan');
        $this->assertDatabaseMissing('laporan', ['id_tiket' => $ticketId]);
    }

    private function reviewTicket(Pengguna $kupp, string $ticketId): void
    {
        $this->actingAs($kupp)
            ->post(route('tickets.workflow.reviewModernization', $ticketId), $this->reviewPayload())
            ->assertSessionHasNoErrors();
    }

    private function assignTicket(Pengguna $kutd, string $ticketId, Pengguna $technician): void
    {
        $this->actingAs($kutd)
            ->post(route('tickets.workflow.assignModernization', $ticketId), [
                'tarikh_lawatan' => now()->addDay()->format('Y-m-d'),
                'masa_lawatan' => '09:30',
                'catatan_lawatan' => 'Lawatan ujian integrasi Pemodenan V2.',
                'senarai_pic_ic' => [$technician->no_ic],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tugasan_tiket', [
            'id_tiket' => $ticketId,
            'no_ic' => $technician->no_ic,
        ]);
    }

    private function submitReport(Pengguna $technician, string $ticketId, string $observation): void
    {
        $this->actingAs($technician)
            ->post(route('tickets.workflow.submitModernizationReport', $ticketId), $this->reportPayload($observation))
            ->assertSessionHasNoErrors();
    }

    private function saveLkk(Pengguna $kupp, string $ticketId, string $summary = 'Pemodenan disyorkan berdasarkan kajian teknikal.'): void
    {
        $this->actingAs($kupp)
            ->post(route('tickets.workflow.saveModernizationLkk', $ticketId), [
                'kos_items' => [[
                    'item' => 'Paparan interaktif',
                    'kuantiti' => 1,
                    'harga_seunit' => 12000,
                ]],
                'rumusan' => $summary,
            ])
            ->assertSessionHasNoErrors();
    }

    private function verifyLkk(Pengguna $kupp, string $ticketId): void
    {
        $this->actingAs($kupp)
            ->post(route('tickets.workflow.reviewModernizationLkk', $ticketId), [
                'tindakan' => 'VERIFIKASI',
            ])
            ->assertSessionHasNoErrors();
    }

    private function reviewPayload(): array
    {
        return [
            'pendahuluan' => 'Kajian keperluan pemodenan bilik mesyuarat.',
            'objektif' => [[
                'teks' => 'Meningkatkan kemudahan persidangan.',
            ]],
            'skop_kajian' => [[
                'teks' => 'Peralatan audio visual dan rangkaian.',
            ]],
        ];
    }

    private function reportPayload(string $observation): array
    {
        return [
            'keadaan_semasa' => [[
                'aspek' => 'Paparan visual',
                'ulasan' => $observation,
            ]],
            'cadangan_penambahbaikan' => 'Naik taraf paparan dan kemudahan persidangan.',
            'gambar_cadangan' => UploadedFile::fake()->create(
                'cadangan.pdf',
                32,
                'application/pdf'
            ),
        ];
    }

    private function createWorkflowUsers(string $prefix): array
    {
        return [
            'kupp' => $this->createUser($prefix.'0000000001', 'KUPP '.$prefix, 'ketua_upp'),
            'kutd' => $this->createUser($prefix.'0000000002', 'KUTD '.$prefix, 'ketua_utd'),
            'technician' => $this->createUser($prefix.'0000000003', 'Juruteknik '.$prefix, 'juruteknik'),
            'kw' => $this->createUser($prefix.'0000000004', 'Ketua Wilayah '.$prefix, 'ketua_wilayah'),
        ];
    }

    private function createUser(string $identity, string $name, string $role): Pengguna
    {
        return Pengguna::query()->create([
            'no_ic' => $identity,
            'nama' => $name,
            'emel' => strtolower(str_replace(' ', '.', $name)).'@example.test',
            'kata_laluan' => Hash::make('password-test'),
            'jawatan' => 'Pegawai Ujian',
            'gred' => 'F9',
            'no_telefon' => '088000000',
            'peranan' => $role,
            'status_pengguna' => 'Aktif',
        ]);
    }

    private function createTicket(string $suffix, string $subCategory): string
    {
        $applicant = $this->createUser(
            '87'.str_pad($suffix, 10, '0', STR_PAD_LEFT),
            'Pemohon '.$suffix,
            'juruteknik'
        );
        $ticketId = 'TEST-TD-MOD-'.$suffix;

        DB::table('tiket')->insert([
            'id_tiket' => $ticketId,
            'perkara' => 'Ujian integrasi Pemodenan V2',
            'tarikh_terima' => now(),
            'saluran' => 'Sistem',
            'nama_pemohon' => 'Pemohon '.$suffix,
            'emel_pemohon' => 'pemohon'.$suffix.'@example.test',
            'notel_pemohon' => '088111111',
            'agensi' => 'Agensi Ujian',
            'lokasi' => 'Bilik Mesyuarat Ujian',
            'daerah' => 'Kota Kinabalu',
            'kategori' => 'Transformasi Digital',
            'status_tiket' => 'Menunggu Semakan',
            'pengguna_ic' => $applicant->no_ic,
            'workflow_version' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('transformasi_digital')->insert([
            'sub_kategori' => $subCategory,
            'id_tiket' => $ticketId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $ticketId;
    }

    private function assertTicketStatus(string $ticketId, string $status): void
    {
        $this->assertDatabaseHas('tiket', [
            'id_tiket' => $ticketId,
            'status_tiket' => $status,
        ]);
    }
}
