<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\NewsArticle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class NewsArticleController extends Controller
{
    public function index(Request $request): Response
    {
        $query = NewsArticle::query()->with('author:id,name')->orderByDesc('published_at')->orderByDesc('id');
        if ($request->filled('search')) {
            $term = $request->string('search');
            $query->where(fn ($q) => $q->where('title', 'like', "%$term%")->orWhere('excerpt', 'like', "%$term%"));
        }
        if ($request->filled('category')) $query->where('category', $request->string('category'));

        return Inertia::render('Website/News/Index', [
            'articles'   => $query->paginate(15)->withQueryString(),
            'filters'    => $request->only(['search', 'category']),
            'categories' => $this->categories(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Website/News/Edit', [
            'article'    => null,
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['slug'] = $this->uniqueSlug($data['title']);
        $data['author_id'] = $request->user()->id;

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('website/news', 'public');
        }
        unset($data['image']);

        NewsArticle::create($data);

        return redirect()->route('website.news.index')->with('success', 'Article published.');
    }

    public function edit(NewsArticle $news): Response
    {
        return Inertia::render('Website/News/Edit', [
            'article'    => $news,
            'categories' => $this->categories(),
        ]);
    }

    public function update(Request $request, NewsArticle $news): RedirectResponse
    {
        $data = $this->validateData($request);

        // Re-slug only if title changed
        if ($data['title'] !== $news->title) {
            $data['slug'] = $this->uniqueSlug($data['title'], $news->id);
        }

        if ($request->hasFile('image')) {
            if ($news->image_path) Storage::disk('public')->delete($news->image_path);
            $data['image_path'] = $request->file('image')->store('website/news', 'public');
        }
        unset($data['image']);

        $news->update($data);

        return redirect()->route('website.news.index')->with('success', 'Article updated.');
    }

    public function destroy(NewsArticle $news): RedirectResponse
    {
        if ($news->image_path) Storage::disk('public')->delete($news->image_path);
        $news->delete();
        return back()->with('success', 'Article deleted.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'title'          => ['required', 'string', 'max:200'],
            'category'       => ['required', 'string', 'max:50'],
            'excerpt'        => ['nullable', 'string', 'max:500'],
            'body'           => ['nullable', 'string'],
            'image'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'image_gradient' => ['nullable', 'string', 'max:80'],
            'is_featured'    => ['nullable', 'boolean'],
            'is_published'   => ['nullable', 'boolean'],
            'published_at'   => ['nullable', 'date'],
        ]);
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'article-' . now()->timestamp;
        $slug = $base;
        $i = 2;
        while (NewsArticle::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }

    private function categories(): array
    {
        return ['Achievement', 'Event', 'Announcement', 'Policy', 'Notice', 'Sports', 'Academic'];
    }
}
