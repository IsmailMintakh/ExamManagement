<?php

namespace App\Imports;

use App\Models\Student;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;

class StudentsImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;

    public function __construct(
        protected int $schoolId,
        protected int $schoolClassId,
        protected int $sectionId,
        protected int $academicSessionId,
    ) {}

    public function model(array $row): Student
    {
        return new Student([
            'admission_no' => $row['admission_no'],
            'roll_no' => $row['roll_no'] ?? null,
            'name' => $row['name'],
            'father_name' => $row['father_name'] ?? null,
            'mother_name' => $row['mother_name'] ?? null,
            'guardian_phone' => $row['guardian_phone'] ?? null,
            'date_of_birth' => $row['date_of_birth'] ?? null,
            'gender' => $row['gender'] ?? 'male',
            'address' => $row['address'] ?? null,
            'blood_group' => $row['blood_group'] ?? null,
            'religion' => $row['religion'] ?? null,
            'cnic'     => $row['cnic'] ?? null,
            'school_id' => $this->schoolId,
            'school_class_id' => $this->schoolClassId,
            'section_id' => $this->sectionId,
            'academic_session_id' => $this->academicSessionId,
            'status' => 'active',
        ]);
    }

    public function rules(): array
    {
        return [
            'admission_no' => 'required|unique:students,admission_no',
            'name' => 'required|string|max:255',
            'gender' => 'nullable|in:male,female,other',
        ];
    }
}
