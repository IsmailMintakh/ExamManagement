<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\FacultyMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class FacultyMemberController extends Controller
{
    public function index(Request $request): Response
    {
        $query = FacultyMember::query()
            ->orderByDesc('is_principal')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('search')) {
            $term = $request->string('search');
            $query->where(fn ($q) => $q->where('name', 'like', "%$term%")
                ->orWhere('designation', 'like', "%$term%")
                ->orWhere('department', 'like', "%$term%"));
        }
        if ($request->filled('department')) $query->where('department', $request->string('department'));

        return Inertia::render('Website/Faculty/Index', [
            'members'     => $query->paginate(24)->withQueryString(),
            'filters'     => $request->only(['search', 'department']),
            'departments' => FacultyMember::query()
                ->whereNotNull('department')
                ->where('department', '!=', '')
                ->distinct()
                ->orderBy('department')
                ->pluck('department'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Website/Faculty/Edit', [
            'member'      => null,
            'departments' => $this->departments(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('website/faculty', 'public');
        }
        unset($data['photo']);

        FacultyMember::create($data);

        return redirect()->route('website.faculty.index')->with('success', 'Faculty member added.');
    }

    public function edit(FacultyMember $faculty): Response
    {
        return Inertia::render('Website/Faculty/Edit', [
            'member'      => $faculty,
            'departments' => $this->departments(),
        ]);
    }

    public function update(Request $request, FacultyMember $faculty): RedirectResponse
    {
        $data = $this->validateData($request);

        if ($request->hasFile('photo')) {
            if ($faculty->photo_path) Storage::disk('public')->delete($faculty->photo_path);
            $data['photo_path'] = $request->file('photo')->store('website/faculty', 'public');
        }
        unset($data['photo']);

        $faculty->update($data);

        return redirect()->route('website.faculty.index')->with('success', 'Faculty member updated.');
    }

    public function destroy(FacultyMember $faculty): RedirectResponse
    {
        if ($faculty->photo_path) Storage::disk('public')->delete($faculty->photo_path);
        $faculty->delete();
        return back()->with('success', 'Faculty member removed.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name'             => ['required', 'string', 'max:120'],
            'designation'      => ['nullable', 'string', 'max:120'],
            'department'       => ['nullable', 'string', 'max:80'],
            'qualification'    => ['nullable', 'string', 'max:200'],
            'photo'            => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'bio'              => ['nullable', 'string', 'max:2000'],
            'email'            => ['nullable', 'email', 'max:120'],
            'phone'            => ['nullable', 'string', 'max:40'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
            'is_principal'     => ['nullable', 'boolean'],
            'is_featured'      => ['nullable', 'boolean'],
            'sort_order'       => ['nullable', 'integer'],
            'is_active'        => ['nullable', 'boolean'],
        ]);
    }

    private function departments(): array
    {
        return ['Administration', 'English', 'Urdu', 'Mathematics', 'Science', 'Computer Science', 'Islamiyat', 'Social Studies', 'Physical Education', 'Arts'];
    }
}
