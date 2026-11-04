@php($locked = $newsletter->exists && $newsletter->status !== 'draft')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>{{ __('PixRiot | Newsletter') }}</title>
        <x-favicons />
        <x-locale-head />
        <x-i18n-script />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="menu-page">
        <div class="pixel-shell">
            @include('partials.site-menu')
            <header class="page-heading">
                <div class="brand">
                    <small>Newsletter</small>
                    <strong class="brand-wordmark">Pix<span>Riot</span></strong>
                </div>
            </header>

            <main>
                <div class="panel">
                    <h3>{{ $newsletter->exists ? 'Newsletter #'.$newsletter->id : 'Neuer Newsletter' }}</h3>
                    @if (session('status'))
                        <p class="admin-flash" role="status">{{ session('status') }}</p>
                    @endif
                    @if ($locked)
                        <p class="report-meta">Dieser Newsletter wurde bereits gesendet und kann nicht mehr geändert werden.</p>
                    @endif
                    <p><a class="secondary-button" href="{{ route('admin.newsletters') }}">← Übersicht</a></p>

                    <div class="nl-admin-layout">
                        <div>
                            <form method="POST" enctype="multipart/form-data" class="profile-form"
                                  action="{{ $newsletter->exists ? route('admin.newsletters.update', $newsletter) : route('admin.newsletters.store') }}">
                                @csrf
                                @if ($newsletter->exists) @method('PUT') @endif
                                <fieldset @disabled($locked) style="border:0;padding:0;margin:0;display:grid;gap:8px;">
                                    <div class="nl-admin-grid2">
                                        <div>
                                            <label for="from_name">Absendername</label>
                                            <input id="from_name" name="from_name" type="text" value="{{ old('from_name', $newsletter->from_name) }}" required maxlength="120">
                                            @error('from_name') <p class="profile-error">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="from_email">Absender-E-Mail</label>
                                            <input id="from_email" name="from_email" type="email" value="{{ old('from_email', $newsletter->from_email) }}" required maxlength="255">
                                            @error('from_email') <p class="profile-error">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="reply_to">Antwort an (optional)</label>
                                            <input id="reply_to" name="reply_to" type="email" value="{{ old('reply_to', $newsletter->reply_to) }}" maxlength="255">
                                            @error('reply_to') <p class="profile-error">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="locale">Sprache (Footer)</label>
                                            <select id="locale" name="locale">
                                                @foreach (\App\Models\Newsletter::LOCALES as $code => $label)
                                                    <option value="{{ $code }}" @selected(old('locale', $newsletter->locale) === $code)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <label for="subject">Betreff</label>
                                    <input id="subject" name="subject" type="text" value="{{ old('subject', $newsletter->subject) }}" required maxlength="255">
                                    @error('subject') <p class="profile-error">{{ $message }}</p> @enderror

                                    <label for="preheader">Vorschautext (Preheader, optional)</label>
                                    <input id="preheader" name="preheader" type="text" value="{{ old('preheader', $newsletter->preheader) }}" maxlength="255">

                                    <label for="template">Vorlage</label>
                                    <select id="template" name="template">
                                        @foreach (\App\Models\Newsletter::TEMPLATES as $key => $label)
                                            <option value="{{ $key }}" @selected(old('template', $newsletter->template) === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>

                                    <label for="headline">Überschrift (optional)</label>
                                    <input id="headline" name="headline" type="text" value="{{ old('headline', $newsletter->headline) }}" maxlength="255">

                                    <label for="body">Text (Markdown: **fett**, *kursiv*, [Link](https://…), Listen mit „- “)</label>
                                    <textarea id="body" name="body" required maxlength="20000">{{ old('body', $newsletter->body) }}</textarea>
                                    @error('body') <p class="profile-error">{{ $message }}</p> @enderror

                                    <div class="nl-admin-grid2">
                                        <div>
                                            <label for="button_label">Button-Text (optional)</label>
                                            <input id="button_label" name="button_label" type="text" value="{{ old('button_label', $newsletter->button_label) }}" maxlength="60">
                                            @error('button_label') <p class="profile-error">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="button_url">Button-Link</label>
                                            <input id="button_url" name="button_url" type="url" value="{{ old('button_url', $newsletter->button_url) }}" maxlength="2000" placeholder="https://">
                                            @error('button_url') <p class="profile-error">{{ $message }}</p> @enderror
                                        </div>
                                    </div>

                                    @foreach (['header_image' => 'Kopfbild (oben, 1200 px breit empfohlen)', 'body_image' => 'Bild unter dem Text'] as $field => $label)
                                        <label for="{{ $field }}">{{ $label }}</label>
                                        @if ($newsletter->{$field})
                                            <img class="nl-thumb" src="{{ $newsletter->imageUrl($newsletter->{$field}) }}" alt="">
                                            <label class="check-row"><input type="checkbox" name="remove_{{ $field }}" value="1"> Bild entfernen</label>
                                        @endif
                                        <input id="{{ $field }}" name="{{ $field }}" type="file" accept="image/jpeg,image/png,image/gif,image/webp">
                                        @error($field) <p class="profile-error">{{ $message }}</p> @enderror
                                    @endforeach
                                    <p class="form-hint">JPG, PNG, GIF oder WebP, max. 4 MB.</p>

                                    @unless ($locked)
                                        <button type="submit" class="primary-button">Entwurf speichern</button>
                                    @endunless
                                </fieldset>
                            </form>

                            @if ($newsletter->exists)
                                <div class="nl-admin-actions">
                                    <form method="POST" action="{{ route('admin.newsletters.test', $newsletter) }}" class="profile-form">
                                        @csrf
                                        <label for="test_email">Testmail an (leer = deine Adresse)</label>
                                        <input id="test_email" name="test_email" type="email" placeholder="{{ auth()->user()->email }}">
                                        @error('test_email') <p class="profile-error">{{ $message }}</p> @enderror
                                        <button type="submit" class="secondary-button">Testmail senden</button>
                                    </form>
                                </div>

                                @unless ($locked)
                                    <div class="nl-admin-actions">
                                        <form method="POST" action="{{ route('admin.newsletters.send', $newsletter) }}" class="profile-form"
                                              onsubmit="return confirm('Newsletter jetzt an alle {{ $subscriberCount }} Abonnenten senden? Das kann nicht rückgängig gemacht werden.')">
                                            @csrf
                                            <label class="check-row"><input type="checkbox" name="confirm" value="1" required> Gespeicherten Entwurf an {{ $subscriberCount }} Abonnenten senden</label>
                                            <button type="submit" class="primary-button">Jetzt an alle senden</button>
                                        </form>
                                    </div>
                                @endunless

                                <div class="nl-admin-actions">
                                    <form method="POST" action="{{ route('admin.newsletters.destroy', $newsletter) }}" onsubmit="return confirm('Newsletter wirklich löschen?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="secondary-button">Löschen</button>
                                    </form>
                                </div>
                            @endif
                        </div>

                        <div>
                            @if ($newsletter->exists)
                                <iframe class="nl-preview-frame" title="Vorschau" src="{{ route('admin.newsletters.preview', $newsletter) }}"></iframe>
                                <p class="form-hint">Die Vorschau zeigt den zuletzt gespeicherten Stand.</p>
                            @else
                                <p class="report-meta">Nach dem ersten Speichern erscheint hier die Vorschau.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </main>
            @include('legal.partials.links')
        </div>
    </body>
</html>
