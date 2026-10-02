<?php

namespace App\Http\Controllers;

use App\Actions\Contractor\ContractorManagement;
use App\Http\Requests\DocumentUploadRequest;
use App\Models\ContractorDocument;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ContractorDocumentController extends Controller
{
    public function adminIndex(User $user): View
    {
        Gate::authorize('view', $user);
        abort_if($user->role() !== 'contractor', 404);

        return view('documents.index', ['owner' => $user->load(['documents' => fn ($query) => $query->orderByDesc('upload_date')]), 'adminView' => true]);
    }

    public function contractorIndex(Request $request, User $user): View
    {
        $owner = $request->user();
        abort_unless((int) $user->user_id === (int) $owner->user_id, 403);
        Gate::authorize('view', $owner);

        return view('documents.index', ['owner' => $owner->load(['documents' => fn ($query) => $query->orderByDesc('upload_date')]), 'adminView' => false]);
    }

    public function store(DocumentUploadRequest $request, User $user, ContractorManagement $management): RedirectResponse
    {
        abort_if($user->role() !== 'contractor', 404);
        $data = $request->validated();
        $management->saveDocument($request->user(), $user, $request->file('document'), $data['file_type'], $data['file_name'] ?? null, $data['expiry_date'] ? now()->parse($data['expiry_date']) : null);

        return back()->with('status', 'Document uploaded to private storage.');
    }

    public function archive(Request $request, User $user, ContractorDocument $document, ContractorManagement $management): RedirectResponse
    {
        abort_if($user->role() !== 'contractor', 404);
        Gate::authorize('update', $document);
        $this->assertParent($user, $document);
        $management->archiveDocument($request->user(), $document);

        return back()->with('status', 'Document archived.');
    }

    public function destroy(Request $request, User $user, ContractorDocument $document, ContractorManagement $management): RedirectResponse
    {
        abort_if($user->role() !== 'contractor', 404);
        Gate::authorize('delete', $document);
        $this->assertParent($user, $document);
        $management->deleteDocument($request->user(), $document);

        return back()->with('status', 'Document permanently deleted.');
    }

    public function download(Request $request, ContractorDocument $document)
    {
        Gate::authorize('view', $document);

        return $this->binaryResponse($document, false);
    }

    public function preview(Request $request, ContractorDocument $document)
    {
        Gate::authorize('view', $document);

        return $this->binaryResponse($document, true);
    }

    private function assertParent(User $parent, ContractorDocument $document): void
    {
        abort_unless((int) $parent->user_id === (int) $document->user_id, 404);
    }

    private function binaryResponse(ContractorDocument $document, bool $inline)
    {
        $document->load(['privateUpload', 'privateFile']);
        $metadata = $document->privateUpload ?: $document->privateFile;
        $disk = $metadata?->disk ?: 'private';
        $path = $metadata?->path;
        $mime = $metadata?->mime_type ?: $document->mime_type ?: 'application/octet-stream';
        $name = $document->file_name ?: $metadata?->original_name ?: 'document';
        $name = basename(str_replace('\\', '/', $name));
        $disposition = $inline ? 'inline' : 'attachment';
        $headers = [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition.'; filename="'.addcslashes($name, '\\"').'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];

        if ($path && Storage::disk($disk)->exists($path)) {
            return response()->file(Storage::disk($disk)->path($path), $headers);
        }
        if ($document->file_data === null) {
            abort(404);
        }

        return response($document->file_data, 200, $headers);
    }
}
