<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageBlock extends Model
{
    protected $fillable = ['page_key', 'type', 'data', 'sort_order', 'is_active'];

    protected $casts = [
        'data'       => 'array',
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    /**
     * Catalog of supported block types. The admin UI builds the type picker
     * from this. Keep in sync with the renderer in resources/js/Components/PageBlock.vue.
     */
    public const TYPES = [
        'rich_text'      => ['label' => 'Rich Text Section',     'desc' => 'Heading + paragraph(s) of text. Use for mission statements, history, descriptions.'],
        'mission_vision' => ['label' => 'Mission & Vision',      'desc' => 'Two-column block for "Our Mission" and "Our Vision".'],
        'feature_grid'   => ['label' => 'Feature Grid',          'desc' => 'Icon + title + description cards in a grid (3-6 items).'],
        'stats_strip'    => ['label' => 'Stats Strip',           'desc' => 'Row of bold numbers with labels (e.g. 1248 Students · 94% Pass Rate).'],
        'timeline'       => ['label' => 'Timeline / Milestones', 'desc' => 'Year + title + description vertical timeline.'],
        'image_text'     => ['label' => 'Image + Text',          'desc' => 'Single image side-by-side with a heading and paragraph.'],
        'testimonials'   => ['label' => 'Testimonials',          'desc' => 'Quote cards with author name and role (perfect for the homepage).'],
        'toppers_table'  => ['label' => 'Toppers / Rankings',    'desc' => 'Numbered table of names, classes, marks, and percentages.'],
        'cta'            => ['label' => 'Call to Action',        'desc' => 'Big heading + button banner.'],
    ];

    /**
     * Pages users can edit. Matches PublicController action keys.
     *
     * Each page supports:
     *  - hero (eyebrow / title / accent / subtitle / style) via PageMeta
     *  - body content blocks via PageBlock (only some pages — 'blocks' flag)
     */
    /**
     * Every page supports both a hero (PageMeta) and content blocks (PageBlock).
     *
     * `auto_content` flags pages that ALSO render an automatic data section
     * (the news listing, gallery albums, faculty grid, contact form). For those
     * pages, custom blocks render BELOW the auto section — the admin can add
     * intros, CTAs, FAQs, etc. without touching the auto-generated content.
     */
    public const PAGES = [
        'home'        => ['label' => 'Home Page',        'route' => '/',               'blocks' => true, 'auto_content' => 'hero slider + stats + DDO message + news strip'],
        'about'       => ['label' => 'About Page',       'route' => '/about',          'blocks' => true, 'auto_content' => null],
        'academics'   => ['label' => 'Academics Page',   'route' => '/academics',      'blocks' => true, 'auto_content' => null],
        'admissions'  => ['label' => 'Admissions Page',  'route' => '/admissions',     'blocks' => true, 'auto_content' => null],
        'results'     => ['label' => 'Board Results',    'route' => '/board-results',  'blocks' => true, 'auto_content' => null],
        'news'        => ['label' => 'News Page',        'route' => '/news',           'blocks' => true, 'auto_content' => 'auto-generated news listing from News & Articles'],
        'gallery'     => ['label' => 'Gallery Page',     'route' => '/gallery',        'blocks' => true, 'auto_content' => 'auto-generated album grid from Photo Gallery'],
        'faculty'     => ['label' => 'Faculty Page',     'route' => '/faculty',        'blocks' => true, 'auto_content' => 'auto-generated faculty grid from Faculty'],
        'contact'     => ['label' => 'Contact Page',     'route' => '/contact',        'blocks' => true, 'auto_content' => 'contact form + info cards from Site Settings'],
    ];

    public function scopeForPage($q, string $key)
    {
        return $q->where('page_key', $key)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
