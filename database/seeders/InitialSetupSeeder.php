<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
            'name' => 'Kofi Mensah',
            'email' => 'kofi.mensah@university.edu',
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

        $bscCsProgramId = DB::table('programs')->insertGetId([
            'university_id' => $universityId,
            'department_id' => $departmentId,
            'name' => 'BSc Computer Science',
            'code' => 'BCS',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('programs')->insert([
            'university_id' => $universityId,
            'department_id' => $departmentId,
            'name' => 'BSc Information Technology',
            'code' => 'BIT',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $levels = [
            ['name' => '100', 'code' => '100', 'sort_order' => 1],
            ['name' => '200', 'code' => '200', 'sort_order' => 2],
            ['name' => '300', 'code' => '300', 'sort_order' => 3],
            ['name' => '400', 'code' => '400', 'sort_order' => 4],
        ];

        $level100Id = null;
        foreach ($levels as $levelData) {
            $levelId = DB::table('levels')->insertGetId([
                'university_id' => $universityId,
                'name' => $levelData['name'],
                'code' => $levelData['code'],
                'sort_order' => $levelData['sort_order'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($levelData['code'] === '100') {
                $level100Id = $levelId;
            }
        }

        $studentNames = [
            'Akua Serwaa',
            'Yaw Boateng',
            'Efua Baidoo',
            'Kwesi Annan',
            'Abena Dapaah',
        ];

        foreach ($studentNames as $index => $studentName) {
            $serial = str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            $indexNumber = 'BCS/'.date('Y').'/'.$serial;
            $email = Str::of($studentName)->lower()->replace(' ', '.')->append('@university.edu')->toString();

            $studentId = DB::table('users')->insertGetId([
                'university_id' => $universityId,
                'program_id' => $bscCsProgramId,
                'level_id' => $level100Id,
                'class_id' => null,
                'name' => $studentName,
                'email' => $email,
                'index_number' => $indexNumber,
                'role' => 'student',
                'is_active' => true,
                'email_verified_at' => $now,
                'password' => Hash::make('student123'),
                'remember_token' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('role_user')->insert([
                'role_id' => $studentRoleId,
                'user_id' => $studentId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
