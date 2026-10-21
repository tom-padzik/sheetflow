<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('sheetflow::messages.title') }}</title>
    <link rel="stylesheet" href="{{ route('sheetflow.assets.stylesheet') }}">
</head>
<body>
    <main
        class="sheetflow"
        id="sheetflow-wizard"
        data-definition="{{ $definition }}"
        data-api="{{ $apiBase }}"
        data-messages="{{ json_encode(__('sheetflow::messages')) }}"
    >
        <h1>{{ __('sheetflow::messages.title') }}</h1>
        <p class="lead">{{ __('sheetflow::messages.lead') }}</p>
        <div id="sheetflow-app"></div>
    </main>
    <script src="{{ route('sheetflow.assets.wizard') }}"></script>
</body>
</html>
