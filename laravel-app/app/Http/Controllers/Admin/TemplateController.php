<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Identity\SecurityAudit;
use App\Http\Controllers\Controller;
use App\Http\Requests\TemplateRequest;
use App\Models\Template;
use App\Support\JobContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Template::class);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:200']]);
        $search = trim($data['q'] ?? '');
        $query = Template::query()->orderBy('name');
        if ($search !== '') {
            $query->where(function ($nested) use ($search): void {
                $nested->where('name', 'like', '%'.$search.'%')->orWhere('type', $search);
            });
        }

        return view('admin.templates.index', [
            'templates' => $query->paginate(20)->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Template::class);

        return view('admin.templates.create', ['template' => null]);
    }

    public function store(TemplateRequest $request): RedirectResponse
    {
        $template = DB::transaction(function () use ($request): Template {
            $template = new Template;
            $template->forceFill($request->validated())->save();
            app(SecurityAudit::class)->record($request->user(), 'created template "'.$template->name.'".');

            return $template;
        });

        return redirect()->route('admin.templates.index')->with('status', 'Template created.');
    }

    public function edit(Template $template): View
    {
        Gate::authorize('update', $template);

        return view('admin.templates.edit', compact('template'));
    }

    public function update(TemplateRequest $request, Template $template): RedirectResponse
    {
        DB::transaction(function () use ($request, $template): void {
            $template->forceFill($request->validated())->save();
            app(SecurityAudit::class)->record($request->user(), 'updated template "'.$template->name.'".');
        });

        return redirect()->route('admin.templates.index')->with('status', 'Template updated.');
    }

    public function destroy(Request $request, Template $template): RedirectResponse
    {
        Gate::authorize('delete', $template);
        DB::transaction(function () use ($request, $template): void {
            $name = $template->name;
            $template->delete();
            app(SecurityAudit::class)->record($request->user(), 'deleted template "'.$name.'".');
        });

        return redirect()->route('admin.templates.index')->with('status', 'Template deleted.');
    }

    /** Return sanitized content for the explicit job-form insertion action. */
    public function content(Template $template): JsonResponse
    {
        Gate::authorize('view', $template);
        abort_unless($template->type === 'Jobs', 404);

        return response()->json([
            'template_id' => $template->template_id,
            'name' => $template->name,
            'html_content' => JobContent::render($template->html_content),
        ])->header('Cache-Control', 'private, no-store');
    }
}
