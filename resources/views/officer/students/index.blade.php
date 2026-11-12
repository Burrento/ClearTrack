@extends('layouts.app')

@section('content')
@php
    $yearLevels = \App\Models\User::YEAR_LEVELS;
    $activeCount = $students->where('is_active', true)->count();
@endphp

<div x-data="{ search: '', yearFilter: '' }"
    x-init="@if($errors->has('csv')) $refs.import.showModal() @elseif($errors->any()) $refs.register.showModal() @endif">
    <x-page-header title="Students" :description="$activeCount . ' active ' . \Illuminate\Support\Str::plural('student', $activeCount) . ' in ' . Auth::user()->department->code . '.'">
        <button type="button" @click="$refs.import.showModal()" class="btn btn-secondary">Import CSV</button>
        <button type="button" @click="$refs.register.showModal()" class="btn btn-primary">Register student</button>
    </x-page-header>

    <div class="mb-4 flex flex-wrap gap-3">
        <label class="relative w-full sm:w-72">
            <span class="sr-only">Search by name or ID</span>
            <svg class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-soft" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
            <input type="search" x-model="search" placeholder="Search name or ID" class="input pl-9">
        </label>
        <label class="w-full sm:w-48">
            <span class="sr-only">Year level</span>
            <select x-model="yearFilter" class="input">
                <option value="">All year levels</option>
                @foreach($yearLevels as $level)
                    <option value="{{ $level }}">{{ $level }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="panel overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Student ID</th>
                    <th>Year level</th>
                    <th>RFID card</th>
                    <th>Status</th>
                    <th><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($students as $student)
                    <tr x-show="(search === '' || @js(strtolower($student->name . ' ' . $student->username)).includes(search.toLowerCase()))
                                && (yearFilter === '' || @js($student->year_level) === yearFilter)"
                        @class(['text-ink-soft' => !$student->is_active])>
                        <td class="font-semibold whitespace-nowrap">{{ $student->name }}</td>
                        <td class="whitespace-nowrap">{{ $student->username }}</td>
                        <td class="whitespace-nowrap">{{ $student->year_level ?? '—' }}</td>
                        <td class="whitespace-nowrap">
                            @if($student->rfid_uid)
                                <span class="code">{{ $student->formattedRfid() }}</span>
                            @else
                                <span class="text-ink-soft">No card</span>
                            @endif
                        </td>
                        <td>
                            @if(!$student->is_active)
                                <x-badge tone="muted">Deactivated</x-badge>
                            @elseif($student->unpaid_total !== null)
                                <x-badge tone="bad">Owes ₱{{ number_format($student->unpaid_total, 2) }}</x-badge>
                            @else
                                <x-badge tone="good">Cleared</x-badge>
                            @endif
                        </td>
                        <td>
                            <div class="flex justify-end gap-4">
                                <a href="{{ route('students.edit', $student) }}" class="action">Edit</a>
                                <form method="POST" action="{{ route('students.toggleActive', $student) }}"
                                    onsubmit="return confirm(@js(($student->is_active ? 'Deactivate ' : 'Reactivate ') . $student->name . '?'))">
                                    @csrf
                                    <button type="submit" @class(['action-danger' => $student->is_active, 'action' => !$student->is_active])>
                                        {{ $student->is_active ? 'Deactivate' : 'Reactivate' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No students yet. Register the first one to start tracking attendance.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Register student --}}
    <dialog x-ref="register" @close="$refs.form.reset()"
        class="m-auto w-[calc(100%-2rem)] max-w-md rounded-xl border border-rule bg-surface p-0 text-ink shadow-2xl backdrop:bg-ink/60">
        <form method="POST" action="{{ route('students.store') }}" x-ref="form">
            @csrf
            <div class="p-6">
                <h2 class="text-2xl font-bold">Register student</h2>
                <p class="mt-1 text-ink-soft">Their password will be their last name and the number at the end of their ID.</p>

                <div class="mt-6 space-y-5">
                    <div>
                        <label for="name" class="label">Full name</label>
                        <input id="name" type="text" name="name" value="{{ old('name') }}" required placeholder="Dela Cruz, Juan"
                            @class(['input', 'input-invalid' => $errors->has('name')])>
                        <p class="hint">For a two-word last name, write it first with a comma.</p>
                        @error('name') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="username" class="label">Student ID</label>
                        <input id="username" type="text" name="username" value="{{ old('username') }}" required
                            placeholder="LGC26-6578" pattern="[Ll][Gg][Cc][0-9]{2}(-[0-9]+)+" title="LGC, the 2-digit year, then numbers, e.g. LGC26-6578"
                            @class(['input', 'input-invalid' => $errors->has('username')])>
                        @error('username') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="rfid_uid" class="label">RFID card <span class="font-normal text-ink-soft">(optional)</span></label>
                        <input id="rfid_uid" type="text" name="rfid_uid" value="{{ old('rfid_uid') }}" data-nfc readonly placeholder="Tap the card on the reader" autocomplete="off"
                            @class(['input code cursor-not-allowed bg-paper', 'input-invalid' => $errors->has('rfid_uid')])>
                        <p id="nfc-status" class="reader-status">Connecting to the card reader…</p>
                        @error('rfid_uid') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="year_level" class="label">Year level</label>
                        <select id="year_level" name="year_level" required @class(['input', 'input-invalid' => $errors->has('year_level')])>
                            <option value="" disabled @selected(!old('year_level'))>Choose a year level</option>
                            @foreach($yearLevels as $level)
                                <option value="{{ $level }}" @selected(old('year_level') === $level)>{{ $level }}</option>
                            @endforeach
                        </select>
                        @error('year_level') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-rule bg-paper/50 px-6 py-4 sm:flex-row sm:justify-end">
                <button type="button" @click="$refs.register.close()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Register student</button>
            </div>
        </form>
    </dialog>

    {{-- Import students from a CSV --}}
    <dialog x-ref="import"
        class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-xl border border-rule bg-surface p-0 text-ink shadow-2xl backdrop:bg-ink/60">
        <form method="POST" action="{{ route('students.import') }}" enctype="multipart/form-data">
            @csrf
            <div class="p-6">
                <h2 class="text-2xl font-bold">Import students</h2>
                <p class="mt-1 text-ink-soft">Register many students at once into {{ Auth::user()->department->code }} from a spreadsheet.</p>

                <ol class="mt-5 list-decimal space-y-2 pl-5">
                    <li><a href="{{ route('students.importTemplate') }}" class="action">Download the template</a>, then open it in Excel or Google Sheets.</li>
                    <li>Add one student per row:
                        <ul class="mt-1 list-disc space-y-0.5 pl-5 text-ink-soft">
                            <li><strong class="text-ink">name</strong>: e.g. <span class="code">Dela Cruz, Juan</span> (last name first with a comma for two-word last names)</li>
                            <li><strong class="text-ink">student_id</strong>: e.g. <span class="code">LGC26-6578</span> or <span class="code">LGC25-105680</span></li>
                            <li><strong class="text-ink">year_level</strong>: First Year, Second Year, Third Year or Fourth Year</li>
                        </ul>
                    </li>
                    <li>Save as <strong>CSV</strong> and upload it here.</li>
                </ol>

                <div class="mt-5">
                    <label for="csv" class="label">CSV file</label>
                    <input id="csv" type="file" name="csv" accept=".csv,text/csv" required
                        class="block w-full text-sm file:mr-3 file:rounded-lg file:border file:border-rule file:bg-surface file:px-3 file:py-2 file:font-semibold file:text-ink hover:file:border-ink-soft">
                    @error('csv')
                        <div class="mt-3 rounded-lg border border-alarm/30 bg-alarm-wash px-4 py-3 text-alarm" role="alert">
                            <p class="font-semibold">{{ $message }}</p>
                            @if(session('csv_problems'))
                                <ul class="mt-2 max-h-48 list-disc space-y-1 overflow-y-auto pl-5 text-sm">
                                    @foreach(session('csv_problems') as $problem) <li>{{ $problem }}</li> @endforeach
                                </ul>
                            @endif
                        </div>
                    @enderror
                </div>
                <p class="hint">Students already registered are skipped. Each new student's password is their last name plus the number at the end of their ID. Add RFID cards afterwards with Edit on each student.</p>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-rule bg-paper/50 px-6 py-4 sm:flex-row sm:justify-end">
                <button type="button" @click="$refs.import.close()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Import students</button>
            </div>
        </form>
    </dialog>
</div>
@endsection

@push('scripts')
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endpush
