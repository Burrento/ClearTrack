@php
    $departmentId = Auth::user()->department_id;
    $semester = \App\Models\Semester::current();

    $events = \App\Models\Event::where('department_id', $departmentId)
        ->withCount(['attendances as present_count' => fn ($q) => $q->where('status', 'present')])->get();

    $live = $events->filter(fn ($e) => $e->phase() === 'live');
    $next = $events->filter(fn ($e) => $e->phase() === 'upcoming')->sortBy('start_time')->first();
    $toProcess = $events->filter(fn ($e) => $e->phase() === 'ended');

    $students = \App\Models\User::role('student')->where('department_id', $departmentId)->where('is_active', true);
    $studentCount = (clone $students)->count();
    $clearedCount = (clone $students)->whereDoesntHave('fines', fn ($q) => $q->where('status', 'unpaid'))->count();

    $unpaid = \App\Models\Fine::where('status', 'unpaid')->whereHas('user', fn ($q) => $q->where('department_id', $departmentId));
    $unpaidCount = (clone $unpaid)->count();
    $unpaidTotal = (clone $unpaid)->sum('amount');
@endphp

<x-page-header :title="Auth::user()->department->name" :description="$semester ? $semester->name : null">
    <a href="{{ route('events.create') }}" class="btn btn-primary">Create event</a>
</x-page-header>

{{-- Happening now --}}
<section aria-labelledby="now-heading" class="mb-10">
    <h2 id="now-heading" class="panel-title mb-4">Happening now</h2>
    @forelse($live as $event)
        <div class="panel mb-3 flex flex-wrap items-center justify-between gap-4 border-l-4 border-l-seal px-5 py-4">
            <div>
                <p class="text-lg font-bold">{{ $event->name }}</p>
                <p class="text-ink-soft">Until {{ $event->endsAt() }}. {{ $event->present_count }} present so far.</p>
            </div>
            <a href="{{ route('attendance.rfid', $event) }}" class="btn btn-primary">Open scanner</a>
        </div>
    @empty
        <div class="panel px-5 py-4 text-ink-soft">
            No event is running.
            @if($next)
                Next up: <a href="{{ route('events.show', $next) }}" class="action">{{ $next->name }}</a>, {{ $next->schedule() }}.
            @else
                <a href="{{ route('events.create') }}" class="action">Create an event</a> to start taking attendance.
            @endif
        </div>
    @endforelse
</section>

<div class="grid gap-10 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
    {{-- Things waiting on an officer --}}
    <section aria-labelledby="todo-heading">
        <h2 id="todo-heading" class="panel-title mb-4">Needs your attention</h2>
        <ul class="panel divide-y divide-rule">
            @foreach($toProcess as $event)
                <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                    <span><strong>{{ $event->name }}</strong> has ended. Absences aren't processed yet.</span>
                    <a href="{{ route('events.show', $event) }}" class="action">Open event</a>
                </li>
            @endforeach
            @if($unpaidCount)
                <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                    <span>{{ $unpaidCount }} unpaid {{ \Illuminate\Support\Str::plural('fine', $unpaidCount) }}, ₱{{ number_format($unpaidTotal, 2) }} in total</span>
                    <a href="{{ route('fines.index') }}" class="action">Record payments</a>
                </li>
            @endif
            @if($toProcess->isEmpty() && !$unpaidCount)
                <li class="px-5 py-4 text-ink-soft">You're all caught up.</li>
            @endif
        </ul>
    </section>

    {{-- Clearance progress --}}
    <section aria-labelledby="clearance-heading">
        <h2 id="clearance-heading" class="panel-title mb-4">Clearance</h2>
        <div class="panel panel-body">
            <p class="text-lg"><strong>{{ $clearedCount }}</strong> of {{ $studentCount }} students cleared</p>
            <div class="mt-3 h-3 overflow-hidden rounded-full bg-paper" role="progressbar" aria-label="Students cleared"
                aria-valuemin="0" aria-valuemax="{{ $studentCount }}" aria-valuenow="{{ $clearedCount }}">
                <div class="h-full rounded-full bg-seal" style="width: {{ $studentCount ? round($clearedCount / $studentCount * 100) : 0 }}%"></div>
            </div>
            <p class="hint">Students with no unpaid fines are cleared automatically.</p>
            <a href="{{ route('clearance.index') }}" class="action mt-4 inline-block">Open clearance list</a>
        </div>
    </section>
</div>
