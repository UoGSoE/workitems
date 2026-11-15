<div class="mx-auto max-w-4xl">
    <div class="flex items-center gap-4">
        <flux:heading size="xl" level="1">People</flux:heading>
        <flux:spacer />
        <flux:modal.trigger name="add-person">
            <flux:button icon="plus">Add a person</flux:button>
        </flux:modal.trigger>
    </div>

    <flux:table class="mt-6">
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Team</flux:table.column>
            <flux:table.column>Reports to</flux:table.column>
            <flux:table.column><span class="sr-only">Actions</span></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($people as $person)
                <flux:table.row wire:key="person-{{ $person->id }}">
                    <flux:table.cell variant="strong">
                        {{ $person->name_with_job_title }}
                        @if ($person->is_admin)
                            <flux:badge size="sm" inset="top bottom" class="ml-2">Developer</flux:badge>
                        @endif
                        @if ($person->is_people_admin)
                            <flux:badge size="sm" inset="top bottom" class="ml-2">Manages people</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $person->team?->name }}</flux:table.cell>
                    <flux:table.cell>{{ $person->lineManager()?->full_name }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:dropdown>
                            <flux:button variant="subtle" size="sm" icon="ellipsis-horizontal" inset="top bottom" aria-label="What to do about {{ $person->full_name }}" />
                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="editPerson({{ $person->id }})">Edit</flux:menu.item>
                                @if ($person->isNot(auth()->user()))
                                    @if (auth()->user()->canImpersonate())
                                        <flux:menu.item icon="eye" href="{{ route('impersonate', $person->id) }}">Impersonate</flux:menu.item>
                                    @endif
                                    <flux:menu.item icon="arrow-right-start-on-rectangle" variant="danger" wire:click="chooseWhoIsGoing({{ $person->id }})">Mark as gone</flux:menu.item>
                                @endif
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    @if ($peopleWhoHaveLeft->isNotEmpty())
        <flux:heading level="2" class="mt-8">People who have left</flux:heading>
        <ul class="mt-2 space-y-1">
            @foreach ($peopleWhoHaveLeft as $person)
                <li wire:key="left-{{ $person->id }}"><flux:text>{{ $person->name_with_job_title }}, left on {{ $person->left_on->format('j M Y') }}</flux:text></li>
            @endforeach
        </ul>
    @endif

    <flux:separator variant="subtle" class="my-8" />

    <div class="flex items-center gap-4">
        <div>
            <flux:heading size="lg" level="2">Teams</flux:heading>
            <flux:text class="mt-2">The members of a team report to its lead.</flux:text>
        </div>
        <flux:spacer />
        <flux:button icon="plus" wire:click="newTeam">Add a team</flux:button>
    </div>

    <flux:table class="mt-6">
        <flux:table.columns>
            <flux:table.column>Team</flux:table.column>
            <flux:table.column>Led by</flux:table.column>
            <flux:table.column>Members</flux:table.column>
            <flux:table.column><span class="sr-only">Actions</span></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($teams as $team)
                <flux:table.row wire:key="team-{{ $team->id }}">
                    <flux:table.cell variant="strong">{{ $team->name }}</flux:table.cell>
                    <flux:table.cell>{{ $team->lead?->full_name }}</flux:table.cell>
                    <flux:table.cell>{{ $team->members_count }} {{ str('member')->plural($team->members_count) }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button variant="subtle" size="sm" icon="pencil-square" inset="top bottom" wire:click="editTeam({{ $team->id }})" aria-label="Edit the {{ $team->name }} team" />
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    @if ($recentChanges->isNotEmpty())
        <flux:separator variant="subtle" class="my-8" />

        <flux:heading size="lg" level="2">Recent changes</flux:heading>
        <flux:text class="mt-2">What has been done on this page, and by whom.</flux:text>
        <ul class="mt-4 space-y-1">
            @foreach ($recentChanges as $line)
                <li wire:key="change-{{ $line->id }}"><flux:text>{{ $line->sentence() }}</flux:text></li>
            @endforeach
        </ul>
    @endif

    <flux:modal name="add-person" variant="flyout">
        <form wire:submit="addPerson" class="space-y-6">
            <flux:heading size="lg" level="2">Add a person</flux:heading>

            <flux:input wire:model="newUsername" label="Username" autofocus />
            <flux:input wire:model="newEmail" type="email" label="Email" description:trailing="They sign in with this, so it has to match exactly." />
            <flux:input wire:model="newForenames" label="Forenames" />
            <flux:input wire:model="newSurname" label="Surname" />
            <flux:input wire:model="newJobTitle" label="Job title" description:trailing="Optional. They can change it themselves." />
            <flux:select wire:model="newTeamId" label="Team">
                <flux:select.option value="">No team</flux:select.option>
                @foreach ($teams as $team)
                    <flux:select.option value="{{ $team->id }}" wire:key="new-person-team-{{ $team->id }}">{{ $team->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:switch wire:model="newManagesPeople" label="Manages people" description="Can use this page: add people, set teams and who leads them, and mark people as gone. Nothing else." />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button x-on:click="$flux.modal('add-person').close()">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Add</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="edit-person" variant="flyout">
        @if ($editingPerson)
            <form wire:submit="savePerson" class="space-y-6">
                <flux:heading size="lg" level="2">{{ $editingPerson->full_name }}</flux:heading>

                <flux:input wire:model="jobTitle" label="Job title" autofocus />
                <flux:select wire:model="teamId" label="Team">
                    <flux:select.option value="">No team</flux:select.option>
                    @foreach ($teams as $team)
                        <flux:select.option value="{{ $team->id }}" wire:key="edit-person-team-{{ $team->id }}">{{ $team->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if ($editingPerson->isNot(auth()->user()))
                    <flux:switch wire:model="managesPeople" label="Manages people" description="Can use this page: add people, set teams and who leads them, and mark people as gone. Nothing else." />
                @endif

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:button x-on:click="$flux.modal('edit-person').close()">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Save</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>

    <flux:modal name="mark-as-gone" variant="flyout">
        @if ($leaver)
            <form wire:submit="markAsGone" class="space-y-6">
                <div>
                    <flux:heading size="lg" level="2">Mark {{ $leaver->full_name }} as gone</flux:heading>
                    <flux:text class="mt-2">They will not be able to sign in, and nobody will be able to add them to work. Their name stays in the history of the work they did.</flux:text>
                </div>

                @if ($workTheyAreOn === 0)
                    <flux:text>{{ $leaver->full_name }} is not on any work that is still going on.</flux:text>
                @else
                    <flux:text>{{ $leaver->full_name }} is on {{ $workTheyAreOn }} {{ str('work item')->plural($workTheyAreOn) }} that {{ $workTheyAreOn === 1 ? 'is' : 'are' }} still going on, and owns {{ $workTheyOwn }} of them.</flux:text>
                @endif

                @if ($workTheyOwn > 0)
                    <flux:select wire:model="giveWorkToId" label="Give the work they own to" description:trailing="It lands on that person's horizon, and they get an email for each work item." autofocus>
                        <flux:select.option value="">Choose someone</flux:select.option>
                        @foreach ($whoCouldTakeOnTheirWork as $person)
                            <flux:select.option value="{{ $person->id }}" wire:key="give-work-to-{{ $person->id }}">{{ $person->full_name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                @if ($leaver->ledTeams->isNotEmpty())
                    <flux:select wire:model="newLeadId" label="Who leads {{ $leaver->ledTeams->pluck('name')->join(', ', ' and ') }} now?" description:trailing="It cannot be someone who is a member of that team.">
                        <flux:select.option value="">Nobody yet</flux:select.option>
                        @foreach ($people->reject(fn ($person) => $person->is($leaver)) as $person)
                            <flux:select.option value="{{ $person->id }}" wire:key="new-lead-{{ $person->id }}">{{ $person->full_name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:button x-on:click="$flux.modal('mark-as-gone').close()">Cancel</flux:button>
                    <flux:button type="submit" variant="danger">Mark as gone</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>

    <flux:modal name="team" variant="flyout">
        <form wire:submit="saveTeam" class="space-y-6">
            <flux:heading size="lg" level="2">{{ $editingTeamId ? 'Team' : 'Add a team' }}</flux:heading>

            <flux:input wire:model="teamName" label="Name" autofocus />
            <flux:select wire:model="leadId" label="Led by" description:trailing="The lead cannot also be a member of the team.">
                <flux:select.option value="">Nobody yet</flux:select.option>
                @foreach ($people as $person)
                    <flux:select.option value="{{ $person->id }}" wire:key="lead-{{ $person->id }}">{{ $person->full_name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button x-on:click="$flux.modal('team').close()">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
