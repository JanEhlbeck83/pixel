<?php

namespace App\Console\Commands;

use App\Models\CommunityGroup;
use App\Models\CommunityGroupMember;
use App\Models\CommunityProject;
use App\Models\CommunityProjectParticipant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PrepareSoakTest extends Command
{
    protected $signature = 'soak:prepare {--confirm-isolated : Confirm that this is a dedicated, disposable staging database}';

    protected $description = 'Prepare 50 synthetic users and two projects for an isolated staging endurance test';

    public function handle(): int
    {
        if (! $this->option('confirm-isolated')
            || ! app()->environment('staging')
            || ! config('soak.enabled')
            || ! in_array(config('mail.default'), ['log', 'array'], true)
            || rtrim(config('app.url'), '/') !== 'https://staging.pixriot.online') {
            $this->error('Requires --confirm-isolated, APP_ENV=staging, SOAK_TEST_ENABLED=true, MAIL_MAILER=log/array and APP_URL=https://staging.pixriot.online.');

            return self::FAILURE;
        }

        $path = config('soak.manifest');
        if (file_exists($path)) {
            $this->error('Manifest already exists. Existing fixtures are never overwritten.');

            return self::FAILURE;
        }
        foreach (['users', 'pixels', 'orders', 'community_projects', 'wall_drafts', 'wall_artworks', 'analytics_visits'] as $table) {
            if (DB::table($table)->exists()) {
                $this->error("Database is not empty: {$table}. Use a separate database; no data was removed.");

                return self::FAILURE;
            }
        }
        $wall = DB::table('wall_dimensions')->first();
        if (! $wall) {
            $this->error('Test requires an initialized wall_dimensions table.');

            return self::FAILURE;
        }
        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0700, true)) {
            throw new RuntimeException('Cannot create private manifest directory.');
        }
        $file = fopen($path, 'x');
        if ($file === false) {
            throw new RuntimeException('Cannot exclusively create manifest.');
        }
        chmod($path, 0600);

        try {
            DB::transaction(function () use ($file) {
                // Match the current live-sized wall, not the smaller fresh-migration default.
                DB::table('wall_dimensions')->where('id', 1)->update(['width' => 3840, 'height' => 2160]);
                $runId = (string) Str::uuid();
                $users = [];
                $groups = [];
                $projects = [];
                for ($i = 0; $i < 50; $i++) {
                    $password = Str::random(40);
                    $user = new User;
                    $user->forceFill([
                        'name' => sprintf('Soak Player %02d', $i + 1),
                        'email' => sprintf('soak-%s-%02d@example.invalid', $runId, $i + 1),
                        'password' => $password, 'email_verified_at' => now(),
                        'free_pixels' => 50, 'free_pixels_at' => now(), 'pixel_credits' => 0,
                        'project_phase_notifications' => false,
                    ])->save();
                    $groupIndex = intdiv($i, 25);
                    if ($i % 25 === 0) {
                        $groups[$groupIndex] = CommunityGroup::create([
                            'founder_id' => $user->id, 'name' => 'Soak '.$runId.' '.$groupIndex,
                            'description' => 'Synthetic endurance-test group; not real users.',
                            'status' => 'approved', 'approved_at' => now(),
                        ]);
                        $projects[$groupIndex] = CommunityProject::create([
                            'community_group_id' => $groups[$groupIndex]->id,
                            'created_by' => $user->id, 'title' => 'Soak project '.$groupIndex,
                            'type' => 'free', 'status' => 'active', 'width' => 32, 'height' => 32,
                            'reserved_x' => 512 + 40 * $groupIndex, 'reserved_y' => 128,
                            'started_at' => now(),
                            'proposals_close_at' => now(),
                            'project_close_at' => now()->addDays(16),
                        ]);
                    }
                    CommunityGroupMember::create([
                        'community_group_id' => $groups[$groupIndex]->id, 'user_id' => $user->id,
                    ]);
                    CommunityProjectParticipant::create([
                        'community_project_id' => $projects[$groupIndex]->id, 'user_id' => $user->id,
                    ]);
                    $users[] = [
                        'id' => $user->id, 'email' => $user->email, 'password' => $password,
                        'group_id' => $groups[$groupIndex]->id, 'project_id' => $projects[$groupIndex]->id,
                        'project_row' => $i % 25, 'wall_x' => 64, 'wall_y' => 180 + $i,
                    ];
                }
                $manifest = [
                    'schema' => 1, 'run_id' => $runId, 'target' => 'https://staging.pixriot.online',
                    'created_at' => now()->toIso8601String(),
                    'wall' => ['width' => 3840, 'height' => 2160],
                    'expires_at' => now()->addDays(16)->toIso8601String(), 'users' => $users,
                ];
                $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
                if (fwrite($file, $json) !== strlen($json) || ! fflush($file)) {
                    throw new RuntimeException('Cannot persist private test credentials.');
                }
            });
        } catch (Throwable $exception) {
            fclose($file);
            // Only the file exclusively created by this invocation is removed on rollback.
            unlink($path);
            throw $exception;
        }
        fclose($file);
        $this->info('Prepared 50 users, 2 groups and 2 active projects. No traffic started.');
        $this->line('Private credentials: '.$path.' (copy securely to the generator; never commit or share).');

        return self::SUCCESS;
    }
}
