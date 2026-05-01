<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class WebsiteSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Website/Settings', [
            'settings' => SiteSetting::allCached(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // School identity
            'school_name'         => ['nullable', 'string', 'max:200'],
            'school_short_name'   => ['nullable', 'string', 'max:80'],
            'tagline'             => ['nullable', 'string', 'max:200'],
            'established_year'    => ['nullable', 'string', 'max:10'],
            'announcement_message' => ['nullable', 'string', 'max:200'],
            'footer_description'  => ['nullable', 'string', 'max:600'],
            // Contact
            'phone_primary'       => ['nullable', 'string', 'max:40'],
            'phone_secondary'     => ['nullable', 'string', 'max:40'],
            'email_primary'       => ['nullable', 'email', 'max:120'],
            'email_admissions'    => ['nullable', 'email', 'max:120'],
            'address'             => ['nullable', 'string', 'max:500'],
            'office_hours'        => ['nullable', 'string', 'max:200'],
            'google_maps_url'     => ['nullable', 'url', 'max:500'],
            // Stats overrides (set blank to use live database counts on the homepage)
            'stat_students_override'    => ['nullable', 'integer', 'min:0'],
            'stat_teachers_override'    => ['nullable', 'integer', 'min:0'],
            'stat_pass_rate'            => ['nullable', 'numeric', 'min:0', 'max:100'],
            'stat_years_legacy'         => ['nullable', 'integer', 'min:0'],
            // Social
            'social_facebook'     => ['nullable', 'url', 'max:255'],
            'social_youtube'      => ['nullable', 'url', 'max:255'],
            'social_instagram'    => ['nullable', 'url', 'max:255'],
            // DDO message
            'ddo_name'            => ['nullable', 'string', 'max:120'],
            'ddo_title'           => ['nullable', 'string', 'max:200'],
            'ddo_message'         => ['nullable', 'string', 'max:4000'],
            'ddo_serving_since'   => ['nullable', 'string', 'max:40'],
            // Logo
            'logo'                => ['nullable', 'image', 'mimes:jpg,jpeg,png,svg,webp', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            $existing = SiteSetting::get('logo_path');
            if ($existing) Storage::disk('public')->delete($existing);
            $path = $request->file('logo')->store('website', 'public');
            SiteSetting::put('logo_path', $path, ['type' => 'image', 'group' => 'identity']);
        }

        unset($validated['logo']);

        // Save all string-typed values
        foreach ($validated as $key => $value) {
            SiteSetting::put($key, $value ?? '', [
                'type'  => $this->typeOf($key),
                'group' => $this->groupOf($key),
            ]);
        }

        return back()->with('success', 'Website settings updated.');
    }

    private function typeOf(string $key): string
    {
        if (str_starts_with($key, 'stat_')) return 'number';
        if (in_array($key, ['ddo_message', 'address'], true)) return 'text';
        return 'string';
    }

    private function groupOf(string $key): string
    {
        return match (true) {
            str_starts_with($key, 'school_') || in_array($key, ['tagline', 'established_year'], true) => 'identity',
            str_starts_with($key, 'phone_') || str_starts_with($key, 'email_') ||
                in_array($key, ['address', 'office_hours', 'google_maps_url'], true) => 'contact',
            str_starts_with($key, 'stat_') => 'stats',
            str_starts_with($key, 'social_') => 'social',
            str_starts_with($key, 'ddo_') => 'ddo',
            default => 'general',
        };
    }
}
