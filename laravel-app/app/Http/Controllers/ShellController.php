<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ContractorDocument;
use App\Models\Job;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ShellController extends Controller
{
    public function home(): View
    {
        return view('welcome', [
            'previewsEnabled' => app()->environment('local') && config('nestcrafters.shell_previews'),
        ]);
    }

    public function dashboard(Request $request): RedirectResponse
    {
        $role = $request->user()?->role();
        abort_unless(in_array($role, ['admin', 'contractor'], true), 403);

        if (! $request->user()->hasVerifiedEmail() || ($role === 'contractor' && ! $request->user()->canAccessWork())) {
            return redirect()->route('account.status');
        }

        return redirect()->route($role.'.dashboard');
    }

    public function admin(): View
    {
        return view('admin.dashboard', $this->adminDashboardData());
    }

    public function contractor(Request $request): View
    {
        return view('contractor.dashboard', $this->contractorDashboardData($request->user()));
    }

    public function preview(string $role): View
    {
        return $this->shell($role, true);
    }

    private function shell(string $role, bool $preview = false): View
    {
        $stats = [];
        if (! $preview && auth()->check() && Schema::hasTable('Users')) {
            if ($role === 'admin') {
                $stats = [
                    'contractors' => User::query()->where('is_admin', 0)->where('is_approved', 2)->count(),
                    'jobs' => Job::query()->where('is_completed', 0)->count(),
                    'expiring' => ContractorDocument::query()->where('archived', 0)->whereBetween('expiry_date', [today(), today()->addDays(30)])->count(),
                ];
            } else {
                $stats = [
                    'status' => auth()->user()->is_approved === 1 ? 'Ready' : 'Review',
                    'jobs' => auth()->user()->jobs()->where('is_completed', 0)->count(),
                    'documents' => auth()->user()->documents()->where('archived', 0)->count(),
                ];
            }
        }

        return view('shell.dashboard', [
            'role' => $role,
            'preview' => $preview,
            'navigation' => config('nestcrafters.navigation.'.$role),
            'stats' => $stats,
        ]);
    }

    /**
     * Dashboard queries live behind the table check so the role shells remain
     * renderable for preview tests and unsaved authentication doubles.
     *
     * @return array<string, mixed>
     */
    private function adminDashboardData(): array
    {
        $empty = [
            'role' => 'admin',
            'navigation' => config('nestcrafters.navigation.admin'),
            'metrics' => ['contractors' => 0, 'priority' => 0, 'pending_zero' => 0, 'pending_review' => 0, 'jobs' => 0, 'unassigned' => 0, 'pending' => 0, 'accepted' => 0, 'w9_missing' => 0, 'insurance_expiring' => 0, 'insurance_expired' => 0],
            'approvalQueue' => collect(), 'overdueJobs' => collect(), 'upcomingJobs' => collect(),
            'missingW9' => collect(), 'insuranceExpiring' => collect(), 'insuranceExpired' => collect(), 'activity' => collect(),
        ];

        if (! Schema::hasTable('Users') || ! Schema::hasTable('Jobs') || ! Schema::hasTable('Files')) {
            return $empty;
        }

        $today = CarbonImmutable::today();
        $inThirtyDays = $today->addDays(30);
        $activeContractor = fn ($query) => $query->where('is_admin', 0)->whereDoesntHave('accountState', fn ($state) => $state->whereNotNull('archived_at')->orWhereNotNull('blocked_at'));
        $activeContractors = User::query()->where($activeContractor);
        $approvalQueue = (clone $activeContractors)->with(['accountState', 'services'])->whereIn('is_approved', [0, 2])->orderByDesc('priority')->orderBy('last_name')->limit(8)->get();
        $baseJobs = Job::query()->where('is_completed', 0);

        $missingW9 = User::query()->with('accountState')->where($activeContractor)->whereDoesntHave('documents', fn ($documents) => $documents->where('file_type', 'W9')->where(fn ($q) => $q->whereNull('archived')->orWhere('archived', 0)));
        $insurance = ContractorDocument::query()->with('owner')->whereHas('owner', $activeContractor)->where('file_type', 'Insurance')->where(fn ($q) => $q->whereNull('archived')->orWhere('archived', 0));
        $insuranceExpiring = (clone $insurance)->whereBetween('expiry_date', [$today->toDateString(), $inThirtyDays->toDateString()])->orderBy('expiry_date')->limit(8)->get();
        $insuranceExpired = (clone $insurance)->whereDate('expiry_date', '<', $today->toDateString())->orderByDesc('expiry_date')->limit(8)->get();

        return [
            'role' => 'admin',
            'navigation' => config('nestcrafters.navigation.admin'),
            'metrics' => [
                'contractors' => (clone $activeContractors)->count(),
                'priority' => (clone $activeContractors)->where('priority', 1)->count(),
                'pending_zero' => (clone $activeContractors)->where('is_approved', 0)->count(),
                'pending_review' => (clone $activeContractors)->where('is_approved', 2)->count(),
                'jobs' => (clone $baseJobs)->count(),
                'unassigned' => (clone $baseJobs)->whereNull('contractor_id')->count(),
                'pending' => (clone $baseJobs)->whereNotNull('contractor_id')->where('is_accepted', 0)->count(),
                'accepted' => (clone $baseJobs)->where('is_accepted', 1)->count(),
                'w9_missing' => (clone $missingW9)->count(),
                'insurance_expiring' => (clone $insurance)->whereBetween('expiry_date', [$today->toDateString(), $inThirtyDays->toDateString()])->count(),
                'insurance_expired' => (clone $insurance)->whereDate('expiry_date', '<', $today->toDateString())->count(),
            ],
            'approvalQueue' => $approvalQueue,
            'overdueJobs' => Job::query()->with('contractor')->where('is_completed', 0)->whereDate('due_date', '<', $today->toDateString())->orderBy('due_date')->limit(8)->get(),
            'upcomingJobs' => Job::query()->with('contractor')->where('is_completed', 0)->whereBetween('due_date', [$today->toDateString(), $today->addDays(7)->toDateString()])->orderBy('due_date')->limit(8)->get(),
            'missingW9' => (clone $missingW9)->orderBy('last_name')->limit(8)->get(),
            'insuranceExpiring' => $insuranceExpiring,
            'insuranceExpired' => $insuranceExpired,
            'activity' => Schema::hasTable('ActivityLogs') ? ActivityLog::query()->latest('timestamp')->limit(10)->get() : collect(),
        ];
    }

    /** @return array<string, mixed> */
    private function contractorDashboardData(?User $user): array
    {
        $empty = [
            'role' => 'contractor', 'navigation' => config('nestcrafters.navigation.contractor'), 'contractor' => $user,
            'metrics' => ['jobs' => 0, 'pending_jobs' => 0, 'accepted_jobs' => 0, 'completed_jobs' => 0, 'documents' => 0, 'expiring_documents' => 0],
            'jobs' => collect(), 'overdueJobs' => collect(), 'upcomingJobs' => collect(), 'documents' => collect(),
            'profileComplete' => false, 'documentComplete' => false, 'nextAction' => 'Complete your profile.',
        ];
        if (! $user || ! Schema::hasTable('Users') || ! Schema::hasTable('Jobs') || ! Schema::hasTable('Files')) {
            return $empty;
        }
        $today = CarbonImmutable::today();
        $jobQuery = $user->jobs();
        $documentQuery = $user->documents()->where(fn ($q) => $q->whereNull('archived')->orWhere('archived', 0));
        $jobs = (clone $jobQuery)->with('services')->orderBy('is_completed')->orderBy('is_accepted')->orderByRaw('due_date IS NULL')->orderBy('due_date')->limit(10)->get();
        $documents = (clone $documentQuery)->orderByDesc('upload_date')->limit(10)->get();
        $profileComplete = collect(['first_name', 'last_name', 'company_name', 'company_address', 'email'])->every(fn ($field) => filled($user->{$field})) && $user->services()->exists();
        $hasW9 = (clone $documentQuery)->where('file_type', 'W9')->exists();
        $hasInsurance = (clone $documentQuery)->where('file_type', 'Insurance')->where(fn ($q) => $q->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', $today->toDateString()))->exists();
        $documentComplete = $hasW9 && $hasInsurance;
        $nextAction = ! $profileComplete ? 'Complete your profile and choose your services.' : (! $hasW9 ? 'Upload your W9.' : (! $hasInsurance ? 'Upload or renew your insurance document.' : ((clone $jobQuery)->where('is_accepted', 0)->where('is_completed', 0)->exists() ? 'Accept your next assigned job.' : 'You are up to date.')));

        return [
            'role' => 'contractor', 'navigation' => config('nestcrafters.navigation.contractor'), 'contractor' => $user,
            'metrics' => ['jobs' => (clone $jobQuery)->where('is_completed', 0)->count(), 'pending_jobs' => (clone $jobQuery)->where('is_accepted', 0)->where('is_completed', 0)->count(), 'accepted_jobs' => (clone $jobQuery)->where('is_accepted', 1)->where('is_completed', 0)->count(), 'completed_jobs' => (clone $jobQuery)->where('is_completed', 1)->count(), 'documents' => (clone $documentQuery)->count(), 'expiring_documents' => (clone $documentQuery)->where('file_type', 'Insurance')->whereBetween('expiry_date', [$today->toDateString(), $today->addDays(30)->toDateString()])->count()],
            'jobs' => $jobs,
            'overdueJobs' => (clone $jobQuery)->where('is_completed', 0)->whereDate('due_date', '<', $today->toDateString())->orderBy('due_date')->limit(8)->get(),
            'upcomingJobs' => (clone $jobQuery)->where('is_completed', 0)->whereBetween('due_date', [$today->toDateString(), $today->addDays(7)->toDateString()])->orderBy('due_date')->limit(8)->get(),
            'documents' => $documents,
            'profileComplete' => $profileComplete, 'documentComplete' => $documentComplete, 'nextAction' => $nextAction,
        ];
    }
}
