<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use RuntimeException;

trait EnsuresSafeTestingDatabase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSafeTestingDatabase();
    }

    protected function assertSafeTestingDatabase(): void
    {
        $expectedDatabase =
            's2p_database_testing';

        if (! app()->environment('testing')) {
            throw new RuntimeException(
                'UJIAN DIHENTIKAN: Environment aplikasi '.
                'bukan testing.'
            );
        }

        $defaultConnection =
            config('database.default');

        $driver = config(
            'database.connections.'.
            $defaultConnection.
            '.driver'
        );

        if ($driver !== 'mysql') {
            throw new RuntimeException(
                'UJIAN DIHENTIKAN: Driver database '.
                'bukan MySQL. Ditemui: '.
                (string) $driver
            );
        }

        $configuredDatabase = config(
            'database.connections.'.
            $defaultConnection.
            '.database'
        );

        if (
            $configuredDatabase
            !== $expectedDatabase
        ) {
            throw new RuntimeException(
                'UJIAN DIHENTIKAN: Konfigurasi database '.
                'bukan s2p_database_testing. Ditemui: '.
                (string) $configuredDatabase
            );
        }

        $connection = DB::connection(
            $defaultConnection
        );

        $activeDatabase =
            $connection->getDatabaseName();

        if ($activeDatabase !== $expectedDatabase) {
            throw new RuntimeException(
                'UJIAN DIHENTIKAN: Sambungan aktif '.
                'bukan s2p_database_testing. Ditemui: '.
                (string) $activeDatabase
            );
        }

        $result = $connection->selectOne(
            'SELECT DATABASE() AS active_database'
        );

        $serverDatabase =
            $result->active_database ?? null;

        if ($serverDatabase !== $expectedDatabase) {
            throw new RuntimeException(
                'UJIAN DIHENTIKAN: Server MySQL '.
                'melaporkan database yang salah. Ditemui: '.
                (string) $serverDatabase
            );
        }

        if (
            $activeDatabase === 's2p_database'
            || $serverDatabase === 's2p_database'
        ) {
            throw new RuntimeException(
                'UJIAN DIHENTIKAN: Database pembangunan '.
                'tidak boleh digunakan oleh PHPUnit.'
            );
        }
    }
}
