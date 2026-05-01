<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['super-admin', 'school-admin', 'class-teacher']);
    }

    public function rules(): array
    {
        return [
            'admission_no' => ['required', 'string', 'max:50', 'unique:students,admission_no'],
            'roll_no' => ['nullable', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'guardian_phone' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['required', 'string', 'in:male,female,other'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:1024'],
            'address' => ['nullable', 'string', 'max:500'],
            'blood_group' => ['nullable', 'string', 'max:5'],
            'religion' => ['nullable', 'string', 'max:50'],
            // CNIC / B-Form: 13 digits, formatted as 12345-1234567-1 (with or without dashes).
            'cnic' => ['nullable', 'string', 'max:18', 'regex:/^[0-9]{5}-?[0-9]{7}-?[0-9]{1}$/'],
            'school_id' => ['required', 'exists:schools,id'],
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'section_id' => ['required', 'exists:sections,id'],
            'academic_session_id' => ['nullable', 'exists:academic_sessions,id'],
            'status' => ['nullable', 'string', 'in:active,inactive,transferred,graduated'],
        ];
    }
}
