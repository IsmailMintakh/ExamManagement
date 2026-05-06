<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSchoolRequest;
use App\Http\Requests\UpdateSchoolRequest;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SchoolController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', School::class);

        $schools = School::query()
            ->when($request->has('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->has('status'), function ($query) use ($request) {
                if ($request->input('status') === 'active') {
                    $query->where('is_active', true);
                } elseif ($request->input('status') === 'inactive') {
                    $query->where('is_active', false);
                }
            })
            ->with('principal')
            ->withCount(['students', 'users', 'classes'])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Schools/Index', [
            'schools' => $schools,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', School::class);

        return Inertia::render('Schools/Create');
    }

    public function store(StoreSchoolRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('schools/logos', 'public');
        }

        if ($request->hasFile('principal_signature')) {
            $data['principal_signature'] = $request->file('principal_signature')->store('schools/signatures', 'public');
        }

        if ($request->hasFile('school_stamp')) {
            $data['school_stamp'] = $request->file('school_stamp')->store('schools/stamps', 'public');
        }

        if ($request->hasFile('exam_officer_signature')) {
            $data['exam_officer_signature'] = $request->file('exam_officer_signature')->store('schools/signatures', 'public');
        }

        School::create($data);

        return redirect()->route('schools.index')->with('success', 'School created successfully.');
    }

    public function show(School $school): Response
    {
        $this->authorize('view', $school);

        $school->load('principal');

        $classes = SchoolClass::where('school_id', $school->id)
            ->with('sections')
            ->withCount('students')
            ->ordered()
            ->get();

        $stats = [
            'classesCount' => $classes->count(),
            'studentsCount' => Student::where('school_id', $school->id)->active()->count(),
            'teachersCount' => User::where('school_id', $school->id)->role(['class-teacher', 'subject-teacher'])->count(),
        ];

        return Inertia::render('Schools/Show', [
            'school' => $school,
            'classes' => $classes,
            'stats' => $stats,
        ]);
    }

    public function edit(School $school): Response
    {
        $this->authorize('update', $school);

        return Inertia::render('Schools/Create', [
            'school' => $school,
        ]);
    }

    public function update(UpdateSchoolRequest $request, School $school): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            if ($school->logo) {
                Storage::disk('public')->delete($school->logo);
            }
            $data['logo'] = $request->file('logo')->store('schools/logos', 'public');
        }

        if ($request->hasFile('principal_signature')) {
            if ($school->principal_signature) {
                Storage::disk('public')->delete($school->principal_signature);
            }
            $data['principal_signature'] = $request->file('principal_signature')->store('schools/signatures', 'public');
        }

        if ($request->hasFile('school_stamp')) {
            if ($school->school_stamp) {
                Storage::disk('public')->delete($school->school_stamp);
            }
            $data['school_stamp'] = $request->file('school_stamp')->store('schools/stamps', 'public');
        }

        if ($request->hasFile('exam_officer_signature')) {
            if ($school->exam_officer_signature) {
                Storage::disk('public')->delete($school->exam_officer_signature);
            }
            $data['exam_officer_signature'] = $request->file('exam_officer_signature')->store('schools/signatures', 'public');
        }

        $school->update($data);

        return redirect()->route('schools.index')->with('success', 'School updated successfully.');
    }

    public function destroy(School $school): RedirectResponse
    {
        $this->authorize('delete', $school);

        $school->delete();

        return redirect()->route('schools.index')->with('success', 'School deleted successfully.');
    }
}
