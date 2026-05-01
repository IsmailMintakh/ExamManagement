<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageMeta extends Model
{
    protected $table = 'page_meta';

    protected $fillable = [
        'page_key', 'hero_eyebrow', 'hero_title', 'hero_title_accent',
        'hero_subtitle', 'hero_style', 'meta_title', 'meta_description',
    ];

    /**
     * Hero color presets the admin can pick from. Each maps to a
     * gradient + accent set rendered by PublicHero.vue.
     */
    public const STYLES = [
        'emerald-night' => ['label' => 'Emerald Night',  'preview' => 'from-slate-950 via-emerald-950 to-slate-900'],
        'amber-dawn'    => ['label' => 'Amber Dawn',     'preview' => 'from-amber-900 via-orange-950 to-slate-900'],
        'sky-twilight'  => ['label' => 'Sky Twilight',   'preview' => 'from-slate-950 via-sky-950 to-indigo-900'],
        'violet-deep'   => ['label' => 'Violet Deep',    'preview' => 'from-slate-950 via-violet-950 to-slate-900'],
        'rose-warm'     => ['label' => 'Rose Warm',      'preview' => 'from-slate-950 via-rose-950 to-slate-900'],
    ];

    /**
     * Get-or-build a meta record for the given page. Returns an unsaved
     * model with safe defaults when none exists, so callers can render
     * the page even before a hero is configured.
     */
    public static function forPage(string $key): self
    {
        return static::firstOrNew(
            ['page_key' => $key],
            ['hero_title' => ucfirst($key), 'hero_style' => 'emerald-night']
        );
    }
}
