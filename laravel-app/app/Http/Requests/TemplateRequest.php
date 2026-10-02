<?php

namespace App\Http\Requests;

use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->route('template');

        return $template instanceof Template
            ? Gate::allows('update', $template)
            : Gate::allows('create', Template::class);
    }

    public function rules(): array
    {
        $template = $this->route('template');
        $unique = Rule::unique('Templates', 'name');
        if ($template instanceof Template) {
            $unique = $unique->ignore($template->template_id, 'template_id');
        }

        return [
            'name' => ['required', 'string', 'max:100', $unique],
            'type' => ['required', 'string', Rule::in(['Jobs', 'Email', 'Contractor'])],
            'html_content' => ['required', 'string', 'max:200000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'type' => is_string($this->type) ? trim($this->type) : $this->type,
            'html_content' => is_string($this->html_content) ? trim($this->html_content) : $this->html_content,
        ]);
    }
}
