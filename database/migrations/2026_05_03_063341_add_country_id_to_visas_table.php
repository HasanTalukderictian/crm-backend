<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visas', function (Blueprint $table) {
            $table->json('country_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('visas', function (Blueprint $table) {
            $table->dropColumn('country_id');
        });
    }
};
