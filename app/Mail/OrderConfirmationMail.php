<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\Locales;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Lang;

class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $emailLocale;

    public string $productTitle;

    public string $price;

    public string $termsUrl;

    public string $consentText;

    public array $seller;

    public array $labels;

    public function __construct(public Order $order)
    {
        $this->emailLocale = Locales::isSupported($order->withdrawal_consent_locale)
            ? $order->withdrawal_consent_locale
            : 'de';

        $product = config('payments.products.'.$order->product, []);
        $titleKey = match ($product['type'] ?? null) {
            'extend' => ($product['axis'] ?? 'x') === 'x'
                ? 'Leinwand nach rechts erweitern (+:amount px)'
                : 'Leinwand nach unten erweitern (+:amount px)',
            'pixels' => ':amount Extra-Pixel',
            default => $order->product,
        };
        $this->productTitle = Lang::get($titleKey, ['amount' => $product['amount'] ?? 0], $this->emailLocale);
        $this->price = $this->emailLocale === 'en'
            ? $order->currency.' '.number_format($order->amount_cents / 100, 2, '.', ',')
            : number_format($order->amount_cents / 100, 2, ',', '.').' '.$order->currency;
        $this->termsUrl = Locales::url($this->emailLocale, '/terms');
        $this->consentText = Lang::get(
            'Ich habe die Widerrufsbelehrung gelesen. Ich stimme ausdrücklich zu, dass PixRiot vor Ablauf der Widerrufsfrist mit der Bereitstellung der digitalen Inhalte beginnt. Mir ist bekannt, dass mein Widerrufsrecht mit Beginn der Bereitstellung erlischt.',
            [],
            $this->emailLocale,
        );
        $this->seller = [
            'name' => config('legal.name'),
            'street' => config('legal.street'),
            'zip_city' => config('legal.zip_city'),
            'country' => config('legal.country'),
            'email' => config('legal.email'),
        ];
        $this->labels = $this->copy();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: match ($this->emailLocale) {
                'en' => 'PixRiot order and contract confirmation',
                'es' => 'Confirmación del pedido y del contrato de PixRiot',
                default => 'PixRiot – Bestell- und Vertragsbestätigung',
            },
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.confirmation',
            text: 'emails.orders.confirmation-text',
            with: [
                'order' => $this->order,
                'locale' => $this->emailLocale,
                'productTitle' => $this->productTitle,
                'price' => $this->price,
                'termsUrl' => $this->termsUrl,
                'consentText' => $this->consentText,
                'seller' => $this->seller,
                'labels' => $this->labels,
            ],
        );
    }

    private function copy(): array
    {
        return match ($this->emailLocale) {
            'en' => [
                'title' => 'Order and contract confirmation',
                'intro' => 'We confirm your purchase and the declarations you made before payment.',
                'order' => 'Order',
                'date' => 'Date',
                'product' => 'Product',
                'amount' => 'Total paid',
                'payment' => 'Payment provider',
                'vat' => 'Small business under Section 19 of the German VAT Act (UStG): no VAT is charged or shown.',
                'consent' => 'Your declaration before payment',
                'terms' => 'Terms of Use and withdrawal policy',
                'seller' => 'Seller',
                'footer' => 'Please keep this email for your records.',
            ],
            'es' => [
                'title' => 'Confirmación del pedido y del contrato',
                'intro' => 'Confirmamos tu compra y las declaraciones que realizaste antes del pago.',
                'order' => 'Pedido',
                'date' => 'Fecha',
                'product' => 'Producto',
                'amount' => 'Total pagado',
                'payment' => 'Proveedor de pago',
                'vat' => 'Pequeña empresa según el § 19 de la Ley alemana del IVA (UStG): no se cobra ni se indica el IVA.',
                'consent' => 'Tu declaración antes del pago',
                'terms' => 'Condiciones de uso e información sobre el desistimiento',
                'seller' => 'Vendedor',
                'footer' => 'Conserva este correo electrónico.',
            ],
            default => [
                'title' => 'Bestell- und Vertragsbestätigung',
                'intro' => 'Wir bestätigen deinen Kauf und die vor der Zahlung abgegebenen Erklärungen.',
                'order' => 'Bestellung',
                'date' => 'Datum',
                'product' => 'Produkt',
                'amount' => 'Gesamtbetrag',
                'payment' => 'Zahlungsanbieter',
                'vat' => 'Kleinunternehmer im Sinne von § 19 UStG: Es wird keine Umsatzsteuer berechnet oder ausgewiesen.',
                'consent' => 'Deine Erklärung vor der Zahlung',
                'terms' => 'Nutzungsbedingungen und Widerrufsinformationen',
                'seller' => 'Verkäufer',
                'footer' => 'Bitte bewahre diese E-Mail für deine Unterlagen auf.',
            ],
        };
    }
}
