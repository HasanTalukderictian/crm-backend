<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visas', function (Blueprint $table) {
            $table->enum('notary_status', ['Pending', 'Processing', 'Missing'])->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('visas', function (Blueprint $table) {
            $table->dropColumn('notary_status');
        });
    }
};
