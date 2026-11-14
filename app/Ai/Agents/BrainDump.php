<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Turns everything a person says is on their plate into draft work items for them to keep, edit or bin.
 */
class BrainDump implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $today = now()->format('l j F Y');

        return <<<TEXT
        Someone who works in a small IT team is going to tell you, in one go, everything they are carrying at work.
        Turn what they say into a list of draft work items. They will check every one, so only write down what they said.

        Today is {$today}.

        Rules:
        - One work item per piece of work. A job repeated across many things ("upgrade every app") is one work item, not many.
        - Work items are coarse: things that take days or weeks. Do not break one into steps.
        - Keep titles short and in the person's own words. Keep their technical terms exactly as said.
        - Rough effort and elapsed time are whole days. Effort is hands-on work; elapsed is start to finish, including waiting. Leave either empty if they did not say.
        - Only give a date if they gave one. "By mid February" means the 15th of the next February.
        - Something they are doing now goes on "my_plate". Something not started, or waiting on something else, goes on "horizon".
        - If one work item cannot start until another is done, or until someone else does something, say what in "waiting_on".
        - Leave out complaints, opinions and anything about other people that is not needed to describe the work.
        - Do not invent work, numbers or dates.
        TEXT;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'work_items' => $schema->array()->items($schema->object(fn (JsonSchema $schema) => [
                'title' => $schema->string()->required(),
                'notes' => $schema->string()->nullable()->required(),
                'section' => $schema->string()->enum(['my_plate', 'horizon'])->required(),
                'effort_days' => $schema->integer()->nullable()->required(),
                'elapsed_days' => $schema->integer()->nullable()->required(),
                'due_on' => $schema->string()->description('YYYY-MM-DD')->nullable()->required(),
                'waiting_on' => $schema->string()->nullable()->required(),
            ]))->required(),
        ];
    }
}
