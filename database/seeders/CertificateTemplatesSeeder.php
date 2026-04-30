<?php

namespace Database\Seeders;

use App\Models\CertificateTemplate;
use Illuminate\Database\Seeder;

class CertificateTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $designs = [
            [
                'key' => 'modern',
                'label' => 'Modern Corporate',
                'title' => 'CERTIFICATE',
                'primary' => '#1e3a8a',
                'accent' => '#f59e0b',
                'border' => 'modern',
            ],
            [
                'key' => 'gold',
                'label' => 'Gold Honors',
                'title' => 'CERTIFICATE',
                'primary' => '#1e293b',
                'accent' => '#f97316',
                'border' => 'modern',
            ],
            [
                'key' => 'graduation',
                'label' => 'Graduation',
                'title' => 'CERTIFICATE OF COMPLETION',
                'primary' => '#1e3a8a',
                'accent' => '#fbbf24',
                'border' => 'modern',
            ],
            [
                'key' => 'classic',
                'label' => 'Classic Elegant',
                'title' => 'Certificate of Achievement',
                'primary' => '#0f766e',
                'accent' => '#c9a227',
                'border' => 'classic',
            ],
        ];

        $types = [
            'merit' => 'For achieving Rank {rank} with {percentage}% (Grade {grade}) in {exam_name} of Academic Session {academic_session}. Your dedication and hard work are truly commendable.',
            'subject_topper' => 'For securing the highest marks in {subject_name} with {percentage}% in {exam_name}. Your outstanding performance sets an inspiring example.',
            'pass' => 'has satisfactorily completed the course of study and has achieved {percentage}% (Grade {grade}) in {exam_name} for the Academic Session {academic_session}.',
            'participation' => 'For actively participating in {exam_name} of Academic Session {academic_session}. Your effort and commitment to learning are deeply appreciated.',
            'special_achievement' => 'In recognition of exceptional performance and commendable conduct at {school_name} during the Academic Session {academic_session}.',
        ];

        // Create one template per (design, type) combination -> 20 templates total
        foreach ($types as $type => $body) {
            foreach ($designs as $i => $design) {
                CertificateTemplate::updateOrCreate(
                    [
                        'name' => $design['label'] . ' — ' . ucwords(str_replace('_', ' ', $type)),
                    ],
                    [
                        'school_id' => null,
                        'type' => $type,
                        'title_text' => $design['title'],
                        'body_text' => $body,
                        'primary_color' => $design['primary'],
                        'accent_color' => $design['accent'],
                        'orientation' => 'landscape',
                        'border_style' => $design['border'],
                        'design_layout' => $design['key'],
                        'is_default' => $i === 0, // first design is default per type
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
