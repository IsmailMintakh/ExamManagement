<?php

namespace Database\Seeders;

use App\Models\HeroSlide;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

/**
 * Default content for the public website CMS.
 *
 * Seeds:
 *   - SiteSetting: school identity, contact info, DDO message, default stats
 *   - HeroSlide: 3 sample slides shown in the homepage rotator
 *
 * All values are safe defaults. The DDO can edit everything in the admin
 * UI under Website → Site Settings / Hero Slider after first login.
 */
class WebsiteContentSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // Identity
            ['key' => 'school_name',       'value' => 'Government Boys Higher Secondary School No.1, Skardu', 'group' => 'identity', 'type' => 'string', 'label' => 'Full School Name'],
            ['key' => 'school_short_name', 'value' => 'GBHSS No.1 Skardu',                                    'group' => 'identity', 'type' => 'string', 'label' => 'Short Name'],
            ['key' => 'tagline',           'value' => 'Where Mountains Meet Excellence',                      'group' => 'identity', 'type' => 'string', 'label' => 'Tagline'],
            ['key' => 'established_year',  'value' => '1954',                                                 'group' => 'identity', 'type' => 'string', 'label' => 'Established Year'],
            // Contact
            ['key' => 'phone_primary',     'value' => '+92 5815 920000',                                      'group' => 'contact',  'type' => 'string', 'label' => 'Primary Phone'],
            ['key' => 'phone_secondary',   'value' => '',                                                     'group' => 'contact',  'type' => 'string', 'label' => 'Secondary Phone'],
            ['key' => 'email_primary',     'value' => 'info@gbhss-skardu.edu.pk',                             'group' => 'contact',  'type' => 'string', 'label' => 'Primary Email'],
            ['key' => 'email_admissions',  'value' => 'admissions@gbhss-skardu.edu.pk',                       'group' => 'contact',  'type' => 'string', 'label' => 'Admissions Email'],
            ['key' => 'address',           'value' => 'Hospital Road, Skardu, Gilgit-Baltistan, Pakistan',    'group' => 'contact',  'type' => 'text',   'label' => 'Address'],
            ['key' => 'office_hours',      'value' => 'Mon–Sat · 9:00 AM – 2:00 PM',                          'group' => 'contact',  'type' => 'string', 'label' => 'Office Hours'],
            ['key' => 'google_maps_url',   'value' => '',                                                     'group' => 'contact',  'type' => 'string', 'label' => 'Google Maps URL'],
            // Stats (overrides blank by default — uses live counts)
            ['key' => 'stat_students_override', 'value' => '', 'group' => 'stats', 'type' => 'number', 'label' => 'Students Override'],
            ['key' => 'stat_teachers_override', 'value' => '', 'group' => 'stats', 'type' => 'number', 'label' => 'Teachers Override'],
            ['key' => 'stat_pass_rate',         'value' => '94.2', 'group' => 'stats', 'type' => 'number', 'label' => 'Board Pass Rate %'],
            ['key' => 'stat_years_legacy',      'value' => '72',   'group' => 'stats', 'type' => 'number', 'label' => 'Years of Legacy'],
            // Social
            ['key' => 'social_facebook',  'value' => '', 'group' => 'social', 'type' => 'string', 'label' => 'Facebook URL'],
            ['key' => 'social_youtube',   'value' => '', 'group' => 'social', 'type' => 'string', 'label' => 'YouTube URL'],
            ['key' => 'social_instagram', 'value' => '', 'group' => 'social', 'type' => 'string', 'label' => 'Instagram URL'],
            // DDO
            ['key' => 'ddo_name',          'value' => 'Wazir Zamin Ali',                                     'group' => 'ddo', 'type' => 'string', 'label' => 'DDO Name'],
            ['key' => 'ddo_title',         'value' => 'District Drawing Officer · Skardu District',          'group' => 'ddo', 'type' => 'string', 'label' => 'DDO Title'],
            ['key' => 'ddo_serving_since', 'value' => '2019 · 7 years',                                      'group' => 'ddo', 'type' => 'string', 'label' => 'Serving Since'],
            ['key' => 'ddo_message',       'value' => "Here in Baltistan, we say — the mountains do not bow, but neither do our sons. For over seven decades, this school has been a beacon on the banks of the Indus — teaching not just subjects, but the spirit of resilience, faith, and service.\n\nAs District Drawing Officer for Skardu, it is my duty and my honor to ensure that every young man who walks through these gates receives the finest education our nation can offer. We are committed to modernizing our facilities, supporting our faculty, and opening doors to opportunities — whether in Karachi, Islamabad, or the great universities of the world.",
                                            'group' => 'ddo', 'type' => 'text', 'label' => 'DDO Message'],
        ];

        foreach ($defaults as $row) {
            SiteSetting::firstOrCreate(['key' => $row['key']], $row);
        }
        SiteSetting::flush();

        // ─── Sample hero slides ───
        if (HeroSlide::count() === 0) {
            $slides = [
                [
                    'eyebrow'             => 'Est. 1954 · Skardu, Gilgit-Baltistan',
                    'title'               => 'Where Mountains Meet Excellence',
                    'subtitle'            => 'Seventy-two years of shaping young men of character.',
                    'description'         => 'The oldest and most distinguished boys\' institution in Baltistan — preparing minds for university, character for life, and souls for purpose.',
                    'cta_label'           => 'Begin Your Application',
                    'cta_url'             => '/admissions',
                    'cta_secondary_label' => 'Our Story',
                    'cta_secondary_url'   => '/about',
                    'overlay_color'       => '#0f172a',
                    'overlay_opacity'     => 60,
                    'sort_order'          => 1,
                    'is_active'           => true,
                ],
                [
                    'eyebrow'             => 'Admissions 2026–27 Now Open',
                    'title'               => 'Your Future Begins in the Mountains',
                    'subtitle'            => 'Matric · FSc Pre-Medical · Pre-Engineering · ICS · FA',
                    'description'         => 'Applications close May 31, 2026. Discover programs that have launched doctors, engineers, and leaders across Pakistan.',
                    'cta_label'           => 'Apply Now',
                    'cta_url'             => '/admissions',
                    'cta_secondary_label' => 'View Programs',
                    'cta_secondary_url'   => '/academics',
                    'overlay_color'       => '#064e3b',
                    'overlay_opacity'     => 55,
                    'sort_order'          => 2,
                    'is_active'           => true,
                ],
                [
                    'eyebrow'             => 'FBISE Matric 2026 Results',
                    'title'               => 'Top 3 in Gilgit-Baltistan, Six Years Running',
                    'subtitle'            => 'Three students placed in the FBISE national top ten this year.',
                    'description'         => 'Muhammad Abbas Ali (1st Position), Sultan Mehdi (4th), Ali Raza (9th) — proof that excellence travels from the Karakoram to the world.',
                    'cta_label'           => 'See Results',
                    'cta_url'             => '/board-results',
                    'cta_secondary_label' => 'Read Story',
                    'cta_secondary_url'   => '/news',
                    'overlay_color'       => '#1e1b4b',
                    'overlay_opacity'     => 65,
                    'sort_order'          => 3,
                    'is_active'           => true,
                ],
            ];

            foreach ($slides as $slide) HeroSlide::create($slide);
        }
    }
}
