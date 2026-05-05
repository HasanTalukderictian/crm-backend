<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::create('refund_requests', function (Blueprint $table) {
        $table->id();
        $table->string('invoice')->unique();
        $table->string('customerName');
        $table->string('customerPhone');
        $table->string('appliedCountry');
        $table->string('salesPerson');
        $table->string('usersName'); // যে ইউজার রিকোয়েস্ট ক্রিয়েট করছে
        $table->text('refundNote');

        // Status: pending, manager_approved, admin_approved, rejected, completed
        $table->string('status')->default('pending');

        $table->foreignId('manager_id')->nullable()->constrained('users');
        $table->foreignId('admin_id')->nullable()->constrained('users');
        $table->foreignId('finance_id')->nullable()->constrained('users');

        $table->text('rejection_reason')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refund_requests');
    }
};
