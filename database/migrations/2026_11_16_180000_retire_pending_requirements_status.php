<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_requests')->where('status', 'pending_requirements')->update(['status' => 'pending_verification']);
        Schema::table('document_requests', function (Blueprint $table) {
            $table->enum('status', ['pending_verification', 'processing', 'ready_for_release', 'completed', 'rejected'])->default('pending_verification')->change();
        });
    }

    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->enum('status', ['pending_requirements', 'pending_verification', 'processing', 'ready_for_release', 'completed', 'rejected'])->default('pending_requirements')->change();
        });
    }
};
