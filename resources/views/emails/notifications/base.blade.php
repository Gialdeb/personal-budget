<x-mail::message>
# {{ $title }}

{{ $message }}

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
@endif

{{ $footer }}
</x-mail::message>
