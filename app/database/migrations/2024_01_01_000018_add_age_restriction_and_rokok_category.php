<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->unsignedTinyInteger('min_age')->nullable()->after('slug');
        });

        DB::table('product_categories')->updateOrInsert(
            ['slug' => 'rokok-21-plus'],
            [
                'name' => 'Rokok 21+',
                'slug' => 'rokok-21-plus',
                'min_age' => 21,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('product_categories')->where('slug', 'rokok-21-plus')->delete();

        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('min_age');
        });
    }
};

