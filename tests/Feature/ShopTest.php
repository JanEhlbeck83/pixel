<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.providers.fake.enabled' => true,
            'payments.providers.paypal.client_id' => null,
            'payments.providers.stripe.secret_key' => null,
            'payments.providers.mollie.api_key' => null,
        ]);
    }

    public function test_catalog_lists_products_and_enabled_providers(): void
    {
        $response = $this->getJson('/api/shop')
            ->assertOk()
            ->assertJsonPath('authenticated', false)
            ->assertJsonCount(4, 'products')
            ->assertJsonPath('providers.0.key', 'fake');

        $products = collect($response->json('products'))->keyBy('id');
        $this->assertSame(300, $products['extend_x']['price_cents']);
        $this->assertSame(300, $products['extend_y']['price_cents']);
        $this->assertSame(500, $products['pixels_100']['price_cents']);
        $this->assertSame(100, $products['pixels_100']['amount']);
        $this->assertSame(1500, $products['pixels_500']['price_cents']);
        $this->assertSame(500, $products['pixels_500']['amount']);
    }

    public function test_checkout_requires_login_and_valid_input(): void
    {
        $this->postJson('/shop/checkout', ['product' => 'pixels_100', 'provider' => 'fake'])->assertUnauthorized();

        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/shop/checkout', ['product' => 'free', 'provider' => 'fake'])->assertUnprocessable();
        $this->actingAs($user)->postJson('/shop/checkout', ['product' => 'pixels_100', 'provider' => 'paypal'])->assertUnprocessable();
    }

    public function test_fake_provider_is_disabled_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->getJson('/api/shop')->assertJsonCount(0, 'providers');
    }

    public function test_buying_100_pixels_via_fake_provider_adds_credits_once(): void
    {
        $user = User::factory()->create();

        $redirect = $this->actingAs($user)
            ->postJson('/shop/checkout', ['product' => 'pixels_100', 'provider' => 'fake'])
            ->assertOk()
            ->json('redirect');

        $order = Order::firstOrFail();
        $this->assertSame(Order::PENDING, $order->status);
        $this->assertSame(500, $order->amount_cents);
        $this->assertSame(route('shop.fake', $order), $redirect);

        $this->actingAs($user)->get($redirect)->assertOk()->assertSee('5,00 €');

        $this->actingAs($user)->post(route('shop.fake.pay', $order), ['result' => 'paid'])
            ->assertRedirect(route('shop.return', $order));
        $this->actingAs($user)->get(route('shop.return', $order))->assertRedirect(route('wall'));
        $this->actingAs($user)->get(route('shop.return', $order));

        $this->assertSame(100, $user->fresh()->pixel_credits);
        $this->assertSame(Order::PAID, $order->fresh()->status);
    }

    public function test_buying_500_pixels_bundle_adds_500_credits(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/shop/checkout', ['product' => 'pixels_500', 'provider' => 'fake'])->assertOk();
        $order = Order::firstOrFail();
        $this->assertSame(1500, $order->amount_cents);

        $this->actingAs($user)->post(route('shop.fake.pay', $order), ['result' => 'paid']);

        $this->assertSame(500, $user->fresh()->pixel_credits);
    }

    public function test_buying_a_wall_extension_grows_the_wall_for_everyone(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/shop/checkout', ['product' => 'extend_x', 'provider' => 'fake'])->assertOk();
        $order = Order::firstOrFail();

        $this->actingAs($user)->post(route('shop.fake.pay', $order), ['result' => 'paid']);

        $this->getJson('/api/wall')->assertJsonPath('width', 1025)->assertJsonPath('height', 1024);
        $this->assertDatabaseCount('pixels', 1024);
        $this->assertDatabaseHas('pixels', ['x' => 1024, 'y' => 1023, 'color' => '#ffffff']);

        $this->actingAs($user)->get(route('shop.return', $order));
        $this->getJson('/api/wall')->assertJsonPath('width', 1025);
    }

    public function test_cancelled_payment_gives_nothing(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/shop/checkout', ['product' => 'pixels_100', 'provider' => 'fake']);
        $order = Order::firstOrFail();

        $this->actingAs($user)->post(route('shop.fake.pay', $order), ['result' => 'cancel'])
            ->assertRedirect(route('shop.cancel', $order));
        $this->actingAs($user)->get(route('shop.cancel', $order))->assertRedirect(route('wall'));

        $this->assertSame(Order::CANCELLED, $order->fresh()->status);
        $this->assertSame(0, $user->fresh()->pixel_credits);
    }

    public function test_other_users_cannot_touch_an_order(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson('/shop/checkout', ['product' => 'pixels_100', 'provider' => 'fake']);
        $order = Order::firstOrFail();

        $this->actingAs(User::factory()->create())->get(route('shop.return', $order))->assertForbidden();
        $this->actingAs(User::factory()->create())->post(route('shop.fake.pay', $order), ['result' => 'paid'])->assertForbidden();
    }

    public function test_paypal_checkout_and_capture(): void
    {
        config([
            'payments.providers.paypal.client_id' => 'id',
            'payments.providers.paypal.client_secret' => 'secret',
        ]);
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'PAYPAL-1',
                'links' => [['rel' => 'payer-action', 'href' => 'https://sandbox.paypal.com/checkoutnow?token=PAYPAL-1']],
            ]),
            'api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL-1/capture' => Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [['payments' => ['captures' => [[
                    'status' => 'COMPLETED',
                    'amount' => ['value' => '5.00', 'currency_code' => 'EUR'],
                ]]]]],
            ]),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/shop/checkout', ['product' => 'pixels_100', 'provider' => 'paypal'])
            ->assertOk()
            ->assertJsonPath('redirect', 'https://sandbox.paypal.com/checkoutnow?token=PAYPAL-1');

        $order = Order::firstOrFail();
        $this->assertSame('PAYPAL-1', $order->provider_reference);

        $this->actingAs($user)->get(route('shop.return', $order).'?token=PAYPAL-1')->assertRedirect(route('wall'));
        $this->assertSame(100, $user->fresh()->pixel_credits);
        $this->assertSame(Order::PAID, $order->fresh()->status);
    }

    public function test_paypal_capture_with_wrong_amount_is_rejected(): void
    {
        config([
            'payments.providers.paypal.client_id' => 'id',
            'payments.providers.paypal.client_secret' => 'secret',
        ]);
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            'api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL-2/capture' => Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [['payments' => ['captures' => [[
                    'status' => 'COMPLETED',
                    'amount' => ['value' => '0.01', 'currency_code' => 'EUR'],
                ]]]]],
            ]),
        ]);
        $user = User::factory()->create();
        $order = Order::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'product' => 'pixels_100',
            'provider' => 'paypal', 'provider_reference' => 'PAYPAL-2', 'status' => 'pending',
            'amount_cents' => 500, 'currency' => 'EUR',
        ]);

        $this->actingAs($user)->get(route('shop.return', $order));

        $this->assertSame(0, $user->fresh()->pixel_credits);
        $this->assertSame(Order::FAILED, $order->fresh()->status);
    }

    public function test_stripe_checkout_and_verification(): void
    {
        config(['payments.providers.stripe.secret_key' => 'sk_test_x']);
        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1']),
            'api.stripe.com/v1/checkout/sessions/cs_test_1' => function () {
                return Http::response([
                    'client_reference_id' => Order::firstOrFail()->uuid, 'amount_total' => 500, 'currency' => 'eur',
                    'payment_status' => 'paid', 'status' => 'complete',
                ]);
            },
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/shop/checkout', ['product' => 'pixels_100', 'provider' => 'stripe'])
            ->assertOk()->assertJsonPath('redirect', 'https://checkout.stripe.com/c/pay/cs_test_1');

        $this->actingAs($user)->get(route('shop.return', Order::firstOrFail()));
        $this->assertSame(100, $user->fresh()->pixel_credits);
    }

    public function test_stripe_webhook_requires_valid_signature(): void
    {
        config(['payments.providers.stripe.webhook_secret' => 'whsec_test', 'payments.providers.stripe.secret_key' => 'sk_test_x']);
        $this->postJson('/webhooks/stripe', ['type' => 'checkout.session.completed'])->assertStatus(400);

        $body = json_encode(['type' => 'ignored', 'data' => ['object' => ['id' => 'x']]]);
        $t = time();
        $sig = hash_hmac('sha256', $t.'.'.$body, 'whsec_test');
        $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t=$t,v1=$sig", 'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();
    }

    public function test_mollie_checkout_and_webhook_verification(): void
    {
        config(['payments.providers.mollie.api_key' => 'test_x']);
        Http::fake([
            'api.mollie.com/v2/payments' => Http::response(['id' => 'tr_1', '_links' => ['checkout' => ['href' => 'https://www.mollie.com/checkout/tr_1']]]),
            'api.mollie.com/v2/payments/tr_1' => function () {
                return Http::response([
                    'status' => 'paid', 'metadata' => ['order_uuid' => Order::firstOrFail()->uuid],
                    'amount' => ['value' => '3.00', 'currency' => 'EUR'],
                ]);
            },
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/shop/checkout', ['product' => 'extend_y', 'provider' => 'mollie'])
            ->assertOk()->assertJsonPath('redirect', 'https://www.mollie.com/checkout/tr_1');

        $this->post('/webhooks/mollie', ['id' => 'tr_1'])->assertOk();
        $this->getJson('/api/wall')->assertJsonPath('height', 1025);
    }

    public function test_provider_failure_returns_friendly_error(): void
    {
        config(['payments.providers.mollie.api_key' => 'test_x']);
        Http::fake(['api.mollie.com/*' => Http::response([], 500)]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/shop/checkout', ['product' => 'pixels_100', 'provider' => 'mollie'])
            ->assertStatus(502)->assertJsonPath('success', false);
        $this->assertSame(Order::FAILED, Order::firstOrFail()->status);
    }

    public function test_profile_shows_orders_and_credits(): void
    {
        $user = User::factory()->create(['pixel_credits' => 42]);
        $this->actingAs($user)->postJson('/shop/checkout', ['product' => 'pixels_100', 'provider' => 'fake']);

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee('Käufe &amp; Zahlungen', false)
            ->assertSee('100 Extra-Pixel')
            ->assertSee('5,00 €')
            ->assertSee('42');
    }
}
