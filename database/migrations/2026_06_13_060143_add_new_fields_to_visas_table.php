// database/migrations/xxxx_add_new_fields_to_visas_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('visas', function (Blueprint $table) {
            // Email field
            $table->string('email')->nullable()->after('phone');

            // Doctor fields
            $table->string('bmdc_certificate')->nullable()->after('missing_file');
            $table->string('retirement_certificate')->nullable()->after('bmdc_certificate');

            // Lawyer fields
            $table->string('bar_council_certificate')->nullable()->after('retirement_certificate');

            // Student fields
            $table->string('student_id')->nullable()->after('bar_council_certificate');
            $table->string('recommendation_letter')->nullable()->after('student_id');
        });
    }

    public function down()
    {
        Schema::table('visas', function (Blueprint $table) {
            $table->dropColumn([
                'email',
                'bmdc_certificate',
                'retirement_certificate',
                'bar_council_certificate',
                'student_id',
                'recommendation_letter'
            ]);
        });
    }
};
