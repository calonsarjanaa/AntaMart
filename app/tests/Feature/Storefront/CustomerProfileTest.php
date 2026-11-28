<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $this->rokokCategory = ProductCategory::where('slug', 'rokok-21-plus')->first()
        ?? ProductCategory::create(['name' => 'Rokok 21+', 'slug' => 'rokok-21-plus', 'min_age' => 21]);

    $this->rokokProduct = Product::create([
        'category_id' => $this->rokokCategory->id,
        'name' => 'Cerutu Profil Test',
        'slug' => 'cerutu-profil-test',
        'description' => 'Khusus dewasa 21+',
        'is_active' => true,
    ]);
    $this->rokokVariant = ProductVariant::create([
        'product_id' => $this->rokokProduct->id,
        'sku' => 'ROKOK-PROF-01',
        'name' => 'Pack',
        'price' => 50000,
        'stock_physical' => 10,
        'stock_reserved' => 0,
        'is_active' => true,
    ]);
});

test('customer can register with optional birth date and address or skip to fill on profile page', function () {
    // 1. Register with skip_profile=1
    $this->post(route('storefront.auth.register'), [
        'name' => 'Skip User',
        'email' => 'skip-user@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'skip_profile' => '1',
    ])
        ->assertRedirect(route('storefront.account.index'))
        ->assertSessionHas('status', 'Akun berhasil dibuat. Kamu dapat melengkapi tanggal lahir dan alamat pada halaman profil.');

    $skipUser = User::where('email', 'skip-user@example.test')->firstOrFail();
    expect($skipUser->birth_date)->toBeNull()
        ->and($skipUser->address_line1)->toBeNull();

    auth('web')->logout();

    // 2. Register with full optional profile data
    $birthDate = now()->subYears(25)->format('Y-m-d');
    $this->post(route('storefront.auth.register'), [
        'name' => 'Full Profile User',
        'email' => 'full-profile@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'birth_date' => $birthDate,
        'recipient_name' => 'Full Recipient',
        'phone' => '081234567890',
        'address_line1' => 'Jl. Profil Raya No. 10',
        'village' => 'Senayan',
        'district' => 'Kebayoran Baru',
        'city' => 'Kota Jakarta Selatan',
        'province' => 'DKI Jakarta',
    ])->assertRedirect(route('storefront.account.index'));

    $fullUser = User::where('email', 'full-profile@example.test')->firstOrFail();
    expect($fullUser->birth_date->format('Y-m-d'))->toBe($birthDate)
        ->and($fullUser->address_line1)->toBe('Jl. Profil Raya No. 10')
        ->and($fullUser->village)->toBe('Senayan')
        ->and($fullUser->district)->toBe('Kebayoran Baru')
        ->and($fullUser->city)->toBe('Kota Jakarta Selatan')
        ->and($fullUser->province)->toBe('DKI Jakarta');
});

test('customer can update personal birth date and shipping address on profile page', function () {
    $user = User::factory()->create(['role' => 'customer']);

    $this->actingAs($user, 'web')
        ->get(route('storefront.account.profile.show'))
        ->assertOk()
        ->assertSee('Profil &amp; Alamat', false);

    $birthDate = now()->subYears(24)->format('Y-m-d');
    $this->actingAs($user, 'web')
        ->put(route('storefront.account.profile.update'), [
            'name' => 'Updated Customer',
            'birth_date' => $birthDate,
            'recipient_name' => 'Penerima Utama',
            'phone' => '081298765432',
            'address_line1' => 'Jl. Asia Afrika No. 8',
            'village' => 'Braga',
            'district' => 'Sumur Bandung',
            'city' => 'Kota Bandung',
            'province' => 'Jawa Barat',
        ])
        ->assertRedirect(route('storefront.account.profile.show'))
        ->assertSessionHas('status');

    $user->refresh();
    expect($user->name)->toBe('Updated Customer')
        ->and($user->birth_date->format('Y-m-d'))->toBe($birthDate)
        ->and($user->profileAddress())->toMatchArray([
            'recipient_name' => 'Penerima Utama',
            'phone' => '081298765432',
            'address_line1' => 'Jl. Asia Afrika No. 8',
            'village' => 'Braga',
            'district' => 'Sumur Bandung',
            'city' => 'Kota Bandung',
            'province' => 'Jawa Barat',
        ]);
});

test('customer with 21+ birth date in profile bypasses manual age verification automatically', function () {
    $adultUser = User::factory()->create([
        'role' => 'customer',
        'birth_date' => now()->subYears(23)->format('Y-m-d'),
        'recipient_name' => 'Dewasa User',
        'phone' => '081211112222',
        'address_line1' => 'Jl. Dewasa No. 21',
        'city' => 'Jakarta',
        'province' => 'DKI Jakarta',
        'postal_code' => '10110',
    ]);

    // Directly view Rokok 21+ category without manual session verification
    $this->actingAs($adultUser, 'web')
        ->get('/?category=rokok-21-plus')
        ->assertOk()
        ->assertSee('Cerutu Profil Test');

    // Directly view product detail
    $this->actingAs($adultUser, 'web')
        ->get(route('storefront.products.show', $this->rokokProduct->slug))
        ->assertOk()
        ->assertSee('Cerutu Profil Test');

    // Directly add to cart & view checkout with "Gunakan alamat sesuai profil" switch
    $this->actingAs($adultUser, 'web')
        ->post(route('storefront.cart.items.store'), [
            'product_variant_id' => $this->rokokVariant->id,
            'quantity' => 1,
        ])
        ->assertRedirect(route('storefront.cart.index'));

    $this->actingAs($adultUser, 'web')
        ->get(route('storefront.checkout.create'))
        ->assertOk()
        ->assertSee('Gunakan alamat sesuai profil')
        ->assertSee('Jl. Dewasa No. 21');
});

test('customer with underage birth date in profile is blocked from rokok 21+ category', function () {
    $underageUser = User::factory()->create([
        'role' => 'customer',
        'birth_date' => now()->subYears(18)->format('Y-m-d'),
    ]);

    $this->actingAs($underageUser, 'web')
        ->get('/?category=rokok-21-plus')
        ->assertRedirect(route('storefront.home'))
        ->assertSessionHasErrors('birth_date');
});

test('checkout persists Indonesian address fields (province, city, district, village, address_line1) without postal_code', function () {
    $adultUser = User::factory()->create([
        'role' => 'customer',
        'birth_date' => now()->subYears(26)->format('Y-m-d'),
    ]);

    $this->actingAs($adultUser, 'web')
        ->post(route('storefront.cart.items.store'), [
            'product_variant_id' => $this->rokokVariant->id,
            'quantity' => 1,
        ])
        ->assertRedirect(route('storefront.cart.index'));

    $this->actingAs($adultUser, 'web')
        ->post(route('storefront.checkout.store'), [
            'payment_method' => 'cod',
            'shipping_address' => [
                'recipient_name' => 'Budi Santoso',
                'phone' => '081234567890',
                'province' => 'Jawa Tengah',
                'city' => 'Kota Semarang',
                'district' => 'Tembalang',
                'village' => 'Bulusan',
                'address_line1' => 'Jl. Prof. Soedarto No. 12 RT 03/RW 05',
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $order = Order::with('address')->latest('id')->firstOrFail();
    expect($order->address->province)->toBe('Jawa Tengah')
        ->and($order->address->city)->toBe('Kota Semarang')
        ->and($order->address->district)->toBe('Tembalang')
        ->and($order->address->village)->toBe('Bulusan')
        ->and($order->address->address_line1)->toBe('Jl. Prof. Soedarto No. 12 RT 03/RW 05');
});

test('region api endpoints return formatted Indonesian provinces, regencies, and districts', function () {
    Http::fake([
        '*/provinces.json' => Http::response([
            ['id' => '31', 'name' => 'DKI JAKARTA'],
            ['id' => '32', 'name' => 'JAWA BARAT'],
        ], 200),
        '*/regencies/31.json' => Http::response([
            ['id' => '3171', 'name' => 'KOTA JAKARTA SELATAN'],
        ], 200),
        '*/districts/3171.json' => Http::response([
            ['id' => '3171010', 'name' => 'KEBAYORAN BARU'],
        ], 200),
    ]);

    $this->getJson('/api/regions/provinces')
        ->assertOk()
        ->assertJsonPath('0.id', '31')
        ->assertJsonPath('0.name', 'DKI Jakarta')
        ->assertJsonPath('1.name', 'Jawa Barat');

    $this->getJson('/api/regions/regencies/31')
        ->assertOk()
        ->assertJsonPath('0.id', '3171')
        ->assertJsonPath('0.name', 'Kota Jakarta Selatan');

    $this->getJson('/api/regions/districts/3171')
        ->assertOk()
        ->assertJsonPath('0.id', '3171010')
        ->assertJsonPath('0.name', 'Kebayoran Baru');
});

