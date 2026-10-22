<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if ($request->filled('website')) {
            return response()->json(['success' => true]);
        }

        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'message' => ['required', 'string', 'min:5', 'max:2000'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);

        Feedback::create([
            'user_id' => $request->user()?->id,
            'email' => $data['email'] ?? null,
            'message' => trim($data['message']),
        ]);

        return response()->json(['success' => true], 201);
    }
}
