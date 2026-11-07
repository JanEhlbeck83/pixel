<?php

namespace Database\Seeders;

use App\Models\CommunityGroup;
use Illuminate\Database\Seeder;

class CommunityStarterGroupsSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            [
                'emblem' => 'retro-riot',
                'name' => 'Retro Riot',
                'description' => 'Für 8-Bit-Klassiker, Arcade-Ästhetik und nostalgische Game-Welten. Pixel für Pixel lassen wir die goldene Ära der Spiele wieder aufleben.',
            ],
            [
                'emblem' => 'cozy-pixels',
                'name' => 'Cozy Pixels',
                'description' => 'Ein gemütlicher Ort für kleine Häuser, warme Landschaften und liebevolle Alltagsszenen. Gemeinsam gestalten wir Pixelart zum Wohlfühlen.',
            ],
            [
                'emblem' => 'neon-district',
                'name' => 'Neon District',
                'description' => 'Cyberpunk, leuchtende Reklamen und urbane Zukunftsvisionen: Erschafft gemeinsam eine Stadt, die niemals schläft.',
            ],
            [
                'emblem' => 'anime-atelier',
                'name' => 'Anime Atelier',
                'description' => 'Für Anime-inspirierte Pixelart, ausdrucksstarke Figuren und fantastische Welten. Gemeinsam erschaffen wir eigene Charaktere und farbenfrohe Szenen.',
            ],
        ];

        foreach ($groups as $group) {
            CommunityGroup::query()->firstOrCreate(
                ['emblem' => $group['emblem']],
                $group + [
                    'founder_id' => null,
                    'status' => 'approved',
                    'approved_at' => now(),
                ],
            );
        }
    }
}
