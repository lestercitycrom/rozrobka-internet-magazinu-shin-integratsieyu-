<div class="mt-3 border-l-4 border-amber-500 bg-amber-50 p-3 text-sm text-amber-950" data-conflict-resolution>
    <input type="hidden" name="conflict_version" value="{{ $currentVersion }}">
    <label class="flex items-start gap-3 font-bold">
        <input class="mt-1 size-4 shrink-0" type="checkbox" name="resolve_conflict" value="1" required>
        <span>{{ __('service_requests.conflict_confirm_apply') }}</span>
    </label>
    <p class="mt-2 text-xs">{{ __('service_requests.conflict_confirm_hint', ['version' => $currentVersion]) }}</p>
</div>
