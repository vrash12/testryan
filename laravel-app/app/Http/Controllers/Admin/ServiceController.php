<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Identity\SecurityAudit;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Service::class);
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:200']]);
        $search = trim($validated['q'] ?? '');

        $query = Service::query()->withCount(['contractors', 'jobs'])->orderBy('service_name');
        if ($search !== '') {
            $query->where('service_name', 'like', '%'.$search.'%');
        }

        return view('admin.services.index', [
            'services' => $query->paginate(30)->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Service::class);

        return view('admin.services.create');
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request, &$service): void {
            $service = new Service;
            $service->forceFill(['service_name' => trim($request->validated('service_name'))])->save();
            app(SecurityAudit::class)->record($request->user(), 'created service "'.$service->service_name.'".');
        });

        return redirect()->route('admin.services.index')->with('status', 'Service added to the catalog.');
    }

    public function edit(Service $service): View
    {
        Gate::authorize('update', $service);

        return view('admin.services.edit', compact('service'));
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        DB::transaction(function () use ($request, $service): void {
            $service->forceFill(['service_name' => trim($request->validated('service_name'))])->save();
            app(SecurityAudit::class)->record($request->user(), 'updated service "'.$service->service_name.'".');
        });

        return redirect()->route('admin.services.index')->with('status', 'Service updated.');
    }

    public function destroy(Request $request, Service $service): RedirectResponse
    {
        Gate::authorize('delete', $service);
        if ($service->contractors()->exists() || $service->jobs()->exists()) {
            return back()->withErrors(['service' => 'This service is in use and cannot be deleted. Remove its assignments first.']);
        }
        DB::transaction(function () use ($request, $service): void {
            $name = $service->service_name;
            $service->delete();
            app(SecurityAudit::class)->record($request->user(), 'deleted service "'.$name.'".');
        });

        return redirect()->route('admin.services.index')->with('status', 'Service deleted.');
    }
}
