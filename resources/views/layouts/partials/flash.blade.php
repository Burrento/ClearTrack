@if (session('status'))
    <div role="status" class="mb-8 flex items-start gap-3 rounded-lg border border-seal/30 bg-seal-wash px-4 py-3 text-seal-deep">
        <svg class="mt-0.5 size-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.86-9.86a.75.75 0 0 0-1.22-.88l-3.24 4.5-1.6-1.6a.75.75 0 1 0-1.06 1.06l2.22 2.22a.75.75 0 0 0 1.14-.09l3.76-5.21Z" clip-rule="evenodd"/></svg>
        <p class="font-semibold">{{ session('status') }}</p>
    </div>
@endif

@if (session('error'))
    <div role="alert" class="mb-8 flex items-start gap-3 rounded-lg border border-alarm/30 bg-alarm-wash px-4 py-3 text-alarm">
        <svg class="mt-0.5 size-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
        <p class="font-semibold">{{ session('error') }}</p>
    </div>
@endif
