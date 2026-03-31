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
            $table->enum('status', ['Pending', 'Processing', 'Complete'])
                  ->default('Pending')
                  ->after('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visas', function (Blueprint $table) {
            //
        });
    }
};
