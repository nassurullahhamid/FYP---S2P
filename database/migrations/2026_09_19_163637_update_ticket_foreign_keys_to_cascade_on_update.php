<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'jejak_tiket',
            'konsultasi_rangkaian',
            'laporan',
            'meja_bantuan',
            'transformasi_digital',
            'tugasan_tiket',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['id_tiket']);

                $table->foreign('id_tiket')
                    ->references('id_tiket')
                    ->on('tiket')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'jejak_tiket',
            'konsultasi_rangkaian',
            'laporan',
            'meja_bantuan',
            'transformasi_digital',
            'tugasan_tiket',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['id_tiket']);

                $table->foreign('id_tiket')
                    ->references('id_tiket')
                    ->on('tiket')
                    ->restrictOnUpdate()
                    ->cascadeOnDelete();
            });
        }
    }
};
