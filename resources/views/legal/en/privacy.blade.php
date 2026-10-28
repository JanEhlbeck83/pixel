@extends('legal.layout')

@section('title', 'Privacy Policy')

@section('content')
    <h2>1. Controller</h2>
    <p>
        <x-legal-value key="name" />, <x-legal-value key="street" />, <x-legal-value key="zip_city" />,
        {{ config('legal.country') }}<br>
        Email: <x-legal-value key="email" />
    </p>

    <h2>2. Overview</h2>
    <p>
        Pixel Chaos is a shared pixel wall. We process personal data only to the extent necessary for operation,
        accounts, payments and security. The legal basis is the GDPR.
    </p>

    <h2>3. Hosting and server log data</h2>
    <p>
        When you visit the site, the server processes technically necessary data (IP address, date and time,
        requested address, browser details). This serves secure and stable operation
        (Art. 6(1)(f) GDPR). Hosting: <x-legal-value key="hosting" />.
    </p>

    <h2>4. Cookies and local storage</h2>
    <p>
        We only set technically necessary cookies: a session cookie, a protection cookie against cross-site
        attacks (CSRF/XSRF) and, if you use “stay logged in”, a login cookie. After a deliberate choice of
        language, the functional cookie “pixel_locale” is added (see section 15). In addition, your browser
        remembers in local storage that you have acknowledged the cookie notice and the rules of play. This
        information does not leave your browser. The legal basis is Section 25(2) no. 2 TDDDG and
        Art. 6(1)(f) GDPR. We do not use tracking or advertising cookies.
    </p>

    <h2>5. Playing as a guest</h2>
    <p>
        Guests may place 50 pixels once. For this we store a hash (SHA-256) of your IP address together with the
        number of pixels placed. The IP address itself is not stored for this purpose
        (Art. 6(1)(f) GDPR, protection against abuse).
    </p>

    <h2>6. Account and login</h2>
    <p>
        When you register, we store your name, email address and a password stored in encrypted form (hash), as
        well as the times of your activity and your pixel stock. We send you emails to confirm your address and
        to reset your password (Art. 6(1)(b) GDPR).
    </p>

    <h2>7. Login with Facebook or Google</h2>
    <p>
        You can log in with Facebook or Google. This is voluntary. On your first login we create an account
        automatically.
    </p>
    <h3>Facebook</h3>
    <p>
        The provider of Facebook Login is Meta Platforms Ireland Limited, Merrion Road, Dublin 4, D04 X2K5,
        Ireland. If you choose “Facebook”, your browser redirects you to Facebook. In doing so, Meta receives
        technical data (including your IP address) and the information that you wish to log in to Pixel Chaos.
        After your consent, Facebook transmits to us your <strong>Facebook ID</strong>, your <strong>name</strong>
        and your <strong>email address</strong>. We store this information (ID, name, email) to maintain your
        account. We do not publish anything on Facebook and do not read friends lists, posts or other content.
        The legal basis is your consent (Art. 6(1)(a) GDPR) and performance of the contract (point (b)). More
        information in <a href="https://www.facebook.com/privacy/policy/" target="_blank" rel="noopener noreferrer">Meta’s Privacy Policy</a>.
        You can remove the link at any time in your Facebook settings under “Apps and websites”.
        See also the <a href="{{ route('legal.data-deletion') }}">data deletion instructions</a>.
    </p>
    <h3>Google</h3>
    <p>
        The provider is Google Ireland Limited, Gordon House, Barrow Street, Dublin 4, Ireland. We receive your
        Google ID, name and email address. The legal basis is Art. 6(1)(a) and (b) GDPR. Privacy information:
        <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">policies.google.com/privacy</a>.
    </p>
    <h3>Sharing on Facebook</h3>
    <p>
        The “Share on Facebook” button is a simple link. Only when you click it is Facebook opened and data
        transmitted to Meta. Merely visiting our site does not result in any transmission to Facebook.
    </p>
    <p>
        Meta and Google may also process data in third countries (e.g. the USA). Both companies are certified
        under the EU-US Data Privacy Framework.
    </p>

    <h2>8. Pixels, activity history and gallery</h2>
    <p>
        When you place a pixel, we store position, colour, your user reference and the time. An activity history
        (“who did what”) and images (snapshots) of the wall publicly show the result. Your name becomes publicly
        visible where it is displayed. The legal basis is Art. 6(1)(b) GDPR (use of the wall). If you delete
        your account, the link to you is removed from pixels and activities. The pixels themselves remain as an
        anonymous image of the wall.
    </p>

    <h2>9. Feedback</h2>
    <p>
        In the feedback form we store your message, your optional email address and, if you are logged in, your
        account. We use this only to handle your message (Art. 6(1)(f) GDPR).
    </p>

    <h2>10. Purchases and payments</h2>
    <p>
        You can buy pixel packs and wall expansions. We store order number, product, amount, payment provider,
        payment reference, status and timestamps. Payment is handled by one of the following service providers,
        depending on your choice:
    </p>
    <ul>
        <li>Stripe Payments Europe Ltd., Dublin, Ireland (<a href="https://stripe.com/privacy" target="_blank" rel="noopener noreferrer">privacy</a>)</li>
        <li>PayPal (Europe) S.à r.l. et Cie, S.C.A., Luxembourg (<a href="https://www.paypal.com/uk/legalhub/privacy-full" target="_blank" rel="noopener noreferrer">privacy</a>)</li>
        <li>Mollie B.V., Amsterdam, Netherlands (<a href="https://www.mollie.com/privacy" target="_blank" rel="noopener noreferrer">privacy</a>)</li>
    </ul>
    <p>
        We do not enter or store your payment data (e.g. card number) ourselves; it goes directly to the
        provider. The legal basis is Art. 6(1)(b) GDPR. We retain order data for up to ten years because of tax
        and commercial-law obligations (Art. 6(1)(c) GDPR). When you delete your account, the order is detached
        from the account but retained as an accounting record.
    </p>

    <h2>11. Fonts</h2>
    <p>
        On the login and account pages we load fonts from the provider Bunny Fonts (BunnyWay d.o.o., Slovenia).
        Your IP address is transmitted to Bunny in the process. Bunny is a privacy-friendly service in the EU
        that keeps no logs for advertising purposes (Art. 6(1)(f) GDPR).
    </p>

    <h2>12. Storage period</h2>
    <p>
        We delete data as soon as the purpose ceases to apply. We delete account data when you delete your
        account. Statutory retention obligations remain unaffected.
    </p>

    <h2>13. Your rights</h2>
    <p>
        You have the right of access (Art. 15), rectification (Art. 16), erasure (Art. 17), restriction
        (Art. 18), data portability (Art. 20) and objection (Art. 21 GDPR). You may withdraw any consent given at
        any time with effect for the future. To do so, write to us at <x-legal-value key="email" />. You may also
        lodge a complaint with a data protection supervisory authority, e.g. the authority in your German federal
        state or in your place of habitual residence. If you live in Spain, you can contact the Spanish data
        protection authority, the
        <a href="https://www.aepd.es" target="_blank" rel="noopener noreferrer">Agencia Española de Protección de Datos (AEPD)</a>.
    </p>

    <h2>14. Deleting your account yourself</h2>
    <p>
        When logged in, you can delete your account yourself at any time under
        <a href="{{ route('profile.edit') }}">Profile</a>. Alternatively, see
        <a href="{{ route('legal.data-deletion') }}">Delete data</a>.
    </p>

    <h2>15. Language selection, automatic redirection and language cookie</h2>
    <p>
        Pixel Chaos is available in English (without a path prefix, e.g. “/”), German (“/de”) and Spanish
        (“/es”). The language is determined by the address (URL).
    </p>
    <p>
        When you first visit the home page, we automatically redirect you to the appropriate language. To do
        this, we determine the country from your IP address using a <strong>local database</strong> (DB-IP
        “IP to Country Lite”). Your IP address is <strong>not passed on to third parties</strong> and
        <strong>not stored</strong> for this purpose; it is processed only transiently in working memory. If no
        match is possible, we fall back to your browser’s Accept-Language header. Bots and search engine
        crawlers are not redirected. The legal basis is Art. 6(1)(f) GDPR; our legitimate interest is a
        user-friendly presentation in your language. You can change the language at any time using the language
        switcher. This also allows you to object to the redirection (Art. 21 GDPR); your choice will then be
        respected.
    </p>
    <p>
        When you deliberately choose a language, we set the functional cookie <code>pixel_locale</code>
        (duration: 1 year, HttpOnly, SameSite=Lax) and additionally store the language in your session. The
        cookie is set only after this deliberate choice, serves solely to remember your language, and is
        technically necessary within the meaning of Section 25(2) no. 2 TDDDG. No consent is required for it.
    </p>

    <h2>16. Attribution</h2>
    <p>
        IP Geolocation by <a href="https://db-ip.com" target="_blank" rel="noopener noreferrer">DB-IP.com</a>
        (licence: <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener noreferrer">CC BY 4.0</a>).
    </p>

    <h2>17. Governing version</h2>
    <p>
        The German version of this privacy policy is legally binding. The English and Spanish versions are
        translations provided for information only.
    </p>

    <h2>18. Changes</h2>
    <p>We amend this policy when our offering or the legal situation changes.</p>
@endsection

