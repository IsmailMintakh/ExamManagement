<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Inertia\Inertia;
use Inertia\Response;

class PublicController extends Controller
{
    /**
     * Public ID card verification — anyone can scan the QR or enter an admission no.
     * No auth required.
     */
    public function verifyStudent(string $admission): Response
    {
        $student = Student::with(['school:id,name,address', 'schoolClass:id,name', 'section:id,name', 'academicSession:id,name'])
            ->where('admission_no', $admission)
            ->first();

        return Inertia::render('Public/VerifyStudent', [
            'valid' => $student !== null,
            'student' => $student ? [
                'name' => $student->name,
                'admission_no' => $student->admission_no,
                'roll_no' => $student->roll_no,
                'class_name' => $student->schoolClass?->name,
                'section_name' => $student->section?->name,
                'school_name' => $student->school?->name,
                'school_address' => $student->school?->address,
                'session' => $student->academicSession?->name,
                'status' => $student->status,
                'gender' => $student->gender,
            ] : null,
        ]);
    }

    public function home(): Response
    {
        return Inertia::render('Public/Home');
    }

    public function about(): Response
    {
        return Inertia::render('Public/About');
    }

    public function schools(): Response
    {
        return Inertia::render('Public/Schools');
    }

    public function academics(): Response
    {
        return Inertia::render('Public/Academics');
    }

    public function admissions(): Response
    {
        return Inertia::render('Public/Admissions');
    }

    public function faculty(): Response
    {
        return Inertia::render('Public/Faculty');
    }

    public function gallery(): Response
    {
        return Inertia::render('Public/Gallery');
    }

    public function news(): Response
    {
        return Inertia::render('Public/News');
    }

    public function results(): Response
    {
        return Inertia::render('Public/Results');
    }

    public function contact(): Response
    {
        return Inertia::render('Public/Contact');
    }
}
