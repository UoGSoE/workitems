<x-mail::message>
<p>{{ $who->full_name }} {{ $whatHappened }}:</p>
<ul>
@foreach ($workItems as $workItem)
<li>{{ str($workItem->title)->squish() }}</li>
@endforeach
</ul>
<p><a href="{{ route('home') }}">Open {{ config('app.name') }}</a></p>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
