<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sheetflow</title>
    <link rel="stylesheet" href="{{ route('sheetflow.assets.stylesheet') }}">
</head>
<body>
    <main
        class="sheetflow"
        id="sheetflow-wizard"
        data-definition="{{ $definition }}"
        data-api="{{ $apiBase }}"
    >
        <h1>Import spreadsheet</h1>
        <p class="lead">Upload a CSV, XLSX, or ODS file, map the columns, then validate and import.</p>
        <div id="sheetflow-app"></div>
    </main>
    <script src="{{ route('sheetflow.assets.wizard') }}"></script>
</body>
</html>
