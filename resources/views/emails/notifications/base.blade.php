<x-mail::message :brand-tagline="$brandTagline ?? null">
# {{ $title }}

{{ $message }}

@if(! empty($notes))
@foreach($notes as $note)
{{ $note }}

@endforeach
@endif

@if(! empty($details))
## {{ $detailsTitle }}

<x-mail::panel>
@foreach($details as $detail)
**{{ $detail['label'] }}:** {{ $detail['value'] }}

@endforeach
</x-mail::panel>
@endif

@if(! empty($actionUrl) && ! empty($actionLabel))
<x-mail::button :url="$actionUrl">
{{ $actionLabel }}
</x-mail::button>

<x-slot:subcopy>
{{ __("If you're having trouble clicking the \":actionText\" button, copy and paste the URL below into your web browser:", ['actionText' => $actionLabel]) }} <span class="break-all">[{{ $actionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endif

@if(! empty($footer))
<x-mail::subcopy>
{{ $footer }}
</x-mail::subcopy>
@endif

</x-mail::message>
