<?php

namespace App\Livewire;

use App\Events\SomethingNoteworthyHappened;
use App\Models\ActivityLog;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkItem;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class People extends Component
{
    public $newUsername = '';

    public $newEmail = '';

    public $newSurname = '';

    public $newForenames = '';

    public $newJobTitle = '';

    public $newTeamId = '';

    public $newManagesPeople = false;

    #[Locked]
    public $editingPersonId = null;

    public $jobTitle = '';

    public $teamId = '';

    public $managesPeople = false;

    #[Locked]
    public $editingTeamId = null;

    public $teamName = '';

    public $leadId = '';

    #[Locked]
    public $leavingPersonId = null;

    public $giveWorkToId = '';

    public $newLeadId = '';

    public function render()
    {
        $everyone = User::with('team.lead')->orderBy('surname')->orderBy('forenames')->get();
        $people = $everyone->reject->hasLeft();
        $leaver = $people->find($this->leavingPersonId);
        $workTheyAreOn = $leaver?->unfinishedWorkItems() ?? collect();

        return view('livewire.people', [
            'people' => $people,
            'peopleWhoHaveLeft' => $everyone->filter->hasLeft(),
            'teams' => Team::with('lead')->withCount('members')->orderBy('name')->get(),
            'editingPerson' => $people->find($this->editingPersonId),
            'recentChanges' => ActivityLog::with('user')->latest()->latest('id')->limit(20)->get(),
            'leaver' => $leaver,
            'whoCouldTakeOnTheirWork' => $leaver?->peopleWhoCouldTakeOnTheirWork(asChosenBy: auth()->user()) ?? collect(),
            'workTheyAreOn' => $workTheyAreOn->count(),
            'workTheyOwn' => $workTheyAreOn->filter(fn (WorkItem $workItem) => $workItem->isOwnedBy($leaver))->count(),
        ]);
    }

    public function boot(): void
    {
        abort_unless(auth()->user()->canManagePeople(), 403);
    }

    public function addPerson(): void
    {
        $this->validate([
            'newUsername' => 'required|string|max:255|unique:users,username',
            'newEmail' => 'required|email|max:255|unique:users,email',
            'newSurname' => 'required|string|max:255',
            'newForenames' => 'required|string|max:255',
            'newJobTitle' => 'nullable|string|max:255',
            'newTeamId' => 'nullable|integer|exists:teams,id',
            'newManagesPeople' => 'boolean',
        ]);

        $person = User::create([
            'username' => $this->newUsername,
            'email' => $this->newEmail,
            'surname' => $this->newSurname,
            'forenames' => $this->newForenames,
            'job_title' => $this->newJobTitle ?: null,
            'team_id' => $this->newTeamId ?: null,
            'is_staff' => true,
            'is_people_admin' => $this->newManagesPeople,
            'password' => Str::random(64),
        ]);

        $this->record('added '.$person->full_name);

        $this->reset('newUsername', 'newEmail', 'newSurname', 'newForenames', 'newJobTitle', 'newTeamId', 'newManagesPeople');

        Flux::modal('add-person')->close();
    }

    public function editPerson(int $personId): void
    {
        $person = User::current()->findOrFail($personId);

        $this->editingPersonId = $person->id;
        $this->jobTitle = $person->job_title ?? '';
        $this->teamId = $person->team_id ?? '';
        $this->managesPeople = $person->is_people_admin;

        Flux::modal('edit-person')->show();
    }

    public function savePerson(): void
    {
        $person = User::current()->findOrFail($this->editingPersonId);

        $this->validate([
            'jobTitle' => 'nullable|string|max:255',
            'teamId' => ['nullable', 'integer', 'exists:teams,id', Rule::notIn($person->ledTeams()->pluck('id'))],
            'managesPeople' => 'boolean',
        ]);

        $teamBefore = $person->team;

        $person->update([
            'job_title' => $this->jobTitle ?: null,
            'team_id' => $this->teamId ?: null,
            'is_people_admin' => $person->is(auth()->user()) ? $person->is_people_admin : $this->managesPeople,
        ]);

        $this->recordChangesTo($person->load('team'), $teamBefore);

        Flux::modal('edit-person')->close();
    }

    public function newTeam(): void
    {
        $this->reset('editingTeamId', 'teamName', 'leadId');

        Flux::modal('team')->show();
    }

    public function editTeam(int $teamId): void
    {
        $team = Team::findOrFail($teamId);

        $this->editingTeamId = $team->id;
        $this->teamName = $team->name;
        $this->leadId = $team->lead_id ?? '';

        Flux::modal('team')->show();
    }

    public function saveTeam(): void
    {
        $members = Team::find($this->editingTeamId)?->members()->pluck('id') ?? [];

        $this->validate([
            'teamName' => 'required|string|max:255',
            'leadId' => ['nullable', Rule::in(User::current()->pluck('id')), Rule::notIn($members)],
        ]);

        $team = Team::findOrNew($this->editingTeamId);
        $nameBefore = $team->name;
        $leadBefore = $team->lead;

        $team->fill(['name' => $this->teamName, 'lead_id' => $this->leadId ?: null])->save();

        $this->recordChangesToTeam($team->load('lead'), $nameBefore, $leadBefore);

        Flux::modal('team')->close();
    }

    public function chooseWhoIsGoing(int $personId): void
    {
        $leaver = $this->someoneWhoCanBeMarkedAsGone($personId);

        $this->leavingPersonId = $leaver->id;
        $this->giveWorkToId = '';
        $this->newLeadId = $leaver->lineManager()?->id ?? '';

        Flux::modal('mark-as-gone')->show();
    }

    public function markAsGone(): void
    {
        $leaver = $this->someoneWhoCanBeMarkedAsGone($this->leavingPersonId);
        $whoCouldStepIn = User::current()->whereKeyNot($leaver->id)->pluck('id');

        $whoCouldTakeOnTheirWork = $leaver->peopleWhoCouldTakeOnTheirWork(asChosenBy: auth()->user())->modelKeys();

        $this->validate([
            'giveWorkToId' => [Rule::requiredIf($leaver->ownsUnfinishedWork()), 'nullable', Rule::in($whoCouldTakeOnTheirWork)],
            'newLeadId' => ['nullable', Rule::in($whoCouldStepIn), Rule::notIn($leaver->reports()->modelKeys())],
        ]);

        $giveWorkTo = $this->giveWorkToId ? User::find($this->giveWorkToId) : null;
        $newLead = $this->newLeadId ? User::find($this->newLeadId) : null;
        $teamsTheyLed = $leaver->ledTeams->pluck('name')->join(', ', ' and ');

        $leaver->markAsGone(by: auth()->user(), giveWorkTo: $giveWorkTo, newLeadOfTheirTeams: $newLead);

        $this->record(collect([
            'marked '.$leaver->full_name.' as gone',
            $giveWorkTo ? 'gave their work to '.$giveWorkTo->full_name : null,
            $teamsTheyLed && $newLead ? 'made '.$newLead->full_name.' the lead of '.$teamsTheyLed : null,
            $teamsTheyLed && ! $newLead ? 'left '.$teamsTheyLed.' without a lead' : null,
        ])->filter()->join(', ', ' and '));

        Flux::modal('mark-as-gone')->close();
    }

    /**
     * Keep one line for each thing that has just changed about a person.
     */
    private function recordChangesTo(User $person, ?Team $teamBefore): void
    {
        if ($person->team?->isNot($teamBefore) && $person->team) {
            $this->record('moved '.$person->full_name.' into '.$person->team->name);
        }

        if ($teamBefore && ! $person->team) {
            $this->record('took '.$person->full_name.' out of '.$teamBefore->name);
        }

        if ($person->wasChanged('job_title')) {
            $this->record($person->job_title ? 'changed the job title of '.$person->full_name.' to '.$person->job_title : 'removed the job title of '.$person->full_name);
        }

        if ($person->wasChanged('is_people_admin')) {
            $this->record($person->is_people_admin ? 'let '.$person->full_name.' manage people' : 'stopped '.$person->full_name.' managing people');
        }
    }

    /**
     * Keep one line for each thing that has just changed about a team.
     */
    private function recordChangesToTeam(Team $team, ?string $nameBefore, ?User $leadBefore): void
    {
        if ($team->wasRecentlyCreated) {
            $this->record('added the team '.$team->name);
        }

        if ($nameBefore && $nameBefore !== $team->name) {
            $this->record('renamed the team '.$nameBefore.' to '.$team->name);
        }

        if ($team->lead && $team->lead->isNot($leadBefore)) {
            $this->record('made '.$team->lead->full_name.' the lead of '.$team->name);
        }

        if ($leadBefore && ! $team->lead) {
            $this->record('left '.$team->name.' without a lead');
        }
    }

    /**
     * Keep a line saying what the person signed in has just done, eg "made Olivia McUser the lead of Developers".
     */
    private function record(string $what): void
    {
        event(new SomethingNoteworthyHappened(auth()->user(), $what));
    }

    /**
     * Anyone still here, apart from the admin doing it: marking yourself as gone would lock you out.
     */
    private function someoneWhoCanBeMarkedAsGone(?int $personId): User
    {
        return User::current()->whereKeyNot(auth()->id())->findOrFail($personId);
    }
}
