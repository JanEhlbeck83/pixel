<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\Payments\StripeProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Stripe Checkout: Visa/Mastercard sowie Apple Pay und Google Pay (laufen bei Stripe als "card"). */
class StripeShopTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test';

    /** Antwort auf GET /v1/checkout/sessions/{id} */
    private ?array $sessionResponse = null;

    private int $sessionStatusCode = 200;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.providers.fake.enabled' => false,
            'payments.providers.paypal.client_id' => null,
            'payments.providers.stripe.secret_key' => 'sk_test_x',
            'payments.providers.stripe.webhook_secret' => self::WEBHOOK_SECRET,
        ]);
    }

    // ---------------------------------------------------------------- Katalog / Aktivierung

    public function test_catalog_lists_only_stripe_without_mollie(): void
    {
        config(['payments.providers.fake.enabled' => true]);

        $keys = collect($this->getJson('/api/shop')->assertOk()->json('providers'))->pluck('key')->all();

        $this->assertContains('stripe', $keys);
        $this->assertNotContains('mollie', $keys);
    }

    public function test_catalog_label_mentions_card_apple_pay_and_google_pay(): void
    {
        $provider = $this->getJson('/api/shop')->json('providers.0');

        $this->assertSame('stripe', $provider['key']);
        $this->assertSame('Sandbox', $provider['mode']);
        $this->assertStringContainsString('Apple Pay', $provider['label']);
        $this->assertStringContainsString('Google Pay', $provider['label']);
    }

    public function test_label_is_translated(): void
    {
        app()->setLocale('en');
        $this->assertSame('Card / Apple Pay / Google Pay (Stripe)', (new StripeProvider)->label());

        app()->setLocale('es');
        $this->assertSame('Tarjeta / Apple Pay / Google Pay (Stripe)', (new StripeProvider)->label());
    }

    public function test_live_key_is_labelled_live(): void
    {
        config(['payments.providers.stripe.secret_key' => 'sk_live_abc']);

        $this->assertSame('Live', $this->getJson('/api/shop')->json('providers.0.mode'));
    }

    #[DataProvider('disabledKeys')]
    public function test_provider_is_disabled_without_a_usable_key(?string $key): void
    {
        config(['payments.providers.stripe.secret_key' => $key]);

        $this->assertFalse((new StripeProvider)->isEnabled());
        $this->getJson('/api/shop')->assertJsonCount(0, 'providers');

        $this->actingAs(User::factory()->create())
            ->postJson('/shop/checkout', ['product' => 'pixels_100', 'provider' => 'stripe'])
            ->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public static function disabledKeys(): array
    {
        return ['null' => [null], 'empty' => ['']];
    }

    public function test_mollie_is_gone(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/shop/checkout', ['product' => 'pixels_100', 'provider' => 'mollie'])
            ->assertUnprocessable();

        $this->post('/webhooks/mollie', ['id' => 'tr_1'])->assertNotFound();
        $this->assertFileDoesNotExist(app_path('Services/Payments/MollieProvider.php'));
        $this->assertNull(config('payments.providers.mollie'));
    }

    // ---------------------------------------------------------------- Checkout anlegen

    public function test_checkout_requires_login(): void
    {
        Http::fake();

        $this->postJson('/shop/checkout', ['product' => 'pixels_100', 'provider' => 'stripe'])->assertUnauthorized();

        Http::assertNothingSent();
        $this->assertDatabaseCount('orders', 0);
    }

    #[DataProvider('invalidCheckoutInput')]
    public function test_checkout_rejects_invalid_input(array $payload): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())->postJson('/shop/checkout', $payload)->assertUnprocessable();

        Http::assertNothingSent();
        $this->assertDatabaseCount('orders', 0);
    }

    public static function invalidCheckoutInput(): array
    {
        return [
            'unknown product' => [['product' => 'free', 'provider' => 'stripe']],
            'missing product' => [['provider' => 'stripe']],
            'missing provider' => [['product' => 'pixels_100']],
            'unknown provider' => [['product' => 'pixels_100', 'provider' => 'bitcoin']],
            'array product' => [['product' => ['pixels_100'], 'provider' => 'stripe']],
            'empty product' => [['product' => '', 'provider' => 'stripe']],
        ];
    }

    public function test_checkout_sends_card_only_session_with_server_side_price(): void
    {
        $this->fakeStripe('cs_test_1');
        $user = User::factory()->create();

        // Ein vom Client mitgeschickter Preis darf nie verwendet werden.
        $this->actingAs($user)
            ->postJson('/shop/checkout', ['product' => 'pixels_100', 'provider' => 'stripe', 'price_cents' => 1, 'amount_cents' => 1])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('redirect', 'https://checkout.stripe.com/c/pay/cs_test_1');

        $order = Order::firstOrFail();
        $this->assertSame('cs_test_1', $order->provider_reference);
        $this->assertSame('stripe', $order->provider);
        $this->assertSame(Order::PENDING, $order->status);
        $this->assertSame(500, $order->amount_cents);

        Http::assertSent(function (Request $request) use ($order) {
            if ($request->method() !== 'POST' || $request->url() !== 'https://api.stripe.com/v1/checkout/sessions') {
                return false;
            }
            $data = $request->data();

            return $request->hasHeader('Authorization', 'Bearer sk_test_x')
                && $request->hasHeader('Idempotency-Key', 'checkout-'.$order->uuid)
                && $data['mode'] === 'payment'
                && $data['payment_method_types'] === ['card', 'sepa_debit', 'klarna', 'link']
                && $data['client_reference_id'] === $order->uuid
                && $data['metadata'] === ['order_uuid' => $order->uuid]
                && $data['success_url'] === route('shop.return', $order)
                && $data['cancel_url'] === route('shop.cancel', $order)
                && $data['line_items'][0]['quantity'] === 1
                && $data['line_items'][0]['price_data']['currency'] === 'eur'
                && $data['line_items'][0]['price_data']['unit_amount'] === 500
                && $data['expires_at'] > time() + 1700
                && $data['expires_at'] <= time() + 86400;
        });
    }

    #[DataProvider('products')]
    public function test_checkout_charges_the_configured_price_per_product(string $product, int $cents): void
    {
        $this->fakeStripe('cs_test_p');

        $this->actingAs(User::factory()->create())
            ->postJson('/shop/checkout', ['product' => $product, 'provider' => 'stripe'])
            ->assertOk();

        $this->assertSame($cents, Order::firstOrFail()->amount_cents);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v1/checkout/sessions')
            && $r->data()['line_items'][0]['price_data']['unit_amount'] === $cents);
    }

    public static function products(): array
    {
        return [
            'extend_x' => ['extend_x', 300],
            'extend_y' => ['extend_y', 300],
            'pixels_100' => ['pixels_100', 500],
            'pixels_1000' => ['pixels_1000', 1500],
        ];
    }

    public function test_checkout_uses_price_from_config(): void
    {
        config(['payments.products.pixels_100.price_cents' => 777]);
        $this->fakeStripe('cs_test_cfg');

        $this->actingAs(User::factory()->create())
            ->postJson('/shop/checkout', ['product' => 'pixels_100', 'provider' => 'stripe'])
            ->assertOk();

        $this->assertSame(777, Order::firstOrFail()->amount_cents);
    }

    #[DataProvider('failingCheckoutResponses')]
    public function test_checkout_failures_mark_order_failed_and_return_friendly_error(int $status, array $body): void
    {
        Http::fake(['api.stripe.com/*' => Http::response($body, $status)]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/shop/checkout', ['product' => 'pixels_100', 'provider' => 'stripe'])
            ->assertStatus(502)
            ->assertJsonPath('success', false)
            ->assertJsonMissingPath('redirect');

        $this->assertSame(Order::FAILED, Order::firstOrFail()->status);
        $this->assertSame(0, $user->fresh()->pixel_credits);
    }

    public static function failingCheckoutResponses(): array
    {
        return [
            '500 server error' => [500, []],
            '401 invalid key' => [401, ['error' => ['message' => 'Invalid API Key provided']]],
            '429 rate limit' => [429, ['error' => ['type' => 'rate_limit_error']]],
            '400 bad request' => [400, ['error' => ['message' => 'bad']]],
            '200 without url' => [200, ['id' => 'cs_test_nourl']],
            '200 with empty url' => [200, ['id' => 'cs_test_empty', 'url' => '']],
            '200 with null url' => [200, ['id' => 'cs_test_null', 'url' => null]],
        ];
    }

    public function test_checkout_network_exception_is_handled(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->actingAs(User::factory()->create())
            ->postJson('/shop/checkout', ['product' => 'pixels_100', 'provider' => 'stripe'])
            ->assertStatus(502);

        $this->assertSame(Order::FAILED, Order::firstOrFail()->status);
    }

    public function test_error_message_does_not_leak_stripe_details(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'sk_test_x secret detail']], 401)]);

        $response = $this->actingAs(User::factory()->create())
            ->postJson('/shop/checkout', ['product' => 'pixels_100', 'provider' => 'stripe'])
            ->assertStatus(502);

        $this->assertStringNotContainsString('sk_test_x', $response->getContent());
        $this->assertStringNotContainsString('secret detail', $response->getContent());
    }

    // ---------------------------------------------------------------- Rückkehr: erfolgreiche Zahlung

    public function test_paid_card_payment_grants_pixels_exactly_once(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order);

        $this->actingAs($user)->get(route('shop.return', $order))
            ->assertRedirect(route('home'))
            ->assertSessionHas('purchase.status', 'ok');

        $this->actingAs($user)->get(route('shop.return', $order));
        $this->actingAs($user)->get(route('shop.return', $order));

        $this->assertSame(250, $user->fresh()->pixel_credits);
        $order->refresh();
        $this->assertSame(Order::PAID, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertNotNull($order->fulfilled_at);
    }

    public function test_bundle_of_1000_pixels_is_credited(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_1000');
        $this->sessionResponse = $this->stripeSession($order);

        $this->actingAs($user)->get(route('shop.return', $order));

        $this->assertSame(1000, $user->fresh()->pixel_credits);
    }

    public function test_existing_credits_are_kept_when_buying(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100', ['pixel_credits' => 42]);
        $this->sessionResponse = $this->stripeSession($order);

        $this->actingAs($user)->get(route('shop.return', $order));

        $this->assertSame(292, $user->fresh()->pixel_credits);
    }

    public function test_paid_extension_grows_the_wall_once(): void
    {
        [$user, $order] = $this->pendingOrder('extend_x');
        $this->sessionResponse = $this->stripeSession($order);

        $this->actingAs($user)->get(route('shop.return', $order));
        $this->actingAs($user)->get(route('shop.return', $order));

        $this->getJson('/api/wall')->assertJsonPath('width', 1025)->assertJsonPath('height', 1024);
    }

    public function test_paid_vertical_extension_grows_the_wall(): void
    {
        [$user, $order] = $this->pendingOrder('extend_y');
        $this->sessionResponse = $this->stripeSession($order);

        $this->actingAs($user)->get(route('shop.return', $order));

        $this->getJson('/api/wall')->assertJsonPath('width', 1024)->assertJsonPath('height', 1025);
    }

    public function test_wallet_payments_are_accepted_like_cards(): void
    {
        // Apple Pay und Google Pay liefern bei Stripe eine normale Karten-Session.
        foreach (['apple_pay', 'google_pay'] as $wallet) {
            [$user, $order] = $this->pendingOrder('pixels_100');
            $this->sessionResponse = $this->stripeSession($order, ['payment_method_types' => ['card'], 'wallet' => $wallet]);

            $this->actingAs($user)->get(route('shop.return', $order));

            $this->assertSame(250, $user->fresh()->pixel_credits, $wallet);
            $this->assertSame(Order::PAID, $order->fresh()->status, $wallet);
        }
    }

    public function test_live_key_accepts_live_payment(): void
    {
        config(['payments.providers.stripe.secret_key' => 'sk_live_abc']);
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order, ['livemode' => true]);

        $this->actingAs($user)->get(route('shop.return', $order));

        $this->assertSame(Order::PAID, $order->fresh()->status);
    }

    public function test_currency_is_compared_case_insensitively(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order, ['currency' => 'EUR']);

        $this->actingAs($user)->get(route('shop.return', $order));

        $this->assertSame(Order::PAID, $order->fresh()->status);
    }

    public function test_verification_queries_the_session_stored_on_the_order(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100', [], 'cs_test_abc');
        $this->sessionResponse = $this->stripeSession($order);

        $this->actingAs($user)->get(route('shop.return', $order));

        Http::assertSent(fn (Request $r) => $r->method() === 'GET'
            && $r->url() === 'https://api.stripe.com/v1/checkout/sessions/cs_test_abc'
            && $r->hasHeader('Authorization', 'Bearer sk_test_x'));
    }

    // ---------------------------------------------------------------- Rückkehr: nicht (voll) bezahlt

    public function test_unpaid_open_session_stays_pending(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order, ['payment_status' => 'unpaid', 'status' => 'open']);

        $this->actingAs($user)->get(route('shop.return', $order))
            ->assertSessionHas('purchase.status', 'pending');

        $this->assertSame(Order::PENDING, $order->fresh()->status);
        $this->assertSame(0, $user->fresh()->pixel_credits);
    }

    public function test_complete_but_unpaid_session_is_not_fulfilled(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order, ['payment_status' => 'unpaid', 'status' => 'complete']);

        $this->actingAs($user)->get(route('shop.return', $order));

        $this->assertSame(Order::PENDING, $order->fresh()->status);
        $this->assertSame(0, $user->fresh()->pixel_credits);
    }

    public function test_no_payment_required_is_not_treated_as_paid(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order, ['payment_status' => 'no_payment_required']);

        $this->actingAs($user)->get(route('shop.return', $order));

        $this->assertSame(0, $user->fresh()->pixel_credits);
        $this->assertNotSame(Order::PAID, $order->fresh()->status);
    }

    public function test_paid_but_open_session_is_not_fulfilled(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order, ['status' => 'open']);

        $this->actingAs($user)->get(route('shop.return', $order));

        $this->assertSame(0, $user->fresh()->pixel_credits);
        $this->assertSame(Order::PENDING, $order->fresh()->status);
    }

    public function test_expired_session_fails_the_order(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order, ['payment_status' => 'unpaid', 'status' => 'expired']);

        $this->actingAs($user)->get(route('shop.return', $order))
            ->assertSessionHas('purchase.status', 'error');

        $this->assertSame(Order::FAILED, $order->fresh()->status);
        $this->assertSame(0, $user->fresh()->pixel_credits);
    }

    #[DataProvider('apiErrorStatuses')]
    public function test_stripe_api_error_while_verifying_keeps_order_pending(int $status): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = [];
        $this->sessionStatusCode = $status;

        $this->actingAs($user)->get(route('shop.return', $order))->assertRedirect(route('home'));

        $this->assertSame(Order::PENDING, $order->fresh()->status);
        $this->assertSame(0, $user->fresh()->pixel_credits);
    }

    public static function apiErrorStatuses(): array
    {
        return ['500' => [500], '502' => [502], '503' => [503], '429' => [429], '404' => [404]];
    }

    public function test_network_exception_while_verifying_does_not_crash_or_fulfill(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        Http::fake(fn () => throw new ConnectionException('timeout'));

                $this->actingAs($user)->get(route('shop.return', $order))->assertRedirect(route('home'));

        $this->assertSame(Order::PENDING, $order->fresh()->status);
        $this->assertSame(0, $user->fresh()->pixel_credits);
    }

    public function test_pending_order_without_reference_fails(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'pixels_100', null);
        Http::fake();

        $this->actingAs($user)->get(route('shop.return', $order));

        Http::assertNothingSent();
        $this->assertSame(Order::FAILED, $order->fresh()->status);
        $this->assertSame(0, $user->fresh()->pixel_credits);
    }

    // ---------------------------------------------------------------- Manipulation / Plausibilität

    #[DataProvider('tamperedSessions')]
    public function test_tampered_session_data_is_rejected(array $override): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order, $override);

        $this->actingAs($user)->get(route('shop.return', $order))
            ->assertSessionHas('purchase.status', 'error');

        $this->assertSame(Order::FAILED, $order->fresh()->status);
        $this->assertSame(0, $user->fresh()->pixel_credits);
        $this->assertNull($order->fresh()->fulfilled_at);
    }

    public static function tamperedSessions(): array
    {
        $other = '00000000-0000-0000-0000-000000000000';

        return [
            'wrong client_reference_id' => [['client_reference_id' => $other]],
            'missing client_reference_id' => [['client_reference_id' => null]],
            'wrong metadata uuid' => [['metadata' => ['order_uuid' => $other]]],
            'missing metadata' => [['metadata' => []]],
            'amount too low' => [['amount_total' => 100]],
            'amount too high' => [['amount_total' => 50000]],
            'amount zero' => [['amount_total' => 0]],
            'amount missing' => [['amount_total' => null]],
            'amount not numeric' => [['amount_total' => 'abc']],
            'wrong currency usd' => [['currency' => 'usd']],
            'missing currency' => [['currency' => null]],
            'subscription mode' => [['mode' => 'subscription']],
            'setup mode' => [['mode' => 'setup']],
            'missing mode' => [['mode' => null]],
            'live payment in test mode' => [['livemode' => true]],
            'missing livemode' => [['livemode' => null]],
            'livemode as string' => [['livemode' => 'false']],
            'foreign session id' => [['id' => 'cs_test_FOREIGN']],
        ];
    }

    public function test_test_mode_payment_is_rejected_with_live_key(): void
    {
        config(['payments.providers.stripe.secret_key' => 'sk_live_abc']);
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order, ['livemode' => false]);

        $this->actingAs($user)->get(route('shop.return', $order));

        $this->assertSame(Order::FAILED, $order->fresh()->status);
        $this->assertSame(0, $user->fresh()->pixel_credits);
    }

    public function test_session_of_another_order_cannot_pay_this_order(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        [, $other] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($other, ['id' => $order->provider_reference]);

        $this->actingAs($user)->get(route('shop.return', $order));

        $this->assertSame(0, $user->fresh()->pixel_credits);
        $this->assertSame(Order::FAILED, $order->fresh()->status);
    }

    public function test_cheaper_product_session_cannot_unlock_expensive_product(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_1000');
        $this->sessionResponse = $this->stripeSession($order, ['amount_total' => 500]);

        $this->actingAs($user)->get(route('shop.return', $order));

        $this->assertSame(0, $user->fresh()->pixel_credits);
        $this->assertSame(Order::FAILED, $order->fresh()->status);
    }

    public function test_price_change_after_order_creation_does_not_affect_pending_order(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        config(['payments.products.pixels_100.price_cents' => 999]);
        $this->sessionResponse = $this->stripeSession($order);

        $this->actingAs($user)->get(route('shop.return', $order));

        $this->assertSame(Order::PAID, $order->fresh()->status);
    }

    // ---------------------------------------------------------------- Zugriff / Statuswechsel

    public function test_other_users_cannot_complete_or_cancel_the_order(): void
    {
        [$owner, $order] = $this->pendingOrder('pixels_100');
        $intruder = User::factory()->create();
        $this->sessionResponse = $this->stripeSession($order);

        $this->actingAs($intruder)->get(route('shop.return', $order))->assertForbidden();
        $this->actingAs($intruder)->get(route('shop.cancel', $order))->assertForbidden();

        $this->assertSame(Order::PENDING, $order->fresh()->status);
        $this->assertSame(0, $owner->fresh()->pixel_credits);
        $this->assertSame(0, $intruder->fresh()->pixel_credits);
    }

    public function test_guests_cannot_use_return_or_cancel(): void
    {
        [, $order] = $this->pendingOrder('pixels_100');

        $this->get(route('shop.return', $order))->assertRedirect();
        $this->get(route('shop.cancel', $order))->assertRedirect();

        $this->assertSame(Order::PENDING, $order->fresh()->status);
    }

    public function test_return_for_unknown_order_is_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/shop/return/'.Str::uuid())
            ->assertNotFound();
    }

    public function test_cancelling_gives_nothing_and_a_later_return_does_not_fulfill(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order);

        $this->actingAs($user)->get(route('shop.cancel', $order))
            ->assertRedirect(route('home'))
            ->assertSessionHas('purchase.status', 'error');
        $this->actingAs($user)->get(route('shop.return', $order));

        $this->assertSame(Order::CANCELLED, $order->fresh()->status);
        $this->assertSame(0, $user->fresh()->pixel_credits);
    }

    public function test_cancel_cannot_revert_a_paid_order(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order);
        $this->actingAs($user)->get(route('shop.return', $order));

        $this->actingAs($user)->get(route('shop.cancel', $order));

        $this->assertSame(Order::PAID, $order->fresh()->status);
                $this->assertSame(250, $user->fresh()->pixel_credits);
    }

    public function test_failed_order_cannot_be_revived_by_a_later_paid_session(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order, ['payment_status' => 'unpaid', 'status' => 'expired']);
        $this->actingAs($user)->get(route('shop.return', $order));

        $this->sessionResponse = $this->stripeSession($order);
        $this->actingAs($user)->get(route('shop.return', $order));

        $this->assertSame(Order::FAILED, $order->fresh()->status);
        $this->assertSame(0, $user->fresh()->pixel_credits);
    }

    public function test_pending_order_can_still_be_paid_after_an_earlier_unpaid_check(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order, ['payment_status' => 'unpaid', 'status' => 'open']);
        $this->actingAs($user)->get(route('shop.return', $order));

        $this->sessionResponse = $this->stripeSession($order);
        $this->actingAs($user)->get(route('shop.return', $order));

        $this->assertSame(Order::PAID, $order->fresh()->status);
        $this->assertSame(250, $user->fresh()->pixel_credits);
    }

    public function test_credits_go_to_the_order_owner_only(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $bystander = User::factory()->create();
        $this->sessionResponse = $this->stripeSession($order);

        $this->actingAs($user)->get(route('shop.return', $order));

        $this->assertSame(250, $user->fresh()->pixel_credits);
        $this->assertSame(0, $bystander->fresh()->pixel_credits);
    }

    // ---------------------------------------------------------------- Webhook

    public function test_webhook_completed_event_fulfills_the_order(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order);

        $this->webhook($this->event('checkout.session.completed', $order->provider_reference))->assertOk();

                $this->assertSame(250, $user->fresh()->pixel_credits);
        $this->assertSame(Order::PAID, $order->fresh()->status);
    }

    public function test_webhook_async_payment_succeeded_fulfills_the_order(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order);

        $this->webhook($this->event('checkout.session.async_payment_succeeded', $order->provider_reference))->assertOk();

                $this->assertSame(250, $user->fresh()->pixel_credits);
    }

    public function test_webhook_replay_and_return_do_not_duplicate_credits(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order);
        $event = $this->event('checkout.session.completed', $order->provider_reference);

        $this->webhook($event)->assertOk();
        $this->webhook($event)->assertOk();
        $this->actingAs($user)->get(route('shop.return', $order));
        $this->webhook($event)->assertOk();

        $this->assertSame(250, $user->fresh()->pixel_credits);    }

    public function test_webhook_does_not_trust_event_payload_but_verifies_with_stripe(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order, ['payment_status' => 'unpaid', 'status' => 'open']);

        // Das Event behauptet "paid", die Stripe-API sagt etwas anderes.
        $event = $this->event('checkout.session.completed', $order->provider_reference, ['payment_status' => 'paid', 'amount_total' => 500]);
        $this->webhook($event)->assertOk();

        $this->assertSame(0, $user->fresh()->pixel_credits);
        $this->assertSame(Order::PENDING, $order->fresh()->status);
    }

    public function test_webhook_with_wrong_amount_fails_the_order(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order, ['amount_total' => 1]);

        $this->webhook($this->event('checkout.session.completed', $order->provider_reference))->assertOk();

        $this->assertSame(Order::FAILED, $order->fresh()->status);
        $this->assertSame(0, $user->fresh()->pixel_credits);
    }

    public function test_webhook_expired_event_fails_the_pending_order(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order, ['payment_status' => 'unpaid', 'status' => 'expired']);

        $this->webhook($this->event('checkout.session.expired', $order->provider_reference))->assertOk();

        $this->assertSame(Order::FAILED, $order->fresh()->status);
        $this->assertSame(0, $user->fresh()->pixel_credits);
    }

    public function test_webhook_expired_event_cannot_undo_a_paid_order(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order);
        $this->webhook($this->event('checkout.session.completed', $order->provider_reference))->assertOk();

        $this->sessionResponse = $this->stripeSession($order, ['payment_status' => 'unpaid', 'status' => 'expired']);
        $this->webhook($this->event('checkout.session.expired', $order->provider_reference))->assertOk();

        $this->assertSame(Order::PAID, $order->fresh()->status);
                $this->assertSame(250, $user->fresh()->pixel_credits);
    }

    public function test_webhook_async_payment_failed_fails_the_order(): void
    {
        [, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order, ['payment_status' => 'unpaid', 'status' => 'expired']);

        $this->webhook($this->event('checkout.session.async_payment_failed', $order->provider_reference))->assertOk();

        $this->assertSame(Order::FAILED, $order->fresh()->status);
    }

    public function test_webhook_cannot_fulfill_a_cancelled_order(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->actingAs($user)->get(route('shop.cancel', $order));
        $this->sessionResponse = $this->stripeSession($order);

        $this->webhook($this->event('checkout.session.completed', $order->provider_reference))->assertOk();

        $this->assertSame(Order::CANCELLED, $order->fresh()->status);
        $this->assertSame(0, $user->fresh()->pixel_credits);
    }

    public function test_webhook_ignores_unrelated_event_types(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order);

        foreach (['payment_intent.succeeded', 'charge.succeeded', 'customer.created', ''] as $type) {
            $this->webhook($this->event($type, $order->provider_reference))->assertOk();
        }

        $this->assertSame(Order::PENDING, $order->fresh()->status);
        $this->assertSame(0, $user->fresh()->pixel_credits);
        Http::assertNothingSent();
    }

    public function test_webhook_for_unknown_session_changes_nothing(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order);

        $this->webhook($this->event('checkout.session.completed', 'cs_test_UNKNOWN'))->assertOk();

        $this->assertSame(Order::PENDING, $order->fresh()->status);
        $this->assertSame(0, $user->fresh()->pixel_credits);
        Http::assertNothingSent();
    }

    #[DataProvider('malformedReferences')]
    public function test_webhook_with_malformed_object_id_does_not_crash(mixed $id): void
    {
        $this->webhook(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => $id]]])->assertOk();

        $this->assertDatabaseCount('orders', 0);
    }

    public static function malformedReferences(): array
    {
        return ['null' => [null], 'int' => [123], 'array' => [['cs']], 'empty' => ['']];
    }

    #[DataProvider('malformedEvents')]
    public function test_webhook_with_malformed_event_body_does_not_crash(string $body): void
    {
        $this->rawWebhook($body)->assertOk();

        $this->assertDatabaseCount('orders', 0);
    }

    public static function malformedEvents(): array
    {
        return [
            'empty json object' => ['{}'],
            'not json' => ['not-json'],
            'json null' => ['null'],
            'missing data' => ['{"type":"checkout.session.completed"}'],
            'data not object' => ['{"type":"checkout.session.completed","data":"x"}'],
        ];
    }

    public function test_webhook_for_order_of_another_provider_is_ignored(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'pixels_100', 'cs_test_shared', 'paypal');
        $this->sessionResponse = $this->stripeSession($order);

        $this->webhook($this->event('checkout.session.completed', 'cs_test_shared'))->assertOk();

        $this->assertSame(Order::PENDING, $order->fresh()->status);
        $this->assertSame(0, $user->fresh()->pixel_credits);
    }

    public function test_webhook_requires_signature_header(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order);
        $body = json_encode($this->event('checkout.session.completed', $order->provider_reference));

        $this->call('POST', '/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)
            ->assertStatus(400);

        $this->assertSame(0, $user->fresh()->pixel_credits);
    }

    #[DataProvider('badSignatureHeaders')]
    public function test_webhook_rejects_malformed_signature_headers(string $header): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order);
        $body = json_encode($this->event('checkout.session.completed', $order->provider_reference));

        $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $header, 'CONTENT_TYPE' => 'application/json',
        ], $body)->assertStatus(400);

        $this->assertSame(0, $user->fresh()->pixel_credits);
    }

    public static function badSignatureHeaders(): array
    {
        $t = (string) time();

        return [
            'garbage' => ['garbage'],
            'empty' => [''],
            'no v1' => ["t={$t}"],
            'no timestamp' => ['v1='.str_repeat('a', 64)],
            'non numeric timestamp' => ['t=abc,v1='.str_repeat('a', 64)],
            'short signature' => ["t={$t},v1=abc"],
            'non hex signature' => ["t={$t},v1=".str_repeat('z', 64)],
            'zero signature' => ["t={$t},v1=".str_repeat('0', 64)],
            'duplicated timestamp' => ["t={$t},t={$t},v1=".str_repeat('a', 64)],
        ];
    }

    public function test_webhook_rejects_tampered_body(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order);
        $signed = json_encode($this->event('checkout.session.completed', 'cs_test_other'));
        $t = time();
        $sig = hash_hmac('sha256', $t.'.'.$signed, self::WEBHOOK_SECRET);
        $tampered = json_encode($this->event('checkout.session.completed', $order->provider_reference));

        $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1={$sig}", 'CONTENT_TYPE' => 'application/json',
        ], $tampered)->assertStatus(400);

        $this->assertSame(0, $user->fresh()->pixel_credits);
    }

    public function test_webhook_rejects_signature_made_with_wrong_secret(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order);

        $this->webhook($this->event('checkout.session.completed', $order->provider_reference), 'whsec_attacker')
            ->assertStatus(400);

        $this->assertSame(0, $user->fresh()->pixel_credits);
    }

    public function test_webhook_rejects_old_and_far_future_timestamps(): void
    {
        [, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order);
        $event = $this->event('checkout.session.completed', $order->provider_reference);

        $this->webhook($event, self::WEBHOOK_SECRET, time() - 301)->assertStatus(400);
        $this->webhook($event, self::WEBHOOK_SECRET, time() + 301)->assertStatus(400);
        $this->webhook($event, self::WEBHOOK_SECRET, time() - 60)->assertOk();
    }

    #[DataProvider('emptySecrets')]
    public function test_webhook_is_rejected_when_no_secret_is_configured(?string $secret): void
    {
        config(['payments.providers.stripe.webhook_secret' => $secret]);
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order);

        // Auch eine mit leerem Secret "korrekt" berechnete Signatur darf nicht akzeptiert werden.
        $this->webhook($this->event('checkout.session.completed', $order->provider_reference), '')->assertStatus(400);

        $this->assertSame(0, $user->fresh()->pixel_credits);
    }

    public static function emptySecrets(): array
    {
        return ['null' => [null], 'empty' => ['']];
    }

    public function test_webhook_route_needs_no_csrf_token_or_login(): void
    {
        [, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order);

        $this->webhook($this->event('checkout.session.completed', $order->provider_reference))->assertOk();
    }

    public function test_webhook_only_accepts_post(): void
    {
        $this->get('/webhooks/stripe')->assertStatus(405);
    }

    public function test_webhook_survives_stripe_api_outage_and_can_be_retried(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $event = $this->event('checkout.session.completed', $order->provider_reference);

        $this->sessionResponse = [];
        $this->sessionStatusCode = 500;
        $this->webhook($event)->assertOk();
        $this->assertSame(Order::PENDING, $order->fresh()->status);

        $this->sessionStatusCode = 200;
        $this->sessionResponse = $this->stripeSession($order);
        $this->webhook($event)->assertOk();

        $this->assertSame(Order::PAID, $order->fresh()->status);
        $this->assertSame(250, $user->fresh()->pixel_credits);
    }

    // ---------------------------------------------------------------- Ende-zu-Ende / Anzeige

    public function test_full_flow_from_checkout_to_credits(): void
    {
        $this->fakeStripe('cs_test_flow');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/shop/checkout',             ['product' => 'pixels_1000', 'provider' => 'stripe'])
            ->assertOk();
        $order = Order::firstOrFail();
        $this->sessionResponse = $this->stripeSession($order, ['id' => 'cs_test_flow']);

                $this->actingAs($user)->get(route('shop.return', $order))->assertRedirect(route('home'));

                    $this->assertSame(1000, $user->fresh()->pixel_credits);
        $this->assertSame(Order::PAID, $order->fresh()->status);
    }

    public function test_profile_lists_the_stripe_purchase(): void
    {
        [$user, $order] = $this->pendingOrder('pixels_100');
        $this->sessionResponse = $this->stripeSession($order);
        $this->actingAs($user)->get(route('shop.return', $order));

        $this->actingAs($user)->get('/de/profile')
            ->assertOk()
            ->assertSee('250 Extra-Pixel')
            ->assertSee('5,00 €');
    }

    // ---------------------------------------------------------------- Helfer

    private function fakeStripe(string $createdSessionId = 'cs_test_created'): void
    {
        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => $createdSessionId,
                'url' => 'https://checkout.stripe.com/c/pay/'.$createdSessionId,
            ]),
            'api.stripe.com/v1/checkout/sessions/*' => fn () => Http::response($this->sessionResponse ?? [], $this->sessionStatusCode),
        ]);
    }

    /** @return array{0: User, 1: Order} */
    private function pendingOrder(string $product, array $userAttributes = [], ?string $reference = null): array
    {
        $this->fakeStripe();
        $user = User::factory()->create($userAttributes);

        return [$user, $this->makeOrder($user, $product, $reference ?? 'cs_test_'.Str::random(12))];
    }

    private function makeOrder(User $user, string $product, ?string $reference, string $provider = 'stripe'): Order
    {
        $definition = config('payments.products.'.$product);

        return Order::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'product' => $product,
            'provider' => $provider,
            'provider_reference' => $reference,
            'status' => Order::PENDING,
            'amount_cents' => $definition['price_cents'],
            'currency' => config('payments.currency'),
            'meta' => array_filter(['axis' => $definition['axis'] ?? null, 'amount' => $definition['amount']]),
        ]);
    }

    /** Eine zur Bestellung passende, bezahlte Stripe-Checkout-Session. */
    private function stripeSession(Order $order, array $override = []): array
    {
        return array_merge([
            'id' => $order->provider_reference,
            'object' => 'checkout.session',
            'client_reference_id' => $order->uuid,
            'metadata' => ['order_uuid' => $order->uuid],
            'amount_total' => $order->amount_cents,
            'currency' => 'eur',
            'mode' => 'payment',
            'livemode' => false,
            'status' => 'complete',
            'payment_status' => 'paid',
        ], $override);
    }

    private function event(string $type, mixed $reference, array $objectExtra = []): array
    {
        return [
            'id' => 'evt_'.Str::random(10),
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => array_merge(['id' => $reference, 'object' => 'checkout.session'], $objectExtra)],
        ];
    }

    private function webhook(array $event, string $secret = self::WEBHOOK_SECRET, ?int $timestamp = null): TestResponse
    {
        return $this->rawWebhook(json_encode($event), $secret, $timestamp);
    }

    private function rawWebhook(string $body, string $secret = self::WEBHOOK_SECRET, ?int $timestamp = null): TestResponse
    {
        $timestamp ??= time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        return $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }
}
