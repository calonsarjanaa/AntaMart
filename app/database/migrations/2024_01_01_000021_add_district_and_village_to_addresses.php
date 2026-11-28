<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('district', 100)->nullable()->after('city');
            $table->string('village', 150)->nullable()->after('district');
        });

        Schema::table('order_addresses', function (Blueprint $table) {
            $table->string('district', 100)->nullable()->after('city');
            $table->string('village', 150)->nullable()->after('district');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['district', 'village']);
        });

        Schema::table('order_addresses', function (Blueprint $table) {
            $table->dropColumn(['district', 'village']);
        });
    }
};

