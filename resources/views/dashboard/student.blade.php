@php
    $user = Auth::user();
    $semester = \App\Models\Semester::current();
    $points = $user->getTotalPenaltyPoints();
    $tier = $user->getMatchedTier();
    $clearanceOpen = $user->isClearanceActive();
    $unpaidFines = $user->fines()->with('event')->where('status', 'unpaid')->get();

    $absences = $user->attendances()->where('status', 'absent')
        ->whereHas('event', fn ($q) => $q->when($semester, fn ($q) => $q->where('start_time', '>=', $semester->starts_at)))
        ->with('event')->get()->sortByDesc('event.start_time');

    $openEvents = \App\Models\Event::where('department_id', $user->department_id)
        ->where('start_time', '<=', now())->where('end_time', '>=', now())->where('fines_processed', false)
        ->get()->reject(fn ($event) => $user->attendances()->where('event_id', $event->id)->exists());
@endphp

<x-page-header :title="'Hello, ' . \Illuminate\Support\Str::before($user->name, ' ')" :description="$semester ? 'Your clearance for ' . $semester->name . '.' : 'Your clearance status.'" />

{{-- Clearance slip --}}
<section class="panel overflow-hidden" aria-labelledby="clearance-heading">
    <h2 id="clearance-heading" class="sr-only">Clearance status</h2>
    <div class="grid md:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
        <div class="stamp-pad flex min-h-56 items-center justify-center border-b border-dashed border-rule bg-paper/40 px-6 py-10 md:border-r md:border-b-0">
            @if(!$clearanceOpen)
                <span class="stamp stamp-waiting">Not open yet</span>
            @elseif($user->isCleared())
                <span class="stamp stamp-cleared">Cleared</span>
            @else
                <span class="stamp stamp-not">Not cleared</span>
            @endif
        </div>

        <div class="panel-body md:p-8">
            @if(!$clearanceOpen)
                <p class="text-lg font-semibold">Clearance hasn't opened for this semester.</p>
                <p class="mt-2 max-w-prose text-ink-soft">
                    Your officers open it after the last event and set your fine from your penalty points.
                    @if($points > 0)
                        So far you have <strong class="text-ink">{{ $points }} {{ \Illuminate\Support\Str::plural('penalty point', $points) }}</strong>.
                    @else
                        You have no penalty points, so you're on track to be cleared.
                    @endif
                </p>
            @elseif($user->isCleared())
                <p class="text-lg font-semibold">You're cleared. There's nothing else to do.</p>
                <p class="mt-2 text-ink-soft">Show this page if your clearance slip asks for proof.</p>
            @else
                <p class="text-lg font-semibold">To be cleared:</p>
                <ul class="mt-4 space-y-3">
                    @forelse($unpaidFines as $fine)
                        <li class="flex items-start gap-3">
                            <span class="mt-1.5 size-2 shrink-0 rounded-full bg-alarm" aria-hidden="true"></span>
                            <span>Pay <strong>₱{{ number_format($fine->amount, 2) }}</strong> to your department officer
                                <span class="text-ink-soft">({{ $fine->event?->name ?? 'clearance fine' }})</span></span>
                        </li>
                    @empty
                        <li class="flex items-start gap-3">
                            <span class="mt-1.5 size-2 shrink-0 rounded-full bg-seal" aria-hidden="true"></span>
                            <span>Your fines are paid.</span>
                        </li>
                    @endforelse
                    @if(!empty($tier['requirements']))
                        <li class="flex items-start gap-3">
                            <span class="mt-1.5 size-2 shrink-0 rounded-full bg-wait" aria-hidden="true"></span>
                            <span>Submit: <strong>{{ $tier['requirements'] }}</strong></span>
                        </li>
                    @endif
                    <li class="flex items-start gap-3">
                        <span class="mt-1.5 size-2 shrink-0 rounded-full bg-rule" aria-hidden="true"></span>
                        <span>Then ask your officer to mark you cleared.</span>
                    </li>
                </ul>
            @endif
        </div>
    </div>
</section>

{{-- Attendance you can still record --}}
@foreach($openEvents as $event)
    <section class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border-2 border-seal bg-seal-wash px-5 py-4">
        <div>
            <p class="font-bold">{{ $event->name }} is happening now</p>
            <p class="text-ink-soft">Ends {{ $event->endsAt() }}. Tap your ID at the officer's table, or send a selfie.</p>
        </div>
        <a href="{{ route('attendance.upload', $event) }}" class="btn btn-primary">Send a selfie</a>
    </section>
@endforeach

<div class="mt-10 grid gap-10 lg:grid-cols-2">
    <section aria-labelledby="absences-heading">
        <h2 id="absences-heading" class="panel-title">Penalty points</h2>
        <p class="mt-1 text-ink-soft">
            @if($points > 0)
                {{ $points }} {{ \Illuminate\Support\Str::plural('point', $points) }} this semester, from missed events.
            @else
                None this semester. Keep attending events.
            @endif
        </p>
        @if($absences->isNotEmpty())
            <ul class="panel mt-4 divide-y divide-rule">
                @foreach($absences as $absence)
                    <li class="flex items-center justify-between gap-4 px-5 py-3.5">
                        <span>
                            <span class="block font-semibold">{{ $absence->event->name }}</span>
                            <span class="text-sm text-ink-soft">{{ $absence->event->start_time->format('D, M j, Y') }}</span>
                        </span>
                        <span class="font-semibold whitespace-nowrap text-alarm">+{{ $absence->event->penalty_points }} {{ \Illuminate\Support\Str::plural('pt', $absence->event->penalty_points) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section aria-labelledby="fines-heading">
        <h2 id="fines-heading" class="panel-title">Fines</h2>
        @php $fines = $user->fines()->with('event', 'semester')->latest()->take(6)->get(); @endphp
        @if($fines->isEmpty())
            <p class="mt-1 text-ink-soft">You have no fines.</p>
        @else
            <p class="mt-1 text-ink-soft">Pay unpaid fines to your department officer.</p>
            <ul class="panel mt-4 divide-y divide-rule">
                @foreach($fines as $fine)
                    <li class="flex items-center justify-between gap-4 px-5 py-3.5">
                        <span>
                            <span class="block font-semibold">₱{{ number_format($fine->amount, 2) }}</span>
                            <span class="text-sm text-ink-soft">{{ $fine->event?->name ?? ($fine->semester?->name ?? 'Clearance fine') }}</span>
                        </span>
                        @if($fine->status === 'paid')
                            <x-badge tone="good">Paid</x-badge>
                        @else
                            <x-badge tone="bad">Unpaid</x-badge>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
