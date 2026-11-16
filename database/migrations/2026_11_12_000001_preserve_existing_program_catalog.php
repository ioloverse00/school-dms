<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(\App\Services\PreserveProgramCatalog::class)->handle();
    }

    public function down(): void
    {
        // Removing catalog values could orphan assigned courses. Data preservation is intentional.
    }
};
