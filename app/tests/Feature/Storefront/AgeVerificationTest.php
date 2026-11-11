<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $this->regularCategory = ProductCategory::where('slug', 'makanan-ringan')->first()
        ?? ProductCategory::create(['name' => 'Makanan Ringan', 'slug' => 'makanan-ringan']);

    $this->rokokCategory = ProductCategory::where('slug', 'rokok-21-plus')->first()
        ?? ProductCategory::create(['name' => 'Rokok 21+', 'slug' => 'rokok-21-plus', 'min_age' => 21]);

    // Regular product
    $this->regularProduct = Product::create([
        'category_id' => $this->regularCategory->id,
        'name' => 'Keripik Kentang Renyah',
        'slug' => 'keripik-kentang-renyah',
        'description' => 'Keripik kentang gurih',
        'is_active' => true,
    ]);
    ProductVariant::create([
        'product_id' => $this->regularProduct->id,
        'sku' => 'SNACK-001',
        'name' => 'Default',
        'price' => 15000,
        'stock_physical' => 10,
        'stock_reserved' => 0,
        'is_active' => true,
    ]);

    // Age-restricted product in Rokok 21+
    $this->rokokProduct = Product::create([
        'category_id' => $this->rokokCategory->id,
        'name' => 'Cerutu Premium Nusantara',
        'slug' => 'cerutu-premium-nusantara',
        'description' => 'Produk tembakau khusus dewasa',
        'is_active' => true,
    ]);
    ProductVariant::create([
        'product_id' => $this->rokokProduct->id,
        'sku' => 'ROKOK-001',
        'name' => 'Pack',
        'price' => 45000,
        'stock_physical' => 10,
        'stock_reserved' => 0,
        'is_active' => true,
    ]);
});

test('rokok products are excluded from regular product browsing and general search', function () {
    // 1. Regular homepage (Semua kategori)
    $response = $this->get('/');
    $response->assertOk()
        ->assertSee('Keripik Kentang Renyah')
        ->assertDontSee('Cerutu Premium Nusantara');

    // 2. Searching without category
    $searchResponse = $this->get('/?q=Cerutu');
    $searchResponse->assertOk()
        ->assertDontSee('Cerutu Premium Nusantara');

    // 3. Browsing another category
    $otherCatResponse = $this->get('/?category=' . $this->regularCategory->slug);
    $otherCatResponse->assertOk()
        ->assertSee('Keripik Kentang Renyah')
        ->assertDontSee('Cerutu Premium Nusantara');
});

test('unverified user clicking rokok category is redirected to age verification page', function () {
    $response = $this->get('/?category=rokok-21-plus');

    $response->assertRedirect(route('storefront.age-verification.show', 'rokok-21-plus'));

    $verifyPage = $this->get(route('storefront.age-verification.show', 'rokok-21-plus'));
    $verifyPage->assertOk()
        ->assertSee('Verifikasi Usia Anda')
        ->assertSee('Rokok 21+')
        ->assertSee('21+')
        ->assertSee('name="birth_date"', false);
});

test('user younger than 21 is rejected and cannot access rokok products', function () {
    // 17 years old
    $underageBirthDate = now()->subYears(17)->format('Y-m-d');

    $response = $this->post(route('storefront.age-verification.verify', 'rokok-21-plus'), [
        'birth_date' => $underageBirthDate,
    ]);

    $response->assertSessionHasErrors('birth_date');
    expect(session('age_verified_21'))->toBeNull();

    // Still cannot view rokok category
    $categoryResponse = $this->get('/?category=rokok-21-plus');
    $categoryResponse->assertRedirect(route('storefront.age-verification.show', 'rokok-21-plus'));
});

test('user 21 or older verifies age successfully and can view rokok category', function () {
    // Exactly 21 years old today
    $validBirthDate = now()->subYears(21)->format('Y-m-d');

    $response = $this->post(route('storefront.age-verification.verify', 'rokok-21-plus'), [
        'birth_date' => $validBirthDate,
    ]);

    $response->assertRedirect(route('storefront.home', ['category' => 'rokok-21-plus']))
        ->assertSessionHas('status');

    expect(session('age_verified_21'))->toBeTrue();

    // Now user can view rokok category products
    $categoryResponse = $this->get('/?category=rokok-21-plus');
    $categoryResponse->assertOk()
        ->assertSee('Cerutu Premium Nusantara')
        ->assertDontSee('Keripik Kentang Renyah');
});

test('unverified user accessing rokok product detail is redirected to verify with return URL', function () {
    $response = $this->get(route('storefront.products.show', $this->rokokProduct->slug));

    $response->assertRedirect(route('storefront.age-verification.show', [
        'category' => 'rokok-21-plus',
        'return_url' => route('storefront.products.show', $this->rokokProduct->slug),
    ]));

    // Now verify age with return_url
    $validBirthDate = now()->subYears(25)->format('Y-m-d');
    $verifyResponse = $this->post(route('storefront.age-verification.verify', 'rokok-21-plus'), [
        'birth_date' => $validBirthDate,
        'return_url' => route('storefront.products.show', $this->rokokProduct->slug),
    ]);

    $verifyResponse->assertRedirect(route('storefront.products.show', $this->rokokProduct->slug));

    // Accessing product now succeeds
    $productResponse = $this->get(route('storefront.products.show', $this->rokokProduct->slug));
    $productResponse->assertOk()
        ->assertSee('Cerutu Premium Nusantara');
});

