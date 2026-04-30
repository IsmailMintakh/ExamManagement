<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoQuestionsSeeder extends Seeder
{
    public function run(): void
    {
        $ddo = User::whereHas('roles', fn ($q) => $q->where('name', 'super-admin'))->first();
        if (!$ddo) {
            $this->command?->warn('No super-admin user found. Skipping demo questions.');
            return;
        }

        $difficulties = ['easy', 'medium', 'hard'];

        $subjects = Subject::all();
        foreach ($subjects as $subject) {
            // 30 MCQs per subject
            for ($i = 1; $i <= 30; $i++) {
                $correct = rand(0, 3);
                Question::firstOrCreate(
                    ['question_text' => "{$subject->name} MCQ #{$i}: Which option is correct for question {$i}?"],
                    [
                        'school_id' => null,
                        'subject_id' => $subject->id,
                        'school_class_id' => null,
                        'created_by' => $ddo->id,
                        'type' => 'mcq',
                        'difficulty' => $difficulties[array_rand($difficulties)],
                        'options' => [
                            ['text' => "Option A for {$subject->code} Q{$i}", 'is_correct' => $correct === 0],
                            ['text' => "Option B for {$subject->code} Q{$i}", 'is_correct' => $correct === 1],
                            ['text' => "Option C for {$subject->code} Q{$i}", 'is_correct' => $correct === 2],
                            ['text' => "Option D for {$subject->code} Q{$i}", 'is_correct' => $correct === 3],
                        ],
                        'correct_answer' => "Option " . chr(65 + $correct) . " for {$subject->code} Q{$i}",
                        'marks' => 1,
                        'topic' => 'Chapter ' . ceil($i / 6),
                        'is_active' => true,
                    ]
                );
            }

            // 15 Short Answer per subject
            for ($i = 1; $i <= 15; $i++) {
                Question::firstOrCreate(
                    ['question_text' => "{$subject->name} Short #{$i}: Explain briefly concept {$i} related to {$subject->name}."],
                    [
                        'school_id' => null,
                        'subject_id' => $subject->id,
                        'school_class_id' => null,
                        'created_by' => $ddo->id,
                        'type' => 'short_answer',
                        'difficulty' => $difficulties[array_rand($difficulties)],
                        'correct_answer' => "Model answer for short question {$i} in {$subject->name}.",
                        'marks' => 2,
                        'topic' => 'Chapter ' . ceil($i / 3),
                        'is_active' => true,
                    ]
                );
            }

            // 8 Long Answer per subject
            for ($i = 1; $i <= 8; $i++) {
                Question::firstOrCreate(
                    ['question_text' => "{$subject->name} Long #{$i}: Describe in detail the topic related to concept {$i} in {$subject->name}, with examples."],
                    [
                        'school_id' => null,
                        'subject_id' => $subject->id,
                        'school_class_id' => null,
                        'created_by' => $ddo->id,
                        'type' => 'long_answer',
                        'difficulty' => $difficulties[array_rand($difficulties)],
                        'correct_answer' => "Detailed model answer for long question {$i} in {$subject->name}, covering definitions, examples, and applications.",
                        'marks' => 5,
                        'topic' => 'Chapter ' . ceil($i / 2),
                        'is_active' => true,
                    ]
                );
            }

            // 10 True/False per subject
            for ($i = 1; $i <= 10; $i++) {
                $isTrue = (bool) rand(0, 1);
                Question::firstOrCreate(
                    ['question_text' => "{$subject->name} T/F #{$i}: Statement {$i} about {$subject->name} is correct."],
                    [
                        'school_id' => null,
                        'subject_id' => $subject->id,
                        'school_class_id' => null,
                        'created_by' => $ddo->id,
                        'type' => 'true_false',
                        'difficulty' => $difficulties[array_rand($difficulties)],
                        'options' => [
                            ['text' => 'True', 'is_correct' => $isTrue],
                            ['text' => 'False', 'is_correct' => !$isTrue],
                        ],
                        'correct_answer' => $isTrue ? 'True' : 'False',
                        'marks' => 1,
                        'topic' => 'Chapter ' . ceil($i / 3),
                        'is_active' => true,
                    ]
                );
            }

            // 8 Fill in the Blank per subject
            for ($i = 1; $i <= 8; $i++) {
                Question::firstOrCreate(
                    ['question_text' => "{$subject->name} Fill #{$i}: The ____ is an important concept in {$subject->name}."],
                    [
                        'school_id' => null,
                        'subject_id' => $subject->id,
                        'school_class_id' => null,
                        'created_by' => $ddo->id,
                        'type' => 'fill_blank',
                        'difficulty' => $difficulties[array_rand($difficulties)],
                        'correct_answer' => "key term {$i}",
                        'marks' => 1,
                        'topic' => 'Chapter ' . ceil($i / 2),
                        'is_active' => true,
                    ]
                );
            }
        }

        $this->command?->info('Demo question bank seeded: ~' . Question::count() . ' questions across ' . $subjects->count() . ' subjects.');
    }
}
