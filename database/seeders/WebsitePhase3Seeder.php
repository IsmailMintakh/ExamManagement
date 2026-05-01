<?php

namespace Database\Seeders;

use App\Models\PageBlock;
use Illuminate\Database\Seeder;

/**
 * Default page blocks for About, Academics, and Admissions pages.
 *
 * These mirror the previously-hardcoded content of those pages so that
 * after the migration to dynamic blocks, the public site looks the same
 * as before. The DDO can edit/reorder/hide each block from
 * Website → Pages Content.
 *
 * Idempotent — only seeds a page if it has zero blocks.
 */
class WebsitePhase3Seeder extends Seeder
{
    public function run(): void
    {
        $this->seedAbout();
        $this->seedAcademics();
        $this->seedAdmissions();
    }

    private function seedAbout(): void
    {
        if (PageBlock::where('page_key', 'about')->exists()) return;

        $blocks = [
            [
                'type' => 'mission_vision',
                'data' => [
                    'mission_title' => 'To educate, elevate, and empower.',
                    'mission_body'  => 'To provide the young men of Baltistan with a rigorous academic foundation, strong moral character, and a worldview that prepares them to lead — whether in the mountains of Skardu or the halls of global universities.',
                    'vision_title'  => 'The finest boys\' school in Gilgit-Baltistan.',
                    'vision_body'   => 'To be recognized — by parents, alumni, and the educational community — as the institution that sets the benchmark for academic excellence, character development, and regional pride in Gilgit-Baltistan.',
                ],
            ],
            [
                'type' => 'feature_grid',
                'data' => [
                    'eyebrow' => 'Our Values',
                    'heading' => 'What we stand for.',
                    'items'   => [
                        ['icon' => 'ShieldCheckIcon', 'title' => 'Integrity',    'desc' => 'Honesty in word and deed — in the classroom, on the field, and in life.'],
                        ['icon' => 'LightBulbIcon',  'title' => 'Innovation',   'desc' => 'Balancing timeless traditions with modern methods of learning.'],
                        ['icon' => 'HeartIcon',      'title' => 'Service',      'desc' => 'Our sons serve Baltistan, Pakistan, and the Ummah — with humility.'],
                        ['icon' => 'UserGroupIcon',  'title' => 'Brotherhood',  'desc' => 'Boys who walk these halls become brothers for life.'],
                    ],
                ],
            ],
            [
                'type' => 'timeline',
                'data' => [
                    'eyebrow' => 'Our Journey',
                    'heading' => 'Seven decades of milestones.',
                    'items'   => [
                        ['year' => '1954', 'title' => 'Foundation Laid',        'desc' => 'Established as Government Middle School, Skardu by the Ministry of Education, shortly after the inclusion of Baltistan into Pakistan. Initial enrollment: 47 students.'],
                        ['year' => '1967', 'title' => 'Upgraded to High School','desc' => 'Matriculation classes (9th and 10th) introduced. Affiliation with the Federal Board of Intermediate and Secondary Education (FBISE) secured.'],
                        ['year' => '1981', 'title' => 'Higher Secondary Status','desc' => 'FSc Pre-Medical and Pre-Engineering introduced. The school became the region\'s premier institution for post-matric education.'],
                        ['year' => '1998', 'title' => 'Science Block Inaugurated','desc' => 'Dedicated physics, chemistry, and biology laboratories built with federal grants. Over 200 alumni would go on to become medical doctors and engineers.'],
                        ['year' => '2014', 'title' => 'Computer Science Launched','desc' => 'ICS (Intermediate in Computer Science) stream added. Computer lab with 40 workstations and satellite internet installed — a first in Baltistan.'],
                        ['year' => '2022', 'title' => 'Golden Jubilee',        'desc' => 'Celebrated 50 years since upgrading to higher secondary. New library wing and sports complex inaugurated by the Chief Secretary, Gilgit-Baltistan.'],
                        ['year' => '2026', 'title' => 'Present Day',           'desc' => 'Over 1,200 students. Sixty-eight qualified teachers. Ranked #2 in Gilgit-Baltistan by FBISE Matric results. Seventy-two years strong.'],
                    ],
                ],
            ],
            [
                'type' => 'cta',
                'data' => [
                    'eyebrow'   => 'Ready to begin?',
                    'heading'   => 'Join the legacy.',
                    'body'      => 'Applications for the 2026–27 academic year are now open.',
                    'cta_label' => 'Start Your Application',
                    'cta_url'   => '/admissions',
                ],
            ],
        ];

        $this->insertBlocks('about', $blocks);
    }

    private function seedAcademics(): void
    {
        if (PageBlock::where('page_key', 'academics')->exists()) return;

        $blocks = [
            [
                'type' => 'feature_grid',
                'data' => [
                    'eyebrow' => 'Academic Programs',
                    'heading' => 'Four paths. One foundation.',
                    'items'   => [
                        ['icon' => 'BookOpenIcon',         'title' => 'Matric (Class 9–10)',   'desc' => 'Science & Arts streams. Foundation for professional careers.'],
                        ['icon' => 'BeakerIcon',           'title' => 'FSc Pre-Medical',       'desc' => 'Physics, Chemistry, Biology — path to medicine.'],
                        ['icon' => 'LightBulbIcon',        'title' => 'FSc Pre-Engineering',   'desc' => 'Math, Physics, Chemistry — engineering track.'],
                        ['icon' => 'ComputerDesktopIcon',  'title' => 'ICS / FA',              'desc' => 'Computer Science and Arts specializations.'],
                    ],
                ],
            ],
            [
                'type' => 'rich_text',
                'data' => [
                    'eyebrow' => 'Curriculum',
                    'heading' => 'Federal Board (FBISE) syllabus.',
                    'body'    => "Our curriculum is fully aligned with the Federal Board of Intermediate and Secondary Education (FBISE), Islamabad — the gold standard for academic rigor in Pakistan.\n\nInstruction is offered in both Urdu and English medium. Students sit FBISE board examinations at the Matric (Class 10) and FSc/FA/ICS (Class 12) levels — examinations recognized by every Pakistani university and most international universities.\n\nIn addition to the core syllabus, we offer enrichment activities including science fairs, debate clubs, mathematics olympiad preparation, and computer literacy programs.",
                ],
            ],
            [
                'type' => 'feature_grid',
                'data' => [
                    'eyebrow' => 'What you\'ll learn',
                    'heading' => 'Subjects we teach.',
                    'items'   => [
                        ['icon' => 'BookOpenIcon',  'title' => 'Languages',     'desc' => 'English, Urdu, and Arabic — both literature and grammar.'],
                        ['icon' => 'BeakerIcon',    'title' => 'Sciences',      'desc' => 'Physics, Chemistry, Biology with full lab work.'],
                        ['icon' => 'LightBulbIcon', 'title' => 'Mathematics',   'desc' => 'From foundational arithmetic to advanced calculus and statistics.'],
                        ['icon' => 'GlobeAltIcon',  'title' => 'Social Studies','desc' => 'Pakistan Studies, History, Geography, and Civics.'],
                        ['icon' => 'HeartIcon',     'title' => 'Islamiyat',     'desc' => 'Quran studies, Hadith, and Islamic ethics.'],
                        ['icon' => 'ComputerDesktopIcon','title' => 'Computer Science', 'desc' => 'Programming fundamentals, databases, and digital literacy.'],
                    ],
                ],
            ],
            [
                'type' => 'cta',
                'data' => [
                    'eyebrow'   => 'Apply Now',
                    'heading'   => 'Ready to learn?',
                    'body'      => 'Join one of these programs in the 2026–27 academic year.',
                    'cta_label' => 'Begin Your Application',
                    'cta_url'   => '/admissions',
                ],
            ],
        ];

        $this->insertBlocks('academics', $blocks);
    }

    private function seedAdmissions(): void
    {
        if (PageBlock::where('page_key', 'admissions')->exists()) return;

        $blocks = [
            [
                'type' => 'timeline',
                'data' => [
                    'eyebrow' => 'The Process',
                    'heading' => 'Five steps to enrollment.',
                    'items'   => [
                        ['year' => '01', 'title' => 'Submit Application', 'desc' => 'Visit the school office between 9:00 AM and 2:00 PM (Mon–Sat) to collect and submit the application form. Bring all required documents.'],
                        ['year' => '02', 'title' => 'Pay Application Fee','desc' => 'Application fee of ₨300 (non-refundable) payable at the office cashier or through our designated bank account.'],
                        ['year' => '03', 'title' => 'Sit Entrance Test',  'desc' => 'Entrance test held in the first week of June. Tests cover English, Urdu, Mathematics, and General Knowledge appropriate to the applied class level.'],
                        ['year' => '04', 'title' => 'Merit List Announced','desc' => 'Merit list posted on the school notice board and the website by June 25. Selected candidates notified by SMS.'],
                        ['year' => '05', 'title' => 'Complete Enrollment','desc' => 'Pay first month\'s fees by July 10 to confirm enrollment. Classes begin August 1.'],
                    ],
                ],
            ],
            [
                'type' => 'rich_text',
                'data' => [
                    'eyebrow' => 'Required Documents',
                    'heading' => 'What to bring.',
                    'body'    => "Please bring all of the following when submitting an application:\n\n• Birth Certificate / Form B (original + two photocopies)\n• Domicile Certificate of Gilgit-Baltistan\n• School Leaving Certificate from the previous institution\n• Most recent academic results / transcript\n• Two passport-size photographs (white background)\n• Father's (or guardian's) CNIC copy\n\nMissing documents may delay your application — please double-check before submitting.",
                ],
            ],
            [
                'type' => 'stats_strip',
                'data' => [
                    'heading' => 'At a glance',
                    'items'   => [
                        ['icon' => 'AcademicCapIcon',  'label' => 'Pass Rate',      'value' => '94.2', 'suffix' => '%'],
                        ['icon' => 'TrophyIcon',       'label' => 'Top in GB',      'value' => '#2',   'suffix' => ''],
                        ['icon' => 'UserGroupIcon',    'label' => 'Active Students','value' => '1248', 'suffix' => ''],
                        ['icon' => 'BuildingLibraryIcon','label' => 'Years',         'value' => '72',   'suffix' => '+'],
                    ],
                ],
            ],
            [
                'type' => 'cta',
                'data' => [
                    'eyebrow'   => 'Questions?',
                    'heading'   => 'Visit our campus.',
                    'body'      => 'Schedule a campus tour or call us during office hours. We\'re happy to answer any questions.',
                    'cta_label' => 'Contact Us',
                    'cta_url'   => '/contact',
                ],
            ],
        ];

        $this->insertBlocks('admissions', $blocks);
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
