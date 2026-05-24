<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Anyone with the exams.create permission may create exams (super-admin
        // and school-admin both qualify). The controller + ExamPolicy enforce
        // school-scoping so principals can only create exams for their own school.
        return (bool) $this->user()?->can('exams.create');
    }

    /**
     * Normalize empty strings to null so 'nullable' validation rules pass.
     */
    protected function prepareForValidation(): void
    {
        $nullableFields = [
            'grading_scale_id', 'start_date', 'end_date', 'description',
            'total_marks', 'passing_marks', 'passing_percentage',
            'min_subjects_to_pass', 'grace_marks', 'grace_marks_max_subjects',
            'marks_entry_deadline', 'exam_controller_id',
        ];
        $payload = [];
        foreach ($nullableFields as $field) {
            if ($this->input($field) === '') {
                $payload[$field] = null;
            }
        }
        if (!empty($payload)) {
            $this->merge($payload);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'exam_type_id' => ['required', 'exists:exam_types,id'],
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'grading_scale_id' => ['nullable', 'exists:grading_scales,id'],
            'term' => ['nullable', 'in:first,second,final'],
            'combine_previous_terms' => ['boolean'],
            'term_weights' => ['nullable', 'array'],
            'term_weights.first' => ['nullable', 'integer', 'min:0', 'max:100'],
            'term_weights.second' => ['nullable', 'integer', 'min:0', 'max:100'],
            'term_weights.final' => ['nullable', 'integer', 'min:0', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'total_marks' => ['nullable', 'numeric', 'min:0'],
            'passing_marks' => ['nullable', 'numeric', 'min:0'],
            'passing_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'min_subjects_to_pass' => ['nullable', 'integer', 'min:0'],
            'main_subjects_must_pass' => ['boolean'],
            'all_subjects_must_pass' => ['boolean'],
            'grace_marks' => ['nullable', 'numeric', 'min:0'],
            'grace_marks_max_subjects' => ['nullable', 'integer', 'min:0'],
            'position_calculation' => ['nullable', 'string', 'in:section,class,school'],
            'passing_rules' => ['nullable', 'array'],
            'combination_rules' => ['nullable', 'array'],
            'apply_to_all_schools' => ['boolean'],
            'applicable_class_ids' => ['nullable', 'array'],
            'applicable_class_ids.*' => ['exists:school_classes,id'],
            // Marks can only be entered after the exam concludes — guard against
            // setting a deadline before the exam window even ends.
            'marks_entry_deadline' => ['nullable', 'date', 'after_or_equal:end_date'],
            'exam_controller_id' => ['nullable', 'exists:users,id'],
            'selected_school_ids' => ['nullable', 'array'],
            'selected_school_ids.*' => ['exists:schools,id'],
            'subjects' => ['nullable', 'array'],
            'subjects.*.subject_id' => ['required_with:subjects', 'exists:subjects,id'],
            'subjects.*.school_class_id' => ['required_with:subjects', 'exists:school_classes,id'],
            'subjects.*.total_marks' => ['required_with:subjects', 'numeric', 'min:0'],
            'subjects.*.passing_marks' => ['required_with:subjects', 'numeric', 'min:0'],
            // Per-subject paper date must fall inside the exam window.
            'subjects.*.exam_date' => ['nullable', 'date', 'after_or_equal:start_date', 'before_or_equal:end_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'The exam end date must be the same as or after the start date.',
            'marks_entry_deadline.after_or_equal' => 'The marks-entry deadline must be on or after the exam end date.',
            'subjects.*.exam_date.after_or_equal' => 'Each subject paper date must fall on or after the exam start date.',
            'subjects.*.exam_date.before_or_equal' => 'Each subject paper date must fall on or before the exam end date.',
        ];
    }

    /**
     * Reject "combine previous terms" unless this is the final-term exam and
     * the three weights add up to 100. We do this after validate() so the
     * standard rules fire first.
     */
    public function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $validator->after(function ($v) {
            if (!$this->boolean('combine_previous_terms')) return;
            if ($this->input('term') !== 'final') {
                $v->errors()->add('combine_previous_terms',
                    'Only the final-term exam can combine previous terms. Set term = "Final" first.');
                return;
            }
            $w = $this->input('term_weights', []);
            $sum = (int) ($w['first'] ?? 0) + (int) ($w['second'] ?? 0) + (int) ($w['final'] ?? 0);
            if ($sum !== 100) {
                $v->errors()->add('term_weights',
                    "Term weights must add up to 100 (currently {$sum}).");
            }
        });
    }
}
