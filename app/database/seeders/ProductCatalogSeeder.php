<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class ProductCatalogSeeder extends Seeder
{
    /** Seed synthetic local catalog content for storefront development. */
    public function run(): void
    {
        $catalog = [
            [
                'name' => 'Everyday Ceramic Mug',
                'slug' => 'everyday-ceramic-mug',
                'description' => 'A balanced stoneware mug with a soft matte glaze, made for the first cup and the last.',
                'sort_order' => 10,
                'variants' => [
                    ['name' => 'Oat', 'sku' => 'AM-MUG-OAT', 'price' => 185000, 'stock' => 18],
                    ['name' => 'Olive', 'sku' => 'AM-MUG-OLIVE', 'price' => 185000, 'stock' => 12],
                ],
            ],
            [
                'name' => 'Market Day Tote',
                'slug' => 'market-day-tote',
                'description' => 'A sturdy everyday carryall with room for the useful things you find along the way.',
                'sort_order' => 20,
                'variants' => [
                    ['name' => 'Natural', 'sku' => 'AM-TOTE-NATURAL', 'price' => 245000, 'stock' => 9],
                ],
            ],
            [
                'name' => 'Linen Kitchen Cloth',
                'slug' => 'linen-kitchen-cloth',
                'description' => 'Washed linen with a relaxed hand, designed to be used often and kept close.',
                'sort_order' => 30,
                'variants' => [
                    ['name' => 'Clay', 'sku' => 'AM-LINEN-CLAY', 'price' => 125000, 'stock' => 15],
                    ['name' => 'Sage', 'sku' => 'AM-LINEN-SAGE', 'price' => 125000, 'stock' => 0],
                ],
            ],
            [
                'name' => 'Sunday Stoneware Bowl',
                'slug' => 'sunday-stoneware-bowl',
                'description' => 'A versatile, hand-finished bowl for slow breakfasts and simple suppers.',
                'sort_order' => 40,
                'variants' => [
                    ['name' => 'Chalk', 'sku' => 'AM-BOWL-CHALK', 'price' => 215000, 'stock' => 7],
                ],
            ],
        ];

        foreach ($catalog as $productData) {
            $variants = $productData['variants'];
            unset($productData['variants']);

            $product = Product::updateOrCreate(
                ['slug' => $productData['slug']],
                $productData + ['is_active' => true, 'image_path' => null]
            );

            foreach ($variants as $variantData) {
                ProductVariant::firstOrCreate(
                    ['sku' => $variantData['sku']],
                    [
                        'product_id' => $product->id,
                        'name' => $variantData['name'],
                        'price' => $variantData['price'],
                        'stock_physical' => $variantData['stock'],
                        'stock_reserved' => 0,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
