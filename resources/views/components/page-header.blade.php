@props(['title', 'description' => null, 'back' => null, 'backLabel' => null])

<header class="mb-8">
    @if($back)
        <a href="{{ $back }}" class="mb-3 inline-flex items-center gap-1 text-sm font-semibold text-ink-soft hover:text-ink">
            <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.8 4.2a.75.75 0 0 1 0 1.06L8.06 10l4.74 4.74a.75.75 0 1 1-1.06 1.06l-5.27-5.27a.75.75 0 0 1 0-1.06l5.27-5.27a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg>
            {{ $backLabel }}
        </a>
    @endif
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="max-w-2xl">
            <h1 class="text-[2.25rem] leading-[1.15] font-bold tracking-tight">{{ $title }}</h1>
            @if($description)
                <p class="mt-2 text-lg text-ink-soft">{{ $description }}</p>
            @endif
        </div>
        @if($slot->isNotEmpty())
            <div class="flex flex-wrap items-center gap-3">{{ $slot }}</div>
        @endif
    </div>
</header>
