<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Payments\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class ShopController extends Controller
{
    public function __construct(private Shop $shop) {}

    public function catalog(Request $request): JsonResponse
    {
        $products = collect(config('payments.products'))->map(fn ($product, $id) => [
            'id' => $id,
            'type' => $product['type'],
            'axis' => $product['axis'] ?? null,
            'amount' => $product['amount'],
            'price_cents' => $product['price_cents'],
            'price' => $this->euro($product['price_cents']),
        ])->values();

        $providers = collect($this->shop->providers())->map(fn ($provider) => [
            'key' => $provider->key(),
            'label' => $provider->label(),
            'mode' => $provider->modeLabel(),
        ])->values();

        return response()->json([
            'authenticated' => $request->user() !== null,
            'credits' => (int) ($request->user()?->pixel_credits ?? 0),
            'max_credits' => (int) config('payments.limits.max_credits'),
            'products' => $products,
            'providers' => $providers,
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product' => ['required', Rule::in(array_keys(config('payments.products')))],
            'provider' => ['required', Rule::in(array_keys($this->shop->providers()))],
            'withdrawal_consent' => ['required', 'accepted'],
        ]);

        $product = config('payments.products.'.$data['product']);
        $max = (int) config('payments.limits.max_credits');
        if (($product['type'] ?? null) === 'pixels' && (int) $request->user()->pixel_credits + $product['amount'] > $max) {
            return response()->json([
                'success' => false,
                'message' => __('Du kannst höchstens :max Extra-Pixel besitzen. Setze erst einige, dann kannst du nachkaufen.', ['max' => number_format($max, 0, ',', '.')]),
            ], 422);
        }

        $order = $this->shop->createOrder(
            $request->user(),
            $data['product'],
            $data['provider'],
            (bool) ($data['withdrawal_consent'] ?? false),
            app()->getLocale(),
        );

        try {
            $url = $this->shop->provider($order->provider)->createCheckout($order);
        } catch (Throwable $e) {
            Log::error('Checkout fehlgeschlagen', ['order' => $order->uuid, 'provider' => $order->provider, 'error' => $e->getMessage()]);
            $order->update(['status' => Order::FAILED]);

            return response()->json([
                'success' => false,
                'message' => __('Der Zahlungsanbieter ist gerade nicht erreichbar oder falsch konfiguriert. Bitte versuche es mit einem anderen Anbieter.'),
            ], 502);
        }

        return response()->json(['success' => true, 'redirect' => $url]);
    }

    public function return(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        try {
            $order = $this->shop->refresh($order, $request);
        } catch (Throwable $e) {
            Log::error('Zahlungsprüfung fehlgeschlagen', ['order' => $order->uuid, 'error' => $e->getMessage()]);
        }

        $flash = match ($order->status) {
            Order::PAID => ['ok', __('Danke! Zahlung erhalten – „:item“ ist freigeschaltet.', ['item' => $order->title()])],
            Order::PENDING => ['pending', __('Deine Zahlung wird noch verarbeitet. Sobald sie bestätigt ist, wird sie freigeschaltet (siehe Profil).')],
            default => ['error', __('Die Zahlung wurde nicht abgeschlossen. Es wurde nichts berechnet.')],
        };

        return redirect()->route('home')->with('purchase', ['status' => $flash[0], 'message' => $flash[1]]);
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);
        $this->shop->cancel($order);

        return redirect()->route('home')->with('purchase', [
            'status' => 'error',
            'message' => __('Zahlung abgebrochen. Es wurde nichts berechnet.'),
        ]);
    }

    public function fake(Request $request, Order $order): View
    {
        $this->authorizeFake($request, $order);

        return view('shop.fake', ['order' => $order]);
    }

    public function fakePay(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeFake($request, $order);

        if ($request->input('result') === 'paid') {
            $this->shop->fulfill($order);

            return redirect()->route('shop.return', $order);
        }

        return redirect()->route('shop.cancel', $order);
    }

    public function stripeWebhook(Request $request)
    {
        $secret = (string) config('payments.providers.stripe.webhook_secret');
        if ($secret === '' || ! $this->validStripeSignature($request, $secret)) {
            return response('invalid signature', 400);
        }

        $event = $request->json()->all();
        $reference = $event['data']['object']['id'] ?? null;
        if (in_array($event['type'] ?? '', [
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded',
            'checkout.session.async_payment_failed',
            'checkout.session.expired',
        ], true)) {
            $this->refreshByReference('stripe', is_string($reference) ? $reference : null);
        }

        return response('ok');
    }

    private function refreshByReference(string $provider, ?string $reference): void
    {
        $order = $reference
            ? Order::where('provider', $provider)->where('provider_reference', $reference)->first()
            : null;

        if ($order) {
            $this->shop->refresh($order);
        }
    }

private function validStripeSignature(Request $request, string $secret): bool
{
    $header = (string) $request->header('Stripe-Signature');
    $parts = [];

    foreach (explode(',', $header) as $part) {
        $pair = explode('=', trim($part), 2);

        if (count($pair) === 2) {
            $parts[$pair[0]][] = $pair[1];
        }
    }

    $timestamps = $parts['t'] ?? [];
    $signatures = $parts['v1'] ?? [];

    if (count($timestamps) !== 1 || ! ctype_digit($timestamps[0]) || $signatures === []) {
        return false;
    }

    $timestamp = $timestamps[0];

    if (abs(time() - (int) $timestamp) > 300) {
        return false;
    }

    $expected = hash_hmac(
        'sha256',
        $timestamp.'.'.$request->getContent(),
        $secret
    );

    foreach ($signatures as $signature) {
        if (preg_match('/^[a-f0-9]{64}$/i', $signature) && hash_equals($expected, $signature)) {
            return true;
        }
    }

    return false;
}
    private function authorizeOrder(Request $request, Order $order): void
    {
        abort_unless($order->user_id === $request->user()->id, 403);
    }

    private function authorizeFake(Request $request, Order $order): void
    {
        $this->authorizeOrder($request, $order);
        abort_unless($order->provider === 'fake' && $order->status === Order::PENDING && isset($this->shop->providers()['fake']), 404);
    }

    private function euro(int $cents): string
    {
        return app()->getLocale() === 'en'
            ? '€'.number_format($cents / 100, 2, '.', ',')
            : number_format($cents / 100, 2, ',', '.').' €';
    }
}
