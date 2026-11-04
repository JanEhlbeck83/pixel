@extends('legal.layout')

@section('title', 'Frequently asked questions (FAQ)')

@php($max = number_format((int) config('payments.limits.max_credits'), 0, '.', ','))

@section('content')
    <div class="faq">
        <h2>Pixels</h2>

        <details open>
            <summary>How many pixels can I place for free?</summary>
            <p>As a guest you get 50 pixels once. With a free account you get a stock of up to 50 pixels that refills by 1 every 30 seconds.</p>
        </details>

        <details>
            <summary>What happens when my free stock is empty?</summary>
            <p>One pixel regenerates every 30 seconds. You can wait, or buy extra pixels in the shop and carry on right away.</p>
        </details>

        <details>
            <summary>What are extra pixels?</summary>
            <p>Extra pixels are pixels you bought. They are stored separately from your free stock, never expire and are available immediately.</p>
        </details>

        <details>
            <summary>Which pixels are used first – free or purchased?</summary>
            <p>Always the free stock first. Extra pixels are only used once it is empty. That way your free stock starts refilling right away and you never waste regeneration while using extra pixels.</p>
        </details>

        <details>
            <summary>How do I read the counter at the bottom right?</summary>
            <p>The large number (e.g. <strong>50/50</strong>) is your free stock: currently available / maximum. The small yellow number below (e.g. <strong>+1,000</strong>) is your purchased extra pixels.</p>
        </details>

        <details>
            <summary>Is there an upper limit for extra pixels?</summary>
            <p>Yes, you can own at most {{ $max }} extra pixels. Packs that would take you over the limit are greyed out in the shop. Use some pixels, then you can buy more.</p>
        </details>

        <details>
            <summary>What if I reach the limit while paying?</summary>
            <p>You still get the purchased pixels credited – you lose nothing.</p>
        </details>

        <h2>The wall</h2>

        <details>
            <summary>Can my pixel be overwritten?</summary>
            <p>Yes, every pixel can be overwritten by anyone. Newly placed pixels are locked for others for 10 minutes though, so you can defend them briefly. You can keep changing the pixel you just placed during that time (each change costs one pixel from your stock). On a locked pixel you see its coordinates and when it becomes free again.</p>
        </details>

        <details>
            <summary>Why does my pixel appear in the background image later?</summary>
            <p>Your pixel is saved immediately and visible to everyone viewing that area. The large background image of the wall is assembled from tiles that are rebuilt every 10 minutes, so changed pixels and wall expansions show up there with a delay of up to 10 minutes.</p>
        </details>

        <details>
            <summary>What does the wall expansion do?</summary>
            <p>An expansion makes the wall grow to the right or downwards, and the new space is available to all players.</p>
        </details>

        <h2>Account, payment and data</h2>

        <details>
            <summary>Do I need an account to buy?</summary>
            <p>Yes. That way your purchases are safely stored in your profile. The account is free (email, Google or Facebook).</p>
        </details>

        <details>
            <summary>Can I withdraw from a purchase?</summary>
            <p>For pixel credits, the right of withdrawal may expire when performance begins after your express consent and acknowledgment. For a wall expansion, it may expire only after the service has been fully performed. Before payment, we ask for the declarations required by law. Details are in the <a href="{{ route('legal.terms') }}#withdrawal">Terms of Use</a>.</p>
        </details>

        <details>
            <summary>How do I delete my account?</summary>
            <p>See <a href="{{ route('legal.data-deletion') }}">Delete data</a>. Extra pixels you have not used yet expire in the process.</p>
        </details>

        <details>
            <summary>How do I report unlawful content?</summary>
            <p>Email <x-legal-value key="email" /> with the position (x/y) on the wall and the reason.</p>
        </details>
    </div>

    <p>More information: <a href="{{ route('legal.terms') }}">Terms of Use</a>, <a href="{{ route('legal.privacy') }}">Privacy Policy</a>.</p>
@endsection
