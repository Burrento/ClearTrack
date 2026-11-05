@php
    $semester = \App\Models\Semester::current();
    $departments = \App\Models\Department::orderBy('code')->get()->map(function ($department) {
        $users = \App\Models\User::where('department_id', $department->id);
        $department->officer_count = (clone $users)->role('officer')->count();
        $department->student_count = (clone $users)->role('student')->where('is_active', true)->count();
        return $department;
    });
@endphp

<x-page-header title="Administration" :description="$semester ? 'Current semester: ' . $semester->name . ', started ' . $semester->starts_at->format('M j, Y') . '.' : 'No semester has been started yet.'">
    <a href="{{ route('admin.semesters') }}" class="btn btn-secondary">Start new semester</a>
    <a href="{{ route('admin.officers') }}" class="btn btn-primary">Add officer</a>
</x-page-header>

<section aria-labelledby="departments-heading">
    <h2 id="departments-heading" class="panel-title mb-4">Departments</h2>
    <div class="panel overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Department</th>
                    <th>Officers</th>
                    <th>Active students</th>
                </tr>
            </thead>
            <tbody>
                @forelse($departments as $department)
                    <tr>
                        <td class="font-bold">{{ $department->code }}</td>
                        <td>
                            {{ $department->name }}
                            @if($department->archived_at) <x-badge tone="muted">Archived</x-badge> @endif
                        </td>
                        <td @class(['text-alarm font-semibold' => !$department->officer_count && !$department->archived_at])>
                            {{ $department->officer_count ?: ($department->archived_at ? '0' : 'None assigned') }}
                        </td>
                        <td>{{ $department->student_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">No departments yet. <a href="{{ route('admin.departments') }}" class="action">Add one</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
