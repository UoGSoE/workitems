<div class="mx-auto max-w-3xl">
    <flux:heading level="1" class="sr-only">My work</flux:heading>

    <form wire:submit="add">
        <flux:input wire:model="whatsOn" id="whats-on" label="What's on?" description:trailing="Press Enter to put it on your plate." autofocus />
    </form>

    <div class="mt-2 flex justify-end">
        <flux:modal.trigger name="palette" shortcut="cmd.k" x-on:keydown.ctrl.k.document="$event.preventDefault(); $dispatch('modal-show', { name: 'palette' })">
            <flux:button size="sm" variant="subtle" icon="magnifying-glass" kbd="⌘K">Find a work item</flux:button>
        </flux:modal.trigger>
    </div>

    @foreach ($sections as $section)
        @continue(! $workItemsBySection->has($section->value))

        <section wire:key="section-{{ $section->value }}" class="mt-8">
            <flux:heading size="lg" level="2">{{ $section->label() }}</flux:heading>

            <ul class="mt-2 divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($workItemsBySection[$section->value] as $workItem)
                    <li wire:key="work-item-{{ $workItem->id }}" class="flex items-center gap-4 py-3">
                        <div class="min-w-0 flex-1">
                            <flux:text size="lg" variant="strong">{{ $workItem->title }}</flux:text>
                            @if ($workItem->summary())
                                <flux:text class="mt-1">{{ $workItem->summary() }}</flux:text>
                            @endif
                            @if ($workItem->pivot->wasAddedBySomeoneElse())
                                <flux:text class="mt-1">
                                    {{ $workItem->pivot->addedByLine() }}
                                    <flux:link href="{{ $workItem->wutLink() }}" aria-label="Wut? Ask {{ $workItem->pivot->addedBy->full_name }} why you were added to {{ $workItem->title }}">Wut?</flux:link>
                                </flux:text>
                            @endif
                            @if ($workItem->colleaguesOf(auth()->user())->isNotEmpty())
                                <flux:text class="mt-1">{{ $workItem->withLine(auth()->user()) }}</flux:text>
                            @endif
                        </div>

                        <flux:button variant="subtle" size="sm" icon="ellipsis-horizontal" wire:click="openPaletteFor({{ $workItem->id }})" aria-label="What to do with {{ $workItem->title }}" />
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach

    <flux:modal name="edit-work-item" variant="flyout">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg" level="2">Details</flux:heading>

            <flux:input wire:model="title" label="What is it?" autofocus />
            <flux:textarea wire:model="notes" label="Notes" rows="auto" />
            <flux:input wire:model="effortDays" type="number" min="1" label="Rough effort, in days" />
            <flux:input wire:model="elapsedDays" type="number" min="1" label="Rough elapsed time, in days" description:trailing="A day of work can still take a week to land." />
            <flux:input wire:model="dueOn" type="date" label="By when" />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button x-on:click="$flux.modal('edit-work-item').close()">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
        </form>

        @if ($history->isNotEmpty())
            <flux:separator class="my-8" />

            <flux:heading level="3">History</flux:heading>
            <ul class="mt-2 space-y-1">
                @foreach ($history as $line)
                    <li wire:key="history-{{ $line->id }}"><flux:text>{{ $line->sentence() }}</flux:text></li>
                @endforeach
            </ul>
        @endif
    </flux:modal>

    <flux:modal name="add-person" variant="flyout">
        <form wire:submit="addPerson" class="space-y-6">
            <flux:heading size="lg" level="2">Add someone</flux:heading>

            <flux:select wire:model="personId" variant="combobox" label="Who?">
                <x-slot name="input">
                    <flux:select.input placeholder="Type a name" autofocus />
                </x-slot>

                @foreach ($peopleToAdd as $person)
                    <flux:select.option value="{{ $person->id }}" wire:key="person-{{ $person->id }}">{{ $person->full_name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="reason" label="Why them?" description:trailing="Optional. It lands on their horizon with this beside it." />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button x-on:click="$flux.modal('add-person').close()">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Add</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="take-off" variant="flyout">
        @if ($personToTakeOff)
            <form wire:submit="takeOff" class="space-y-6">
                <div>
                    <flux:heading size="lg" level="2">Take {{ $personToTakeOff->nameAsSeenBy(auth()->user()) }} off this</flux:heading>
                    <flux:text class="mt-2">{{ $takingOffFrom->title }}</flux:text>
                </div>

                <flux:field>
                    <flux:label>Anything to say?</flux:label>
                    <flux:textarea wire:model="message" rows="auto" autofocus />
                    <flux:description>Optional. {{ $whoGetsTold->full_name }} gets an email to let them know, with or without it.</flux:description>
                    <flux:error name="message" />
                </flux:field>

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:button x-on:click="$flux.modal('take-off').close()">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Take {{ $personToTakeOff->nameAsSeenBy(auth()->user()) }} off</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>

    {{--
        A mouse pointer resting where the palette opens must not choose what Enter does.
        So hovering is ignored until the mouse really moves, and at that moment the
        entry under the pointer is highlighted as if the mouse had just arrived on it.
    --}}
    <div
        x-data="{
            mouseHasMoved: false,
            noticeMouse(event) {
                if (this.mouseHasMoved || ! (event.movementX || event.movementY)) {
                    return
                }

                this.mouseHasMoved = true
                event.target.closest('[data-flux-command-item]')?.dispatchEvent(new MouseEvent('mouseenter'))
            },
        }"
        x-on:modal-show.document="mouseHasMoved = false"
        x-on:mousemove.window="noticeMouse($event)"
    >
    <flux:modal name="palette" variant="bare" class="w-full max-w-[30rem] my-[12vh] max-h-screen overflow-y-hidden" @close="unpick">
        <flux:command wire:key="palette-{{ $picked?->id ?? 'all' }}" class="border-none shadow-lg inline-flex flex-col max-h-[76vh]" x-init="mouseHasMoved = false" x-on:mouseenter.capture="mouseHasMoved || $event.stopPropagation()">
            @if ($picked)
                {{-- On a touch screen the actions are tapped, so the on-screen keyboard is kept out of the way. --}}
                <flux:command.input :placeholder="$picked->title.': do what?'" closable autofocus x-init="$nextTick(() => $el.focus())" x-bind:inputmode="window.matchMedia('(pointer: coarse)').matches ? 'none' : null" />

                <flux:command.items>
                    @foreach ($sections->sortByDesc(fn ($section) => $section === $done) as $section)
                        @continue($section === $picked->section())

                        <flux:command.item wire:key="palette-move-{{ $section->value }}" :icon="$section->moveIcon()" wire:click="moveTo({{ $picked->id }}, '{{ $section->value }}')" x-on:click="$flux.modal('palette').close()">{{ $section->moveLabel() }}</flux:command.item>
                    @endforeach

                    <flux:command.item icon="pencil-square" wire:click="edit({{ $picked->id }})" x-on:click="$flux.modal('palette').close()">Details</flux:command.item>
                    <flux:command.item icon="user-plus" wire:click="choosePersonFor({{ $picked->id }})" x-on:click="$flux.modal('palette').close()">Add someone</flux:command.item>
                    @if ($picked->owner->isNot(auth()->user()))
                        <flux:command.item icon="hand-raised" wire:click="takeOver({{ $picked->id }})" x-on:click="$flux.modal('palette').close()">Take this over</flux:command.item>
                    @endif

                    @foreach ($picked->peopleWhoCanBeTakenOffBy(auth()->user()) as $person)
                        <flux:command.item wire:key="palette-take-off-{{ $person->id }}" icon="x-mark" wire:click="chooseToTakeOff({{ $picked->id }}, {{ $person->id }})" x-on:click="$flux.modal('palette').close()">Take {{ $person->nameAsSeenBy(auth()->user()) }} off this</flux:command.item>
                    @endforeach

                    <flux:command.item icon="arrow-left" wire:click="unpick">Back to all work items</flux:command.item>
                </flux:command.items>
            @else
                <flux:command.input placeholder="Find a work item" closable autofocus x-init="$nextTick(() => $el.focus())" />

                <flux:command.items>
                    <flux:command.item icon="plus" x-on:click="$flux.modal('palette').close(); $nextTick(() => document.getElementById('whats-on').focus())">Add new item</flux:command.item>

                    @foreach ($workItems as $workItem)
                        <flux:command.item wire:key="palette-work-item-{{ $workItem->id }}" wire:click="pick({{ $workItem->id }})">{{ $workItem->title }}</flux:command.item>
                    @endforeach
                </flux:command.items>
            @endif
        </flux:command>
    </flux:modal>
    </div>
</div>
