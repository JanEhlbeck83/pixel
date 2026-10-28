<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\HtmlString;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Passwort-Reset-Mail in der Sprache der Anfrage (Nutzer haben keine eigene Sprachspalte). */
    protected static function booted(): void
    {
        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            return (new MailMessage)
                ->subject(__('Passwort zurücksetzen – :app', ['app' => config('app.name')]))
                ->greeting(__('Hallo!'))
                ->line(__('Du erhältst diese E-Mail, weil wir eine Anfrage zum Zurücksetzen deines Passworts erhalten haben.'))
                ->action(__('Passwort zurücksetzen'), $url)
                ->line(__('Dieser Link zum Zurücksetzen läuft in :count Minuten ab.', ['count' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire')]))
                ->line(__('Wenn du kein neues Passwort angefordert hast, ist keine weitere Aktion erforderlich.'))
                ->salutation(new HtmlString(e(__('Viele Grüße,')).'<br>'.e(config('app.name'))));
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'free_pixels_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
