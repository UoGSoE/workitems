<div class="mx-auto max-w-5xl">
    <flux:heading size="xl" level="1">My team</flux:heading>

    <form wire:submit="tryDraft" class="mt-6 space-y-4">
        <flux:heading level="2">Draft a new piece of work</flux:heading>

        <div class="grid gap-4 sm:grid-cols-3">
            <flux:input wire:model="draftTitle" label="What is it?" />
            <flux:date-picker wire:model.live="draftStartsOn" label="Starts around" selectable-header />
            <flux:date-picker wire:model="draftDueOn" label="By when" selectable-header />
        </div>

        <flux:checkbox.group wire:model="draftPersonIds" label="Try it against">
            <div class="flex flex-wrap gap-x-6 gap-y-2">
                @foreach ($plans as $plan)
                    <flux:checkbox wire:key="draft-person-{{ $plan['person']->id }}" value="{{ $plan['person']->id }}" label="{{ $plan['person']->full_name }}" />
                @endforeach
            </div>
        </flux:checkbox.group>

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">Try it</flux:button>
            @if ($tryingDraft)
                <flux:button wire:click="clearDraft">Clear</flux:button>
            @endif
        </div>
    </form>

    <flux:separator class="my-8" />

    @foreach ($plans as $plan)
        <section wire:key="person-{{ $plan['person']->id }}" class="mt-8 first-of-type:mt-0">
            <div class="flex items-center gap-4">
                <flux:heading size="lg" level="2">{{ $plan['person']->full_name }}</flux:heading>
                @if ($plan['otherWork']->isNotEmpty())
                    <flux:modal.trigger :name="'other-work-'.$plan['person']->id">
                        <flux:badge as="button" size="sm" aria-label="{{ $plan['otherWork']->sum->count() }} other {{ Str::plural('thing', $plan['otherWork']->sum->count()) }} for {{ $plan['person']->full_name }}">+ {{ $plan['otherWork']->sum->count() }}</flux:badge>
                    </flux:modal.trigger>
                @endif
            </div>

            <div class="mt-3 overflow-x-auto">
                <div class="grid min-w-[56rem] gap-y-1" style="grid-template-columns: repeat({{ $weeks }}, minmax(0, 1fr))">
                    @foreach ($months as $month)
                        <div wire:key="month-{{ $plan['person']->id }}-{{ $month['from'] }}" class="border-l border-zinc-200 pl-1 dark:border-zinc-700" style="grid-column: {{ $month['from'] }} / span {{ $month['span'] }}">
                            <flux:text size="sm">{{ $month['name'] }}</flux:text>
                        </div>
                    @endforeach

                    @if ($plan['draft'])
                        <div
                            class="truncate rounded border-2 border-dashed border-zinc-800 px-2 py-1 text-sm font-medium text-zinc-900 dark:border-white dark:text-white"
                            aria-label="Draft for {{ $plan['person']->full_name }}: {{ $draftTitle }}, {{ $plan['draft']['startsOn']->format('j M Y') }} to {{ $plan['draft']['dueOn']->format('j M Y') }}"
                            style="grid-column: {{ $plan['draft']['from'] }} / {{ $plan['draft']['to'] }}"
                        >Draft: {{ $draftTitle }}</div>
                    @endif

                    @forelse ($plan['bars'] as $bar)
                        <button
                            type="button"
                            wire:click="show({{ $bar['workItem']->id }})"
                            wire:key="bar-{{ $plan['person']->id }}-{{ $bar['workItem']->id }}"
                            class="cursor-pointer truncate rounded bg-zinc-200 px-2 py-1 text-left text-sm text-zinc-900 dark:bg-zinc-600 dark:text-white"
                            aria-label="{{ $bar['workItem']->title }}, {{ $bar['where'] }}, {{ $bar['workItem']->starts_on->format('j M Y') }} to {{ $bar['workItem']->due_on->format('j M Y') }}"
                            title="{{ $bar['workItem']->title }}: {{ $bar['workItem']->starts_on->format('j M') }} to {{ $bar['workItem']->due_on->format('j M Y') }}, {{ $bar['where'] }}"
                            style="grid-column: {{ $bar['from'] }} / {{ $bar['to'] }}"
                        >{{ $bar['workItem']->title }}</button>
                    @empty
                        <div style="grid-column: 1 / -1"><flux:text class="py-1">Nothing with dates yet.</flux:text></div>
                    @endforelse
                </div>
            </div>

            @if ($plan['draft'])
                <flux:text class="sr-only">
                    @if ($plan['draft']['overlaps']->isEmpty())
                        The draft does not overlap with any of their dated work.
                    @else
                        The draft overlaps with {{ $plan['draft']['overlaps']->join(', ', ' and ') }}.
                    @endif
                </flux:text>
            @endif

            @if ($plan['otherWork']->isNotEmpty())
                <flux:modal :name="'other-work-'.$plan['person']->id" variant="flyout">
                    <div class="space-y-6">
                        <flux:heading size="lg" level="2">Other work: {{ $plan['person']->full_name }}</flux:heading>

                        @foreach ($plan['otherWork'] as $where => $workItems)
                            <div wire:key="other-work-{{ $plan['person']->id }}-{{ $loop->index }}">
                                <flux:heading level="3">{{ $where }}</flux:heading>
                                <ul class="mt-2 space-y-1">
                                    @foreach ($workItems as $workItem)
                                        <li wire:key="other-work-{{ $plan['person']->id }}-{{ $workItem->id }}">
                                            <flux:button variant="subtle" size="sm" inset="left" wire:click="show({{ $workItem->id }})">{{ $workItem->title }}</flux:button>
                                            @if ($workItem->summary())
                                                <flux:text>{{ $workItem->summary() }}</flux:text>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </flux:modal>
            @endif
        </section>
    @endforeach

    <flux:modal name="work-item" variant="flyout">
        @if ($showing)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" level="2">{{ $showing->title }}</flux:heading>
                    @if ($showing->summary())
                        <flux:text class="mt-2">{{ $showing->summary() }}</flux:text>
                    @endif
                </div>

                <div>
                    <flux:heading level="3">Who is on it</flux:heading>
                    <flux:text class="mt-2">{{ $showing->people->map(fn ($person) => $person->full_name.($showing->isOwnedBy($person) ? ' (owner)' : ''))->join(', ', ' and ') }}</flux:text>
                </div>

                <div>
                    <flux:heading level="3">Who can see it</flux:heading>
                    <flux:text class="mt-2">{{ $showing->visibility->label() }}</flux:text>
                </div>

                <div>
                    <flux:heading level="3">Notes</flux:heading>
                    <flux:text class="mt-2 whitespace-pre-line">{{ $showing->notes ?: 'No notes.' }}</flux:text>
                </div>

                @if ($history->isNotEmpty())
                    <div>
                        <flux:heading level="3">History</flux:heading>
                        <ul class="mt-2 space-y-1">
                            @foreach ($history as $line)
                                <li wire:key="history-{{ $line->id }}"><flux:text>{{ $line->sentence() }}</flux:text></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="flex">
                    <flux:spacer />
                    <flux:button x-on:click="$flux.modal('work-item').close()" autofocus>Close</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
