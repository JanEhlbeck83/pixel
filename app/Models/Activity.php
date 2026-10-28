<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'type',
        'params',
        'description',
    ];

    protected function casts(): array
    {
        return ['params' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Beschreibung in der aktuellen Sprache; Altzeilen ohne type werden per Muster zugeordnet. */
    public function localizedDescription(): string
    {
        $params = $this->params ?? [];

        if ($this->type !== null) {
            $name = (string) ($params['name'] ?? '');
            $count = (int) ($params['count'] ?? 0);

            $localized = match ($this->type) {
                'pixel_set' => __(':name hat ein Pixel gesetzt.', ['name' => $name]),
                'pixels_simulated' => __(':name hat 100 Pixel im Simulationslauf gesetzt.', ['name' => $name]),
                'pixels_scattered' => __(':name hat :count zufällig verteilte Pixel geändert.', ['name' => $name, 'count' => $count]),
                'user_deleted' => __('Ein gelöschter Nutzer hat Pixel gesetzt.'),
                default => null,
            };

            if ($localized !== null) {
                return $localized;
            }
        }

        $text = (string) $this->description;

        if ($text === 'Ein gelöschter Nutzer hat Pixel gesetzt.') {
            return __('Ein gelöschter Nutzer hat Pixel gesetzt.');
        }
        if (preg_match('/^(.*) hat ein Pixel gesetzt\.$/su', $text, $m)) {
            return __(':name hat ein Pixel gesetzt.', ['name' => $m[1]]);
        }
        if (preg_match('/^(.*) hat 100 Pixel im Simulationslauf gesetzt\.$/su', $text, $m)) {
            return __(':name hat 100 Pixel im Simulationslauf gesetzt.', ['name' => $m[1]]);
        }
        if (preg_match('/^(.*) hat (\d+) zufällig verteilte Pixel geändert\.$/su', $text, $m)) {
            return __(':name hat :count zufällig verteilte Pixel geändert.', ['name' => $m[1], 'count' => $m[2]]);
        }

        return $text;
    }
}
