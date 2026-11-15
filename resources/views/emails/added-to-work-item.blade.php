<x-mail::message>
<p>{{ $addedBy->full_name }} added you to <a href="{{ route('home') }}">{{ str($workItem->title)->squish() }}</a>. It is on your horizon.</p>

@if ($reason)
<p>Why you: {{ str($reason)->squish() }}</p>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
