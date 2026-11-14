<x-mail::message>
<p>{{ $newOwner->full_name }} took over <a href="{{ route('home') }}">{{ $workItem->title }}</a> from you.</p>

<p>You are still on it. If your part is finished, you can now take yourself off.</p>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
