<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\GalleryAlbum;
use App\Models\GalleryPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GalleryAlbumController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Website/Gallery/Index', [
            'albums' => GalleryAlbum::withCount('photos')
                ->orderBy('sort_order')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Website/Gallery/Edit', [
            'album'  => null,
            'photos' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['slug'] = $this->uniqueSlug($data['title']);

        if ($request->hasFile('cover')) {
            $data['cover_image'] = $request->file('cover')->store('website/gallery/covers', 'public');
        }
        unset($data['cover']);

        $album = GalleryAlbum::create($data);

        return redirect()->route('website.gallery.edit', $album->id)
            ->with('success', 'Album created. Add photos below.');
    }

    public function edit(GalleryAlbum $album): Response
    {
        return Inertia::render('Website/Gallery/Edit', [
            'album'  => $album,
            'photos' => $album->photos()->get(),
        ]);
    }

    public function update(Request $request, GalleryAlbum $album): RedirectResponse
    {
        $data = $this->validateData($request);

        if ($data['title'] !== $album->title) {
            $data['slug'] = $this->uniqueSlug($data['title'], $album->id);
        }

        if ($request->hasFile('cover')) {
            if ($album->cover_image) Storage::disk('public')->delete($album->cover_image);
            $data['cover_image'] = $request->file('cover')->store('website/gallery/covers', 'public');
        }
        unset($data['cover']);

        $album->update($data);

        return back()->with('success', 'Album updated.');
    }

    public function destroy(GalleryAlbum $album): RedirectResponse
    {
        // Delete photo files
        foreach ($album->photos as $photo) {
            if ($photo->image_path) Storage::disk('public')->delete($photo->image_path);
        }
        if ($album->cover_image) Storage::disk('public')->delete($album->cover_image);
        $album->delete();

        return redirect()->route('website.gallery.index')->with('success', 'Album deleted.');
    }

    /**
     * Upload one or more photos to an album.
     */
    public function uploadPhotos(Request $request, GalleryAlbum $album): RedirectResponse
    {
        $request->validate([
            'photos'   => ['required', 'array'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
        ]);

        $nextOrder = ($album->photos()->max('sort_order') ?? 0) + 1;

        foreach ($request->file('photos') as $file) {
            $album->photos()->create([
                'image_path' => $file->store('website/gallery/photos', 'public'),
                'sort_order' => $nextOrder++,
            ]);
        }

        return back()->with('success', count($request->file('photos')) . ' photo(s) uploaded.');
    }

    public function deletePhoto(GalleryAlbum $album, GalleryPhoto $photo): RedirectResponse
    {
        if ($photo->album_id !== $album->id) abort(404);
        if ($photo->image_path) Storage::disk('public')->delete($photo->image_path);
        $photo->delete();
        return back()->with('success', 'Photo removed.');
    }

    public function updatePhoto(Request $request, GalleryAlbum $album, GalleryPhoto $photo): RedirectResponse
    {
        if ($photo->album_id !== $album->id) abort(404);
        $photo->update($request->validate([
            'caption' => ['nullable', 'string', 'max:200'],
        ]));
        return back();
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'title'       => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'event_date'  => ['nullable', 'date'],
            'cover'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'sort_order'  => ['nullable', 'integer'],
            'is_active'   => ['nullable', 'boolean'],
        ]);
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'album-' . now()->timestamp;
        $slug = $base;
        $i = 2;
        while (GalleryAlbum::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }
}
