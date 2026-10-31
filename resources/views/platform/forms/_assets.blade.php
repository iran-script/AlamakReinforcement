@once
    @push('styles')
        <meta name="app-base-url" content="{{ request()->getBaseUrl() }}">
        @vite('resources/css/form-studio.css')
    @endpush
    @push('scripts')
        @vite('resources/js/form-studio.js')
    @endpush
@endonce
