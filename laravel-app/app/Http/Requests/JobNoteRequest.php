<?php

namespace App\Http\Requests;

use App\Models\Job;
use App\Models\JobNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class JobNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $job = $this->route('job');

        return $job instanceof Job && Gate::allows('create', [JobNote::class, $job]);
    }

    public function rules(): array
    {
        return ['note_text' => ['required', 'string', 'max:10000']];
    }
}
