<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tiket', 'workflow_version')) {
            throw new RuntimeException(
                'Medan workflow_version sudah wujud. Semak struktur sebelum meneruskan.'
            );
        }

        Schema::table('tiket', function (Blueprint $table): void {
            $table->unsignedSmallInteger('workflow_version')->default(1);
        });
    }

    public function down(): void
    {
        if (
            DB::table('tiket')
                ->where('workflow_version', '!=', 1)
                ->exists()
        ) {
            throw new RuntimeException(
                'Rollback dibatalkan kerana terdapat tiket menggunakan versi workflow lain.'
            );
        }

        Schema::table('tiket', function (Blueprint $table): void {
            $table->dropColumn('workflow_version');
        });
    }
};
