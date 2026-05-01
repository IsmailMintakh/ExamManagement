<?php

namespace Database\Seeders;

use App\Models\PageBlock;
use App\Models\PageMeta;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

/**
 * Seeds the new dynamic content for Phase 5:
 *   - Two new site settings (announcement_message, footer_description)
 *     used by the public layout's top bar and footer.
 *   - Default Home page blocks (features, programs, testimonials, CTA)
 *     so the homepage middle section keeps its content after the
 *     hardcoded sections were removed.
 *   - Default Results page blocks (stats, toppers table, CTA).
 *   - Page hero meta for home + results pages.
 *
 * Idempotent — checks before inserting.
 */
class WebsitePhase5Seeder extends Seeder
{
    public function run(): void
    {
        $this->seedNewSettings();
        $this->seedHomeBlocks();
        $this->seedResultsBlocks();
        $this->seedNewHeroes();
    }

    private function seedNewSettings(): void
    {
        $defaults = [
            ['key' => 'announcement_message', 'value' => 'Admissions Open 2026–27', 'group' => 'identity', 'type' => 'string', 'label' => 'Header Announcement'],
            ['key' => 'footer_description',
             'value' => 'Nestled in the heart of Baltistan, our institution has shaped generations of leaders — blending mountain spirit with world-class education for over seven decades.',
             'group' => 'identity', 'type' => 'text', 'label' => 'Footer Description'],
        ];

        foreach ($defaults as $row) {
            SiteSetting::firstOrCreate(['key' => $row['key']], $row);
        }
        SiteSetting::flush();
    }

    private function seedHomeBlocks(): void
    {
        if (PageBlock::where('page_key', 'home')->exists()) return;

        $blocks = [
            [
                'type' => 'feature_grid',
                'data' => [
                    'eyebrow' => 'What Sets Us Apart',
                    'heading' => 'A foundation of trust. A future of possibility.',
                    'items'   => [
                        ['icon' => 'ShieldCheckIcon', 'title' => 'Seven Decades of Trust',     'desc' => 'Established in 1954, we are the oldest and most respected boys\' institution in Baltistan — a heritage of excellence passed down through generations.'],
                        ['icon' => 'BeakerIcon',      'title' => 'Modern Science Facilities', 'desc' => 'Fully-equipped physics, chemistry, and biology laboratories. Computer lab with 40 workstations and high-speed satellite internet.'],
                        ['icon' => 'HeartIcon',       'title' => 'Values & Character',        'desc' => 'Rooted in Islamic and Baltistani cultural values — we shape honest, responsible, and compassionate young men ready for the world.'],
                        ['icon' => 'GlobeAltIcon',    'title' => 'Federal Board Affiliated',  'desc' => 'Registered with the Federal Board of Intermediate & Secondary Education, Islamabad (FBISE) for Matric and FSc / FA / ICS programs.'],
                        ['icon' => 'UserGroupIcon',   'title' => 'Dedicated Faculty',         'desc' => 'Sixty-eight teachers, many holding Master\'s and M.Phil degrees — mentoring students in both Urdu and English medium.'],
                        ['icon' => 'TrophyIcon',      'title' => 'Top in Gilgit-Baltistan',   'desc' => 'Ranked among the top 3 higher secondary schools in Gilgit-Baltistan region for the past six years running — by FBISE results.'],
                    ],
                ],
            ],
            [
                'type' => 'feature_grid',
                'data' => [
                    'eyebrow' => 'Academic Programs',
                    'heading' => 'Four paths. One foundation.',
                    'items'   => [
                        ['icon' => 'BookOpenIcon',         'title' => 'Matric (Class 9–10)',  'desc' => 'Science & Arts streams. Foundation for professional careers.'],
                        ['icon' => 'BeakerIcon',           'title' => 'FSc Pre-Medical',      'desc' => 'Physics, Chemistry, Biology — path to medicine.'],
                        ['icon' => 'LightBulbIcon',        'title' => 'FSc Pre-Engineering',  'desc' => 'Math, Physics, Chemistry — engineering track.'],
                        ['icon' => 'ComputerDesktopIcon',  'title' => 'ICS / FA',             'desc' => 'Computer Science and Arts specializations.'],
                    ],
                ],
            ],
            [
                'type' => 'testimonials',
                'data' => [
                    'eyebrow' => 'Voices of Our Community',
                    'heading' => 'Stories of success.',
                    'items'   => [
                        ['name' => 'Dr. Ghulam Hussain', 'role' => 'Alumnus — Matric 1998 · Cardiologist, AKU Karachi', 'quote' => 'This school gave me wings. My teachers in Skardu saw in me what I could not see in myself. Every success in my career traces back to these mountain classrooms.'],
                        ['name' => 'Col. Muhammad Ali',  'role' => 'Alumnus — FSc 1989 · Pakistan Army (Retd.)',         'quote' => 'Seventy-two years of legacy is not a number — it is a responsibility. This school builds character first, and knowledge second. Both serve you for life.'],
                        ['name' => 'Wazir Ahmed',        'role' => 'Parent — Grade 10 student',                          'quote' => 'As a father, I see my son grow not just in marks but in manners. The discipline, the prayers, the respect for elders — all nurtured here. A school worthy of our sons.'],
                    ],
                ],
            ],
            [
                'type' => 'cta',
                'data' => [
                    'eyebrow'   => 'Admissions 2026–27 · Now Open',
                    'heading'   => 'Join the legacy. Write your chapter.',
                    'body'      => 'Applications close May 31, 2026. Whether in Matric, FSc Pre-Medical, Pre-Engineering, or ICS — your future begins here.',
                    'cta_label' => 'Start Your Application',
                    'cta_url'   => '/admissions',
                ],
            ],
        ];

        $this->insertBlocks('home', $blocks);
    }

    private function seedResultsBlocks(): void
    {
        if (PageBlock::where('page_key', 'results')->exists()) return;

        $blocks = [
            [
                'type' => 'stats_strip',
                'data' => [
                    'heading' => 'Most recent FBISE results',
                    'items'   => [
                        ['icon' => 'TrophyIcon',     'label' => 'Matric Pass Rate',  'value' => '94.2', 'suffix' => '%'],
                        ['icon' => 'StarIcon',       'label' => 'A+ Grades',          'value' => '28',   'suffix' => ''],
                        ['icon' => 'AcademicCapIcon','label' => 'GB Ranking',         'value' => '#2',   'suffix' => ''],
                        ['icon' => 'CheckBadgeIcon', 'label' => 'FSc Pass Rate',      'value' => '91.8', 'suffix' => '%'],
                    ],
                ],
            ],
            [
                'type' => 'toppers_table',
                'data' => [
                    'eyebrow' => 'FBISE Matric & Intermediate · 2026',
                    'heading' => 'Our top performers.',
                    'items'   => [
                        ['rank' => 1, 'name' => 'Muhammad Abbas Ali',    'class' => 'Matric Science · Class 10',     'marks' => '1087/1100', 'percent' => '98.8'],
                        ['rank' => 2, 'name' => 'Sultan Mehdi Abbasi',   'class' => 'Matric Science · Class 10',     'marks' => '1082/1100', 'percent' => '98.4'],
                        ['rank' => 3, 'name' => 'Ali Raza Baltistani',   'class' => 'Matric Science · Class 10',     'marks' => '1078/1100', 'percent' => '98.0'],
                        ['rank' => 4, 'name' => 'Ghulam Hussain',        'class' => 'FSc Pre-Medical · Class 12',    'marks' => '1068/1100', 'percent' => '97.1'],
                        ['rank' => 5, 'name' => 'Wazir Ahmed Khan',      'class' => 'FSc Pre-Engineering · Class 12','marks' => '1061/1100', 'percent' => '96.5'],
                        ['rank' => 6, 'name' => 'Syed Mehdi Hussain',    'class' => 'Matric Arts · Class 10',        'marks' => '1054/1100', 'percent' => '95.8'],
                        ['rank' => 7, 'name' => 'Muhammad Ismail',       'class' => 'ICS · Class 12',                'marks' => '1048/1100', 'percent' => '95.3'],
                        ['rank' => 8, 'name' => 'Ahmed Hassan',          'class' => 'FSc Pre-Medical · Class 12',    'marks' => '1041/1100', 'percent' => '94.6'],
                    ],
                ],
            ],
            [
                'type' => 'rich_text',
                'data' => [
                    'eyebrow' => 'Six years running',
                    'heading' => 'Top 3 in Gilgit-Baltistan.',
                    'body'    => "Our school has consistently placed among the top three higher secondary institutions in Gilgit-Baltistan for the past six years — by FBISE Matric pass rate, A+ grade count, and average marks per student.\n\nWhat the numbers don't capture is the journey: students arriving from villages across the Skardu, Shigar, and Kharmang valleys, often the first in their families to attend higher secondary school, and leaving as confident young men ready for medicine, engineering, computer science, and the arts.\n\nFor full board results, individual mark sheets, or to verify a transcript, please contact the Examinations Office during regular hours.",
                ],
            ],
            [
                'type' => 'cta',
                'data' => [
                    'eyebrow'   => 'Be next year\'s topper',
                    'heading'   => 'Your name belongs on this list.',
                    'body'      => 'Admissions for the 2026–27 academic year are now open.',
                    'cta_label' => 'Apply Now',
                    'cta_url'   => '/admissions',
                ],
            ],
        ];

        $this->insertBlocks('results', $blocks);
    }

    private function seedNewHeroes(): void
    {
        $heroes = [
            'home' => [
                'hero_eyebrow'      => '',
                'hero_title'        => 'Home',
                'hero_title_accent' => '',
                'hero_subtitle'     => 'Home page hero is the rotating slider, not this banner. (Edit slides under Hero Slider.)',
                'hero_style'        => 'emerald-night',
            ],
            'results' => [
                'hero_eyebrow'      => 'FBISE Board Results',
                'hero_title'        => 'Excellence',
                'hero_title_accent' => 'on the board.',
                'hero_subtitle'     => 'Year after year, our students rise to the top — both at home and across Pakistan.',
                'hero_style'        => 'amber-dawn',
                'meta_description'  => 'FBISE Matric and FSc/FA/ICS board results, top performers, and academic rankings for GBHSS No.1 Skardu.',
            ],
        ];

        foreach ($heroes as $key => $data) {
            PageMeta::firstOrCreate(['page_key' => $key], $data);
        }
    }

    private function insertBlocks(string $pageKey, array $blocks): void
    {
        foreach ($blocks as $i => $block) {
            PageBlock::create([
                'page_key'   => $pageKey,
                'type'       => $block['type'],
                'data'       => $block['data'],
                'sort_order' => $i,
                'is_active'  => true,
            ]);
        }
    }
}
