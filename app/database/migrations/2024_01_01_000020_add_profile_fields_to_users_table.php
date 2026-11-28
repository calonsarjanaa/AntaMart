<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('role');
            $table->string('recipient_name')->nullable()->after('birth_date');
            $table->string('phone', 30)->nullable()->after('recipient_name');
            $table->string('address_line1')->nullable()->after('phone');
            $table->string('address_line2')->nullable()->after('address_line1');
            $table->string('city', 100)->nullable()->after('address_line2');
            $table->string('province', 100)->nullable()->after('city');
            $table->string('postal_code', 20)->nullable()->after('province');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'birth_date',
                'recipient_name',
                'phone',
                'address_line1',
                'address_line2',
                'city',
                'province',
                'postal_code',
            ]);
        });
    }
};

