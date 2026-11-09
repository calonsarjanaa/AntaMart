<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 120)->unique();
            $table->timestamps();
        });

        $now = now();
        DB::table('product_categories')->insert([
            ['name' => 'Kebutuhan Pokok', 'slug' => 'kebutuhan-pokok', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Makanan Ringan', 'slug' => 'makanan-ringan', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Minuman', 'slug' => 'minuman', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Obat-obatan', 'slug' => 'obat-obatan', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Alat Kebersihan', 'slug' => 'alat-kebersihan', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Alat Mandi', 'slug' => 'alat-mandi', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Alat Tulis dan Sekolah', 'slug' => 'alat-tulis-dan-sekolah', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Perawatan Diri', 'slug' => 'perawatan-diri', 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->index();
        });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('products', function (Blueprint $table) {
                $table->foreign('category_id')->references('id')->on('product_categories')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('products', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
            });
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('category_id');
        });

        Schema::dropIfExists('product_categories');
    }
};
