<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ShippingRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Cache::flush();

    config()->set('services.store.latitude', -1.606188);
    config()->set('services.store.longitude', 103.521922);
    config()->set('services.store.origin_postal_code', '36361');
    config()->set('services.rajaongkir.api_key', 'test-rajaongkir-key');
    config()->set('services.rajaongkir.base_url', 'https://rajaongkir.komerce.id/api/v1');
    config()->set('services.rajaongkir.origin_id', '36361');
    config()->set('services.rajaongkir.couriers', 'jne:jnt');

    $this->dailyCategory = ProductCategory::firstOrCreate(
        ['slug' => 'kebutuhan-pokok'],
        ['name' => 'Kebutuhan Pokok', 'min_age' => null]
    );

    $this->dailyProduct = Product::create([
        'category_id' => $this->dailyCategory->id,
        'name' => 'Beras AntaMart 5kg',
        'slug' => 'beras-antamart-5kg',
        'description' => 'Kebutuhan harian',
        'is_active' => true,
    ]);

    $this->dailyVariant = ProductVariant::create([
        'product_id' => $this->dailyProduct->id,
        'sku' => 'BERAS-5KG-01',
        'name' => 'Karung 5kg',
        'price' => 75000,
        'weight_grams' => 5000,
        'stock_physical' => 20,
        'stock_reserved' => 0,
        'is_active' => true,
    ]);
});

test('kurir antamart distance tiers price 0-5km at 5000, >5-10km at 10000, >10-20km at 20000, and >20km unavailable', function () {
    $service = app(ShippingRateService::class);

    expect($service->buildLocalCourierOption(0.0)['cost'])->toBe(5000.0)
        ->and($service->buildLocalCourierOption(3.2)['cost'])->toBe(5000.0)
        ->and($service->buildLocalCourierOption(5.0)['cost'])->toBe(5000.0)
        ->and($service->buildLocalCourierOption(5.1)['cost'])->toBe(10000.0)
        ->and($service->buildLocalCourierOption(10.0)['cost'])->toBe(10000.0)
        ->and($service->buildLocalCourierOption(10.1)['cost'])->toBe(20000.0)
        ->and($service->buildLocalCourierOption(20.0)['cost'])->toBe(20000.0)
        ->and($service->buildLocalCourierOption(20.1))->toBeNull();
});

test('shipping rates endpoint returns both Kurir AntaMart and RajaOngkir expeditions when within 20km', function () {
    Http::fake([
        'https://rajaongkir.komerce.id/api/v1/destination/domestic-destination*' => Http::response([
            'data' => [
                ['id' => 15001, 'label' => 'JAMBI LUAR KOTA, MUARO JAMBI', 'district_name' => 'JAMBI LUAR KOTA', 'city_name' => 'MUARO JAMBI', 'province_name' => 'JAMBI'],
            ],
        ], 200),
        'https://rajaongkir.komerce.id/api/v1/calculate/domestic-cost' => Http::response([
            'data' => [
                ['name' => 'JNE', 'code' => 'jne', 'service' => 'REG', 'description' => 'Layanan Reguler', 'cost' => 12000, 'etd' => '1-2'],
                ['name' => 'J&T Express', 'code' => 'jnt', 'service' => 'EZ', 'description' => 'Reguler', 'cost' => 14000, 'etd' => '2-3'],
            ],
        ], 200),
    ]);

    // ~2.5 km from AntaMart store (-1.606188, 103.521922)
    $response = $this->postJson('/api/shipping/rates', [
        'province' => 'Jambi',
        'city' => 'Kabupaten Muaro Jambi',
        'district' => 'Jambi Luar Kota',
        'latitude' => -1.625000,
        'longitude' => 103.535000,
        'weight_grams' => 1000,
    ]);

    $response->assertOk()
        ->assertJsonPath('local_available', true)
        ->assertJsonPath('options.0.code', 'antamart:local')
        ->assertJsonPath('options.0.cost', 5000)
        ->assertJsonPath('options.1.code', 'jne:REG')
        ->assertJsonPath('options.1.cost', 12000)
        ->assertJsonPath('options.2.code', 'jnt:EZ')
        ->assertJsonPath('options.2.cost', 14000);
});

test('shipping rates endpoint hides Kurir AntaMart beyond 20km and shows expeditions for out-of-town orders', function () {
    Http::fake([
        'https://rajaongkir.komerce.id/api/v1/destination/domestic-destination*' => Http::response([
            'data' => [
                ['id' => 31710, 'label' => 'KEBAYORAN BARU, JAKARTA SELATAN', 'district_name' => 'KEBAYORAN BARU', 'city_name' => 'JAKARTA SELATAN', 'province_name' => 'DKI JAKARTA'],
            ],
        ], 200),
        'https://rajaongkir.komerce.id/api/v1/calculate/domestic-cost' => Http::response([
            'data' => [
                ['name' => 'JNE', 'code' => 'jne', 'service' => 'REG', 'description' => 'Layanan Reguler', 'cost' => 28000, 'etd' => '2-3'],
            ],
        ], 200),
    ]);

    // Jakarta coordinates (> 600 km from Jambi)
    $response = $this->postJson('/api/shipping/rates', [
        'province' => 'DKI Jakarta',
        'city' => 'Kota Jakarta Selatan',
        'district' => 'Kebayoran Baru',
        'latitude' => -6.244200,
        'longitude' => 106.799500,
        'weight_grams' => 1000,
    ]);

    $response->assertOk()
        ->assertJsonPath('local_available', false)
        ->assertJsonCount(1, 'options')
        ->assertJsonPath('options.0.code', 'jne:REG')
        ->assertJsonPath('options.0.cost', 28000);
});

test('checkout authoritatively applies Kurir AntaMart rate within 20km and rejects spoofed local option beyond 20km', function () {
    Http::fake([
        'https://rajaongkir.komerce.id/api/v1/destination/domestic-destination*' => Http::response([
            'data' => [
                ['id' => 31710, 'label' => 'KEBAYORAN BARU, JAKARTA SELATAN', 'district_name' => 'KEBAYORAN BARU', 'city_name' => 'JAKARTA SELATAN', 'province_name' => 'DKI JAKARTA'],
            ],
        ], 200),
        'https://rajaongkir.komerce.id/api/v1/calculate/domestic-cost' => Http::response([
            'data' => [
                ['name' => 'JNE', 'code' => 'jne', 'service' => 'REG', 'description' => 'Layanan Reguler', 'cost' => 32000, 'etd' => '2-3'],
            ],
        ], 200),
    ]);

    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer, 'web')
        ->post(route('storefront.cart.items.store'), [
            'product_variant_id' => $this->dailyVariant->id,
            'quantity' => 1,
        ])
        ->assertRedirect(route('storefront.cart.index'));

    // 1. Attempt to choose antamart:local from Jakarta (> 20km) -> rejected!
    $this->actingAs($customer, 'web')
        ->post(route('storefront.checkout.store'), [
            'payment_method' => 'cod',
            'shipping_option' => 'antamart:local',
            'shipping_address' => [
                'recipient_name' => 'Customer Jakarta',
                'phone' => '081234567890',
                'province' => 'DKI Jakarta',
                'city' => 'Kota Jakarta Selatan',
                'district' => 'Kebayoran Baru',
                'village' => 'Senayan',
                'address_line1' => 'Jl. Sudirman No. 1',
                'latitude' => -6.244200,
                'longitude' => 106.799500,
            ],
        ])
        ->assertSessionHasErrors('shipping_option');

    // 2. Out-of-town non-rokok product with COD & RajaOngkir JNE REG -> succeeds!
    $this->actingAs($customer, 'web')
        ->post(route('storefront.checkout.store'), [
            'payment_method' => 'cod',
            'shipping_option' => 'jne:REG',
            'shipping_address' => [
                'recipient_name' => 'Customer Jakarta',
                'phone' => '081234567890',
                'province' => 'DKI Jakarta',
                'city' => 'Kota Jakarta Selatan',
                'district' => 'Kebayoran Baru',
                'village' => 'Senayan',
                'address_line1' => 'Jl. Sudirman No. 1',
                'latitude' => -6.244200,
                'longitude' => 106.799500,
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $order = Order::with('address')->latest('id')->firstOrFail();
    expect((float) $order->shipping_cost)->toBe(32000.0)
        ->and((float) $order->total)->toBe(107000.0)
        ->and($order->shipping_courier)->toBe('jne')
        ->and($order->shipping_service)->toBe('REG');

    // 3. Local checkout within ~7 km (>5-10 km tier = Rp 10.000)
    $this->actingAs($customer, 'web')
        ->post(route('storefront.cart.items.store'), [
            'product_variant_id' => $this->dailyVariant->id,
            'quantity' => 1,
        ]);

    $this->actingAs($customer, 'web')
        ->post(route('storefront.checkout.store'), [
            'payment_method' => 'cod',
            'shipping_option' => 'antamart:local',
            'shipping_address' => [
                'recipient_name' => 'Customer Jambi',
                'phone' => '081234567891',
                'province' => 'Jambi',
                'city' => 'Kota Jambi',
                'district' => 'Telanaipura',
                'village' => 'Pematang Sulur',
                'address_line1' => 'Jl. Pattimura No. 5',
                'latitude' => -1.606188,
                'longitude' => 103.585000, // ~7.0 km east
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $localOrder = Order::with('address')->where('id', '!=', $order->id)->latest('id')->firstOrFail();
    expect((float) $localOrder->shipping_cost)->toBe(10000.0)
        ->and((float) $localOrder->total)->toBe(85000.0)
        ->and($localOrder->shipping_courier)->toBe('antamart')
        ->and($localOrder->shipping_service)->toBe('Jarak >5–10 km')
        ->and($localOrder->address->latitude)->toBe(-1.606188);
});

