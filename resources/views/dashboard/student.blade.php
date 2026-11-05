@php
    $user = Auth::user();
    $semester = \App\Models\Semester::current();
    $unpaidFines = $user->fines()->with('event')->where('status', 'unpaid')->get();
    $cleared = $unpaidFines->isEmpty();

    $openEvents = \App\Models\Event::where('department_id', $user->department_id)
        ->where('end_time', '>=', now())->where('fines_processed', false)->get()
        ->filter->isOpenForAttendance()
        ->reject(fn ($event) => $user->attendances()->where('event_id', $event->id)->exists());
@endphp

<x-page-header :title="'Hello, ' . \Illuminate\Support\Str::before($user->name, ' ')" :description="$semester ? 'Your clearance for ' . $semester->name . '.' : 'Your clearance status.'" />

{{-- Clearance slip --}}
<section class="panel overflow-hidden" aria-labelledby="clearance-heading">
    <h2 id="clearance-heading" class="sr-only">Clearance status</h2>
    <div class="grid md:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
        <div class="stamp-pad flex min-h-56 items-center justify-center border-b border-dashed border-rule bg-paper/40 px-6 py-10 md:border-r md:border-b-0">
            @if($cleared)
                <span class="stamp stamp-cleared">Cleared</span>
            @else
                <span class="stamp stamp-not">Not cleared</span>
            @endif
        </div>

        <div class="panel-body md:p-8">
            @if($cleared)
                <p class="text-lg font-semibold">You're cleared. You have no unpaid fines.</p>
                <p class="mt-2 text-ink-soft">Show this page if your clearance slip asks for proof. Missing or arriving late to an event adds a fine.</p>
            @else
                <p class="text-lg font-semibold">To be cleared, pay ₱{{ number_format($unpaidFines->sum('amount'), 2) }} to your department officer:</p>
                <ul class="mt-4 space-y-3">
                    @foreach($unpaidFines as $fine)
                        <li class="flex items-start gap-3">
                            <span class="mt-1.5 size-2 shrink-0 rounded-full bg-alarm" aria-hidden="true"></span>
                            <span><strong>₱{{ number_format($fine->amount, 2) }}</strong>
                                <span class="text-ink-soft">{{ $fine->event?->name ?? 'Clearance fine' }}@if($fine->reason), {{ strtolower($fine->reason) }}@endif</span></span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4 text-ink-soft">You're cleared automatically once the officer records your payments.</p>
            @endif
        </div>
    </div>
</section>

{{-- Attendance you can still record --}}
@foreach($openEvents as $event)
    <section class="mt-6 rounded-xl border-2 border-seal bg-seal-wash px-5 py-4">
        <p class="font-bold">{{ $event->name }}: tap your ID at the officer's table</p>
        <p class="text-ink-soft">
            @if($event->start_time->isFuture())
                Starts {{ $event->start_time->format('g:i A') }}. Tap in before then to avoid a late fine.
            @else
                Started {{ $event->start_time->format('g:i A') }}, so tapping in now counts as late. Ends {{ $event->endsAt() }}.
            @endif
        </p>
    </section>
@endforeach

<section class="mt-10" aria-labelledby="fines-heading">
    <h2 id="fines-heading" class="panel-title">Fines</h2>
    @php $fines = $user->fines()->with('event', 'semester')->latest()->take(10)->get(); @endphp
    @if($fines->isEmpty())
        <p class="mt-1 text-ink-soft">You have no fines. Keep attending events on time.</p>
    @else
        <p class="mt-1 text-ink-soft">Your most recent fines. Pay unpaid ones to your department officer.</p>
        <ul class="panel mt-4 divide-y divide-rule">
            @foreach($fines as $fine)
                <li class="flex items-center justify-between gap-4 px-5 py-3.5">
                    <span>
                        <span class="block font-semibold">₱{{ number_format($fine->amount, 2) }}</span>
                        <span class="text-sm text-ink-soft">
                            {{ $fine->event?->name ?? ($fine->semester?->name ?? 'Clearance fine') }}@if($fine->reason), {{ strtolower($fine->reason) }}@endif
                        </span>
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
