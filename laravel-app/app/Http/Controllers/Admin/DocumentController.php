<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContractorDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ContractorDocument::class);
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'in:active,archived,expiring,expired'],
            'type' => ['nullable', 'in:W9,Insurance,Other'],
        ]);
        $query = ContractorDocument::query()->select(['file_id', 'user_id', 'file_type', 'file_name', 'upload_date', 'expiry_date', 'archived'])->with('owner')
            ->whereHas('owner', fn ($q) => $q->where('is_admin', 0)->whereDoesntHave('accountState', fn ($s) => $s->whereNotNull('archived_at')->orWhereNotNull('blocked_at')));
        $status = $data['status'] ?? 'active';
        $query->where('archived', $status === 'archived' ? 1 : 0);
        if ($type = $data['type'] ?? null) {
            $query->where('file_type', $type);
        }
        if ($status === 'expiring') {
            $query->where('file_type', 'Insurance')->whereBetween('expiry_date', [today()->toDateString(), today()->addDays(30)->toDateString()]);
        } elseif ($status === 'expired') {
            $query->where('file_type', 'Insurance')->whereDate('expiry_date', '<', today()->toDateString());
        }
        if ($search = trim($data['q'] ?? '')) {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($q) => $q->where('file_name', 'like', $like)->orWhereHas('owner', fn ($owner) => $owner->where('company_name', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like)));
        }

        return view('admin.documents.index', ['documents' => $query->orderByRaw('expiry_date IS NULL')->orderBy('expiry_date')->orderByDesc('file_id')->paginate(20)->withQueryString()]);
    }
}
