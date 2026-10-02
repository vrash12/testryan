<?php

namespace App\Http\Requests;

use App\Enums\MediaTag;
use App\Models\Job;
use App\Models\JobMedia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class JobMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $job = $this->route('job');

        return $job instanceof Job && Gate::allows('create', [JobMedia::class, $job]);
    }

    public function rules(): array
    {
        return [
            'upload_id' => [$this->expectsJson() ? 'required' : 'nullable', 'uuid'],
            'upload_group' => ['nullable', 'uuid'],
            'tag' => ['required', Rule::enum(MediaTag::class)],
            'media' => ['required', 'array', 'min:1', 'max:10'],
            'media.*' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ];
    }

    public function attributes(): array
    {
        return ['media.*' => 'media file'];
    }
}
