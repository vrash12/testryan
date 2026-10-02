<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Activity logs</title>
    <style>body{font-family:system-ui,sans-serif;color:#292524;margin:2rem}h1{font-size:1.5rem}table{border-collapse:collapse;width:100%;font-size:.8rem}th,td{border-bottom:1px solid #d6d3d1;padding:.55rem;text-align:left;vertical-align:top}th{color:#57534e;text-transform:uppercase;font-size:.7rem}.print-limit{border:1px solid #a8a29e;padding:.75rem}@media print{body{margin:.5rem}.no-print{display:none}}</style>
</head>
<body>
    <div class="no-print"><a href="{{ route('admin.activity.index', request()->query()) }}">Back to activity logs</a></div>
    <h1>Activity logs</h1>
    <p>Generated {{ now()->format('M j, Y g:i A') }} · {{ number_format($logs->count()) }} of {{ number_format($total) }} matching records</p>
    <p>Matching records: {{ number_format($stats['admin_associated']) }} admin-associated · {{ number_format($stats['contractor_associated']) }} contractor-associated · {{ number_format($stats['orphan']) }} deleted or missing account · {{ number_format($stats['today']) }} today ({{ config('app.timezone') }}).</p>
    <p>Historical account associations may differ from the person who performed the action.</p>
    @if($total > $printLimit)
        <p class="print-limit">This print view includes the latest {{ number_format($printLimit) }} matching records. Narrow the date or action filters to print the remaining records.</p>
    @endif
    @include('admin.activity._table', ['logs' => $logs])
    <script>window.addEventListener('load',()=>window.print())</script>
</body>
</html>
