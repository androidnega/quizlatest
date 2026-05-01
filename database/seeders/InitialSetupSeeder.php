<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InitialSetupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        $universityId = DB::table('universities')->insertGetId([
            'name' => 'Default University',
            'code' => 'DU',
            'is_active' => true,
            'settings' => json_encode([
                'proctoring_defaults' => [
                    'face_match_threshold' => 55,
                    'tab_switch_limit' => 3,
                    'copy_paste_blocked' => true,
                    'audio_monitoring' => true,
                    'camera_required' => true,
                    'screen_capture_interval_seconds' => 10,
                    'violation_actions' => [
                        'warn' => true,
                        'deduct' => false,
                        'autosubmit' => false,
                    ],
                ],
                'assessment_defaults' => [
                    'duration_minutes' => 30,
                    'auto_publish_results' => false,
                ],
            ]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $adminRoleId = DB::table('roles')->insertGetId([
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'System administrator role',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $coordinatorRoleId = DB::table('roles')->insertGetId([
            'name' => 'Coordinator',
            'slug' => 'coordinator',
            'description' => 'Faculty/department coordinator role',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $studentRoleId = DB::table('roles')->insertGetId([
            'name' => 'Student',
            'slug' => 'student',
            'description' => 'Student role',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $examinerPermissionId = DB::table('permissions')->insertGetId([
            'name' => 'Examiner',
            'slug' => 'examiner',
            'description' => 'Allows coordinator examiner capabilities per assigned courses',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $studentPermissionId = DB::table('permissions')->insertGetId([
            'name' => 'Student',
            'slug' => 'student',
            'description' => 'Allows student capabilities in assigned assessments',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('permission_role')->insert([
            [
                'permission_id' => $examinerPermissionId,
                'role_id' => $coordinatorRoleId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'permission_id' => $studentPermissionId,
                'role_id' => $studentRoleId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $adminUserId = DB::table('users')->insertGetId([
            'university_id' => $universityId,
            'name' => 'System Admin',
            'email' => 'admin',
            'index_number' => null,
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => $now,
            'password' => Hash::make('admin123'),
            'remember_token' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('role_user')->insert([
            'role_id' => $adminRoleId,
            'user_id' => $adminUserId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $facultyId = DB::table('faculties')->insertGetId([
            'university_id' => $universityId,
            'name' => 'Faculty of Computing',
            'code' => 'FC',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $departmentId = DB::table('departments')->insertGetId([
            'university_id' => $universityId,
            'faculty_id' => $facultyId,
            'name' => 'Department of Computer Science',
            'code' => 'CS',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $coordinatorUserId = DB::table('users')->insertGetId([
            'university_id' => $universityId,
            'name' => 'Kwame Mensah',
            'email' => 'kwame.mensah@du.edu.gh',
            'index_number' => 'AB/CS/2024/001',
            'role' => 'coordinator',
            'is_active' => true,
            'email_verified_at' => $now,
            'password' => Hash::make('admin123'),
            'remember_token' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('role_user')->insert([
            'role_id' => $coordinatorRoleId,
            'user_id' => $coordinatorUserId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('coordinator_assignments')->insert([
            'user_id' => $coordinatorUserId,
            'faculty_id' => $facultyId,
            'department_id' => $departmentId,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
