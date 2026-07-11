<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * DDO super-admin impersonation. Lets the DDO pick a school and be
 * logged in as that school's school-admin (principal) with a single
 * click. Storing the original DDO user_id in session gives us a
 * one-click "Return to my DDO account" route.
 *
 * Security notes:
 *   - Only a genuine super-admin (currently authenticated OR stored
 *     as the impersonator) can trigger these endpoints.
 *   - We NEVER persist impersonation state to the database — only the
 *     session — so a stolen session cookie is the only attack surface,
 *     same as a normal login.
 *   - We regenerate the session on both enter/leave to defeat session
 *     fixation attacks across the auth flip.
 */
class ImpersonationController extends Controller
{
    /**
     * DDO clicks a school in the "Viewing / Impersonate" dropdown →
     * we find that school's principal (or the first active school-admin
     * for the school) and log the DDO in as them.
     */
    public function start(Request $request, School $school): RedirectResponse
    {
        $me = $request->user();
        // Both a genuine DDO AND an already-impersonating DDO can switch
        // schools without first returning to their own account. The
        // stored impersonator_id gates the second case.
        $originalId = session('impersonator_id') ?? $me?->id;
        $original = $originalId ? User::find($originalId) : null;
        abort_unless($original?->isSuperAdmin(), 403, 'Only DDO super-admins can impersonate a school.');

        // Prefer the school's principal_user_id if the School model has
        // one, else the first active school-admin scoped to this school.
        $target = User::query()
            ->where('school_id', $school->id)
            ->whereHas('roles', fn ($q) => $q->where('name', 'school-admin'))
            ->orderByDesc('id')
            ->first();

        if (!$target) {
            return back()->with('error',
                "No school-admin (principal) account exists for {$school->name}. Create one first, then impersonate.");
        }

        // Preserve the ORIGINAL DDO id — even if the DDO is already
        // impersonating another school, we still keep the very first
        // impersonator id so "Return to DDO" always lands back home.
        session(['impersonator_id' => $original->id]);
        session()->forget('viewing_school_id'); // impersonated user IS the school admin now

        Auth::login($target, remember: false);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success',
            "Now signed in as {$target->name} ({$school->name}). Use \"Return to DDO account\" to switch back.");
    }

    /**
     * Return to the original DDO account. Ends impersonation, restores
     * the DDO user, and rotates the session id.
     */
    public function leave(Request $request): RedirectResponse
    {
        $originalId = session('impersonator_id');
        abort_unless($originalId, 403, 'Not currently impersonating.');

        $original = User::find($originalId);
        abort_unless($original?->isSuperAdmin(), 403, 'Original account is no longer a super-admin.');

        Auth::login($original, remember: false);
        $request->session()->regenerate();
        session()->forget('impersonator_id');

        return redirect()->route('dashboard')->with('success',
            'Returned to your DDO account.');
    }
}
