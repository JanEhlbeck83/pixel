@php
    $themes = [
        'neon' => ['page' => '#0f1117', 'card' => '#13171f', 'head' => '#13171f', 'brand' => '#ffffff', 'brandAccent' => '#ff2d95', 'text' => '#edf2f7', 'muted' => '#a3b1c4', 'accent' => '#ff2d95', 'btnText' => '#ffffff', 'line' => '#2a3140', 'headline' => '#ffffff'],
        'poster' => ['page' => '#fff1f8', 'card' => '#ffffff', 'head' => '#ff2d95', 'brand' => '#ffffff', 'brandAccent' => '#ffd93d', 'text' => '#1f2430', 'muted' => '#6b7385', 'accent' => '#ff2d95', 'btnText' => '#ffffff', 'line' => '#f3d3e4', 'headline' => '#ff2d95'],
        'clean' => ['page' => '#f4f5f8', 'card' => '#ffffff', 'head' => '#ffffff', 'brand' => '#0f1117', 'brandAccent' => '#ff2d95', 'text' => '#1f2430', 'muted' => '#6b7385', 'accent' => '#ff2d95', 'btnText' => '#ffffff', 'line' => '#e5e7ee', 'headline' => '#0f1117'],
    ];
    $c = $themes[$newsletter->template] ?? $themes['neon'];

    $footers = [
        'de' => ['unsub' => 'Newsletter abbestellen', 'why' => 'Du erhältst diese E-Mail, weil du dem PixRiot-Newsletter zugestimmt hast.', 'imprint' => 'Impressum', 'preview' => 'Vorschau – kein Abmelde-Link'],
        'en' => ['unsub' => 'Unsubscribe', 'why' => 'You are receiving this email because you subscribed to the PixRiot newsletter.', 'imprint' => 'Imprint', 'preview' => 'Preview – no unsubscribe link'],
        'es' => ['unsub' => 'Cancelar suscripción', 'why' => 'Recibes este correo porque te suscribiste al boletín de PixRiot.', 'imprint' => 'Aviso legal', 'preview' => 'Vista previa – sin enlace de baja'],
    ];
    $f = $footers[$newsletter->locale] ?? $footers['de'];
    $font = "-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
    $heroUrl = $newsletter->imageUrl($newsletter->header_image);
    $bodyImageUrl = $newsletter->imageUrl($newsletter->body_image);
    $prefix = $newsletter->locale === 'en' ? '' : $newsletter->locale.'/';
    $imprintUrl = url('/'.$prefix.'imprint');
    $homeUrl = url('/'.rtrim($prefix, '/'));
@endphp
<!DOCTYPE html>
<html lang="{{ $newsletter->locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $newsletter->subject }}</title>
    <style>
        .nl-body p { margin: 0 0 16px; }
        .nl-body a { color: {{ $c['accent'] }}; }
        .nl-body ul, .nl-body ol { margin: 0 0 16px; padding-left: 22px; }
        .nl-body h2, .nl-body h3 { margin: 24px 0 10px; color: {{ $c['headline'] }}; line-height: 1.25; }
        .nl-body img { max-width: 100%; height: auto; border-radius: 8px; }
        .nl-body blockquote { margin: 0 0 16px; padding-left: 14px; border-left: 3px solid {{ $c['accent'] }}; color: {{ $c['muted'] }}; }
        @media (max-width: 620px) { .nl-wrap { width: 100% !important; } .nl-pad { padding: 20px !important; } }
    </style>
</head>
<body style="margin:0;padding:0;background:{{ $c['page'] }};">
    @if (filled($newsletter->preheader))
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">{{ $newsletter->preheader }}</div>
    @endif
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $c['page'] }};">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" class="nl-wrap" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:100%;background:{{ $c['card'] }};border-radius:12px;overflow:hidden;font-family:{{ $font }};">
                    <tr>
                        <td align="{{ $newsletter->template === 'clean' ? 'left' : 'center' }}" class="nl-pad" style="background:{{ $c['head'] }};padding:22px 32px;border-bottom:4px solid {{ $c['accent'] }};">
                            <a href="{{ $homeUrl }}" style="text-decoration:none;font-size:28px;font-weight:800;letter-spacing:-0.5px;color:{{ $c['brand'] }};font-family:{{ $font }};">Pix<span style="color:{{ $c['brandAccent'] }};">Riot</span></a>
                        </td>
                    </tr>
                    @if ($heroUrl)
                        <tr>
                            <td style="padding:0;line-height:0;"><img src="{{ $heroUrl }}" alt="" width="600" style="display:block;width:100%;height:auto;border:0;"></td>
                        </tr>
                    @endif
                    <tr>
                        <td class="nl-pad" style="padding:32px;color:{{ $c['text'] }};font-size:16px;line-height:1.6;">
                            @if (filled($newsletter->headline))
                                <h1 style="margin:0 0 18px;font-size:28px;line-height:1.2;color:{{ $c['headline'] }};font-family:{{ $font }};">{{ $newsletter->headline }}</h1>
                            @endif
                            <div class="nl-body" style="color:{{ $c['text'] }};">{!! $newsletter->bodyHtml() !!}</div>

                            @if (filled($newsletter->button_label) && filled($newsletter->button_url))
                                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0;">
                                    <tr>
                                        <td style="background:{{ $c['accent'] }};border-radius:8px;">
                                            <a href="{{ $newsletter->button_url }}" style="display:inline-block;padding:14px 28px;font-size:16px;font-weight:700;color:{{ $c['btnText'] }};text-decoration:none;font-family:{{ $font }};">{{ $newsletter->button_label }}</a>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            @if ($bodyImageUrl)
                                <img src="{{ $bodyImageUrl }}" alt="" width="536" style="display:block;width:100%;height:auto;margin-top:24px;border:0;border-radius:8px;">
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="nl-pad" style="padding:20px 32px 28px;border-top:1px solid {{ $c['line'] }};color:{{ $c['muted'] }};font-size:12px;line-height:1.6;text-align:center;">
                            {{ $f['why'] }}<br>
                            @if ($unsubscribeUrl)
                                <a href="{{ $unsubscribeUrl }}" style="color:{{ $c['accent'] }};">{{ $f['unsub'] }}</a>
                            @else
                                <span>{{ $f['preview'] }}</span>
                            @endif
                            &nbsp;·&nbsp;
                            <a href="{{ $imprintUrl }}" style="color:{{ $c['accent'] }};">{{ $f['imprint'] }}</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
