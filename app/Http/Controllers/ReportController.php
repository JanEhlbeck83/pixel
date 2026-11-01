<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Services\Wall\ReportSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'x' => ['required', 'integer', 'min:0'],
            'y' => ['required', 'integer', 'min:0'],
            'width' => ['required', 'integer', 'min:1', 'max:'.Report::MAX_SIZE],
            'height' => ['required', 'integer', 'min:1', 'max:'.Report::MAX_SIZE],
            'reason' => ['required', Rule::in(Report::REASONS)],
            'message' => ['nullable', 'string', 'max:1000'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
        ]);

        $dimensions = DB::table('wall_dimensions')->where('id', 1)->first();
        if ($dimensions && ($data['x'] >= $dimensions->width || $data['y'] >= $dimensions->height)) {
            return response()->json(['message' => __('Der Bereich liegt außerhalb der Pixelwand.')], 422);
        }

        $report = Report::create([
            'reporter_user_id' => $request->user()?->id,
            'reporter_email' => $request->user()?->email ?? ($data['email'] ?? null),
            'reason' => $data['reason'],
            'message' => isset($data['message']) ? trim($data['message']) : null,
            'x' => $data['x'],
            'y' => $data['y'],
            'width' => $data['width'],
            'height' => $data['height'],
        ]);

        ReportSnapshot::create($report);
        $this->notifyAdmins($report);

        return response()->json(['success' => true], 201);
    }

    private function notifyAdmins(Report $report): void
    {
        $admins = (array) config('wall.admin_emails', []);
        if ($admins === []) {
            return;
        }

        try {
            Mail::raw(
                "Neue Meldung #{$report->id} ({$report->reason}): Bereich {$report->x}:{$report->y}, {$report->width}x{$report->height}.\n".route('admin.reports'),
                fn ($mail) => $mail->to($admins)->subject(config('app.name').': neue Inhaltsmeldung')
            );
        } catch (\Throwable $exception) {
            Log::warning('Report mail failed: '.$exception->getMessage());
        }
    }
}
