<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Contractor directory</title><style>body{font-family:system-ui,sans-serif;color:#292524;margin:2rem}h1{font-size:1.5rem}table{border-collapse:collapse;width:100%;font-size:.8rem}th,td{border-bottom:1px solid #d6d3d1;padding:.55rem;text-align:left;vertical-align:top;overflow-wrap:anywhere}th{color:#57534e;text-transform:uppercase;font-size:.7rem}.notice{padding:.8rem;border:1px solid #d6d3d1}@media print{body{margin:.5rem}.no-print{display:none}thead{display:table-header-group}tr{break-inside:avoid}}</style></head>
<body>
    <div class="no-print"><a href="{{ route('admin.contractors.index', request()->query()) }}">Back to contractor directory</a> · <button type="button" onclick="window.print()">Print directory</button></div>
    <h1>Contractor directory</h1><p>Generated {{ now()->format('M j, Y g:i A') }} · {{ $contractors->count() }} of {{ $total }} matching contractors</p>
    <p>Filters: search {{ $filters['q'] ?? 'none' }}; approval {{ isset($filters['approval']) ? match ((int) $filters['approval']) { 1 => 'Approved', 2 => 'Pending admin review', default => 'Not approved' } : 'all' }}; priority {{ ($filters['priority'] ?? false) ? 'only' : 'all' }}; archived {{ ($filters['archived'] ?? false) ? 'included' : 'excluded' }}; missing active W9 {{ ($filters['missing_w9'] ?? false) ? 'only' : 'all' }}; all services {{ $selectedServices->join(', ') ?: 'none selected' }}.</p>
    @if ($total > 1000)<p class="notice">This print view includes the first 1,000 matching contractors. Narrow the filters to print the remaining records.</p>@endif
    <table><thead><tr><th>Contractor / company</th><th>Contact</th><th>Approval</th><th>Services</th><th>Completed jobs</th></tr></thead><tbody>
    @forelse ($contractors as $contractor)
        <tr><td>{{ $contractor->first_name }} {{ $contractor->last_name }}<br>{{ $contractor->company_name }}@if ($contractor->priority)<br>Priority @endif</td><td>{{ $contractor->email ?: 'No email' }}<br>{{ $contractor->company_phone ?: $contractor->phone1 ?: 'No phone' }}</td><td>{{ match ($contractor->is_approved) { 1 => 'Approved', 2 => 'Pending admin review', default => 'Not approved' } }}<br>{{ $contractor->hasVerifiedEmail() ? 'Email verified' : 'Email unverified' }}@if ($contractor->accountState?->archived_at)<br>Archived @endif @if ($contractor->accountState?->blocked_at)<br>Access blocked @endif</td><td>{{ $contractor->services->pluck('service_name')->join(', ') ?: 'None selected' }}</td><td>{{ $contractor->completed_jobs_count }}</td></tr>
    @empty <tr><td colspan="5">No contractors match these filters.</td></tr>
    @endforelse
    </tbody></table>
</body></html>
