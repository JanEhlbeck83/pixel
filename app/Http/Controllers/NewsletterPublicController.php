<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

class NewsletterPublicController extends Controller
{
    /** Bilder müssen ohne Login erreichbar sein, damit E-Mail-Clients sie laden können. */
    public function image(string $file)
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{20,60}\.(jpe?g|png|gif|webp)$/', $file) === 1, 404);
        $path = 'newsletters/'.$file;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'public, max-age=86400']);
    }

    public function unsubscribe(User $user)
    {
        $user->forceFill(['newsletter_subscribed_at' => null])->save();

        return view('newsletter.unsubscribed');
    }
}
