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
                @foreach ($canDraftFor as $person)
                    <flux:checkbox wire:key="draft-person-{{ $person->id }}" value="{{ $person->id }}" label="{{ ucfirst($person->nameAsSeenBy(auth()->user())) }}" />
                @endforeach
            </div>
        </flux:checkbox.group>

        <div class="flex gap-2">
            <flux:button type="submit" :variant="$tryingDraft ? null : 'primary'">Try it</flux:button>
            @if ($tryingDraft)
                <flux:button wire:click="clearDraft">Clear</flux:button>
                <flux:spacer />
                <flux:button wire:click="addDraft" variant="primary">Add it for real</flux:button>
            @endif
        </div>
    </form>

    <flux:separator class="my-8" />

    @foreach ($plans as $plan)
        <section wire:key="person-{{ $plan['person']->id }}" class="mt-8 first-of-type:mt-0">
            <div class="flex items-center gap-4">
                <flux:heading size="lg" level="2">{{ $plan['name'] }}</flux:heading>
                @if ($plan['openWorkCount'])
                    <flux:modal.trigger :name="'work-of-'.$plan['person']->id">
                        <flux:badge as="button" size="sm" aria-label="{{ $plan['openWorkCount'] }} open work {{ Str::plural('item', $plan['openWorkCount']) }} for {{ $plan['name'] }}">{{ $plan['openWorkCount'] }}</flux:badge>
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
                        <flux:tooltip
                            :content="'Draft: '.$draftTitle.', '.$plan['draft']['startsOn']->format('j M').' to '.$plan['draft']['dueOn']->format('j M Y')"
                            style="grid-column: {{ $plan['draft']['from'] }} / {{ $plan['draft']['to'] }}"
                        >
                            <div
                                class="truncate rounded border-2 border-dashed border-zinc-800 px-2 py-1 text-sm font-medium text-zinc-900 dark:border-white dark:text-white"
                                aria-label="Draft for {{ $plan['person']->nameAsSeenBy(auth()->user()) }}: {{ $draftTitle }}, {{ $plan['draft']['startsOn']->format('j M Y') }} to {{ $plan['draft']['dueOn']->format('j M Y') }}"
                            >Draft: {{ $draftTitle }}</div>
                        </flux:tooltip>
                    @endif

                    @forelse ($plan['bars'] as $bar)
                        <flux:tooltip
                            wire:key="bar-{{ $plan['person']->id }}-{{ $bar['workItem']->id }}"
                            :content="$bar['workItem']->title.', '.$bar['workItem']->starts_on->format('j M').' to '.$bar['workItem']->due_on->format('j M Y')"
                            style="grid-column: {{ $bar['from'] }} / {{ $bar['to'] }}"
                        >
                            <button
                                type="button"
                                wire:click="show({{ $bar['workItem']->id }}, {{ $plan['person']->id }})"
                                class="block w-full cursor-pointer truncate rounded bg-zinc-200 px-2 py-1 text-left text-sm text-zinc-900 dark:bg-zinc-600 dark:text-white"
                                aria-label="{{ $bar['workItem']->title }}, {{ $bar['where'] }}, {{ $bar['workItem']->starts_on->format('j M Y') }} to {{ $bar['workItem']->due_on->format('j M Y') }}"
                            >{{ $bar['workItem']->title }}</button>
                        </flux:tooltip>
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

            @if ($plan['openWorkCount'])
                <flux:modal :name="'work-of-'.$plan['person']->id" variant="flyout">
                    <div class="space-y-6">
                        <flux:heading size="lg" level="2">{{ $plan['name'] }}</flux:heading>

                        @foreach ($plan['openWork'] as $where => $workItems)
                            <div wire:key="work-of-{{ $plan['person']->id }}-{{ $loop->index }}">
                                <flux:heading level="3">{{ $where }}</flux:heading>
                                <ul class="mt-2 space-y-1">
                                    @foreach ($workItems as $workItem)
                                        <li wire:key="work-of-{{ $plan['person']->id }}-{{ $workItem->id }}">
                                            <flux:button variant="subtle" size="sm" inset="left" wire:click="show({{ $workItem->id }}, {{ $plan['person']->id }})">{{ $workItem->title }}</flux:button>
                                            @if ($workItem->isSecret())
                                                <flux:badge size="sm" icon="lock-closed">Secret</flux:badge>
                                            @endif
                                            @if ($workItem->summary())
                                                <flux:text>{{ $workItem->summary() }}</flux:text>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach

                        @if ($plan['canMoveAllTo']->isNotEmpty())
                            <flux:separator />

                            <form wire:submit="askToMoveAll({{ $plan['person']->id }})" class="space-y-6">
                                <flux:select wire:model="moveAllTo.{{ $plan['person']->id }}" label="Move it all from {{ $plan['name'] }} to" placeholder="Choose someone">
                                    @foreach ($plan['canMoveAllTo'] as $person)
                                        <flux:select.option wire:key="move-all-{{ $plan['person']->id }}-to-{{ $person->id }}" value="{{ $person->id }}">{{ ucfirst($person->nameAsSeenBy(auth()->user())) }}</flux:select.option>
                                    @endforeach
                                </flux:select>

                                <flux:button type="submit">Move it all</flux:button>
                            </form>
                        @endif
                    </div>
                </flux:modal>
            @endif
        </section>
    @endforeach

    <flux:modal name="draft-owner" variant="flyout">
        @if ($tryingDraft)
            <form wire:submit="addDraftWithOwner" class="space-y-6">
                <flux:heading size="lg" level="2">Who owns it?</flux:heading>

                <flux:select wire:model="draftOwnerId" label="{{ $draftTitle }}" placeholder="Choose who owns it" autofocus>
                    @foreach ($plans->filter(fn ($plan) => in_array($plan['person']->id, $draftPersonIds)) as $plan)
                        <flux:select.option wire:key="draft-owner-{{ $plan['person']->id }}" value="{{ $plan['person']->id }}">{{ $plan['name'] }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">Add it for real</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>

    <flux:modal name="confirm-move-all" class="md:w-96">
        @if ($movingAllFrom)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" level="2">Move all {{ $movingAllFrom['openWorkCount'] }} work {{ Str::plural('item', $movingAllFrom['openWorkCount']) }}?</flux:heading>
                    <flux:text class="mt-2">From {{ $movingAllFrom['name'] }} to {{ ucfirst($movingAllTo->nameAsSeenBy(auth()->user())) }}. This cannot be undone in one go.</flux:text>
                </div>

                @if ($secretWarning)
                    <flux:callout variant="warning" icon="lock-closed" :text="$secretWarning" />
                @endif

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button autofocus>Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button wire:click="moveAll" variant="primary">Move it all</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

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

                @if ($canMoveTo->isNotEmpty())
                    <flux:separator />

                    @if ($showing->isSecret())
                        <flux:callout variant="warning" icon="lock-closed" text="This work item is secret. Whoever you move it to will be able to see it." />
                    @endif

                    <form wire:submit="move" id="move-work-item">
                        <flux:select wire:model="moveToId" label="Move it from {{ $showingFor->full_name }} to" placeholder="Choose someone">
                            @foreach ($canMoveTo as $person)
                                <flux:select.option wire:key="move-to-{{ $person->id }}" value="{{ $person->id }}">{{ ucfirst($person->nameAsSeenBy(auth()->user())) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </form>
                @endif

                <div class="flex gap-2">
                    @if ($canMoveTo->isNotEmpty())
                        <flux:button type="submit" form="move-work-item">Move it</flux:button>
                    @endif
                    <flux:spacer />
                    <flux:button x-on:click="$flux.modal('work-item').close()" autofocus>Close</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
