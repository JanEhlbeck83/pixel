<?php

namespace App\Http\Controllers;

use App\Models\Snapshot;
use App\Services\Wall\SnapshotThumbnail;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

class GalleryController extends Controller
{
    public function index()
    {
        $directory = public_path('images/snapshots');
        $storedSnapshots = Snapshot::query()
            ->whereNotNull('image_path')
            ->get()
            ->keyBy('image_path');

        $snapshots = collect(File::isDirectory($directory) ? File::files($directory) : [])
            ->map(function ($file) use ($storedSnapshots) {
                $imagePath = 'images/snapshots/'.$file->getFilename();
                $snapshot = $storedSnapshots->get($imagePath);

                if ($snapshot) {
                    return $snapshot;
                }

                $createdAt = Carbon::createFromTimestamp($file->getMTime());
                $snapshot = new Snapshot([
                    'label' => 'Snapshot '.$createdAt->format('d.m.Y H:i'),
                    'image_path' => $imagePath,
                ]);
                $snapshot->created_at = $createdAt;

                return $snapshot;
            })
            ->sortByDesc('created_at')
            ->values()
            ->each(function ($snapshot) {
                $snapshot->thumbnail_path = SnapshotThumbnail::ensure($snapshot->image_path) ?? $snapshot->image_path;
            });

        return view('gallery', compact('snapshots'));
    }
}
