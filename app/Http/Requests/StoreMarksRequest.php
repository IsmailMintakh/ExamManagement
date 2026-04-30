<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMarksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['super-admin', 'school-admin', 'class-teacher', 'subject-teacher']);
    }

    public function rules(): array
    {
        return [
            'exam_id' => ['required', 'exists:exams,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'section_id' => ['required', 'exists:sections,id'],
            'status' => ['required', 'string', 'in:draft,submitted'],
            'marks' => ['required', 'array', 'min:1'],
            'marks.*.student_id' => ['required', 'exists:students,id'],
            'marks.*.marks_obtained' => ['nullable', 'numeric', 'min:0'],
            'marks.*.is_absent' => ['boolean'],
            'marks.*.grace_marks' => ['nullable', 'numeric', 'min:0'],
            'marks.*.remarks' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'marks.*.marks_obtained.min' => 'Marks cannot be negative.',
            'marks.*.marks_obtained.numeric' => 'Marks must be a valid number.',
        ];
    }
}
