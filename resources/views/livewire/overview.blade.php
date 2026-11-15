<div class="mx-auto max-w-5xl">
    <flux:heading size="xl" level="1">Overview</flux:heading>
    <flux:text class="mt-2">The unfinished work of the {{ $people }} {{ Str::plural('person', $people) }} below you. Secret work is not counted.</flux:text>

    <dl class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-overview.tile label="Open work" :value="$totals['open']" />
        <x-overview.tile label="Past their by-when date" :value="$totals['pastByWhen']" :amber="$totals['pastByWhen'] > 0" />
        <x-overview.tile label="Parked" :value="$totals['parked']" />
        <x-overview.tile label="Classifieds" :value="$totals['lookingForAHand']" />
    </dl>

    <div class="h-8"></div>

    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($areas as $area)
            <section wire:key="area-{{ $area['lead']->id }}" @class(['rounded-lg p-4', 'bg-amber-50 dark:bg-amber-900/20' => $area['pastByWhen'], 'bg-zinc-50 dark:bg-zinc-700/60' => ! $area['pastByWhen']])>
                <h2 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $area['lead']->full_name }}</h2>
                <div class="text-lg text-zinc-800 dark:text-zinc-200">{{ $area['teams'] }}</div>
                <div class="mt-2 text-base text-zinc-700 dark:text-zinc-300">{{ $area['summary'] }}</div>
                @if ($area['pastByWhen'])
                    <div class="mt-1 text-base font-medium text-amber-900 dark:text-amber-200">{{ $area['pastByWhen'] }} past {{ $area['pastByWhen'] === 1 ? 'its' : 'their' }} by-when date</div>
                @endif
            </section>
        @endforeach
    </div>
</div>
