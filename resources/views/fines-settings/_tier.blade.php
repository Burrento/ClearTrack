<li class="tier-row grid grid-cols-2 items-end gap-3 rounded-lg border border-rule bg-paper/40 p-4 sm:grid-cols-[6rem_6rem_8rem_1fr_auto]">
    <div>
        <label for="tier-{{ $index }}-min" class="mb-1 block text-sm font-semibold">From (pts)</label>
        <input id="tier-{{ $index }}-min" type="number" name="tiers[{{ $index }}][min_points]" value="{{ $tier['min_points'] ?? '' }}" required min="1" class="input">
    </div>
    <div>
        <label for="tier-{{ $index }}-max" class="mb-1 block text-sm font-semibold">Up to (pts)</label>
        <input id="tier-{{ $index }}-max" type="number" name="tiers[{{ $index }}][max_points]" value="{{ $tier['max_points'] ?? '' }}" min="1" placeholder="No limit" class="input">
    </div>
    <div>
        <label for="tier-{{ $index }}-fine" class="mb-1 block text-sm font-semibold">Fine (₱)</label>
        <input id="tier-{{ $index }}-fine" type="number" step="0.01" name="tiers[{{ $index }}][fine_amount]" value="{{ $tier['fine_amount'] ?? '' }}" required min="0" class="input">
    </div>
    <div>
        <label for="tier-{{ $index }}-req" class="mb-1 block text-sm font-semibold">Also requires <span class="font-normal text-ink-soft">(optional)</span></label>
        <input id="tier-{{ $index }}-req" type="text" name="tiers[{{ $index }}][requirements]" value="{{ $tier['requirements'] ?? '' }}" placeholder="Letter of apology" class="input">
    </div>
    <button type="button" onclick="removeTier(this)" class="btn btn-secondary text-alarm max-sm:col-span-2">Remove</button>
</li>
