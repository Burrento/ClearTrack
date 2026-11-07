<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $this->officer = User::role('officer')->first();
    }

    private function upload(string $csv)
    {
        return $this->actingAs($this->officer)->post(route('students.import'), [
            'csv' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ]);
    }

    public function test_template_has_the_headers(): void
    {
        $response = $this->actingAs($this->officer)->get(route('students.importTemplate'))->assertOk();

        $this->assertSame("\u{FEFF}name,student_id,year_level\r\n", $response->streamedContent());
        $this->assertStringContainsString('attachment; filename=cleartrack-students-template.csv', $response->headers->get('Content-Disposition'));
    }

    public function test_imports_students_into_the_officers_department(): void
    {
        // Excel-style: BOM, capitalised headers, quoted name with a comma, lowercase ID and year, blank line,
        // and an extra column (an old template's rfid) that is ignored
        $csv = "\u{FEFF}Name,Student_ID,Year_Level,RFID\r\n"
            . "\"Dela Cruz, Juan\",lgc25-200001,second year,04:a1:b2:c3\r\n"
            . "\r\n"
            . "Maria Santos,LGC25-200002,Fourth Year,\r\n"
            . "Test Student,LGC25-105680,First Year,\r\n"; // already registered by the seeder

        $this->upload($csv)->assertRedirect(route('students.index'))
            ->assertSessionHas('status', fn ($s) => str_contains($s, '2 students imported. 1 already registered, so skipped.'));

        $juan = User::where('username', 'LGC25-200001')->sole();
        $this->assertSame('Dela Cruz, Juan', $juan->name);
        $this->assertSame('Second Year', $juan->year_level);
        $this->assertNull($juan->rfid_uid); // cards are added later with Edit
        $this->assertSame($this->officer->department_id, $juan->department_id);
        $this->assertTrue($juan->hasRole('student'));
        $this->assertTrue(Hash::check('Delacruz200001', $juan->password));
    }

    public function test_imports_the_schools_real_id_shapes_with_the_number_after_the_last_dash_as_password(): void
    {
        $csv = "name,student_id,year_level\n"
            . "\"Santos, Ana\",LGC26-6578,First Year\n"
            . "\"Reyes, Ben\",LGC26-20011,First Year\n"
            . "\"Cruz, Cara\",LGC-5985,Second Year\n"
            . "\"Garcia, Dan\",LGC26-2-0014,Second Year\n"
            . "\"Lim, Eve\",LGC21-00026,Fourth Year\n";

        $this->upload($csv)->assertSessionHas('status', fn ($s) => str_starts_with($s, '5 students imported.'));

        foreach (['LGC26-6578' => 'Santos6578', 'LGC26-20011' => 'Reyes20011', 'LGC-5985' => 'Cruz5985',
            'LGC26-2-0014' => 'Garcia0014', 'LGC21-00026' => 'Lim00026'] as $id => $password) {
            $this->assertTrue(Hash::check($password, User::where('username', $id)->value('password')), "{$id} → {$password}");
        }
        $this->assertSame('Santos105680', User::defaultPassword('Santos, Juan', 'LGC25-105680')); // 6-digit IDs unchanged
    }

    public function test_any_bad_row_imports_nothing_and_lists_the_problems(): void
    {
        $csv = "name,student_id,year_level\n"
            . "Good Student,LGC25-300001,First Year\n"
            . ",LGC25-300002,First Year\n"
            . "Bad Id,12345,First Year\n"
            . "Bad Year,LGC25-300003,Freshman\n"
            . "Twin,LGC25-300001,First Year\n";

        $this->upload($csv)->assertSessionHasErrors('csv')
            ->assertSessionHas('csv_problems', fn ($p) => count($p) === 4
                && str_starts_with($p[0], 'Line 3: name is empty')
                && str_contains($p[1], 'Line 4: student ID "12345"')
                && str_contains($p[2], 'year level "Freshman"')
                && str_contains($p[3], 'also on line 2'));

        $this->assertSame(0, User::where('username', 'like', 'LGC25-3%')->count());
    }

    public function test_missing_columns_are_rejected(): void
    {
        $this->upload("name,id\nJuan,LGC25-400001\n")->assertSessionHasErrors('csv');
        $this->assertNull(User::where('username', 'LGC25-400001')->first());
    }

    public function test_only_officers_can_import(): void
    {
        $student = User::role('student')->first();
        $this->actingAs($student)->get(route('students.importTemplate'))->assertForbidden();
        $this->actingAs($student)->post(route('students.import'))->assertForbidden();
    }
}
