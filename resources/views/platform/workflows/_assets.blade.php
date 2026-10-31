@once
@push('styles')<meta name="app-base-url" content="{{ request()->getBaseUrl() }}">@vite('resources/css/workflow-studio.css')@endpush
@push('scripts')@vite('resources/js/workflow-studio.js')@endpush
@endonce
