@php
    // Floating, auto-dismissing toasts for flash messages (behaviour lives in resources/js/app.js).
    $messages = [];
    foreach (['status' => 'success', 'success' => 'success', 'error' => 'error', 'warning' => 'warning', 'info' => 'info'] as $key => $tone) {
        if (is_string(session($key)) && session($key) !== '') {
            $messages[] = [$tone, session($key)];
        }
    }
    if (isset($errors) && $errors->any()) {
        $messages[] = ['error', $errors->count() === 1 ? $errors->first() : 'Please fix the highlighted fields ('.$errors->count().').'];
    }
    $titles = ['success' => 'Done', 'error' => 'Couldn’t do that', 'warning' => 'Heads up', 'info' => 'Note'];
    $icons = ['success' => 'check-circle', 'error' => 'x-circle', 'warning' => 'alert-triangle', 'info' => 'info'];
@endphp
<div class="ns-toasts" data-toasts aria-live="polite" aria-relevant="additions">
    @foreach ($messages as [$tone, $text])
        <div class="ns-toast ns-toast--{{ $tone }}" role="{{ $tone === 'error' ? 'alert' : 'status' }}" data-toast data-toast-ms="{{ $tone === 'error' ? 8000 : 5200 }}" style="--ns-toast-ms: {{ $tone === 'error' ? 8000 : 5200 }}ms">
            <span class="ns-toast__icon"><x-ui.icon :name="$icons[$tone]" class="size-4" /></span>
            <div class="min-w-0">
                <p class="ns-toast__title">{{ $titles[$tone] }}</p>
                <p class="ns-toast__body">{{ $text }}</p>
            </div>
            <button type="button" class="ns-toast__close" data-toast-close aria-label="Dismiss"><x-ui.icon name="x" class="size-4" /></button>
            <span class="ns-toast__timer" aria-hidden="true"></span>
        </div>
    @endforeach
</div>
