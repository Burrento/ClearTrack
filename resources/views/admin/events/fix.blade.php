@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl">
    <x-page-header :title="'Fix ' . $event->name" :back="route('admin.events')" back-label="All events"
        :description="($event->department?->code ?? 'No department') . ', ' . $event->schedule()" />

    @if(session('refunds'))
        <section class="panel mb-6 border-wait px-5 py-4" aria-labelledby="refunds-heading">
            <h2 id="refunds-heading" class="font-semibold">{{ count(session('refunds')) }} paid {{ \Illuminate\Support\Str::plural('fine', count(session('refunds'))) }} no longer apply</h2>
            <p class="mb-3 text-sm text-ink-soft">These were already paid, so they were kept. Refund the students or keep the payment.</p>
            <ul class="space-y-1 text-sm">
                @foreach(session('refunds') as $refund)
                    <li><strong>{{ $refund['name'] }}</strong> <span class="code text-ink-soft">{{ $refund['username'] }}</span>: {{ $refund['reason'] }}, ₱{{ number_format($refund['amount'], 2) }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <form method="POST" action="{{ route('admin.events.reprocess', $event) }}" class="panel"
        onsubmit="return confirm(@js('Charge ' . $event->name . ' again with these settings? Its unpaid fines are removed and charged again. Paid fines are kept and never charged twice.'))">
        @csrf
        @method('PUT')
        <div class="panel-body space-y-6">
            <fieldset>
                <legend class="label">Taps each session takes</legend>
                <p class="hint mt-0 mb-3">Untick a tap that wasn't really required, e.g. Time out if students were told there was none. Taps already recorded are kept but no longer needed.</p>
                <div class="panel divide-y divide-rule">
                    @foreach($event->sessions as $session)
                        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
                            <span class="w-44 font-semibold">{{ $session->label() }} <span class="block text-sm font-normal text-ink-soft">{{ $session->times() }}</span></span>
                            @foreach(['has_in' => 'Time in', 'has_out' => 'Time out'] as $flag => $label)
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="sessions[{{ $session->id }}][{{ $flag }}]" value="1" class="checkbox"
                                        @checked(old("sessions.{$session->id}.$flag", $errors->any() ? false : $session->$flag))>
                                    {{ $label }}
                                </label>
                            @endforeach
                            @error("sessions.{$session->id}") <p class="field-error mt-0 w-full">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            </fieldset>

            <fieldset>
                <legend class="label">Fines, per session</legend>
                <div class="grid gap-x-6 gap-y-4 sm:grid-cols-3">
                    @foreach(['missed_in_fine' => 'No time in', 'missed_out_fine' => 'No time out', 'late_fine' => 'Late time in'] as $field => $label)
                        <div>
                            <label for="{{ $field }}" class="mb-1.5 block text-[0.9375rem] text-ink-soft">{{ $label }}</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-ink-soft" aria-hidden="true">₱</span>
                                <input type="number" name="{{ $field }}" id="{{ $field }}" required min="0" step="0.01" inputmode="decimal"
                                    value="{{ old($field, (float) $event->$field) }}" @class(['input pl-8', 'input-invalid' => $errors->has($field)])>
                            </div>
                            @error($field) <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
                <p class="hint">Absent (no taps) is no time in + no time out for each session.</p>
            </fieldset>
        </div>

        <div class="flex justify-end gap-3 border-t border-rule px-5 py-4 sm:px-6">
            <a href="{{ route('admin.events') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save and charge again</button>
        </div>
    </form>
</div>
@endsection
