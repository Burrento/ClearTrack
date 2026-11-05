@php
    // [label, route, active pattern]
    $link = fn ($label, $route, $pattern = null) => [$label, $route, $pattern ?? $route];

    $groups = match (true) {
        auth()->user()->hasRole('officer') => [
            ['title' => null, 'links' => [$link('Dashboard', 'dashboard')]],
            // A real sequence: attendance becomes fines, paid fines mean cleared
            ['title' => 'Clearance process', 'sequence' => true, 'links' => [
                $link('Events and attendance', 'events.index', 'events.*'),
                $link('Fines', 'fines.index', 'fines.*'),
                $link('Clearance', 'clearance.index', 'clearance.*'),
            ]],
            ['title' => 'Records', 'links' => [$link('Students', 'students.index', 'students.*')]],
        ],
        auth()->user()->hasRole('admin') => [
            ['title' => null, 'links' => [
                $link('Dashboard', 'dashboard'),
                $link('Semesters', 'admin.semesters'),
                $link('Departments', 'admin.departments'),
                $link('Officers', 'admin.officers'),
                $link('Backups', 'admin.backups', 'admin.backups*'),
            ]],
        ],
        default => [
            ['title' => null, 'links' => [
                $link('My clearance', 'dashboard'),
                $link('My events', 'student.events'),
            ]],
        ],
    };
@endphp

<nav aria-label="Main" class="space-y-7">
    @foreach($groups as $group)
        <div>
            @if($group['title'])
                <p class="mb-2 px-3 text-sm font-semibold text-ink-soft">{{ $group['title'] }}</p>
            @endif
            <ul @class(['space-y-0.5', 'relative ml-3 border-l-2 border-rule pl-2' => $group['sequence'] ?? false])>
                @foreach($group['links'] as [$label, $route, $pattern])
                    @php $active = request()->routeIs($pattern); @endphp
                    <li @class(['relative' => $group['sequence'] ?? false])>
                        @if($group['sequence'] ?? false)
                            <span @class([
                                'absolute top-1/2 -left-[0.9rem] size-2.5 -translate-y-1/2 rounded-full border-2',
                                'border-seal bg-seal' => $active,
                                'border-ink-soft/50 bg-surface' => !$active,
                            ]) aria-hidden="true"></span>
                        @endif
                        <a href="{{ route($route) }}" @if($active) aria-current="page" @endif @class([
                            'block rounded-lg px-3 py-2 text-[0.9375rem] transition-colors',
                            'bg-seal-wash font-semibold text-seal-deep' => $active,
                            'text-ink-soft hover:bg-paper hover:text-ink' => !$active,
                        ])>{{ $label }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>
