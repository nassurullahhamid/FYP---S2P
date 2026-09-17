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
        Schema::table('pemulangan_aset', function (Blueprint $table) {
            $table->unique(
                ['id_tiket', 'serial_no'],
                'pemulangan_aset_tiket_serial_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pemulangan_aset', function (Blueprint $table) {
            $table->dropUnique(
                'pemulangan_aset_tiket_serial_unique'
            );
        });
    }
};