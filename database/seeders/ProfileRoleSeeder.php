<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\StudyClass;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProfileRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Assigns roles to profiles via profile_roles pivot table.
     * Distribution:
     * - 12 teachers (role_id = 2)
     * - Remaining students (role_id = 1) + assign to study classes
     */
    public function run(): void
    {
        $profiles = Profile::with('user')->orderBy('created_at')->get();
        $studyClasses = StudyClass::all();

        $profileRoles = [];
        $teacherCount = 0;
        $studentCount = 0;

        // Distribute students across study classes evenly
        $classIndex = 0;

        foreach ($profiles as $index => $profile) {

            if ($index < 15) {
                // Next 12 profiles → Teacher
                $profileRoles[] = [
                    'profile_id' => $profile->id,
                    'role_id' => 2, // teacher
                ];
                $teacherCount++;
            } else {
                // Remaining profiles → Student
                $profileRoles[] = [
                    'profile_id' => $profile->id,
                    'role_id' => 1, // student
                ];

                // Assign study class to this student
                $studyClass = $studyClasses[$classIndex % $studyClasses->count()];

                DB::table('profiles')
                    ->where('id', $profile->id)
                    ->update(['study_class_id' => $studyClass->id]);

                $classIndex++;
                $studentCount++;
            }
        }

        // Bulk insert profile_roles
        DB::table('profile_roles')->insert($profileRoles);

        $this->command->info('✅ Profile roles assigned: ' . count($profileRoles));
        $this->command->info('   👨‍🏫 Teachers: ' . $teacherCount);
        $this->command->info('   🎓 Students: ' . $studentCount);
        $this->command->info('   📚 Students distributed across ' . $studyClasses->count() . ' study classes');
        $this->command->newLine();

        // Show distribution per class
        foreach ($studyClasses as $class) {
            $count = DB::table('profiles')
                ->where('study_class_id', $class->id)
                ->count();
            $this->command->info("      {$class->name}: {$count} students");
        }
    }
}
