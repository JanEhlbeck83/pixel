@extends('legal.layout')

@section('title', 'Terms of Use')

@section('content')
    <h2>1. Scope</h2>
    <p>
        These terms apply to the use of PixRiot, operated by <x-legal-value key="name" />. Using the wall is
        free of charge. Additional services (pixel packs, wall expansion) are chargeable.
    </p>

    <h2>2. Rules of play</h2>
    <ul>
        <li>Every pixel can be overwritten by anyone, including yours. There is no entitlement to any pixel or image being preserved.</li>
        <li>Newly placed pixels are briefly locked (10 minutes).</li>
        <li>Guests receive 50 pixels once. With an account, one pixel regenerates every 30 seconds, up to a maximum stock of 50.</li>
        <li>Purchased extra pixels are stored separately from the free stock and are only used once the free stock is empty. They do not expire. An account can hold at most 10,000 extra pixels; packs that would exceed this cannot be purchased.</li>
        <li>Community projects are created by group members together. Each person may belong to one group and found at most one group. New groups require admin approval.</li>
        <li>Project ideas are collected for 7 days and voted on by the group for 3 days. The project is created on a separate protected canvas and is transferred to the shared wall only after all project participants unanimously agree to finish.</li>
        <li>After publication, the reserved wall area is initially locked for the group for 7 weeks. At least 5 current group members may extend the reservation by 7 weeks at a time. If it is not extended in time, the area is released to everyone.</li>
        <li>The project chat is visible only to project participants. They can still read the history after publication, but cannot post new messages.</li>
        <li>Prohibited are hateful imagery, unconstitutional or banned symbols, insults, pornography, depictions of violence and content that infringes third-party rights.</li>
        <li>Automated access, bots and scripts used to circumvent the pixel limits are not permitted.</li>
    </ul>

    <h2>3. Community groups and projects</h2>
    <p>
        Group projects are planned and created collaboratively. Proposals and votes take place within the group;
        during the creation phase, only project participants can use the protected project canvas and chat.
        Publication transfers the created pixels to the reserved area of the shared wall and adds the project
        to the gallery. Reservations are time-limited and may be extended only by at least five current group
        members. Project participants may unanimously finish a project before the creation period ends.
    </p>

    <h2>4. Account</h2>
    <p>
        You must provide truthful information and keep your login credentials secret. One account per person is
        intended. We may suspend or delete accounts if you breach these terms. You can delete your account at
        any time, see <a href="{{ route('legal.data-deletion') }}">Delete data</a>.
    </p>

    <h2>5. Content and rights</h2>
    <p>
        The wall is created collectively. You grant us a non-exclusive, royalty-free right to display, store and
        reproduce the pixels you place within the scope of the service, and to show them in images and the Pixelart section
        of the wall. Pixel colours alone do not, as a rule, give rise to copyright in individual pixels. We may
        remove content that violates these terms or the law.
    </p>

    <h2>6. Paid services</h2>
    <p>
        Prices are displayed in euros as final prices. We apply the German small-business VAT scheme under
        Section 19 of the German VAT Act (UStG); therefore, no VAT is charged or shown. Payment is made through
        the payment service providers offered there. Purchased pixel packs are credited to your account; a wall
        expansion is implemented after successful payment. A purchase requires an account.
    </p>

    <h2 id="withdrawal">7. Right of withdrawal for digital content</h2>
    <p>
        Consumers generally have a 14-day right of withdrawal for distance contracts. Our products—pixel
        credits and wall expansions—are digital content not supplied on a tangible medium. Under Section
        356(6) of the German Civil Code (BGB), the right of withdrawal expires once we have begun performance,
        provided that you expressly consented to performance beginning before the withdrawal period expires
        and acknowledged that you understand your right of withdrawal expires when performance begins. We
        ask for this consent and acknowledgment before payment starts. You will then receive an order
        confirmation by email.
    </p>
    <p>
        This is not a blanket exclusion of all refund or warranty rights. Statutory rights relating to defects
        and other mandatory consumer rights remain unaffected. If the applicable statutory requirements for
        expiry of the right of withdrawal are not met, that right remains in force.
    </p>

    <h2>8. Availability and liability</h2>
    <p>
        We strive for uninterrupted operation but do not owe permanent availability. We are liable without
        limitation for intent and gross negligence, for injury to life, body and health, and under the Product
        Liability Act. In cases of slight negligence we are liable only for breach of essential contractual
        obligations and limited to the foreseeable damage typical for this type of contract.
    </p>

    <h2>9. Reporting violations</h2>
    <p>
        You can report unlawful content to <x-legal-value key="email" />. Please state the position (x/y) on
        the wall and the reason.
    </p>

    <h2>10. Final provisions</h2>
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
