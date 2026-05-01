<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\HeroSlide;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class HeroSlideController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Website/HeroSlides/Index', [
            'slides' => HeroSlide::orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Website/HeroSlides/Edit', [
            'slide' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('website/hero', 'public');
        }
        unset($data['image']);

        $data['sort_order'] = $data['sort_order'] ?? ((HeroSlide::max('sort_order') ?? 0) + 1);

        HeroSlide::create($data);

        return redirect()->route('website.hero-slides.index')
            ->with('success', 'Slide added.');
    }

    public function edit(HeroSlide $heroSlide): Response
    {
        return Inertia::render('Website/HeroSlides/Edit', [
            'slide' => $heroSlide,
        ]);
    }

    public function update(Request $request, HeroSlide $heroSlide): RedirectResponse
    {
        $data = $this->validateData($request);

        if ($request->hasFile('image')) {
            if ($heroSlide->image_path) Storage::disk('public')->delete($heroSlide->image_path);
            $data['image_path'] = $request->file('image')->store('website/hero', 'public');
        }
        unset($data['image']);

        $heroSlide->update($data);

        return redirect()->route('website.hero-slides.index')
            ->with('success', 'Slide updated.');
    }

    public function destroy(HeroSlide $heroSlide): RedirectResponse
    {
        if ($heroSlide->image_path) Storage::disk('public')->delete($heroSlide->image_path);
        $heroSlide->delete();

        return back()->with('success', 'Slide deleted.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer', 'exists:hero_slides,id'],
        ]);

        foreach ($request->input('order') as $index => $id) {
            HeroSlide::where('id', $id)->update(['sort_order' => $index]);
        }

        return back();
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'eyebrow'             => ['nullable', 'string', 'max:80'],
            'title'               => ['required', 'string', 'max:200'],
            'subtitle'            => ['nullable', 'string', 'max:200'],
            'description'         => ['nullable', 'string', 'max:1000'],
            'image'               => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'cta_label'           => ['nullable', 'string', 'max:60'],
            'cta_url'             => ['nullable', 'string', 'max:255'],
            'cta_secondary_label' => ['nullable', 'string', 'max:60'],
            'cta_secondary_url'   => ['nullable', 'string', 'max:255'],
            'overlay_color'       => ['nullable', 'string', 'max:20'],
            'overlay_opacity'     => ['nullable', 'integer', 'min:0', 'max:100'],
            'sort_order'          => ['nullable', 'integer', 'min:0'],
            'is_active'           => ['nullable', 'boolean'],
        ]);
    }
}
