<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tiket', 'workflow_version')) {
            return;
        }

        DB::statement(
            'ALTER TABLE tiket '.
            'ALTER workflow_version SET DEFAULT 2'
        );
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tiket', 'workflow_version')) {
            return;
        }

        DB::statement(
            'ALTER TABLE tiket '.
            'ALTER workflow_version DROP DEFAULT'
        );
    }
};
