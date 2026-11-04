<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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
        ]);
        Mail::fake();
    }

    public function test_catalog_lists_products_and_enabled_providers(): void
    {
        $response = $this->getJson('/api/shop')
            ->assertOk()
            ->assertJsonPath('authenticated', false)
            ->assertJsonCount(7, 'products')
            ->assertJsonPath('providers.0.key', 'fake');

        $products = collect($response->json('products'))->keyBy('id');
        $this->assertSame(300, $products['extend_x']['price_cents']);
        $this->assertSame(300, $products['extend_y']['price_cents']);
        $this->assertSame([1200, 5], [$products['extend_x_5']['price_cents'], $products['extend_x_5']['amount']]);
        $this->assertSame(500, $products['pixels_100']['price_cents']);
        $this->assertSame(250, $products['pixels_100']['amount']);
        $this->assertSame(1000, $products['pixels_600']['price_cents']);
        $this->assertSame(600, $products['pixels_600']['amount']);
        $this->assertSame(1500, $products['pixels_1000']['price_cents']);
        $this->assertSame(1000, $products['pixels_1000']['amount']);
    }

    public function test_checkout_rejects_packs_beyond_the_credit_limit(): void
    {
        $user = User::factory()->create(['pixel_credits' => 9700]);

        $this->actingAs($user)->postJson('/shop/checkout', ['product' => 'pixels_1000', 'provider' => 'fake', 'withdrawal_consent' => true])
            ->assertStatus(422);
        $this->assertDatabaseCount('orders', 0);

        $this->actingAs($user)->postJson('/shop/checkout', ['product' => 'pixels_100', 'provider' => 'fake', 'withdrawal_consent' => true])->assertOk();
    }

    public function test_checkout_requires_login_and_valid_input(): void
    {
        $this->postJson('/shop/checkout', ['product' => 'pixels_100', 'provider' => 'fake', 'withdrawal_consent' => true])->assertUnauthorized();

        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/shop/checkout', ['product' => 'free', 'provider' => 'fake', 'withdrawal_consent' => true])->assertUnprocessable();
        $this->actingAs($user)->postJson('/shop/checkout', ['product' => 'pixels_100', 'provider' => 'paypal'])->assertUnprocessable();
    }

    public function test_real_payment_checkout_requires_withdrawal_consent(): void
    {
        config([
            'payments.providers.paypal.client_id' => 'id',
            'payments.providers.paypal.client_secret' => 'secret',
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/shop/checkout', ['product' => 'pixels_100', 'provider' => 'paypal'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('withdrawal_consent');

        $this->assertDatabaseCount('orders', 0);
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
            ->withHeader('X-App-Locale', 'de')
            ->postJson('/shop/checkout', ['product' => 'pixels_100', 'provider' => 'fake', 'withdrawal_consent' => true])
            ->assertOk()
            ->json('redirect');

        $order = Order::firstOrFail();
        $this->assertSame(Order::PENDING, $order->status);
        $this->assertSame(500, $order->amount_cents);
        $this->assertSame(route('shop.fake', $order), $redirect);
        $this->assertSame($user->email, $order->purchaser_email);

        $this->actingAs($user)->withHeader('X-App-Locale', 'de')->get($redirect)->assertOk()->assertSee('5,00 €');

        $this->actingAs($user)->post(route('shop.fake.pay', $order), ['result' => 'paid'])
            ->assertRedirect(route('shop.return', $order));
        $this->actingAs($user)->get(route('shop.return', $order))->assertRedirect(route('home'));
        $this->actingAs($user)->get(route('shop.return', $order));

        $this->assertSame(250, $user->fresh()->pixel_credits);
        $this->assertNotNull($order->fresh()->contract_confirmation_sent_at);
        Mail::assertSent(\App\Mail\OrderConfirmationMail::class, function ($mail) use ($user) {
            $rendered = $mail->render();

            return $mail->hasTo($user->email)
                && str_contains($rendered, 'Widerrufsrecht')
                && str_contains($rendered, '5,00 EUR');
        });
    }

    public function test_buying_1000_pixels_bundle_adds_1000_credits(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/shop/checkout', ['product' => 'pixels_1000', 'provider' => 'fake', 'withdrawal_consent' => true])->assertOk();
        $order = Order::firstOrFail();
        $this->assertSame(1500, $order->amount_cents);

        $this->actingAs($user)->post(route('shop.fake.pay', $order), ['result' => 'paid']);

        $this->assertSame(1000, $user->fresh()->pixel_credits);
    }

    public function test_buying_a_wall_extension_grows_the_wall_for_everyone(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/shop/checkout', ['product' => 'extend_x', 'provider' => 'fake', 'withdrawal_consent' => true])->assertOk();
        $order = Order::firstOrFail();

        $this->actingAs($user)->post(route('shop.fake.pay', $order), ['result' => 'paid']);

        $this->getJson('/api/wall')->assertJsonPath('width', 1025)->assertJsonPath('height', 1024);
        $this->assertDatabaseCount('pixels', 1024);
        $this->assertDatabaseHas('pixels', ['x' => 1024, 'y' => 1023, 'color' => '#ffffff']);

        $this->actingAs($user)->get(route('shop.return', $order));
        $this->getJson('/api/wall')->assertJsonPath('width', 1025);
    }

    public function test_buying_five_pixels_of_extension_at_once_costs_twelve_euro(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/shop/checkout', ['product' => 'extend_y_5', 'provider' => 'fake', 'withdrawal_consent' => true])->assertOk();
        $order = Order::firstOrFail();
        $this->assertSame(1200, $order->amount_cents);

        $this->actingAs($user)->post(route('shop.fake.pay', $order), ['result' => 'paid']);

        $this->getJson('/api/wall')->assertJsonPath('width', 1024)->assertJsonPath('height', 1029);
        $this->assertDatabaseCount('pixels', 1024 * 5);
        $this->assertDatabaseHas('pixels', ['x' => 1023, 'y' => 1028, 'color' => '#ffffff']);
    }

    public function test_cancelled_payment_gives_nothing(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/shop/checkout', ['product' => 'pixels_100', 'provider' => 'fake', 'withdrawal_consent' => true]);
        $order = Order::firstOrFail();

        $this->actingAs($user)->post(route('shop.fake.pay', $order), ['result' => 'cancel'])
            ->assertRedirect(route('shop.cancel', $order));
        $this->actingAs($user)->get(route('shop.cancel', $order))->assertRedirect(route('home'));

        $this->assertSame(Order::CANCELLED, $order->fresh()->status);
        $this->assertSame(0, $user->fresh()->pixel_credits);
    }

public function test_cancelled_order_cannot_be_fulfilled(): void
{
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/shop/checkout', [
        'product' => 'pixels_100',
        'provider' => 'fake',
        'withdrawal_consent' => true,
    ]);

    $order = Order::firstOrFail();

    app(\App\Services\Payments\Shop::class)->cancel($order);
    app(\App\Services\Payments\Shop::class)->fulfill($order->fresh());

    $this->assertSame(Order::CANCELLED, $order->fresh()->status);
    $this->assertSame(0, $user->fresh()->pixel_credits);
    $this->assertNull($order->fresh()->fulfilled_at);
}
public function test_cancel_cannot_overwrite_fulfilled_order(): void
{
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/shop/checkout', [
        'product' => 'pixels_100',
        'provider' => 'fake',
        'withdrawal_consent' => true,
    ]);

    $order = Order::firstOrFail();

    $shop = app(\App\Services\Payments\Shop::class);

    $shop->fulfill($order);
    $shop->cancel($order);

    $this->assertSame(Order::PAID, $order->fresh()->status);
    $this->assertSame(250, $user->fresh()->pixel_credits);
    $this->assertNotNull($order->fresh()->fulfilled_at);
}
    public function test_other_users_cannot_touch_an_order(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson('/shop/checkout', ['product' => 'pixels_100', 'provider' => 'fake', 'withdrawal_consent' => true]);
        $order = Order::firstOrFail();

        $this->actingAs(User::factory()->create())->get(route('shop.return', $order))->assertForbidden();
        $this->actingAs(User::factory()->create())->post(route('shop.fake.pay', $order), ['result' => 'paid'])->assertForbidden();
    }

    public function test_paypal_checkout_and_capture(): void
{
    config([
        'payments.providers.paypal.merchant_id' => '6N6Y8RVAF5K3L',
        'payments.providers.paypal.client_id' => 'id',
        'payments.providers.paypal.client_secret' => 'secret',
    ]);

   Http::fake(function ($request) {
    $url = $request->url();

if (str_contains($url, '/v1/oauth2/token')) {
    return Http::response([
        'access_token' => 'tok',
        'token_type' => 'Bearer',
        'expires_in' => 3600,
    ]);
}
    if (str_ends_with($url, '/v2/checkout/orders')) {
    return Http::response([
        'id' => 'PAYPAL-1',
        'status' => 'CREATED',
        'links' => [[
            'rel' => 'payer-action',
            'href' => 'https://sandbox.paypal.com/checkoutnow?token=PAYPAL-1',
        ]],
    ]);
}

    if (str_contains($url, '/v2/checkout/orders/PAYPAL-1/capture')) {
        return Http::response(['status' => 'COMPLETED']);
    }

    if (str_contains($url, '/v2/checkout/orders/PAYPAL-1')) {
        $order = Order::firstOrFail();

        return Http::response([
            'id' => 'PAYPAL-1',
            'status' => 'COMPLETED',
            'purchase_units' => [[
                'reference_id' => $order->uuid,
                'custom_id' => $order->uuid,
                'payee' => [
                    'merchant_id' => '6N6Y8RVAF5K3L',
                ],
                'payments' => [
                    'captures' => [[
                        'status' => 'COMPLETED',
                        'amount' => [
                            'value' => '5.00',
                            'currency_code' => 'EUR',
                        ],
                    ]],
                ],
            ]],
        ]);
    }

    return Http::response(['error' => 'Unexpected test request: '.$url], 500);
});

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/shop/checkout', [
            'product' => 'pixels_100',
            'provider' => 'paypal',
            'withdrawal_consent' => true,
        ])
        ->assertOk()
        ->assertJsonPath(
            'redirect',
            'https://sandbox.paypal.com/checkoutnow?token=PAYPAL-1'
        );

    $order = Order::firstOrFail();

    $this->assertSame('PAYPAL-1', $order->provider_reference);

    $this->actingAs($user)
        ->get(route('shop.return', $order).'?token=PAYPAL-1')
        ->assertRedirect(route('home'));

    $this->assertSame(250, $user->fresh()->pixel_credits);
    $this->assertSame(Order::PAID, $order->fresh()->status);
}

   public function test_paypal_capture_with_wrong_amount_is_rejected(): void
{
    config([
        'payments.providers.paypal.merchant_id' => '6N6Y8RVAF5K3L',
        'payments.providers.paypal.client_id' => 'id',
        'payments.providers.paypal.client_secret' => 'secret',
    ]);

    Http::fake(function ($request) {
    $url = $request->url();

    if (str_contains($url, '/v1/oauth2/token')) {
        return Http::response(['access_token' => 'tok']);
    }

    if (str_contains($url, '/v2/checkout/orders/PAYPAL-2/capture')) {
        return Http::response(['status' => 'COMPLETED']);
    }

    if (str_contains($url, '/v2/checkout/orders/PAYPAL-2')) {
        $order = Order::firstOrFail();

        return Http::response([
            'id' => 'PAYPAL-2',
            'status' => 'COMPLETED',
            'purchase_units' => [[
                'reference_id' => $order->uuid,
                'custom_id' => $order->uuid,
                'payee' => [
		    'merchant_id' => '6N6Y8RVAF5K3L',
		],
		'payments' => [
                    'captures' => [[
                        'status' => 'COMPLETED',
                        'amount' => [
                            'value' => '0.01',
                            'currency_code' => 'EUR',
                        ],
                    ]],
                ],
            ]],
        ]);
    }

    return Http::response(['error' => 'Unexpected test request: '.$url], 500);
});

    $user = User::factory()->create();

    $order = Order::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'product' => 'pixels_100',
        'provider' => 'paypal',
        'provider_reference' => 'PAYPAL-2',
        'status' => 'pending',
        'amount_cents' => 500,
        'currency' => 'EUR',
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
'metadata' => ['order_uuid' => Order::firstOrFail()->uuid],
'payment_status' => 'paid',
'status' => 'complete',
'livemode' => false,
'mode' => 'payment',

                ]);
            },
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/shop/checkout', [
            'product' => 'pixels_100',
            'provider' => 'stripe',
            'withdrawal_consent' => true,
        ])
            ->assertOk()->assertJsonPath('redirect', 'https://checkout.stripe.com/c/pay/cs_test_1');

        $order = Order::firstOrFail();
        $this->assertNotNull($order->withdrawal_consent_at);
        $this->assertSame('2026-11-04-v2', $order->withdrawal_consent_version);
        $this->assertSame('en', $order->withdrawal_consent_locale);

        $this->actingAs($user)->get(route('shop.return', Order::firstOrFail()));
        $this->assertSame(250, $user->fresh()->pixel_credits);
    }

public function test_paypal_capture_with_wrong_custom_id_is_rejected(): void
{
    $orderUuid = (string) Str::uuid();

    config([
        'payments.providers.paypal.merchant_id' => '6N6Y8RVAF5K3L',
        'payments.providers.paypal.client_id' => 'id',
        'payments.providers.paypal.client_secret' => 'secret',
    ]);

   Http::fake(function ($request) {
    $url = $request->url();

    if (str_contains($url, '/v1/oauth2/token')) {
        return Http::response(['access_token' => 'tok']);
    }

    if (str_contains($url, '/v2/checkout/orders/PAYPAL-3/capture')) {
        return Http::response(['status' => 'COMPLETED']);
    }

    if (str_contains($url, '/v2/checkout/orders/PAYPAL-3')) {
        $order = Order::firstOrFail();

        return Http::response([
            'id' => 'PAYPAL-3',
            'status' => 'COMPLETED',
            'purchase_units' => [[
                'reference_id' => $order->uuid,
                'custom_id' => 'FALSCHE-BESTELL-ID',
                'payee' => [
                    'merchant_id' => '6N6Y8RVAF5K3L',
                ],
                'payments' => [
                    'captures' => [[
                        'status' => 'COMPLETED',
                        'amount' => [
                            'value' => '5.00',
                            'currency_code' => 'EUR',
                        ],
                    ]],
                ],
            ]],
        ]);
    }

    return Http::response(['error' => 'Unexpected test request: '.$url], 500);
});

    $user = User::factory()->create();

    $order = Order::create([
        'uuid' => $orderUuid,
        'user_id' => $user->id,
        'product' => 'pixels_100',
        'provider' => 'paypal',
        'provider_reference' => 'PAYPAL-3',
        'status' => 'pending',
        'amount_cents' => 500,
        'currency' => 'EUR',
    ]);

    $this->actingAs($user)->get(route('shop.return', $order));

    $this->assertSame(0, $user->fresh()->pixel_credits);
    $this->assertSame(Order::FAILED, $order->fresh()->status);
}
public function test_paypal_capture_with_wrong_merchant_id_is_rejected(): void
{
    $orderUuid = (string) Str::uuid();

    config([
        'payments.providers.paypal.merchant_id' => '6N6Y8RVAF5K3L',
        'payments.providers.paypal.client_id' => 'id',
        'payments.providers.paypal.client_secret' => 'secret',
    ]);

    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, '/v1/oauth2/token')) {
            return Http::response(['access_token' => 'tok']);
        }

        if (str_contains($url, '/v2/checkout/orders/PAYPAL-4/capture')) {
            return Http::response(['status' => 'COMPLETED']);
        }

        if (str_contains($url, '/v2/checkout/orders/PAYPAL-4')) {
            $order = Order::firstOrFail();

            return Http::response([
                'id' => 'PAYPAL-4',
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'reference_id' => $order->uuid,
                    'custom_id' => $order->uuid,
                    'payee' => [
                        'merchant_id' => 'FALSCHE-HÄNDLER-ID',
                    ],
                    'payments' => [
                        'captures' => [[
                            'status' => 'COMPLETED',
                            'amount' => [
                                'value' => '5.00',
                                'currency_code' => 'EUR',
                            ],
                        ]],
                    ],
                ]],
            ]);
        }

        return Http::response(['error' => 'Unexpected test request: '.$url], 500);
    });

    $user = User::factory()->create();

    $order = Order::create([
        'uuid' => $orderUuid,
        'user_id' => $user->id,
        'product' => 'pixels_100',
        'provider' => 'paypal',
        'provider_reference' => 'PAYPAL-4',
        'status' => 'pending',
        'amount_cents' => 500,
        'currency' => 'EUR',
    ]);

    $this->actingAs($user)->get(route('shop.return', $order));

    $this->assertSame(0, $user->fresh()->pixel_credits);
    $this->assertSame(Order::FAILED, $order->fresh()->status);
}

public function test_paypal_capture_without_merchant_id_is_rejected(): void
{
    $orderUuid = (string) Str::uuid();

    config([
        'payments.providers.paypal.merchant_id' => '6N6Y8RVAF5K3L',
        'payments.providers.paypal.client_id' => 'id',
        'payments.providers.paypal.client_secret' => 'secret',
    ]);

    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, '/v1/oauth2/token')) {
            return Http::response(['access_token' => 'tok']);
        }

        if (str_contains($url, '/v2/checkout/orders/PAYPAL-4/capture')) {
            return Http::response(['status' => 'COMPLETED']);
        }

        if (str_contains($url, '/v2/checkout/orders/PAYPAL-4')) {
            $order = Order::firstOrFail();

            return Http::response([
                'id' => 'PAYPAL-4',
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'reference_id' => $order->uuid,
                    'custom_id' => $order->uuid,
                    'payments' => [
                        'captures' => [[
                            'status' => 'COMPLETED',
                            'amount' => [
                                'value' => '5.00',
                                'currency_code' => 'EUR',
                            ],
                        ]],
                    ],
                ]],
            ]);
        }

        return Http::response(['error' => 'Unexpected test request: '.$url], 500);
    });

    $user = User::factory()->create();

    $order = Order::create([
        'uuid' => $orderUuid,
        'user_id' => $user->id,
        'product' => 'pixels_100',
        'provider' => 'paypal',
        'provider_reference' => 'PAYPAL-4',
        'status' => 'pending',
        'amount_cents' => 500,
        'currency' => 'EUR',
    ]);

    $this->actingAs($user)->get(route('shop.return', $order));

    $this->assertSame(0, $user->fresh()->pixel_credits);
    $this->assertSame(Order::FAILED, $order->fresh()->status);
}
public function test_paypal_capture_with_pending_status_is_rejected(): void
{
    $orderUuid = (string) Str::uuid();

    config([
        'payments.providers.paypal.merchant_id' => '6N6Y8RVAF5K3L',
        'payments.providers.paypal.client_id' => 'id',
        'payments.providers.paypal.client_secret' => 'secret',
    ]);

    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, '/v1/oauth2/token')) {
            return Http::response(['access_token' => 'tok']);
        }

        if (str_contains($url, '/v2/checkout/orders/PAYPAL-5/capture')) {
            return Http::response([
                'status' => 'PENDING',
            ]);
        }

        if (str_contains($url, '/v2/checkout/orders/PAYPAL-5')) {
            $order = Order::firstOrFail();

            return Http::response([
                'id' => 'PAYPAL-5',
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'reference_id' => $order->uuid,
                    'custom_id' => $order->uuid,
                    'payee' => [
                        'merchant_id' => '6N6Y8RVAF5K3L',
                    ],
                    'payments' => [
                        'captures' => [[
                            'status' => 'PENDING',
                            'amount' => [
                                'value' => '5.00',
                                'currency_code' => 'EUR',
                            ],
                        ]],
                    ],
                ]],
            ]);
        }

        return Http::response(['error' => 'Unexpected test request: '.$url], 500);
    });

    $user = User::factory()->create();

    $order = Order::create([
        'uuid' => $orderUuid,
        'user_id' => $user->id,
        'product' => 'pixels_100',
        'provider' => 'paypal',
        'provider_reference' => 'PAYPAL-5',
        'status' => 'pending',
        'amount_cents' => 500,
        'currency' => 'EUR',
    ]);

    $this->actingAs($user)->get(route('shop.return', $order));

    $this->assertSame(0, $user->fresh()->pixel_credits);
    $this->assertSame(Order::PENDING, $order->fresh()->status);
}

public function test_paypal_capture_with_denied_status_is_rejected(): void
{
    $orderUuid = (string) Str::uuid();

    config([
        'payments.providers.paypal.merchant_id' => '6N6Y8RVAF5K3L',
        'payments.providers.paypal.client_id' => 'id',
        'payments.providers.paypal.client_secret' => 'secret',
    ]);

    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, '/v1/oauth2/token')) {
            return Http::response(['access_token' => 'tok']);
        }

        if (str_contains($url, '/v2/checkout/orders/PAYPAL-5/capture')) {
            return Http::response([
                'status' => 'DENIED',
            ]);
        }

        if (str_contains($url, '/v2/checkout/orders/PAYPAL-5')) {
            $order = Order::firstOrFail();

            return Http::response([
                'id' => 'PAYPAL-5',
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'reference_id' => $order->uuid,
                    'custom_id' => $order->uuid,
                    'payee' => [
                        'merchant_id' => '6N6Y8RVAF5K3L',
                    ],
                    'payments' => [
                        'captures' => [[
                            'status' => 'DENIED',
                            'amount' => [
                                'value' => '5.00',
                                'currency_code' => 'EUR',
                            ],
                        ]],
                    ],
                ]],
            ]);
        }

        return Http::response(['error' => 'Unexpected test request: '.$url], 500);
    });

    $user = User::factory()->create();

    $order = Order::create([
        'uuid' => $orderUuid,
        'user_id' => $user->id,
        'product' => 'pixels_100',
        'provider' => 'paypal',
        'provider_reference' => 'PAYPAL-5',
        'status' => 'pending',
        'amount_cents' => 500,
        'currency' => 'EUR',
    ]);

    $this->actingAs($user)->get(route('shop.return', $order));

    $this->assertSame(0, $user->fresh()->pixel_credits);
    $this->assertSame(Order::FAILED, $order->fresh()->status);
}

public function test_paypal_capture_with_declined_status_is_rejected(): void
{
    $orderUuid = (string) Str::uuid();

    config([
        'payments.providers.paypal.merchant_id' => '6N6Y8RVAF5K3L',
        'payments.providers.paypal.client_id' => 'id',
        'payments.providers.paypal.client_secret' => 'secret',
    ]);

    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, '/v1/oauth2/token')) {
            return Http::response(['access_token' => 'tok']);
        }

        if (str_contains($url, '/v2/checkout/orders/PAYPAL-5/capture')) {
            return Http::response([
                'status' => 'DECLINED',
            ]);
        }

        if (str_contains($url, '/v2/checkout/orders/PAYPAL-5')) {
            $order = Order::firstOrFail();

            return Http::response([
                'id' => 'PAYPAL-5',
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'reference_id' => $order->uuid,
                    'custom_id' => $order->uuid,
                    'payee' => [
                        'merchant_id' => '6N6Y8RVAF5K3L',
                    ],
                    'payments' => [
                        'captures' => [[
                            'status' => 'DECLINED',
                            'amount' => [
                                'value' => '5.00',
                                'currency_code' => 'EUR',
                            ],
                        ]],
                    ],
                ]],
            ]);
        }

        return Http::response(['error' => 'Unexpected test request: '.$url], 500);
    });

    $user = User::factory()->create();

    $order = Order::create([
        'uuid' => $orderUuid,
        'user_id' => $user->id,
        'product' => 'pixels_100',
        'provider' => 'paypal',
        'provider_reference' => 'PAYPAL-5',
        'status' => 'pending',
        'amount_cents' => 500,
        'currency' => 'EUR',
    ]);

    $this->actingAs($user)->get(route('shop.return', $order));

    $this->assertSame(0, $user->fresh()->pixel_credits);
    $this->assertSame(Order::FAILED, $order->fresh()->status);
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

    public function test_provider_failure_returns_friendly_error(): void
    {
        config(['payments.providers.stripe.secret_key' => 'sk_test_x']);
        Http::fake(['api.stripe.com/*' => Http::response([], 500)]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/shop/checkout', [
            'product' => 'pixels_100',
            'provider' => 'stripe',
            'withdrawal_consent' => true,
        ])
            ->assertStatus(502)->assertJsonPath('success', false);
        $this->assertSame(Order::FAILED, Order::firstOrFail()->status);
    }

    public function test_profile_shows_orders_and_credits(): void
    {
        $user = User::factory()->create(['pixel_credits' => 42]);
        $this->actingAs($user)->postJson('/shop/checkout', ['product' => 'pixels_100', 'provider' => 'fake', 'withdrawal_consent' => true]);

        $this->actingAs($user)->get('/de/profile')
            ->assertOk()
            ->assertSee('Käufe &amp; Zahlungen', false)
            ->assertSee('250 Extra-Pixel')
            ->assertSee('5,00 €')
            ->assertSee('42');
    }

public function test_failed_payment_cannot_overwrite_paid_order(): void
{
    $user = User::factory()->create();

    $order = Order::create([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'user_id' => $user->id,
        'product' => 'pixels_100',
        'provider' => 'fake',
        'purchaser_email' => $user->email,
        'withdrawal_consent_at' => now(),
        'withdrawal_consent_version' => '2026-11-04-v2',
        'withdrawal_consent_locale' => 'de',
        'status' => Order::PENDING,
        'amount_cents' => 500,
        'currency' => 'EUR',
        'meta' => ['amount' => 100],
    ]);

    $staleOrder = $order->fresh();

    app(\App\Services\Payments\Shop::class)->fulfill($order);

    app(\App\Services\Payments\Shop::class)->settle(
        $staleOrder,
        Order::FAILED
    );

    $this->assertSame(Order::PAID, $order->fresh()->status);
    $this->assertSame(250, $user->fresh()->pixel_credits);
    $this->assertNotNull($order->fresh()->fulfilled_at);
}
public function test_failed_payment_cannot_overwrite_cancelled_order(): void
{
    $user = User::factory()->create();

    $order = Order::create([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'user_id' => $user->id,
        'product' => 'pixels_100',
        'provider' => 'fake',
        'status' => Order::PENDING,
        'amount_cents' => 500,
        'currency' => 'EUR',
        'meta' => ['amount' => 100],
    ]);

    $staleOrder = $order->fresh();

    $shop = app(\App\Services\Payments\Shop::class);
    $shop->cancel($order);
    $shop->settle($staleOrder, Order::FAILED);

    $this->assertSame(Order::CANCELLED, $order->fresh()->status);
    $this->assertSame(0, $user->fresh()->pixel_credits);
    $this->assertNull($order->fresh()->fulfilled_at);
}
public function test_paypal_does_not_accept_wrong_first_capture_amount(): void
{
    config([
        'payments.providers.paypal.mode' => 'sandbox',
        'payments.providers.paypal.merchant_id' => '6N6Y8RVAF5K3L',
'payments.providers.paypal.client_id' => 'test_client_id',
'payments.providers.paypal.client_secret' => 'test_client_secret',
    ]);

Http::fake([
    'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
        'access_token' => 'test_token',
        'expires_in' => 3600,
    ]),

    'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
        'id' => 'ORDER_TEST',
        'links' => [[
            'rel' => 'approve',
            'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER_TEST',
        ]],
    ], 201),

    'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER_TEST/capture' => Http::response([
        'status' => 'COMPLETED',
    ]),

    'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER_TEST' => function () {
        $order = Order::firstOrFail();

        return Http::response([
            'status' => 'COMPLETED',
            'purchase_units' => [[
                'reference_id' => $order->uuid,
                'custom_id' => $order->uuid,
                'payee' => [
                    'merchant_id' => '6N6Y8RVAF5K3L',
                ],
                'payments' => [
                    'captures' => [
                        [
                            'status' => 'COMPLETED',
                            'amount' => [
                                'value' => '1.00',
                                'currency_code' => 'EUR',
                            ],
                        ],
                        [
                            'status' => 'COMPLETED',
                            'amount' => [
                                'value' => '5.00',
                                'currency_code' => 'EUR',
                            ],
                        ],
                    ],
                ],
            ]],
        ]);
    },
]);

    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/shop/checkout', [
        'product' => 'pixels_100',
        'provider' => 'paypal',
            'withdrawal_consent' => true,
    ])->assertOk();

    $order = Order::firstOrFail();

    $status = app(\App\Services\Payments\PayPalProvider::class)
        ->verify($order);

    $this->assertSame(Order::FAILED, $status);
}
public function test_paypal_does_not_accept_matching_second_purchase_unit(): void
{
    config([
        'payments.providers.paypal.mode' => 'sandbox',
        'payments.providers.paypal.merchant_id' => '6N6Y8RVAF5K3L',
        'payments.providers.paypal.client_id' => 'test_client_id',
        'payments.providers.paypal.client_secret' => 'test_client_secret',
    ]);

    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 3600,
        ]),
        'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
            'id' => 'ORDER_TEST',
            'links' => [[
                'rel' => 'approve',
                'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER_TEST',
            ]],
        ], 201),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER_TEST/capture' => Http::response([
            'status' => 'COMPLETED',
        ]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER_TEST' => function () {
            $order = Order::firstOrFail();

            $validUnit = [
                'reference_id' => $order->uuid,
                'custom_id' => $order->uuid,
                'payee' => ['merchant_id' => '6N6Y8RVAF5K3L'],
                'payments' => [
                    'captures' => [[
                        'status' => 'COMPLETED',
                        'amount' => [
                            'value' => number_format($order->amount_cents / 100, 2, '.', ''),
                            'currency_code' => 'EUR',
                        ],
                    ]],
                ],
            ];

            $invalidUnit = $validUnit;
            $invalidUnit['reference_id'] = 'OTHER_ORDER';

            return Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [$invalidUnit, $validUnit],
            ]);
        },
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/shop/checkout', [
        'product' => 'pixels_100',
        'provider' => 'paypal',
            'withdrawal_consent' => true,
    ])->assertOk();

    $order = Order::firstOrFail();

    $status = app(\App\Services\Payments\PayPalProvider::class)
        ->verify($order);

    $this->assertSame(Order::FAILED, $status);
}
public function test_paypal_rejects_unexpected_additional_purchase_unit(): void
{
    config([
        'payments.providers.paypal.mode' => 'sandbox',
        'payments.providers.paypal.merchant_id' => '6N6Y8RVAF5K3L',
        'payments.providers.paypal.client_id' => 'test_client_id',
        'payments.providers.paypal.client_secret' => 'test_client_secret',
    ]);

    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 3600,
        ]),
        'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
            'id' => 'ORDER_TEST',
            'links' => [[
                'rel' => 'approve',
                'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER_TEST',
            ]],
        ], 201),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER_TEST/capture' => Http::response([
            'status' => 'COMPLETED',
        ]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER_TEST' => function () {
            $order = Order::firstOrFail();

            $validUnit = [
                'reference_id' => $order->uuid,
                'custom_id' => $order->uuid,
                'payee' => ['merchant_id' => '6N6Y8RVAF5K3L'],
                'payments' => [
                    'captures' => [[
                        'status' => 'COMPLETED',
                        'amount' => [
                            'value' => number_format($order->amount_cents / 100, 2, '.', ''),
                            'currency_code' => 'EUR',
                        ],
                    ]],
                ],
            ];

            $unexpectedUnit = $validUnit;
            $unexpectedUnit['reference_id'] = 'UNEXPECTED_ORDER';
            $unexpectedUnit['custom_id'] = 'UNEXPECTED_ORDER';

            return Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [$validUnit, $unexpectedUnit],
            ]);
        },
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/shop/checkout', [
        'product' => 'pixels_100',
        'provider' => 'paypal',
            'withdrawal_consent' => true,
    ])->assertOk();

    $order = Order::firstOrFail();

    $status = app(\App\Services\Payments\PayPalProvider::class)
        ->verify($order);

    $this->assertSame(Order::FAILED, $status);
}
public function test_paypal_keeps_order_pending_when_capture_list_is_empty(): void
{
    config([
        'payments.providers.paypal.mode' => 'sandbox',
        'payments.providers.paypal.merchant_id' => '6N6Y8RVAF5K3L',
        'payments.providers.paypal.client_id' => 'test_client_id',
        'payments.providers.paypal.client_secret' => 'test_client_secret',
    ]);

    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 3600,
        ]),
        'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
            'id' => 'ORDER_TEST',
            'links' => [[
                'rel' => 'approve',
                'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER_TEST',
            ]],
        ], 201),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER_TEST/capture' => Http::response([
            'status' => 'COMPLETED',
        ]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER_TEST' => function () {
            $order = Order::firstOrFail();

            return Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'reference_id' => $order->uuid,
                    'custom_id' => $order->uuid,
                    'payee' => [
                        'merchant_id' => '6N6Y8RVAF5K3L',
                    ],
                    'payments' => [
                        'captures' => [],
                    ],
                ]],
            ]);
        },
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/shop/checkout', [
        'product' => 'pixels_100',
        'provider' => 'paypal',
            'withdrawal_consent' => true,
    ])->assertOk();

    $order = Order::firstOrFail();

    $status = app(\App\Services\Payments\PayPalProvider::class)
        ->verify($order);

    $this->assertSame(Order::PENDING, $status);
}
public function test_paypal_keeps_order_pending_when_captures_field_is_missing(): void
{
    config([
        'payments.providers.paypal.mode' => 'sandbox',
        'payments.providers.paypal.merchant_id' => '6N6Y8RVAF5K3L',
        'payments.providers.paypal.client_id' => 'test_client_id',
        'payments.providers.paypal.client_secret' => 'test_client_secret',
    ]);

    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 3600,
        ]),
        'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
            'id' => 'ORDER_TEST',
            'links' => [[
                'rel' => 'approve',
                'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER_TEST',
            ]],
        ], 201),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER_TEST/capture' => Http::response([
            'status' => 'COMPLETED',
        ]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER_TEST' => function () {
            $order = Order::firstOrFail();

            return Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'reference_id' => $order->uuid,
                    'custom_id' => $order->uuid,
                    'payee' => [
                        'merchant_id' => '6N6Y8RVAF5K3L',
                    ],
                    'payments' => [],
                ]],
            ]);
        },
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/shop/checkout', [
        'product' => 'pixels_100',
        'provider' => 'paypal',
            'withdrawal_consent' => true,
    ])->assertOk();

    $order = Order::firstOrFail();

    $status = app(\App\Services\Payments\PayPalProvider::class)
        ->verify($order);

    $this->assertSame(Order::PENDING, $status);
}
public function test_paypal_rejects_denied_capture_even_when_order_is_completed(): void
{
    config([
        'payments.providers.paypal.mode' => 'sandbox',
        'payments.providers.paypal.merchant_id' => '6N6Y8RVAF5K3L',
        'payments.providers.paypal.client_id' => 'test_client_id',
        'payments.providers.paypal.client_secret' => 'test_client_secret',
    ]);

    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 3600,
        ]),
        'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
            'id' => 'ORDER_TEST',
            'links' => [[
                'rel' => 'approve',
                'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER_TEST',
            ]],
        ], 201),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER_TEST/capture' => Http::response([
            'status' => 'COMPLETED',
        ]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER_TEST' => function () {
            $order = Order::firstOrFail();

            return Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'reference_id' => $order->uuid,
                    'custom_id' => $order->uuid,
                    'payee' => [
                        'merchant_id' => '6N6Y8RVAF5K3L',
                    ],
                    'payments' => [
                        'captures' => [[
                            'status' => 'DENIED',
                            'amount' => [
                                'value' => number_format($order->amount_cents / 100, 2, '.', ''),
                                'currency_code' => 'EUR',
                            ],
                        ]],
                    ],
                ]],
            ]);
        },
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/shop/checkout', [
        'product' => 'pixels_100',
        'provider' => 'paypal',
            'withdrawal_consent' => true,
    ])->assertOk();

    $order = Order::firstOrFail();

    $status = app(\App\Services\Payments\PayPalProvider::class)
        ->verify($order);

    $this->assertSame(Order::FAILED, $status);
}
public function test_paypal_accepts_completed_order_after_capture_request_returns_server_error(): void
{
    config([
        'payments.providers.paypal.mode' => 'sandbox',
        'payments.providers.paypal.merchant_id' => '6N6Y8RVAF5K3L',
        'payments.providers.paypal.client_id' => 'test_client_id',
        'payments.providers.paypal.client_secret' => 'test_client_secret',
    ]);

    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 3600,
        ]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER_TEST/capture' => Http::response([
            'name' => 'INTERNAL_SERVER_ERROR',
        ], 500),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER_TEST' => function () {
            $order = Order::firstOrFail();

            return Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'reference_id' => $order->uuid,
                    'custom_id' => $order->uuid,
                    'payee' => ['merchant_id' => '6N6Y8RVAF5K3L'],
                    'payments' => [
                        'captures' => [[
                            'status' => 'COMPLETED',
                            'amount' => [
                                'value' => '5.00',
                                'currency_code' => 'EUR',
                            ],
                        ]],
                    ],
                ]],
            ]);
        },
    ]);

    $user = User::factory()->create();

    $order = Order::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'product' => 'pixels_100',
        'provider' => 'paypal',
        'provider_reference' => 'ORDER_TEST',
        'status' => Order::PENDING,
        'amount_cents' => 500,
        'currency' => 'EUR',
    ]);

    $status = app(\App\Services\Payments\PayPalProvider::class)->verify($order);

    $this->assertSame(Order::PAID, $status);
}

public function test_paypal_keeps_order_pending_when_order_is_not_approved(): void
{
    config([
        'payments.providers.paypal.mode' => 'sandbox',
        'payments.providers.paypal.client_id' => 'test_client_id',
        'payments.providers.paypal.client_secret' => 'test_client_secret',
    ]);

    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 3600,
        ]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER_TEST/capture' => Http::response([
            'name' => 'UNPROCESSABLE_ENTITY',
            'details' => [[
                'issue' => 'ORDER_NOT_APPROVED',
            ]],
        ], 422),
    ]);

    $user = User::factory()->create();

    $order = Order::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'product' => 'pixels_100',
        'provider' => 'paypal',
        'provider_reference' => 'ORDER_TEST',
        'status' => Order::PENDING,
        'amount_cents' => 500,
        'currency' => 'EUR',
    ]);

    $status = app(\App\Services\Payments\PayPalProvider::class)->verify($order);

    $this->assertSame(Order::PENDING, $status);
}

public function test_paypal_keeps_order_pending_when_order_status_request_fails(): void
{
    config([
        'payments.providers.paypal.mode' => 'sandbox',
        'payments.providers.paypal.client_id' => 'test_client_id',
        'payments.providers.paypal.client_secret' => 'test_client_secret',
    ]);

    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 3600,
        ]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER_TEST/capture' => Http::response([
            'status' => 'COMPLETED',
        ]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER_TEST' => Http::response([
            'message' => 'Temporary server error',
        ], 500),
    ]);

    $user = User::factory()->create();

    $order = Order::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'product' => 'pixels_100',
        'provider' => 'paypal',
        'provider_reference' => 'ORDER_TEST',
        'status' => Order::PENDING,
        'amount_cents' => 500,
        'currency' => 'EUR',
    ]);

    $status = app(\App\Services\Payments\PayPalProvider::class)->verify($order);

    $this->assertSame(Order::PENDING, $status);
}

public function test_paypal_does_not_fulfill_order_that_is_only_approved(): void
{
    config([
        'payments.providers.paypal.mode' => 'sandbox',
        'payments.providers.paypal.merchant_id' => '6N6Y8RVAF5K3L',
        'payments.providers.paypal.client_id' => 'test_client_id',
        'payments.providers.paypal.client_secret' => 'test_client_secret',
    ]);

    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 3600,
        ]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER_TEST/capture' => Http::response([
            'status' => 'COMPLETED',
        ]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER_TEST' => function () {
            $order = Order::firstOrFail();

            return Http::response([
                'status' => 'APPROVED',
                'purchase_units' => [[
                    'reference_id' => $order->uuid,
                    'custom_id' => $order->uuid,
                    'payee' => ['merchant_id' => '6N6Y8RVAF5K3L'],
                    'payments' => [
                        'captures' => [[
                            'status' => 'COMPLETED',
                            'amount' => [
                                'value' => '5.00',
                                'currency_code' => 'EUR',
                            ],
                        ]],
                    ],
                ]],
            ]);
        },
    ]);

    $user = User::factory()->create();

    $order = Order::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'product' => 'pixels_100',
        'provider' => 'paypal',
        'provider_reference' => 'ORDER_TEST',
        'status' => Order::PENDING,
        'amount_cents' => 500,
        'currency' => 'EUR',
    ]);

    $status = app(\App\Services\Payments\PayPalProvider::class)->verify($order);

    $this->assertSame(Order::PENDING, $status);
    $this->assertSame(0, $user->fresh()->pixel_credits);
}

public function test_paypal_rejects_capture_with_missing_amount_value(): void
{
    config([
        'payments.providers.paypal.mode' => 'sandbox',
        'payments.providers.paypal.merchant_id' => '6N6Y8RVAF5K3L',
        'payments.providers.paypal.client_id' => 'test_client_id',
        'payments.providers.paypal.client_secret' => 'test_client_secret',
    ]);

    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
            'access_token' => 'test_token',
            'expires_in' => 3600,
        ]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER_TEST/capture' => Http::response([
            'status' => 'COMPLETED',
        ]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER_TEST' => function () {
            $order = Order::firstOrFail();

            return Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'reference_id' => $order->uuid,
                    'custom_id' => $order->uuid,
                    'payee' => ['merchant_id' => '6N6Y8RVAF5K3L'],
                    'payments' => [
                        'captures' => [[
                            'status' => 'COMPLETED',
                            'amount' => [
                                'currency_code' => 'EUR',
                            ],
                        ]],
                    ],
                ]],
            ]);
        },
    ]);

    $user = User::factory()->create();

    $order = Order::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'product' => 'pixels_100',
        'provider' => 'paypal',
        'provider_reference' => 'ORDER_TEST',
        'status' => Order::PENDING,
        'amount_cents' => 500,
        'currency' => 'EUR',
    ]);

    $status = app(\App\Services\Payments\PayPalProvider::class)->verify($order);

    $this->assertSame(Order::FAILED, $status);
    $this->assertSame(0, $user->fresh()->pixel_credits);
}
public function test_stripe_webhook_accepts_multiple_v1_signatures(): void
{
    config(['payments.providers.stripe.webhook_secret' => 'whsec_test']);

    $body = json_encode([
        'type' => 'ignored',
        'data' => ['object' => ['id' => 'x']],
    ]);

    $timestamp = (string) time();
    $validSignature = hash_hmac(
        'sha256',
        $timestamp.'.'.$body,
        'whsec_test'
    );

    $this->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1=".str_repeat('0', 64).",v1={$validSignature}",
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();
}

public function test_stripe_webhook_rejects_expired_timestamp(): void
{
    config(['payments.providers.stripe.webhook_secret' => 'whsec_test']);

    $body = json_encode([
        'type' => 'ignored',
        'data' => ['object' => ['id' => 'x']],
    ]);

    $timestamp = (string) (time() - 600);
    $signature = hash_hmac(
        'sha256',
        $timestamp.'.'.$body,
        'whsec_test'
    );

    $this->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertStatus(400);
}
public function test_stripe_rejects_live_payment_in_test_mode(): void
{
    config(['payments.providers.stripe.secret_key' => 'sk_test_x']);

    Http::fake([
        'api.stripe.com/v1/checkout/sessions' => Http::response([
            'id' => 'cs_test_mode',
            'url' => 'https://checkout.stripe.com/test',
        ]),
        'api.stripe.com/v1/checkout/sessions/cs_test_mode' => function () {
            return Http::response([
                'client_reference_id' => Order::firstOrFail()->uuid,
                'amount_total' => 500,
                'currency' => 'eur',
                'payment_status' => 'paid',
                'status' => 'complete',
                'livemode' => true,
                'mode' => 'payment',
            ]);
        },
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/shop/checkout', [
        'product' => 'pixels_100',
        'provider' => 'stripe',
        'withdrawal_consent' => true,
    ])->assertOk();

    $this->actingAs($user)->get(route('shop.return', Order::firstOrFail()));

    $this->assertSame(0, $user->fresh()->pixel_credits);
    $this->assertSame(Order::FAILED, Order::firstOrFail()->fresh()->status);
}
}
