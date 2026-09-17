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
        Schema::create('pemulangan_aset', function (Blueprint $table) {
            $table->id('id_pemulangan');

            $table->string('id_tiket', 255);
            $table->string('serial_no', 255);
            $table->dateTime('tarikh_pulang');
            $table->string('diterima_oleh_ic', 255);
            $table->string('keadaan_aset', 50);
            $table->text('catatan')->nullable();

            $table->timestamps();

            $table->foreign('id_tiket')
                ->references('id_tiket')
                ->on('tiket')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('serial_no')
                ->references('serial_no')
                ->on('aset')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('diterima_oleh_ic')
                ->references('no_ic')
                ->on('pengguna')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->index('id_tiket');
            $table->index('serial_no');
            $table->index('tarikh_pulang');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pemulangan_aset');
    }
};