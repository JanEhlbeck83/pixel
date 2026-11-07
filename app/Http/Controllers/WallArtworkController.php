<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WallArtwork;
use Illuminate\Http\Request;

class WallArtworkController extends Controller
{
    public function index(Request $request, User $user)
    {
        $owner = $request->user()?->id === $user->id;
        $artworks = WallArtwork::with('user')->where('user_id', $user->id)
            ->when(! $owner, fn ($query) => $query->where('is_public', true))->latest()->paginate(24);

        return response()->view('profile.gallery', compact('user', 'owner', 'artworks'))->header('Cache-Control', 'private, no-store');
    }

    public function image(Request $request, WallArtwork $artwork)
    {
        abort_unless($artwork->is_public || $artwork->user_id === $request->user()?->id, 404);
        $image = imagecreatetruecolor(20, 50);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        foreach ($artwork->pixels as $index => $hex) {
            $rgb = sscanf($hex, '#%02x%02x%02x');
            imagesetpixel($image, (int) $index % 20, intdiv((int) $index, 20), imagecolorallocate($image, ...$rgb));
        }
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return response($png)->header('Content-Type', 'image/png')->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }

    public function update(Request $request, WallArtwork $artwork)
    {
        abort_unless($artwork->user_id === $request->user()->id, 403);
        $data = $request->validate(['is_public' => ['required', 'boolean']]);
        $artwork->update($data);

        return redirect()->route('artworks.index', $request->user())->with('status', 'artwork-updated');
    }

    public function destroy(Request $request, WallArtwork $artwork)
    {
        abort_unless($artwork->user_id === $request->user()->id, 403);
        $artwork->delete();

        return redirect()->route('artworks.index', $request->user())->with('status', 'artwork-deleted');
    }
}
