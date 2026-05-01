<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\PageBlock;
use App\Models\PageMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PageBlockController extends Controller
{
    /**
     * Hub showing every editable page with block counts.
     */
    public function index(): Response
    {
        $counts = PageBlock::query()
            ->selectRaw('page_key, count(*) as total, sum(case when is_active = 1 then 1 else 0 end) as active')
            ->groupBy('page_key')
            ->get()
            ->keyBy('page_key');

        $heroSet = PageMeta::pluck('page_key')->flip();

        $pages = collect(PageBlock::PAGES)->map(function ($meta, $key) use ($counts, $heroSet) {
            $row = $counts->get($key);
            return [
                'key'          => $key,
                'label'        => $meta['label'],
                'route'        => $meta['route'],
                'auto_content' => $meta['auto_content'] ?? null,
                'has_hero'     => $heroSet->has($key),
                'total'        => (int) ($row->total ?? 0),
                'active'       => (int) ($row->active ?? 0),
            ];
        })->values();

        return Inertia::render('Website/Pages/Index', [
            'pages' => $pages,
        ]);
    }

    /**
     * Per-page block manager.
     */
    public function show(string $key): Response
    {
        if (!isset(PageBlock::PAGES[$key])) abort(404);

        $meta = PageBlock::PAGES[$key];

        return Inertia::render('Website/Pages/Manage', [
            'page'    => array_merge($meta, ['key' => $key]),
            'hero'    => PageMeta::forPage($key),
            'blocks'  => PageBlock::forPage($key)->get(),
            'types'   => PageBlock::TYPES,
            'styles'  => PageMeta::STYLES,
        ]);
    }

    /**
     * Update the hero (and SEO meta) for a page.
     */
    public function updateHero(Request $request, string $key): RedirectResponse
    {
        if (!isset(PageBlock::PAGES[$key])) abort(404);

        $data = $request->validate([
            'hero_eyebrow'        => ['nullable', 'string', 'max:120'],
            'hero_title'          => ['required', 'string', 'max:200'],
            'hero_title_accent'   => ['nullable', 'string', 'max:200'],
            'hero_subtitle'       => ['nullable', 'string', 'max:400'],
            'hero_style'          => ['nullable', 'string', Rule::in(array_keys(PageMeta::STYLES))],
            'meta_title'          => ['nullable', 'string', 'max:200'],
            'meta_description'    => ['nullable', 'string', 'max:400'],
        ]);

        PageMeta::updateOrCreate(['page_key' => $key], $data);

        return back()->with('success', 'Hero updated.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'page_key' => ['required', 'string', Rule::in(array_keys(PageBlock::PAGES))],
            'type'     => ['required', 'string', Rule::in(array_keys(PageBlock::TYPES))],
            'data'     => ['required', 'array'],
        ]);

        $data['sort_order'] = (PageBlock::where('page_key', $data['page_key'])->max('sort_order') ?? 0) + 1;
        $data['is_active'] = true;

        PageBlock::create($data);

        return back()->with('success', 'Block added.');
    }

    public function update(Request $request, PageBlock $block): RedirectResponse
    {
        $data = $request->validate([
            'data'      => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $block->update(array_filter($data, fn ($v) => $v !== null));

        return back()->with('success', 'Block updated.');
    }

    public function destroy(PageBlock $block): RedirectResponse
    {
        $block->delete();
        return back()->with('success', 'Block removed.');
    }

    public function reorder(Request $request, string $key): RedirectResponse
    {
        if (!isset(PageBlock::PAGES[$key])) abort(404);

        $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        foreach ($request->input('order') as $i => $id) {
            PageBlock::where('id', $id)->where('page_key', $key)->update(['sort_order' => $i]);
        }

        return back();
    }
}
