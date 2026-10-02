<?php

namespace App\Http\Requests;

use App\Actions\Notifications\NotificationPreferences;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationInboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role() === 'admin'
            || ($this->user()?->role() === 'contractor' && $this->user()->is_approved === 1);
    }

    public function rules(): array
    {
        $rules = [
            'status' => ['nullable', Rule::in(['all', 'unread', 'read'])],
            'unread' => ['nullable', 'boolean'],
            'type' => ['nullable', Rule::in(array_keys(NotificationPreferences::LABELS))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('from'), 'after_or_equal:from')],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
        if ($this->routeIs('notifications.bulk')) {
            $rules += [
                'ids' => ['required', 'array', 'min:1', 'max:50'],
                'ids.*' => ['required', 'ulid', 'distinct'],
                'action' => ['required', Rule::in(['read', 'unread'])],
            ];
        }

        return $rules;
    }

    public function filters(): array
    {
        $data = $this->safe()->only(['status', 'type', 'from', 'to']);
        $data['status'] = $data['status'] ?? ($this->boolean('unread') ? 'unread' : 'all');

        return array_filter($data, fn ($value) => $value !== null && $value !== '');
    }

    public function messages(): array
    {
        return ['ids.required' => 'Select at least one notification.', 'ids.max' => 'Select at most 50 notifications.'];
    }
}
