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
            'marks_entry_deadline',
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
            'marks_entry_deadline' => ['nullable', 'date'],
            'selected_school_ids' => ['nullable', 'array'],
            'selected_school_ids.*' => ['exists:schools,id'],
            'subjects' => ['nullable', 'array'],
            'subjects.*.subject_id' => ['required_with:subjects', 'exists:subjects,id'],
            'subjects.*.school_class_id' => ['required_with:subjects', 'exists:school_classes,id'],
            'subjects.*.total_marks' => ['required_with:subjects', 'numeric', 'min:0'],
            'subjects.*.passing_marks' => ['required_with:subjects', 'numeric', 'min:0'],
            'subjects.*.exam_date' => ['nullable', 'date'],
        ];
    }
}
