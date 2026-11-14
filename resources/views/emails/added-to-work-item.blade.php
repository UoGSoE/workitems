<x-mail::message>
<p>{{ $addedBy->full_name }} added you to <a href="{{ route('home') }}">{{ $workItem->title }}</a>. It is on your horizon.</p>

@if ($reason)
<p>Why you: {{ $reason }}</p>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
