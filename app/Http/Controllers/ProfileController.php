<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Upload a signature image — used to auto-sign attendance sheets,
     * award lists, mark sheets, etc. Accepts PNG / JPG. A transparent PNG
     * (pen-on-paper scanned + background removed) prints best.
     */
    public function uploadSignature(Request $request): RedirectResponse
    {
        $request->validate([
            'signature' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:1024'],
        ]);

        $user = $request->user();
        if ($user->signature_image) {
            Storage::disk('public')->delete($user->signature_image);
        }
        $user->update([
            'signature_image' => $request->file('signature')->store('signatures', 'public'),
        ]);

        return Redirect::route('profile.edit')->with('success', 'Signature uploaded — it will appear on your signed reports.');
    }

    /** Remove the signature image. */
    public function deleteSignature(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->signature_image) {
            Storage::disk('public')->delete($user->signature_image);
            $user->update(['signature_image' => null]);
        }
        return Redirect::route('profile.edit')->with('success', 'Signature removed.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
