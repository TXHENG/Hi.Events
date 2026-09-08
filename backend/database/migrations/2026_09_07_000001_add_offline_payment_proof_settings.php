<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table): void {
            $table->boolean('allow_offline_payment_proof')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('event_settings', function (Blueprint $table): void {
            $table->dropColumn('allow_offline_payment_proof');
        });
    }
};
