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
    <p class="hint -mt-3">Attendance is only accepted between these times.</p>

    <div class="max-w-48">
        <label for="penalty_points" class="label">Penalty points</label>
        <input type="number" name="penalty_points" id="penalty_points" required min="0"
            value="{{ old('penalty_points', $event?->penalty_points ?? 1) }}"
            @class(['input', 'input-invalid' => $errors->has('penalty_points')])>
        @error('penalty_points') <p class="field-error">{{ $message }}</p> @enderror
    </div>
    <p class="hint -mt-4">Added to each student who misses this event.</p>

    <label class="flex items-start gap-3">
        <input type="checkbox" name="requires_survey" value="1" class="checkbox mt-0.5"
            @checked(old('requires_survey', $event?->requires_survey))>
        <span>
            <span class="block font-semibold">Students must answer a survey</span>
            <span class="text-sm text-ink-soft">You can attach the survey from the event page after saving.</span>
        </span>
    </label>
</div>
