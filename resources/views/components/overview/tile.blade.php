@props(['label', 'value', 'amber' => false])

<div @class(['rounded-lg p-4', 'bg-amber-100 dark:bg-amber-900/40' => $amber, 'bg-zinc-100 dark:bg-zinc-700' => ! $amber])>
    <dt @class(['text-sm font-medium', 'text-amber-800 dark:text-amber-300' => $amber, 'text-zinc-600 dark:text-zinc-300' => ! $amber])>{{ $label }}</dt>
    <dd @class(['text-4xl font-bold tabular-nums', 'text-amber-900 dark:text-amber-100' => $amber, 'text-zinc-900 dark:text-zinc-100' => ! $amber])>{{ $value }}</dd>
</div>
