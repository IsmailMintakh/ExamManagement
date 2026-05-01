<?php

namespace Database\Seeders;

use App\Models\FacultyMember;
use App\Models\GalleryAlbum;
use App\Models\NewsArticle;
use Illuminate\Database\Seeder;

/**
 * Sample news, gallery, and faculty data so the public website is not
 * empty on first install. The DDO can edit/delete everything via Website
 * admin pages after first login.
 *
 * Idempotent — uses firstOrCreate / count checks so re-running won't dupe.
 */
class WebsitePhase2Seeder extends Seeder
{
    public function run(): void
    {
        // ─── News articles ─────────────────────────────
        if (NewsArticle::count() === 0) {
            $news = [
                [
                    'title'          => 'Three Students Secure Top Positions in FBISE Matric Board 2026',
                    'category'       => 'Achievement',
                    'excerpt'        => 'Muhammad Abbas Ali (1st), Sultan Mehdi (4th), and Ali Raza (9th) — all from our Matric Batch 2026 — have brought honor to Skardu by securing top positions in the Federal Board.',
                    'body'           => "Three students from our Matric Class of 2026 have placed in the top ten of the Federal Board of Intermediate and Secondary Education (FBISE) results announced today.\n\nMuhammad Abbas Ali secured the 1st position nationwide with 1098 out of 1100 marks — the highest score ever achieved by a GBHSS Skardu student. Sultan Mehdi placed 4th with 1093 marks, and Ali Raza took the 9th position with 1086 marks.\n\nDDO Wazir Zamin Ali personally honored the students at a ceremony attended by the Chief Secretary of Gilgit-Baltistan. \"This is the result of seven decades of dedication by our teachers and the unwavering trust of Skardu's families,\" he said.\n\nAll three students have indicated they intend to apply for medical or engineering programs after FSc.",
                    'image_gradient' => 'from-emerald-700 to-emerald-950',
                    'is_featured'    => true,
                    'is_published'   => true,
                    'published_at'   => now()->subDays(2),
                ],
                [
                    'title'          => 'Annual Sports Gala 2026 Concludes at Municipal Ground',
                    'category'       => 'Event',
                    'excerpt'        => 'Three days of fierce but friendly competition across cricket, football, volleyball, and traditional polo. Class 10-A emerged as overall champions with 184 points.',
                    'body'           => "The 47th Annual Sports Gala wrapped up Sunday at the Skardu Municipal Ground after three days of intense competition. Over 500 students participated across 14 events.\n\nClass 10-A claimed the Overall Champions trophy with 184 points, narrowly edging out Class 11-Pre-Engineering (172 points). Notable individual performances came from Sultan Abbasi (gold in 100m and 200m sprints) and the Class 9-B cricket team (unbeaten through the tournament).\n\nThe traditional polo demonstration on the final day drew the largest crowd of the festival.",
                    'image_gradient' => 'from-amber-600 to-orange-800',
                    'is_published'   => true,
                    'published_at'   => now()->subDays(8),
                ],
                [
                    'title'          => 'Admissions for Matric & FSc (2026–27) Open Until May 31',
                    'category'       => 'Announcement',
                    'excerpt'        => 'Applications for Class 9 and Class 11 (all streams: Pre-Medical, Pre-Engineering, ICS, FA) are now being accepted at the school office.',
                    'body'           => "Admissions for the 2026–27 academic year are now open. Applications for Class 9 (Matric) and Class 11 (all FSc/FA/ICS streams) will be accepted at the school office from 9:00 AM to 2:00 PM, Monday through Saturday, until May 31, 2026.\n\nRequired documents:\n• Birth certificate / Form B (original + copy)\n• Domicile certificate of Gilgit-Baltistan\n• School leaving certificate from previous institution\n• Most recent academic results / transcript\n• Two passport-size photographs\n• Father's CNIC copy\n\nEntrance test will be held in the first week of June. Selected candidates will be notified by SMS and through the school website.",
                    'image_gradient' => 'from-sky-700 to-indigo-900',
                    'is_published'   => true,
                    'published_at'   => now()->subDays(15),
                ],
                [
                    'title'          => 'Sultan Abbasi Wins Bronze at National Physics Olympiad',
                    'category'       => 'Achievement',
                    'excerpt'        => 'Class 12 student Sultan Abbasi represented Gilgit-Baltistan at the National Physics Olympiad held in Islamabad and returned with the bronze medal.',
                    'body'           => "Sultan Abbasi from Class 12 (Pre-Engineering) brought home the bronze medal from the 2026 National Physics Olympiad held at the Pakistan Institute of Engineering and Applied Sciences (PIEAS), Islamabad.\n\nSultan was one of 84 finalists selected from over 12,000 applicants nationwide. His performance is the highest by a Gilgit-Baltistan student in the competition's 18-year history.\n\nSultan credits his physics teacher, Mr. Wazir Hassan, for his preparation. He plans to apply for undergraduate physics programs at LUMS and NUST.",
                    'image_gradient' => 'from-rose-600 to-pink-800',
                    'is_published'   => true,
                    'published_at'   => now()->subDays(22),
                ],
                [
                    'title'          => 'New Attendance Policy Effective May 1, 2026',
                    'category'       => 'Policy',
                    'excerpt'        => 'Students require a minimum of 75% attendance to sit for annual examinations. Parents will receive daily SMS updates.',
                    'body'           => "Effective May 1, 2026, all students must maintain a minimum attendance of 75% to be eligible to sit for annual examinations. Students falling below this threshold may be barred from exams pending approval of a leave application by the principal.\n\nA new digital attendance system has been deployed in all classrooms. Parents will receive an SMS notification each morning if their child is marked absent.\n\nThe policy was approved by the school management committee in consultation with the DDO and is aligned with FBISE recommendations.",
                    'image_gradient' => 'from-slate-700 to-slate-900',
                    'is_published'   => true,
                    'published_at'   => now()->subDays(28),
                ],
                [
                    'title'          => 'Library Wing Expansion — 2,000 New Titles Added',
                    'category'       => 'Announcement',
                    'excerpt'        => 'Our library now holds over 6,000 books. New additions include science reference texts, Urdu classics, Balti folk literature, and English fiction.',
                    'body'           => "The library expansion project, funded by an alumni donation drive, has added 2,000 new titles to the school library, bringing the total collection to over 6,000 volumes.\n\nKey additions:\n• Updated FBISE-aligned reference texts in physics, chemistry, biology, and mathematics\n• Complete works of Allama Iqbal, Faiz Ahmed Faiz, and Ahmed Faraz\n• A dedicated Balti folk literature section — the first of its kind in any GB school\n• English fiction by contemporary Pakistani authors including Mohsin Hamid and Kamila Shamsie\n\nLibrary hours have been extended to 6:00 PM on weekdays.",
                    'image_gradient' => 'from-violet-600 to-purple-800',
                    'is_published'   => true,
                    'published_at'   => now()->subDays(40),
                ],
            ];

            foreach ($news as $row) NewsArticle::create($row);
        }

        // ─── Gallery albums ─────────────────────────────
        if (GalleryAlbum::count() === 0) {
            GalleryAlbum::create([
                'title'       => 'Annual Sports Gala 2026',
                'slug'        => 'annual-sports-gala-2026',
                'description' => 'Three days of cricket, football, volleyball, and traditional polo at the Skardu Municipal Ground. Class 10-A emerged as overall champions.',
                'event_date'  => now()->subDays(8)->toDateString(),
                'sort_order'  => 1,
                'is_active'   => true,
            ]);

            GalleryAlbum::create([
                'title'       => 'Pakistan Day Celebrations',
                'slug'        => 'pakistan-day-celebrations',
                'description' => 'Flag-hoisting ceremony, national songs, and a speech contest on the ideology of Pakistan. DDO Wazir Zamin Ali delivered the keynote.',
                'event_date'  => now()->subDays(50)->toDateString(),
                'sort_order'  => 2,
                'is_active'   => true,
            ]);

            GalleryAlbum::create([
                'title'       => 'FBISE Matric 2026 — Results Day',
                'slug'        => 'fbise-matric-2026-results',
                'description' => 'Honoring the top performers of the Matric Class of 2026, with three students placing in the FBISE national top 10.',
                'event_date'  => now()->subDays(2)->toDateString(),
                'sort_order'  => 3,
                'is_active'   => true,
            ]);
        }

        // ─── Faculty ─────────────────────────────
        if (FacultyMember::count() === 0) {
            $faculty = [
                ['name' => 'Muhammad Ismail Khan',         'designation' => 'Principal',                       'department' => 'Administration',     'qualification' => 'M.Phil Mathematics, University of Karachi', 'years_experience' => 22, 'is_principal' => true,  'is_featured' => true,  'sort_order' => 1, 'bio' => 'Mr. Ismail Khan has served as Principal since 2020. A mathematician by training and an educator by calling, he believes character and curiosity matter more than marks.'],
                ['name' => 'Prof. Ghulam Hussain Baltistani', 'designation' => 'Vice Principal · Academics', 'department' => 'Urdu',               'qualification' => 'M.A Urdu',                            'years_experience' => 28, 'is_featured' => true, 'sort_order' => 2],
                ['name' => 'Syed Ali Mehdi',                'designation' => 'Dean of Student Affairs',         'department' => 'Science',            'qualification' => 'M.Sc Physics',                        'years_experience' => 24, 'sort_order' => 3],
                ['name' => 'Dr. Wazir Hassan',              'designation' => 'Head · Science Department',       'department' => 'Science',            'qualification' => 'Ph.D Chemistry, Quaid-i-Azam University', 'years_experience' => 17, 'is_featured' => true, 'sort_order' => 4],
                ['name' => 'Mr. Sultan Mehdi Abbasi',       'designation' => 'Senior Teacher',                  'department' => 'Science',            'qualification' => 'M.Sc Biology',                        'years_experience' => 15, 'sort_order' => 5],
                ['name' => 'Mr. Muhammad Abbas',            'designation' => 'Head · Computer Science',         'department' => 'Computer Science',   'qualification' => 'M.S Computer Science',                'years_experience' => 11, 'sort_order' => 6],
                ['name' => 'Mr. Ghulam Mehdi',              'designation' => 'Senior Teacher',                  'department' => 'English',            'qualification' => 'M.A English Literature',              'years_experience' => 19, 'sort_order' => 7],
                ['name' => 'Moulvi Syed Ahmed',             'designation' => 'Head · Islamiyat',                'department' => 'Islamiyat',          'qualification' => 'Shahadat-ul-Aalmia · M.A',            'years_experience' => 26, 'sort_order' => 8],
                ['name' => 'Mr. Hassan Raza',               'designation' => 'Mathematics Teacher',             'department' => 'Mathematics',        'qualification' => 'M.Sc Mathematics, B.Ed',              'years_experience' => 8,  'sort_order' => 9],
                ['name' => 'Mr. Karim Ali',                 'designation' => 'Physical Education',              'department' => 'Physical Education', 'qualification' => 'M.A Sports Sciences',                 'years_experience' => 12, 'sort_order' => 10],
            ];

            foreach ($faculty as $row) FacultyMember::create($row + ['is_active' => true]);
        }
    }
}
