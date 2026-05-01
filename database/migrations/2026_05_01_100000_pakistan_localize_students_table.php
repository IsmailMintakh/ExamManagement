<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pakistan-localize the students table.
 *
 *  - `aadhaar_no` (Indian National ID) → `cnic` (Pakistani Computerized
 *    National Identity Card / B-Form for minors). Format: 13 digits,
 *    typically displayed as 12345-1234567-1.
 *  - Drop `caste` (Indian caste system — not used in Pakistani govt schools).
 *  - Drop `category` (Indian SC/ST/OBC reservation category — Pakistan has
 *    no equivalent system in education).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Add the new CNIC column. 18 chars accommodates "12345-1234567-1" with dashes.
            if (!Schema::hasColumn('students', 'cnic')) {
                $table->string('cnic', 18)->nullable()->after('blood_group');
            }
        });

        // Copy any existing aadhaar_no values into cnic so historical data isn't lost.
        if (Schema::hasColumn('students', 'aadhaar_no')) {
            DB::statement('UPDATE students SET cnic = aadhaar_no WHERE cnic IS NULL AND aadhaar_no IS NOT NULL');
        }

        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'aadhaar_no')) $table->dropColumn('aadhaar_no');
            if (Schema::hasColumn('students', 'caste'))      $table->dropColumn('caste');
            if (Schema::hasColumn('students', 'category'))   $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'aadhaar_no')) {
                $table->string('aadhaar_no', 20)->nullable()->after('blood_group');
            }
            if (!Schema::hasColumn('students', 'caste')) {
                $table->string('caste', 100)->nullable()->after('blood_group');
            }
            if (!Schema::hasColumn('students', 'category')) {
                $table->string('category', 50)->nullable()->after('blood_group');
            }
        });

        if (Schema::hasColumn('students', 'cnic')) {
            DB::statement('UPDATE students SET aadhaar_no = cnic WHERE aadhaar_no IS NULL AND cnic IS NOT NULL');
        }

        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'cnic')) $table->dropColumn('cnic');
        });
    }
};
