@php($collection = $logs instanceof \Illuminate\Pagination\LengthAwarePaginator ? $logs->getCollection() : $logs)
<div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="border-b border-stone-200 text-xs uppercase tracking-wide text-stone-600"><tr><th class="px-3 py-3">ID</th><th class="px-3 py-3">Associated account</th><th class="px-3 py-3">Action</th><th class="px-3 py-3">Date and time</th></tr></thead><tbody class="divide-y divide-stone-100">
@forelse($collection as $log)
    @php($actor = $log->associatedUser)
    <tr><td class="whitespace-nowrap px-3 py-3 text-stone-600">{{ $log->log_id }}</td><td class="px-3 py-3"><span class="font-semibold">{{ $actor ? ($actor->is_admin ? trim($actor->first_name.' '.$actor->last_name) : ($actor->company_name ?: trim($actor->first_name.' '.$actor->last_name))) : 'Deleted user #'.$log->user_id }}</span>@if($actor)<span class="ml-2 inline-flex rounded-full bg-stone-100 px-2 py-0.5 text-xs text-stone-600">{{ $actor->is_admin ? 'Admin' : 'Contractor' }}</span>@endif</td><td class="max-w-3xl break-words px-3 py-3">{{ $log->action }}</td><td class="whitespace-nowrap px-3 py-3 text-stone-600">{{ optional($log->timestamp)->format('M j, Y g:i A') }}</td></tr>
@empty
    <tr><td colspan="4" class="px-3 py-10 text-center text-stone-600">No activity logs match these filters.</td></tr>
@endforelse
</tbody></table></div>
