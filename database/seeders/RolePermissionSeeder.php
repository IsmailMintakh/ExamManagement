<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Schools
            'schools.view', 'schools.create', 'schools.edit', 'schools.delete',
            // Academic Sessions
            'sessions.view', 'sessions.create', 'sessions.edit', 'sessions.delete',
            // Subjects
            'subjects.view', 'subjects.create', 'subjects.edit', 'subjects.delete',
            // Classes
            'classes.view', 'classes.create', 'classes.edit', 'classes.delete',
            // Sections
            'sections.view', 'sections.create', 'sections.edit', 'sections.delete',
            // Students
            'students.view', 'students.create', 'students.edit', 'students.delete', 'students.import',
            // Users
            'users.view', 'users.create', 'users.edit', 'users.delete', 'users.assign-roles',
            // Exams
            'exams.view', 'exams.create', 'exams.edit', 'exams.delete', 'exams.publish',
            // Exam Types
            'exam-types.view', 'exam-types.create', 'exam-types.edit', 'exam-types.delete',
            // Marks
            'marks.view', 'marks.enter', 'marks.edit', 'marks.submit', 'marks.verify',
            // Results
            'results.view', 'results.generate', 'results.submit', 'results.finalize', 'results.review',
            // Reports
            'reports.view', 'reports.export',
            // Analytics & Insights
            'analytics.view', 'insights.view',
            // Grading
            'grading.view', 'grading.create', 'grading.edit', 'grading.delete',
            // Settings
            'settings.view', 'settings.edit',
            // Activity Log
            'activity.view',
            // Teacher assignments
            'teacher-assignments.view', 'teacher-assignments.create', 'teacher-assignments.edit', 'teacher-assignments.delete',
            // Student / Parent portal
            'results.view-own', 'results.view-children', 'profile.view',
            // Question Bank & Paper Generator
            'questions.view', 'questions.create', 'questions.edit', 'questions.delete',
            'papers.view', 'papers.create', 'papers.delete',
            // Certificates
            'certificates.view', 'certificates.generate', 'certificates.revoke',
            'certificates.templates.view', 'certificates.templates.manage',
            // Exam Scheduling (date sheets, rooms, invigilators, admit cards)
            'scheduling.view', 'scheduling.manage',
            // Supplementary Exams
            'supplementary.view', 'supplementary.manage',
            // Student Transfers
            'transfers.view', 'transfers.create', 'transfers.approve',
            // Student Promotion
            'promotion.view', 'promotion.process',
            // Multi-Year Archive
            'archive.view',
            // Result Card Templates
            'result-card-templates.view', 'result-card-templates.manage',
            // Roles & Permissions
            'roles.view', 'roles.manage',
            // Notifications
            'notifications.view',
            // Public Website CMS (DDO only — manages homepage content, slider, news, etc.)
            'website.view', 'website.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // --------- Super Admin (DDO) — gets everything ---------
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdmin->syncPermissions(Permission::all());

        // --------- School Admin (Principal) — full school scope ---------
        $schoolAdmin = Role::firstOrCreate(['name' => 'school-admin']);
        $schoolAdmin->syncPermissions([
            'schools.view',
            'sessions.view',
            'subjects.view',
            'classes.view', 'classes.create', 'classes.edit',
            'sections.view', 'sections.create', 'sections.edit',
            'students.view', 'students.create', 'students.edit', 'students.delete', 'students.import',
            'users.view', 'users.create', 'users.edit',
            'exams.view', 'exams.create', 'exams.edit', 'exams.delete', 'exams.publish',
            'exam-types.view',
            'marks.view', 'marks.verify',
            'results.view', 'results.generate', 'results.submit',
            'reports.view', 'reports.export',
            'analytics.view', 'insights.view',
            'grading.view',
            'activity.view',
            'teacher-assignments.view', 'teacher-assignments.create', 'teacher-assignments.edit', 'teacher-assignments.delete',
            'questions.view', 'questions.create', 'questions.edit', 'questions.delete',
            'papers.view', 'papers.create', 'papers.delete',
            'certificates.view', 'certificates.generate', 'certificates.revoke',
            'certificates.templates.view', 'certificates.templates.manage',
            'scheduling.view', 'scheduling.manage',
            'supplementary.view', 'supplementary.manage',
            'transfers.view', 'transfers.create', 'transfers.approve',
            'promotion.view', 'promotion.process',
            'archive.view',
            'result-card-templates.view',
            'notifications.view',
            'profile.view',
        ]);

        // --------- Class Teacher ---------
        // Class teachers manage ONE section (their own). All section data is reached via the
        // "My Class" hub (/my-class). They do NOT need access to global Exams, Results,
        // Subjects, Classes, Sections, Reports, etc. — those would show data outside their
        // section and break role isolation.
        $classTeacher = Role::firstOrCreate(['name' => 'class-teacher']);
        $classTeacher->syncPermissions([
            // Student management for THEIR section (controller scopes by section)
            'students.view', 'students.create', 'students.edit', 'students.delete', 'students.import',
            // Marks entry (only for subjects they teach as well — scoped at controller)
            'marks.view', 'marks.enter', 'marks.submit',
            // Certificates (their students only — scoped at controller)
            'certificates.view', 'certificates.generate',
            // Personal
            'notifications.view', 'profile.view',
        ]);

        // --------- Subject Teacher ---------
        // Subject teachers ONLY enter marks for assigned (subject, section) pairs and manage
        // their own questions/papers. They have NO access to school-wide views — no exams list,
        // no results page, no reports, no scheduling, no master data.
        $subjectTeacher = Role::firstOrCreate(['name' => 'subject-teacher']);
        $subjectTeacher->syncPermissions([
            // Marks entry (controller scopes to their assignments only)
            'marks.view', 'marks.enter', 'marks.submit',
            // Question Bank & Paper Generator (their own content)
            'questions.view', 'questions.create', 'questions.edit', 'questions.delete',
            'papers.view', 'papers.create', 'papers.delete',
            // Personal
            'notifications.view', 'profile.view',
        ]);

        // --------- Student ---------
        $student = Role::firstOrCreate(['name' => 'student']);
        $student->syncPermissions([
            'results.view-own',
            'profile.view',
            'notifications.view',
        ]);

        // --------- Parent ---------
        $parent = Role::firstOrCreate(['name' => 'parent']);
        $parent->syncPermissions([
            'results.view-children',
            'profile.view',
            'notifications.view',
        ]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
