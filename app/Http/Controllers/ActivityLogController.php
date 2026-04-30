<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (!$user->isSuperAdmin() && !$user->isSchoolAdmin()) {
            abort(403, 'You do not have permission to view activity logs.');
        }

        $activities = Activity::query()
            ->when($request->has('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                      ->orWhere('subject_type', 'like', "%{$search}%");
                });
            })
            ->when($request->has('log_name'), function ($query) use ($request) {
                $query->where('log_name', $request->input('log_name'));
            })
            ->when($request->has('subject_type'), function ($query) use ($request) {
                $query->where('subject_type', $request->input('subject_type'));
            })
            ->when($request->has('causer_id'), function ($query) use ($request) {
                $query->where('causer_id', $request->input('causer_id'));
            })
            ->when($request->has('date_from'), function ($query) use ($request) {
                $query->whereDate('created_at', '>=', $request->input('date_from'));
            })
            ->when($request->has('date_to'), function ($query) use ($request) {
                $query->whereDate('created_at', '<=', $request->input('date_to'));
            })
            ->when(!$user->isSuperAdmin(), function ($query) use ($user) {
                // School admins only see activities related to their school's data
                $query->where(function ($q) use ($user) {
                    $q->where('causer_id', $user->id)
                      ->orWhereHas('causer', function ($causerQuery) use ($user) {
                          $causerQuery->where('school_id', $user->school_id);
                      });
                });
            })
            ->with('causer')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $logNames = Activity::distinct()->pluck('log_name')->filter();
        $subjectTypes = Activity::distinct()->pluck('subject_type')->filter()->map(function ($type) {
            return [
                'value' => $type,
                'label' => class_basename($type),
            ];
        });

        return Inertia::render('ActivityLog/Index', [
            'activities' => $activities,
            'logNames' => $logNames,
            'subjectTypes' => $subjectTypes,
            'filters' => $request->only(['search', 'log_name', 'subject_type', 'causer_id', 'date_from', 'date_to']),
        ]);
    }
}
