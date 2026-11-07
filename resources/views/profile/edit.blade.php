<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>PixRiot | {{ __('Mein Profil') }}</title>
        <x-favicons />
        <x-locale-head />
        @vite(['resources/css/app.css'])
    </head>
    <body class="profile-page menu-page">
        <div class="pixel-shell">
            @include('partials.site-menu')
            <header class="page-heading">
                <div class="brand">
                    <small>{{ __('Mein Konto') }}</small>
                    <a class="brand-wordmark" href="{{ route('home') }}" aria-label="{{ __('PixRiot – zur Wand') }}">Pix<span>Riot</span></a>
                </div>
            </header>

            <main class="profile-main">
                <h1 class="profile-title">{{ __('Hallo, :name', ['name' => $user->name]) }}</h1>
                <section class="profile-card">
                    <h2>{{ __('Meine Pixelgalerie') }}</h2>
                    <p>{{ __('Veröffentlichte Entwürfe bleiben hier als eigene Bilder erhalten. Du entscheidest, welche davon öffentlich sichtbar sind.') }}</p>
                    <a class="primary-button" href="{{ route('artworks.index', $user) }}">{{ __('Meine Pixelgalerie') }}</a>
                    <a class="secondary-button" href="{{ route('home') }}#draft">{{ __('Am Entwurf weitermalen') }}</a>
                </section>

                @if (session('status') === 'profile-updated')
                    <p class="profile-flash" role="status">{{ __('Profil gespeichert.') }}</p>
                @elseif (session('status') === 'password-updated')
                    <p class="profile-flash" role="status">{{ __('Passwort geändert.') }}</p>
                @elseif (session('status') === 'project-notifications-updated')
                    <p class="profile-flash" role="status">{{ __('Projekteinstellungen gespeichert.') }}</p>
                @endif

                @if ($communityMembership)
                    <section class="profile-card profile-community-group" aria-labelledby="profileCommunityGroupTitle">
                        <div class="community-my-group-emblem">
                            @if ($communityMembership->group->emblem)
                                <img class="community-group-emblem" src="{{ asset('images/community/emblems/'.$communityMembership->group->emblem.'.svg') }}" alt="{{ __('Emblem der Gruppe :name', ['name' => $communityMembership->group->name]) }}" width="88" height="88">
                            @endif
                        </div>
                        <div class="community-my-group-content">
                            <span class="community-eyebrow">{{ __('Deine Gruppe') }}</span>
                            <h2 id="profileCommunityGroupTitle"><a href="{{ route('community.groups.show', $communityMembership->group) }}">{{ $communityMembership->group->name }}</a></h2>
                            <p>{{ $communityMembership->group->description }}</p>
                            <div class="community-my-group-meta">
                                <span>{{ __('In den letzten 5 Minuten aktiv: :count', ['count' => $activeGroupMembersCount]) }}</span>
                                @if ($communityMembership->last_active_at)
                                    <span>{{ __('Zuletzt in der Gruppe aktiv: :time', ['time' => $communityMembership->last_active_at->diffForHumans()]) }}</span>
                                @endif
                            </div>
                            <a class="community-gallery-link" href="{{ route('community.groups.show', $communityMembership->group) }}">{{ __('Zur Gruppe') }}</a>
                            <form method="POST" action="{{ route('community.groups.leave') }}" onsubmit="return confirm('{{ __('Möchtest du die Gruppe wirklich verlassen?') }}')">
                                @csrf
                                @method('DELETE')
                                <button class="secondary-button" type="submit">{{ __('Gruppe verlassen') }}</button>
                            </form>
                        </div>
                    </section>
                @endif

                <section class="profile-stats" aria-label="{{ __('Übersicht') }}">
                    <div class="profile-stat">
                        <span class="profile-stat-value">{{ $quota['free'] }} / {{ $quota['max_free'] }}</span>
                        <span class="profile-stat-label">{{ __('Kostenlose Pixel (+1 alle :seconds Sek., max. :max)', ['seconds' => $regenSeconds, 'max' => $quota['max_free']]) }}</span>
                    </div>
                    <div class="profile-stat">
                        <span class="profile-stat-value">{{ $user->pixel_credits }}</span>
                        <span class="profile-stat-label">{{ __('Gekaufte Extra-Pixel') }}</span>
                    </div>
                    <div class="profile-stat">
                        <span class="profile-stat-value">
                            @if ($nextFreePixelIn > 0)
                                {{ gmdate('i:s', $nextFreePixelIn) }} min
                            @else
                                {{ __('Voll') }}
                            @endif
                        </span>
                        <span class="profile-stat-label">{{ __('Nächstes kostenloses Pixel') }}</span>
                    </div>
                    <div class="profile-stat">
                        <span class="profile-stat-value">{{ $orders->where('status', 'paid')->count() }}</span>
                        <span class="profile-stat-label">{{ __('Bezahlte Käufe') }}</span>
                    </div>
                </section>

                <section class="profile-card" aria-labelledby="ordersTitle">
                    <h2 id="ordersTitle">{{ __('Käufe & Zahlungen') }}</h2>
                    @if ($orders->isEmpty())
                        <p class="profile-muted">{{ __('Noch keine Käufe. Auf der Wand findest du Extra-Pixel und die Leinwand-Erweiterung.') }}</p>
                    @else
                        <div class="profile-table-wrap">
                            <table class="profile-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Datum') }}</th>
                                        <th>{{ __('Artikel') }}</th>
                                        <th>{{ __('Anbieter') }}</th>
                                        <th>{{ __('Betrag') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th>{{ __('Referenz') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($orders as $order)
                                        <tr>
                                            <td>{{ $order->created_at->timezone(config('app.timezone'))->format(__('d.m.Y H:i')) }}</td>
                                            <td>{{ $order->title() }}</td>
                                            <td>{{ ucfirst($order->provider) }}</td>
                                            <td>{{ $order->priceFormatted() }}</td>
                                            <td>
                                                <span class="order-badge order-{{ $order->status }}">
                                                    {{ ['paid' => __('Bezahlt'), 'pending' => __('Offen'), 'cancelled' => __('Abgebrochen'), 'failed' => __('Fehlgeschlagen')][$order->status] ?? $order->status }}
                                                </span>
                                            </td>
                                            <td class="profile-ref">{{ $order->provider_reference ?? '–' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>

                <section class="profile-card" aria-labelledby="projectNotificationsTitle">
                    <h2 id="projectNotificationsTitle">{{ __('Projekteinstellungen & Benachrichtigungen') }}</h2>
                    <p class="profile-muted">{{ __('Erhalte eine E-Mail, wenn ein Gruppenprojekt die Phase wechselt, zum Beispiel von der Vorschlagseinreichung zur Abstimmung. Wenn du in den letzten 5 Minuten auf PixRiot aktiv warst, wird keine E-Mail gesendet.') }}</p>
                    <form method="POST" action="{{ route('profile.project-notifications.update') }}" class="profile-form">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="project_phase_notifications" value="0">
                        <label class="check-row"><input type="checkbox" name="project_phase_notifications" value="1" @checked(old('project_phase_notifications', $user->project_phase_notifications))> {{ __('E-Mails zu Projektphasen erhalten') }}</label>
                        @error('project_phase_notifications') <p class="profile-error">{{ $message }}</p> @enderror
                        <button class="primary-button" type="submit">{{ __('Speichern') }}</button>
                    </form>
                </section>

                <div class="profile-grid">
                    <section class="profile-card" aria-labelledby="accountTitle">
                        <h2 id="accountTitle">{{ __('Benutzername & E-Mail') }}</h2>
                        <form method="POST" action="{{ route('profile.update') }}" class="profile-form">
                            @csrf
                            @method('PATCH')
                            <label for="name">{{ __('Benutzername') }}</label>
                            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="username">
                            @error('name') <p class="profile-error">{{ $message }}</p> @enderror

                            <label for="email">{{ __('E-Mail') }}</label>
                            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="email">
                            @error('email') <p class="profile-error">{{ $message }}</p> @enderror

                            <input type="hidden" name="newsletter" value="0">
                            <label class="check-row"><input type="checkbox" name="newsletter" value="1" @checked(old('newsletter', $user->newsletter_subscribed_at !== null))> {{ __('PixRiot-Newsletter erhalten') }}</label>

                            <button type="submit" class="primary-button">{{ __('Speichern') }}</button>
                        </form>
                    </section>

                    <section class="profile-card" aria-labelledby="passwordTitle">
                        <h2 id="passwordTitle">{{ __('Passwort ändern') }}</h2>
                        <form method="POST" action="{{ route('password.update') }}" class="profile-form">
                            @csrf
                            @method('PUT')
                            <label for="current_password">{{ __('Aktuelles Passwort') }}</label>
                            <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                            @if ($errors->updatePassword->has('current_password')) <p class="profile-error">{{ $errors->updatePassword->first('current_password') }}</p> @endif

                            <label for="password">{{ __('Neues Passwort') }}</label>
                            <input id="password" name="password" type="password" autocomplete="new-password" required>
                            @if ($errors->updatePassword->has('password')) <p class="profile-error">{{ $errors->updatePassword->first('password') }}</p> @endif

                            <label for="password_confirmation">{{ __('Neues Passwort wiederholen') }}</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>

                            <button type="submit" class="primary-button">{{ __('Passwort ändern') }}</button>
                        </form>
                    </section>
                </div>

                <section class="profile-card profile-danger" aria-labelledby="deleteTitle">
                    <h2 id="deleteTitle">{{ __('Konto löschen') }}</h2>
                    <details {{ $errors->userDeletion->isNotEmpty() ? 'open' : '' }}>
                        <summary class="secondary-button">{{ __('Konto unwiderruflich löschen …') }}</summary>
                        <form method="POST" action="{{ route('profile.destroy') }}" class="profile-form">
                            @csrf
                            @method('DELETE')
                            <p class="profile-muted">{{ __('Noch nicht verbrauchte Extra-Pixel verfallen. Zur Bestätigung dein Passwort eingeben:') }}</p>
                            <label for="delete_password">{{ __('Passwort') }}</label>
                            <input id="delete_password" name="password" type="password" autocomplete="current-password" required>
                            @if ($errors->userDeletion->has('password')) <p class="profile-error">{{ $errors->userDeletion->first('password') }}</p> @endif
                            <button type="submit" class="danger-solid-button">{{ __('Konto löschen') }}</button>
                        </form>
                    </details>
                </section>

                <form method="POST" action="{{ route('logout') }}" class="profile-logout">
                    @csrf
                    <button type="submit" class="secondary-button">{{ __('Abmelden') }}</button>
                </form>
            </main>
            @include('legal.partials.links')
        </div>
    </body>
</html>
