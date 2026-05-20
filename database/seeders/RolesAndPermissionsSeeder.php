<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Department;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'officer']);
        Role::create(['name' => 'student']);

        // Create Departments
        $sit = Department::create(['name' => 'School of Information and Technology', 'code' => 'SIT']);
        $soa = Department::create(['name' => 'School of Accountancy', 'code' => 'SOA']);

        // Create Default Admin
        $admin = User::create([
            'name' => 'System Administrator',
            'username' => 'superadmin',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole('admin');

        // Create a test Officer for SIT
        $officer = User::create([
            'name' => 'SIT Officer',
            'username' => 'SIT_Officer',
            'password' => Hash::make('password'),
            'department_id' => $sit->id,
        ]);
        $officer->assignRole('officer');

        // Create a test Student for SIT
        $studentId = '51410';
        $student = User::create([
            'name' => 'Test Student',
            'username' => $studentId,
            'password' => Hash::make('Dwcc' . $studentId),
            'department_id' => $sit->id,
            'rfid_uid' => '1234567890',
        ]);
        $student->assignRole('student');
    }
}
