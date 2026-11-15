<?php

namespace App\Livewire;

use Flux\Flux;
use Livewire\Component;

class Settings extends Component
{
    public $jobTitle = '';

    public $showsWorkToTeam = false;

    public function mount(): void
    {
        $this->jobTitle = auth()->user()->job_title ?? '';
        $this->showsWorkToTeam = auth()->user()->shows_work_to_team;
    }

    public function render()
    {
        return view('livewire.settings');
    }

    public function save(): void
    {
        $this->validate([
            'jobTitle' => 'nullable|string|max:255',
            'showsWorkToTeam' => 'boolean',
        ]);

        auth()->user()->update([
            'job_title' => $this->jobTitle ?: null,
            'shows_work_to_team' => $this->showsWorkToTeam,
        ]);

        Flux::toast('Saved.', variant: 'success');
    }
}
