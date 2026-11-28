<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedInteger('weight_grams')->default(500)->after('price');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('postal_code');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        Schema::table('order_addresses', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('country');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_courier', 50)->nullable()->after('shipping_cost');
            $table->string('shipping_service', 100)->nullable()->after('shipping_courier');
            $table->string('shipping_etd', 50)->nullable()->after('shipping_service');
            $table->decimal('shipping_distance_km', 8, 2)->nullable()->after('shipping_etd');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_courier',
                'shipping_service',
                'shipping_etd',
                'shipping_distance_km',
            ]);
        });

        Schema::table('order_addresses', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('weight_grams');
        });
    }
};

