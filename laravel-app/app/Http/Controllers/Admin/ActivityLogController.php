<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ActivityLog::class);
        $filters = $this->filters($request);
        $query = $this->query($filters);
        $stats = $this->stats($query);
        $logs = $query->paginate(30)->withQueryString();

        return view('admin.activity.index', array_merge($this->viewData($logs, $filters), ['stats' => $stats]));
    }

    public function print(Request $request): View
    {
        Gate::authorize('viewAny', ActivityLog::class);
        $filters = $this->filters($request);
        $query = $this->query($filters);
        $stats = $this->stats($query);
        $logs = $query->limit(2000)->get();

        return view('admin.activity.print', array_merge($this->viewData($logs, $filters), ['total' => $stats['total'], 'stats' => $stats, 'printLimit' => 2000]));
    }

    /** @return array{user_id:?int,action:?string,date_from:?string,date_to:?string} */
    private function filters(Request $request): array
    {
        $dateToRules = ['nullable', 'date_format:Y-m-d'];
        if (is_string($request->input('date_from')) && trim($request->input('date_from')) !== '') {
            $dateToRules[] = 'after_or_equal:date_from';
        }
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'action' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => $dateToRules,
        ]);
        $action = trim($validated['action'] ?? '');

        return [
            'user_id' => isset($validated['user_id']) ? (int) $validated['user_id'] : null,
            'action' => $action !== '' ? $action : null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
        ];
    }

    private function query(array $filters): Builder
    {
        return ActivityLog::query()->with('associatedUser')->when($filters['user_id'], fn ($query, $id) => $query->where('user_id', $id))->when($filters['action'], fn ($query, $action) => $query->where('action', 'like', '%'.$action.'%'))->when($filters['date_from'], fn ($query, $date) => $query->whereDate('timestamp', '>=', $date))->when($filters['date_to'], fn ($query, $date) => $query->whereDate('timestamp', '<=', $date))->orderByDesc('timestamp')->orderByDesc('log_id');
    }

    /** @return array{total:int,admin_associated:int,contractor_associated:int,orphan:int,today:int} */
    private function stats(Builder $query): array
    {
        return [
            'total' => (clone $query)->count(),
            'admin_associated' => (clone $query)->whereHas('associatedUser', fn (Builder $user) => $user->where('is_admin', 1))->count(),
            'contractor_associated' => (clone $query)->whereHas('associatedUser', fn (Builder $user) => $user->where('is_admin', 0))->count(),
            'orphan' => (clone $query)->whereDoesntHave('associatedUser')->count(),
            'today' => (clone $query)->whereDate('timestamp', today()->toDateString())->count(),
        ];
    }

    private function viewData($logs, array $filters): array
    {
        return [
            'logs' => $logs,
            'filters' => $filters,
            'users' => User::query()->orderByDesc('is_admin')->orderBy('company_name')->orderBy('first_name')->get(['user_id', 'first_name', 'last_name', 'company_name', 'is_admin']),
        ];
    }
}
