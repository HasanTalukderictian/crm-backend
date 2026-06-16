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
        Schema::table('visas', function (Blueprint $table) {
            // New student document columns
            $table->string('parent_office_id')->nullable()->after('recommendation_letter');
            $table->string('consent_letter')->nullable()->after('parent_office_id');
            $table->string('hotel_booking')->nullable()->after('consent_letter');
            $table->string('air_ticket')->nullable()->after('hotel_booking');
            $table->string('proof_of_residency')->nullable()->after('air_ticket');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visas', function (Blueprint $table) {
            $table->dropColumn([
                'parent_office_id',
                'consent_letter',
                'hotel_booking',
                'air_ticket',
                'proof_of_residency'
            ]);
        });
    }
};
