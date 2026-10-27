<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Pixel Chaos | Mein Profil</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="profile-page">
        <div class="pixel-shell">
            <header class="pixel-topbar">
                <div class="brand">
                    <small>Mein Konto</small>
                    <strong>PIXEL CHAOS</strong>
                </div>
                <div class="top-actions">
                    <a href="{{ route('wall') }}">Zur Wand</a>
                    <a href="{{ route('gallery') }}">Galerie</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit">Abmelden</button>
                    </form>
                </div>
            </header>

            <main class="profile-main">
                <h1 class="profile-title">Hallo, {{ $user->name }}</h1>

                @if (session('status') === 'profile-updated')
                    <p class="profile-flash" role="status">Profil gespeichert.</p>
                @elseif (session('status') === 'password-updated')
                    <p class="profile-flash" role="status">Passwort geändert.</p>
                @endif

                <section class="profile-stats" aria-label="Übersicht">
                    <div class="profile-stat">
                        <span class="profile-stat-value">{{ $quota['free'] }} / {{ $quota['max_free'] }}</span>
                        <span class="profile-stat-label">Kostenlose Pixel (+1 alle {{ $regenSeconds }} Sek., max. {{ $quota['max_free'] }})</span>
                    </div>
                    <div class="profile-stat">
                        <span class="profile-stat-value">{{ $user->pixel_credits }}</span>
                        <span class="profile-stat-label">Gekaufte Extra-Pixel</span>
                    </div>
                    <div class="profile-stat">
                        <span class="profile-stat-value">
                            @if ($nextFreePixelIn > 0)
                                {{ gmdate('i:s', $nextFreePixelIn) }} min
                            @else
                                Voll
                            @endif
                        </span>
                        <span class="profile-stat-label">Nächstes kostenloses Pixel</span>
                    </div>
                    <div class="profile-stat">
                        <span class="profile-stat-value">{{ $orders->where('status', 'paid')->count() }}</span>
                        <span class="profile-stat-label">Bezahlte Käufe</span>
                    </div>
                </section>

                <section class="profile-card" aria-labelledby="ordersTitle">
                    <h2 id="ordersTitle">Käufe &amp; Zahlungen</h2>
                    @if ($orders->isEmpty())
                        <p class="profile-muted">Noch keine Käufe. Auf der Wand findest du Extra-Pixel und die Wand-Erweiterung.</p>
                    @else
                        <div class="profile-table-wrap">
                            <table class="profile-table">
                                <thead>
                                    <tr>
                                        <th>Datum</th>
                                        <th>Artikel</th>
                                        <th>Anbieter</th>
                                        <th>Betrag</th>
                                        <th>Status</th>
                                        <th>Referenz</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($orders as $order)
                                        <tr>
                                            <td>{{ $order->created_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</td>
                                            <td>{{ $order->title() }}</td>
                                            <td>{{ ucfirst($order->provider) }}</td>
                                            <td>{{ $order->priceFormatted() }}</td>
                                            <td>
                                                <span class="order-badge order-{{ $order->status }}">
                                                    {{ ['paid' => 'Bezahlt', 'pending' => 'Offen', 'cancelled' => 'Abgebrochen', 'failed' => 'Fehlgeschlagen'][$order->status] ?? $order->status }}
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

                <div class="profile-grid">
                    <section class="profile-card" aria-labelledby="accountTitle">
                        <h2 id="accountTitle">Benutzername &amp; E-Mail</h2>
                        <form method="POST" action="{{ route('profile.update') }}" class="profile-form">
                            @csrf
                            @method('PATCH')
                            <label for="name">Benutzername</label>
                            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="username">
                            @error('name') <p class="profile-error">{{ $message }}</p> @enderror

                            <label for="email">E-Mail</label>
                            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="email">
                            @error('email') <p class="profile-error">{{ $message }}</p> @enderror

                            <button type="submit" class="primary-button">Speichern</button>
                        </form>
                    </section>

                    <section class="profile-card" aria-labelledby="passwordTitle">
                        <h2 id="passwordTitle">Passwort ändern</h2>
                        <form method="POST" action="{{ route('password.update') }}" class="profile-form">
                            @csrf
                            @method('PUT')
                            <label for="current_password">Aktuelles Passwort</label>
                            <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                            @if ($errors->updatePassword->has('current_password')) <p class="profile-error">{{ $errors->updatePassword->first('current_password') }}</p> @endif

                            <label for="password">Neues Passwort</label>
                            <input id="password" name="password" type="password" autocomplete="new-password" required>
                            @if ($errors->updatePassword->has('password')) <p class="profile-error">{{ $errors->updatePassword->first('password') }}</p> @endif

                            <label for="password_confirmation">Neues Passwort wiederholen</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>

                            <button type="submit" class="primary-button">Passwort ändern</button>
                        </form>
                    </section>
                </div>

                <section class="profile-card profile-danger" aria-labelledby="deleteTitle">
                    <h2 id="deleteTitle">Konto löschen</h2>
                    <details {{ $errors->userDeletion->isNotEmpty() ? 'open' : '' }}>
                        <summary class="secondary-button">Konto unwiderruflich löschen …</summary>
                        <form method="POST" action="{{ route('profile.destroy') }}" class="profile-form">
                            @csrf
                            @method('DELETE')
                            <p class="profile-muted">Noch nicht verbrauchte Extra-Pixel verfallen. Zur Bestätigung dein Passwort eingeben:</p>
                            <label for="delete_password">Passwort</label>
                            <input id="delete_password" name="password" type="password" autocomplete="current-password" required>
                            @if ($errors->userDeletion->has('password')) <p class="profile-error">{{ $errors->userDeletion->first('password') }}</p> @endif
                            <button type="submit" class="danger-solid-button">Konto löschen</button>
                        </form>
                    </details>
                </section>
            </main>
        </div>
    </body>
</html>
