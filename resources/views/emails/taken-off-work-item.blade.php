<x-mail::message>
<p>{{ $who->full_name }} {{ $whatHappened }} <a href="{{ route('home') }}">{{ $workItem->title }}</a>.</p>

@if ($theirMessage)
<p>Message: {!! nl2br(e($theirMessage)) !!}</p>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
