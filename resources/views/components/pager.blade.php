@props(['items', 'label' => 'Pages'])

{{-- Previous / Page x of y / Next under a paginated list; nothing when it fits on one page --}}
@if($items->hasPages())
    <nav class="mt-4 flex items-center justify-between gap-3" aria-label="{{ $label }}">
        @if($items->onFirstPage())
            <span class="btn btn-secondary btn-sm opacity-50">Previous</span>
        @else
            <a href="{{ $items->previousPageUrl() }}" class="btn btn-secondary btn-sm">Previous</a>
        @endif
        <span class="text-sm text-ink-soft">Page {{ $items->currentPage() }} of {{ $items->lastPage() }}</span>
        @if($items->hasMorePages())
            <a href="{{ $items->nextPageUrl() }}" class="btn btn-secondary btn-sm">Next</a>
        @else
            <span class="btn btn-secondary btn-sm opacity-50">Next</span>
        @endif
    </nav>
@endif
