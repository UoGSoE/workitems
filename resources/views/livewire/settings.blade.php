<div class="mx-auto max-w-xl">
    <flux:heading size="xl" level="1">Settings</flux:heading>

    <form wire:submit="save" class="mt-6 space-y-6">
        <flux:input wire:model="jobTitle" label="Job title" description:trailing="Shown beside your name, so people can tell what you do." />

        <flux:switch
            wire:model="showsWorkToTeam"
            label="Let people on my team see my work items"
            description="This sets how a new work item starts. You can change who sees each work item on its own."
        />

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">Save</flux:button>
        </div>
    </form>
</div>
