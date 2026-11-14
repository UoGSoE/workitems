<?php

namespace App\Console\Commands;

use App\Ai\Agents\BrainDump;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:try-brain-dump {file : A text file holding the brain dump} {--provider=ollama} {--model=gemma4:e4b}')]
#[Description('Throwaway spike: send a brain dump to a model and show the draft work items that come back')]
class TryBrainDump extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startedAt = microtime(true);

        $response = (new BrainDump)->prompt(
            file_get_contents($this->argument('file')),
            provider: $this->option('provider'),
            model: $this->option('model'),
            timeout: 300,
        );

        $this->table(
            ['Title', 'Section', 'Effort', 'Elapsed', 'Due', 'Waiting on', 'Notes'],
            collect($response['work_items'])->map(fn (array $workItem) => [
                $workItem['title'],
                $workItem['section'],
                $workItem['effort_days'],
                $workItem['elapsed_days'],
                $workItem['due_on'],
                $workItem['waiting_on'],
                $workItem['notes'],
            ]),
        );

        $this->line('Seconds taken: '.round(microtime(true) - $startedAt));
        $this->line('Thinking kept apart from the answer: '.strlen($response->reasoning).' characters');

        return self::SUCCESS;
    }
}
