<?php

namespace Database\Seeders;

use App\Models\PageMeta;
use Illuminate\Database\Seeder;

/**
 * Default page heroes for all editable public pages.
 *
 * These mirror the previously-hardcoded hero sections so the live site
 * looks the same after the dynamic-hero migration. The DDO can edit each
 * one in Website → Pages Content → [page] → Page Hero.
 *
 * Idempotent — only seeds rows that don't already exist.
 */
class WebsitePhase4Seeder extends Seeder
{
    public function run(): void
    {
        $heroes = [
            'about' => [
                'hero_eyebrow'      => 'Our Heritage',
                'hero_title'        => 'Seventy-Two Years',
                'hero_title_accent' => 'of Learning.',
                'hero_subtitle'     => 'From a modest middle school in 1954 to Skardu\'s most prestigious institution — this is our story.',
                'hero_style'        => 'emerald-night',
                'meta_description'  => 'Government Boys Higher Secondary School No.1 Skardu — over seven decades of excellence in education. Our mission, vision, history, and values.',
            ],
            'academics' => [
                'hero_eyebrow'      => 'Curriculum',
                'hero_title'        => 'Rigor in every',
                'hero_title_accent' => 'subject.',
                'hero_subtitle'     => 'Federal Board (FBISE) affiliated — Urdu & English medium instruction.',
                'hero_style'        => 'sky-twilight',
                'meta_description'  => 'Matric, FSc Pre-Medical, Pre-Engineering, ICS, FA — all four streams aligned with the Federal Board of Intermediate and Secondary Education.',
            ],
            'admissions' => [
                'hero_eyebrow'      => 'Applications Open · 2026–27',
                'hero_title'        => 'Begin your',
                'hero_title_accent' => 'journey.',
                'hero_subtitle'     => 'Five steps. One of the finest schools in Gilgit-Baltistan.',
                'hero_style'        => 'amber-dawn',
                'meta_description'  => 'Applications for the 2026–27 academic year are now open. Find requirements, key dates, and how to apply.',
            ],
            'news' => [
                'hero_eyebrow'      => 'Stay Updated',
                'hero_title'        => 'News &',
                'hero_title_accent' => 'events.',
                'hero_subtitle'     => 'Latest from the halls of GBHSS No.1 Skardu.',
                'hero_style'        => 'emerald-night',
                'meta_description'  => 'Achievements, events, announcements, and updates from Government Boys Higher Secondary School No.1, Skardu.',
            ],
            'gallery' => [
                'hero_eyebrow'      => 'Campus Life',
                'hero_title'        => 'Moments &',
                'hero_title_accent' => 'memories.',
                'hero_subtitle'     => 'Glimpses of school events, ceremonies, and the everyday spirit of our school.',
                'hero_style'        => 'violet-deep',
                'meta_description'  => 'Photo galleries from sports galas, ceremonies, academic events, and daily campus life.',
            ],
            'faculty' => [
                'hero_eyebrow'      => 'Our Educators',
                'hero_title'        => 'Sixty-eight teachers.',
                'hero_title_accent' => 'One mission.',
                'hero_subtitle'     => 'Every member of our faculty is a mentor, a guide, and a friend.',
                'hero_style'        => 'emerald-night',
                'meta_description'  => 'Meet our principal, vice-principal, department heads, and teachers — many holding Master\'s and M.Phil degrees.',
            ],
            'contact' => [
                'hero_eyebrow'      => 'Get in Touch',
                'hero_title'        => 'We\'d love to',
                'hero_title_accent' => 'hear from you.',
                'hero_subtitle'     => 'Questions about admissions, academics, or anything else? Reach out — we typically respond within 1–2 business days.',
                'hero_style'        => 'rose-warm',
                'meta_description'  => 'Contact GBHSS No.1 Skardu — phone, email, address, and office hours. Message us through our online form.',
            ],
        ];

        foreach ($heroes as $key => $data) {
            PageMeta::firstOrCreate(['page_key' => $key], $data);
        }
    }
}
