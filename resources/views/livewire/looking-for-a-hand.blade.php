<div class="mx-auto max-w-3xl">
    <flux:heading size="xl" level="1">Classifieds</flux:heading>
    <flux:text class="mt-2">Work that someone would like help with, or that might be a good way to learn something. It comes from every team.</flux:text>

    @if ($lookingForAHand->isEmpty())
        <flux:text class="mt-6">Nothing in the classifieds just now.</flux:text>
    @else
        <ul class="mt-6 divide-y divide-zinc-200 dark:divide-zinc-700">
            @foreach ($lookingForAHand as $workItem)
                <li wire:key="looking-for-a-hand-{{ $workItem->id }}" class="py-4">
                    <flux:heading level="2">{{ $workItem->title }}</flux:heading>
                    <flux:text class="mt-1">{{ $workItem->owner->name_with_job_title }}</flux:text>
                    @if ($workItem->summary())
                        <flux:text class="mt-1">{{ $workItem->summary() }}</flux:text>
                    @endif
                    @if ($workItem->blurb)
                        <div class="mt-2 space-y-2 text-sm text-zinc-500 dark:text-white/70">{!! $workItem->blurbAsHtml() !!}</div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($myTeamsWork->isNotEmpty())
        <flux:separator variant="subtle" class="my-8" />

        <flux:heading size="lg" level="2">My team's work</flux:heading>
        <flux:text class="mt-2">Work your team has chosen to show you, that you are not on yourself.</flux:text>

        <ul class="mt-6 divide-y divide-zinc-200 dark:divide-zinc-700">
            @foreach ($myTeamsWork as $workItem)
                <li wire:key="my-teams-work-{{ $workItem->id }}" class="py-4">
                    <flux:heading level="3">{{ $workItem->title }}</flux:heading>
                    <flux:text class="mt-1">{{ $workItem->owner->name_with_job_title }}</flux:text>
                    @if ($workItem->summary())
                        <flux:text class="mt-1">{{ $workItem->summary() }}</flux:text>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
