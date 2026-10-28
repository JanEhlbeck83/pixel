@extends('legal.layout')

@section('title', 'Terms of Use')

@section('content')
    <h2>1. Scope</h2>
    <p>
        These terms apply to the use of Pixel Chaos, operated by <x-legal-value key="name" />. Using the wall is
        free of charge. Additional services (pixel packs, wall expansion) are chargeable.
    </p>

    <h2>2. Rules of play</h2>
    <ul>
        <li>Every pixel can be overwritten by anyone, including yours. There is no entitlement to any pixel or image being preserved.</li>
        <li>Newly placed pixels are briefly locked (10 minutes).</li>
        <li>Guests receive 50 pixels once. With an account, one pixel regenerates every 30 seconds, up to a maximum stock of 50.</li>
        <li>Prohibited are hateful imagery, unconstitutional or banned symbols, insults, pornography, depictions of violence and content that infringes third-party rights.</li>
        <li>Automated access, bots and scripts used to circumvent the pixel limits are not permitted.</li>
    </ul>

    <h2>3. Account</h2>
    <p>
        You must provide truthful information and keep your login credentials secret. One account per person is
        intended. We may suspend or delete accounts if you breach these terms. You can delete your account at
        any time, see <a href="{{ route('legal.data-deletion') }}">Delete data</a>.
    </p>

    <h2>4. Content and rights</h2>
    <p>
        The wall is created collectively. You grant us a non-exclusive, royalty-free right to display, store and
        reproduce the pixels you place within the scope of the service, and to show them in images and galleries
        of the wall. Pixel colours alone do not, as a rule, give rise to copyright in individual pixels. We may
        remove content that violates these terms or the law.
    </p>

    <h2>5. Paid services</h2>
    <p>
        Prices are displayed in the shop in euros, including statutory value added tax where applicable.
        Payment is made through the payment service providers offered there. Purchased pixel packs are credited
        to your account; a wall expansion is implemented after successful payment. A purchase requires an account.
    </p>

    <h2>6. Right of withdrawal for digital content</h2>
    <p>
        Consumers generally have a 14-day right of withdrawal for distance contracts. For digital content not
        supplied on a tangible medium (here: crediting of pixels and wall expansion), the right of withdrawal
        expires under Section 356(5) of the German Civil Code (BGB) once we have begun performance after you
        have expressly consented to our beginning performance before the withdrawal period has expired and have
        acknowledged that you thereby lose your right of withdrawal.
    </p>

    <h2>7. Availability and liability</h2>
    <p>
        We strive for uninterrupted operation but do not owe permanent availability. We are liable without
        limitation for intent and gross negligence, for injury to life, body and health, and under the Product
        Liability Act. In cases of slight negligence we are liable only for breach of essential contractual
        obligations and limited to the foreseeable damage typical for this type of contract.
    </p>

    <h2>8. Reporting violations</h2>
    <p>
        You can report unlawful content to <x-legal-value key="email" />. Please state the position (x/y) on
        the wall and the reason.
    </p>

    <h2>9. Final provisions</h2>
    <p>
        German law applies, excluding the UN Convention on Contracts for the International Sale of Goods.
        For consumers, mandatory consumer protection provisions of their country of residence remain unaffected.
        Should individual provisions be invalid, the remainder stays effective.
    </p>
    <p>
        The German version of these terms is legally binding. The English and Spanish versions are translations
        provided for information only. In matters of interpretation, the German version prevails.
    </p>

    <p>Further information: <a href="{{ route('legal.privacy') }}">Privacy Policy</a>, <a href="{{ route('legal.impressum') }}">Legal Notice</a>.</p>
@endsection

