<?php

namespace App\Http\Controllers;

use App\Models\BoardExam;
use App\Models\BoardResult;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public FBISE result lookup.
 *
 * Students / parents open this page WITHOUT logging in, punch a board
 * roll number, pick the school + exam, and get their result card back.
 *
 * Only exams that have been LOCKED (admin has finalised entry) are
 * searchable — draft/unlocked exams stay private so half-entered data
 * never leaks. Rate-limited via a throttled route to prevent scraping.
 */
class PublicBoardResultController extends Controller
{
    /** Render the public search form. Lists only locked exams. */
    public function search(Request $request): Response
    {
        // Group locked exams by school so the picker is friendly.
        $exams = BoardExam::query()
            ->where('is_locked', true)
            ->with(['school:id,name', 'schoolClass:id,name', 'academicSession:id,name'])
            ->orderByDesc('announced_on')
            ->get(['id', 'school_id', 'school_class_id', 'academic_session_id',
                   'title', 'level', 'board_name', 'announced_on']);

        return Inertia::render('Public/BoardResultSearch', [
            'exams' => $exams,
        ]);
    }

    /**
     * Look up a result. Returns the result payload (or a null result +
     * "not found" flag) — the frontend renders the card inline so the
     * student doesn't have to download a PDF just to see their grade.
     */
    public function lookup(Request $request)
    {
        $data = $request->validate([
            'board_exam_id'  => ['required', 'exists:board_exams,id'],
            'board_roll_no'  => ['required', 'string', 'max:32'],
        ]);

        $exam = BoardExam::where('id', $data['board_exam_id'])
            ->where('is_locked', true)   // still gate on locked in case URL is tampered
            ->with(['school:id,name', 'schoolClass:id,name', 'academicSession:id,name'])
            ->firstOrFail();

        // Normalise the roll number — trim + collapse whitespace + lowercase
        // for comparison. Actual stored value stays as-is for display.
        $rollInput = strtolower(preg_replace('/\s+/', '', $data['board_roll_no']));

        $result = BoardResult::where('board_exam_id', $exam->id)
            ->get()
            ->first(fn ($r) => strtolower(preg_replace('/\s+/', '', (string) $r->board_roll_no)) === $rollInput);

        if (!$result) {
            return response()->json([
                'found' => false,
                'message' => "No result found for board roll “{$data['board_roll_no']}” under {$exam->title}.",
            ]);
        }

        $result->load(['student:id,name,father_name,roll_no,admission_no,date_of_birth', 'subjects.subject:id,name,code']);

        return response()->json([
            'found'  => true,
            'exam'   => $exam,
            'result' => $result,
        ]);
    }
}
