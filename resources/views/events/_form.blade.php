@php
    $event ??= null;
    $defaults = ['morning' => ['08:00', '12:00'], 'afternoon' => ['13:00', '17:00'], 'evening' => ['18:00', '21:00']];
    $existing = $event?->sessions->keyBy('name');
@endphp

<div class="space-y-6">
    <div>
        <label for="name" class="label">Event name</label>
        <input type="text" name="name" id="name" value="{{ old('name', $event?->name) }}" required placeholder="Nurses Week, Day 1"
            @class(['input', 'input-invalid' => $errors->has('name')])>
        @error('name') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div class="max-w-56">
        <label for="date" class="label">Date</label>
        <input type="date" name="date" id="date" required value="{{ old('date', $event?->start_time->toDateString()) }}"
            @class(['input', 'input-invalid' => $errors->has('date')])>
        @error('date') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <fieldset>
        <legend class="label">Sessions</legend>
        <p class="hint mt-0 mb-3">Students tap in and out of each session. Time in opens 1 hour before the start, and tapping in after the start is late. Time out is open from 30 minutes before the end until 30 minutes after.</p>
        <div class="panel divide-y divide-rule">
            @foreach($defaults as $name => [$defaultStart, $defaultEnd])
                @php
                    $saved = $existing?->get($name);
                    $enabled = old("sessions.$name.enabled", $event ? (bool) $saved : $name === 'morning');
                @endphp
                <div x-data="{ on: @js((bool) $enabled) }" class="flex flex-wrap items-center gap-x-6 gap-y-3 px-4 py-3.5">
                    <label class="flex w-32 items-center gap-3 font-semibold">
                        <input type="checkbox" name="sessions[{{ $name }}][enabled]" value="1" class="checkbox" x-model="on" @checked($enabled)>
                        {{ ucfirst($name) }}
                    </label>
                    <div class="flex items-center gap-2" :class="!on && 'opacity-40'">
                        <label for="{{ $name }}_start" class="sr-only">{{ ucfirst($name) }} start</label>
                        <input type="time" name="sessions[{{ $name }}][start]" id="{{ $name }}_start" class="input w-36" :disabled="!on"
                            value="{{ old("sessions.$name.start", $saved?->starts_at->format('H:i') ?? $defaultStart) }}">
                        <span class="text-ink-soft" aria-hidden="true">to</span>
                        <label for="{{ $name }}_end" class="sr-only">{{ ucfirst($name) }} end</label>
                        <input type="time" name="sessions[{{ $name }}][end]" id="{{ $name }}_end" class="input w-36" :disabled="!on"
                            value="{{ old("sessions.$name.end", $saved?->ends_at->format('H:i') ?? $defaultEnd) }}">
                    </div>
                    @error("sessions.$name") <p class="field-error mt-0 w-full">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>
        @error('sessions') <p class="field-error">{{ $message }}</p> @enderror
    </fieldset>

    <fieldset>
        <legend class="label">Fines, per session</legend>
        <div class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
            @foreach([
                'absent_fine' => 'Absent (no taps at all)',
                'late_fine' => 'Late time in',
                'missed_in_fine' => 'No time in (only tapped out)',
                'missed_out_fine' => 'No time out',
            ] as $field => $label)
                <div>
                    <label for="{{ $field }}" class="mb-1.5 block text-[0.9375rem] text-ink-soft">{{ $label }}</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-ink-soft" aria-hidden="true">₱</span>
                        <input type="number" name="{{ $field }}" id="{{ $field }}" required min="0" step="0.01" inputmode="decimal"
                            value="{{ old($field, $event ? (float) $event->$field : 0) }}"
                            @class(['input pl-8', 'input-invalid' => $errors->has($field)])>
                    </div>
                    @error($field) <p class="field-error">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>
        <p class="hint">Charged when absences are processed after the last session. Late and no time out can both apply. Enter 0 for no fine.</p>
    </fieldset>

    <label class="flex items-start gap-3">
        <input type="checkbox" name="requires_survey" value="1" class="checkbox mt-0.5"
            @checked(old('requires_survey', $event?->requires_survey))>
        <span>
            <span class="block font-semibold">Students must answer a survey</span>
            <span class="text-sm text-ink-soft">You can attach the survey from the event page after saving.</span>
        </span>
    </label>
</div>

@push('scripts')
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endpush
