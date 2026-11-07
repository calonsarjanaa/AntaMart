<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Preserve existing variant product IDs by creating a minimal catalog record for each.
        $now = now();
        $productIds = DB::table('product_variants')
            ->select('product_id')
            ->distinct()
            ->orderBy('product_id')
            ->pluck('product_id');

        foreach ($productIds as $productId) {
            $name = DB::table('product_variants')
                ->where('product_id', $productId)
                ->orderBy('id')
                ->value('name');

            DB::table('products')->insert([
                'id' => $productId,
                'name' => $name ?: "Catalog item {$productId}",
                'slug' => "catalog-item-{$productId}",
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // SQLite cannot add a foreign key to an existing table; its app-level relation checks remain active.
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->dropForeign(['product_id']);
            });
        }

        Schema::dropIfExists('products');
    }
};
