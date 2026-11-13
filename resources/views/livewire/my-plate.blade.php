<div class="mx-auto max-w-3xl">
    <flux:heading level="1" class="sr-only">My work</flux:heading>

    <form wire:submit="add">
        <flux:input wire:model="whatsOn" label="What's on?" description:trailing="Press Enter to put it on your plate." autofocus />
    </form>

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
                            @if ($workItem->colleaguesOf(auth()->user())->isNotEmpty())
                                <flux:text class="mt-1">With {{ $workItem->colleaguesOf(auth()->user())->pluck('full_name')->join(', ', ' and ') }}</flux:text>
                            @endif
                        </div>

                        @if ($section !== $done)
                            <flux:button
                                size="sm"
                                wire:click="moveTo({{ $workItem->id }}, '{{ $done->value }}')"
                                aria-label="{{ $done->moveLabel() }}: {{ $workItem->title }}"
                            >{{ $done->moveLabel() }}</flux:button>
                        @endif

                        <flux:dropdown align="end">
                            <flux:button variant="subtle" size="sm" icon="ellipsis-horizontal" aria-label="More options for {{ $workItem->title }}" />

                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="edit({{ $workItem->id }})">Edit</flux:menu.item>
                                <flux:menu.item icon="user-plus" wire:click="choosePersonFor({{ $workItem->id }})">Add someone</flux:menu.item>
                                <flux:menu.separator />

                                @foreach ($sections as $otherSection)
                                    @continue($otherSection === $section || $otherSection === $done)

                                    <flux:menu.item wire:click="moveTo({{ $workItem->id }}, '{{ $otherSection->value }}')">{{ $otherSection->moveLabel() }}</flux:menu.item>
                                @endforeach
                            </flux:menu>
                        </flux:dropdown>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach

    <flux:modal name="edit-work-item" variant="flyout">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg" level="2">Details</flux:heading>

            <flux:input wire:model="title" label="What is it?" />
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
    </flux:modal>

    <flux:modal name="add-person" variant="flyout">
        <form wire:submit="addPerson" class="space-y-6">
            <flux:heading size="lg" level="2">Add someone</flux:heading>

            <flux:select wire:model="personId" variant="listbox" searchable label="Who?" placeholder="Choose a colleague">
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
</div>
