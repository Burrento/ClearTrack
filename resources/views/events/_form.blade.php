@php $event ??= null; @endphp

<div class="space-y-6">
    <div>
        <label for="name" class="label">Event name</label>
        <input type="text" name="name" id="name" value="{{ old('name', $event?->name) }}" required placeholder="General assembly"
            @class(['input', 'input-invalid' => $errors->has('name')])>
        @error('name') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <label for="start_time" class="label">Starts</label>
            <input type="datetime-local" name="start_time" id="start_time" required
                value="{{ old('start_time', $event?->start_time->format('Y-m-d\TH:i')) }}"
                @class(['input', 'input-invalid' => $errors->has('start_time')])>
            @error('start_time') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="end_time" class="label">Ends</label>
            <input type="datetime-local" name="end_time" id="end_time" required
                value="{{ old('end_time', $event?->end_time->format('Y-m-d\TH:i')) }}"
                @class(['input', 'input-invalid' => $errors->has('end_time')])>
            @error('end_time') <p class="field-error">{{ $message }}</p> @enderror
        </div>
    </div>
    <p class="hint -mt-3">Scanning opens 1 hour before the start. A tap after the start time counts as late.</p>

    <fieldset>
        <legend class="label">Fines</legend>
        <div class="grid gap-6 sm:grid-cols-2">
            @foreach(['absent_fine' => 'If absent', 'late_fine' => 'If late'] as $field => $label)
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
        <p class="hint">Charged to each absent or late student when absences are processed after the event. Enter 0 for no fine.</p>
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
