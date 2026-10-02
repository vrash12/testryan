<?php

namespace App\Http\Requests;

use App\Models\ContractorNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ContractorNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', ContractorNote::class);
    }

    public function rules(): array
    {
        return ['note_text' => ['required', 'string', 'max:5000']];
    }
}
