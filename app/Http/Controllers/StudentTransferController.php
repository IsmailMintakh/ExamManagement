<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentTransfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StudentTransferController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user->can('transfers.view'), 403);

        $transfers = StudentTransfer::query()
            ->when(!$user->isSuperAdmin(), function ($q) use ($user) {
                $q->where(function ($qq) use ($user) {
                    $qq->where('from_school_id', $user->school_id)
                        ->orWhere('to_school_id', $user->school_id);
                });
            })
            ->with([
                'student',
                'fromSchool',
                'toSchool',
                'fromClass',
                'toClass',
                'initiatedBy',
            ])
            ->orderByDesc('initiated_at')
            ->paginate(15)
            ->withQueryString();

        // Stats
        $statsQuery = StudentTransfer::query()
            ->when(!$user->isSuperAdmin(), function ($q) use ($user) {
                $q->where(function ($qq) use ($user) {
                    $qq->where('from_school_id', $user->school_id)
                        ->orWhere('to_school_id', $user->school_id);
                });
            });

        $stats = [
            'pending' => (clone $statsQuery)->where('status', 'pending')->count(),
            'completed_this_month' => (clone $statsQuery)
                ->where('status', 'completed')
                ->whereMonth('completed_at', now()->month)
                ->whereYear('completed_at', now()->year)
                ->count(),
            'inter_school' => (clone $statsQuery)->where('type', 'inter_school')->count(),
            'intra_school' => (clone $statsQuery)->where('type', 'intra_school')->count(),
        ];

        return Inertia::render('Transfers/Index', [
            'transfers' => $transfers,
            'stats' => $stats,
            'currentSchoolId' => $user->school_id,
            'isSuperAdmin' => $user->isSuperAdmin(),
        ]);
    }

    public function create(Request $request, Student $student): Response
    {
        $user = $request->user();

        abort_unless($user->can('transfers.view'), 403);

        if (!$user->isSuperAdmin() && $student->school_id !== $user->school_id) {
            abort(403);
        }

        $student->load(['school', 'schoolClass', 'section', 'academicSession']);

        // Schools available as targets
        $schools = $user->isSuperAdmin()
            ? School::active()->orderBy('name')->get(['id', 'name'])
            : School::where('id', $user->school_id)->get(['id', 'name']);

        // All classes & sections for those schools
        $schoolIds = $schools->pluck('id');

        $classes = SchoolClass::query()
            ->whereIn('school_id', $schoolIds)
            ->active()
            ->ordered()
            ->get(['id', 'name', 'school_id']);

        $sections = Section::query()
            ->whereIn('school_class_id', $classes->pluck('id'))
            ->active()
            ->get(['id', 'name', 'school_class_id']);

        return Inertia::render('Transfers/Create', [
            'student' => $student,
            'schools' => $schools,
            'classes' => $classes,
            'sections' => $sections,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->can('transfers.create'), 403);

        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'to_school_id' => ['required', 'integer', 'exists:schools,id'],
            'to_class_id' => ['required', 'integer', 'exists:school_classes,id'],
            'to_section_id' => ['required', 'integer', 'exists:sections,id'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $student = Student::findOrFail($validated['student_id']);

        if (!$user->isSuperAdmin() && $student->school_id !== $user->school_id) {
            abort(403);
        }

        $isIntra = (int) $student->school_id === (int) $validated['to_school_id'];
        $type = $isIntra ? 'intra_school' : 'inter_school';

        // Intra-school by school admin / super admin: auto-approved
        $autoApprove = $isIntra && ($user->isSchoolAdmin() || $user->isSuperAdmin());
        $status = $autoApprove ? 'approved' : 'pending';

        $transfer = DB::transaction(function () use ($validated, $student, $type, $status, $autoApprove, $user) {
            $transfer = StudentTransfer::create([
                'student_id' => $student->id,
                'from_school_id' => $student->school_id,
                'to_school_id' => $validated['to_school_id'],
                'from_class_id' => $student->school_class_id,
                'to_class_id' => $validated['to_class_id'],
                'from_section_id' => $student->section_id,
                'to_section_id' => $validated['to_section_id'],
                'type' => $type,
                'status' => $status,
                'reason' => $validated['reason'],
                'initiated_by' => $user->id,
                'initiated_at' => now(),
                'approved_by' => $autoApprove ? $user->id : null,
                'approved_at' => $autoApprove ? now() : null,
            ]);

            // For intra-school by school-admin, complete immediately
            if ($autoApprove) {
                $this->applyTransfer($transfer);
            }

            return $transfer;
        });

        $message = $transfer->status === 'completed'
            ? 'Student transferred successfully.'
            : ($transfer->status === 'approved'
                ? 'Transfer approved and ready for completion.'
                : 'Transfer request submitted and is pending approval.');

        return redirect()->route('transfers.index')->with('success', $message);
    }

    public function approve(Request $request, StudentTransfer $transfer): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->can('transfers.approve'), 403);

        if ($transfer->status !== 'pending') {
            return redirect()->route('transfers.index')
                ->with('error', 'Only pending transfers can be approved.');
        }

        // Only super-admin or receiving school admin can approve
        if (!$user->isSuperAdmin() && $user->school_id !== $transfer->to_school_id) {
            abort(403);
        }

        $transfer->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        return redirect()->route('transfers.index')->with('success', 'Transfer approved.');
    }

    public function complete(Request $request, StudentTransfer $transfer): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->can('transfers.approve'), 403);

        if (!in_array($transfer->status, ['approved'], true)) {
            return redirect()->route('transfers.index')
                ->with('error', 'Only approved transfers can be completed.');
        }

        // Only super-admin or receiving school admin can complete
        if (!$user->isSuperAdmin() && $user->school_id !== $transfer->to_school_id) {
            abort(403);
        }

        DB::transaction(function () use ($transfer) {
            $this->applyTransfer($transfer);
        });

        return redirect()->route('transfers.index')->with('success', 'Transfer completed successfully.');
    }

    public function reject(Request $request, StudentTransfer $transfer): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->can('transfers.approve'), 403);

        if ($transfer->status !== 'pending') {
            return redirect()->route('transfers.index')
                ->with('error', 'Only pending transfers can be rejected.');
        }

        if (!$user->isSuperAdmin() && $user->school_id !== $transfer->to_school_id) {
            abort(403);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $transfer->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        return redirect()->route('transfers.index')->with('success', 'Transfer rejected.');
    }

    /**
     * Apply the transfer to the student record and mark transfer completed.
     */
    protected function applyTransfer(StudentTransfer $transfer): void
    {
        $student = Student::findOrFail($transfer->student_id);

        $oldSchoolId = $student->school_id;

        $student->update([
            'school_id' => $transfer->to_school_id,
            'school_class_id' => $transfer->to_class_id,
            'section_id' => $transfer->to_section_id,
            'transferred_from_school_id' => $oldSchoolId,
            'is_transferred' => true,
        ]);

        $transfer->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }
}
