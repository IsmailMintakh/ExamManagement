<?php

namespace App\Http\Controllers;

use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (!$user->isSuperAdmin() && !$user->isSchoolAdmin()) {
            abort(403, 'You do not have permission to manage settings.');
        }

        $school = null;
        if ($user->isSchoolAdmin()) {
            $school = School::find($user->school_id);
        } elseif ($user->isSuperAdmin() && $request->has('school_id')) {
            $school = School::find($request->input('school_id'));
        }

        $schools = $user->isSuperAdmin()
            ? School::active()->orderBy('name')->get(['id', 'name'])
            : [];

        return Inertia::render('Settings/Index', [
            'school' => $school,
            'schools' => $schools,
            'filters' => $request->only(['school_id']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (!$user->isSuperAdmin() && !$user->isSchoolAdmin()) {
            abort(403, 'You do not have permission to update settings.');
        }

        $validated = $request->validate([
            'school_id' => ['nullable', 'exists:schools,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'principal_signature' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:1024'],
            'school_stamp' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:1024'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,svg', 'max:2048'],
        ]);

        // Determine school: super admin can specify school_id, school admin uses their own
        if ($user->isSuperAdmin() && !empty($validated['school_id'])) {
            $school = School::findOrFail($validated['school_id']);
        } else {
            $school = School::findOrFail($user->school_id);
        }

        // School admins can only update their own school
        if ($user->isSchoolAdmin() && $school->id !== $user->school_id) {
            abort(403, 'You can only update settings for your own school.');
        }

        $updateData = [];

        if (isset($validated['name'])) $updateData['name'] = $validated['name'];
        if (isset($validated['address'])) $updateData['address'] = $validated['address'];
        if (isset($validated['phone'])) $updateData['phone'] = $validated['phone'];
        if (isset($validated['email'])) $updateData['email'] = $validated['email'];
        if (isset($validated['website'])) $updateData['website'] = $validated['website'];

        if ($request->hasFile('logo')) {
            if ($school->logo) {
                Storage::disk('public')->delete($school->logo);
            }
            $updateData['logo'] = $request->file('logo')->store('schools/logos', 'public');
        }

        if ($request->hasFile('principal_signature')) {
            if ($school->principal_signature) {
                Storage::disk('public')->delete($school->principal_signature);
            }
            $updateData['principal_signature'] = $request->file('principal_signature')->store('schools/signatures', 'public');
        }

        if ($request->hasFile('school_stamp')) {
            if ($school->school_stamp) {
                Storage::disk('public')->delete($school->school_stamp);
            }
            $updateData['school_stamp'] = $request->file('school_stamp')->store('schools/stamps', 'public');
        }

        $school->update($updateData);

        return redirect()->back()->with('success', 'Settings updated successfully.');
    }
}
