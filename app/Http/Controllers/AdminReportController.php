<?php

namespace App\Http\Controllers;

use App\Models\Pixel;
use App\Models\PixelEditLock;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReportController extends Controller
{
    public function index()
    {
        $reports = Report::query()
            ->with('reporter')
            ->where('status', 'open')
            ->orderBy('created_at')
            ->get();

        $reports->each(function (Report $report) {
            $report->authors = $this->authors($report)->get(['id', 'name', 'email', 'banned_at']);
        });

        $handled = Report::query()->where('status', '!=', 'open')->latest('handled_at')->limit(20)->get();

        return view('admin.reports', ['reports' => $reports, 'handled' => $handled, 'reasons' => Report::REASONS]);
    }

    public function image(Report $report)
    {
        abort_unless(is_file($report->snapshotPath()), 404);

        return response()->file($report->snapshotPath(), ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=3600']);
    }

    public function reset(Request $request, Report $report): RedirectResponse
    {
        return $this->handle($request, $report, 'reset', ban: false);
    }

    public function resetAndBan(Request $request, Report $report): RedirectResponse
    {
        return $this->handle($request, $report, 'reset_ban', ban: true);
    }

    public function dismiss(Request $request, Report $report): RedirectResponse
    {
        $report->update([
            'status' => 'dismissed',
            'action' => 'dismiss',
            'handled_by_user_id' => $request->user()->id,
            'handled_at' => now(),
        ]);

        return back()->with('status', __('Meldung abgelehnt.'));
    }

    private function handle(Request $request, Report $report, string $action, bool $ban): RedirectResponse
    {
        DB::transaction(function () use ($request, $report, $action, $ban) {
            if ($ban) {
                $authorIds = $this->authors($report)->pluck('id')->reject(fn ($id) => $id === $request->user()->id);
                User::query()->whereIn('id', $authorIds)->update(['banned_at' => now()]);
            }

            $this->area($report)->update([
                'color' => '#ffffff',
                'owner_user_id' => null,
                'updated_by_user_id' => null,
                'updated_at' => now(),
            ]);

            PixelEditLock::query()
                ->whereBetween('x', [$report->x, $report->x + $report->width - 1])
                ->whereBetween('y', [$report->y, $report->y + $report->height - 1])
                ->delete();

            // Überlappende offene Meldungen sind mit dem Zurücksetzen ebenfalls erledigt.
            Report::query()
                ->where('status', 'open')
                ->where('x', '<', $report->x + $report->width)
                ->whereRaw('x + width > ?', [$report->x])
                ->where('y', '<', $report->y + $report->height)
                ->whereRaw('y + height > ?', [$report->y])
                ->orWhere('id', $report->id)
                ->update([
                    'status' => 'resolved',
                    'action' => $action,
                    'handled_by_user_id' => $request->user()->id,
                    'handled_at' => now(),
                ]);
        });

        return back()->with('status', __('Bereich zurückgesetzt.'));
    }

    private function area(Report $report)
    {
        return Pixel::query()
            ->whereBetween('x', [$report->x, $report->x + $report->width - 1])
            ->whereBetween('y', [$report->y, $report->y + $report->height - 1]);
    }

    private function authors(Report $report)
    {
        $ids = $this->area($report)->whereNotNull('updated_by_user_id')->distinct()->pluck('updated_by_user_id');

        return User::query()->whereIn('id', $ids);
    }
}
