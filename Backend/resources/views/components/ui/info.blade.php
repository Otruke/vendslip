{{-- A small "i" that explains something without cluttering the page: hover or focus on desktop, tap on phones.
     Same look and behaviour as the web app's InfoTip. The bubble is placed by resources/js/app.js. --}}
@props(['label' => 'More info'])
<button type="button" data-tip aria-label="{{ $label }}" aria-expanded="false" {{ $attributes->merge(['class' => 'vs-info']) }}><span aria-hidden="true">i</span><span data-tip-body hidden>{{ $slot }}</span></button>
