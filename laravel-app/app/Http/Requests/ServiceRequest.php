<?php

namespace App\Http\Requests;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $service = $this->route('service');

        return $service instanceof Service
            ? Gate::allows('update', $service)
            : Gate::allows('create', Service::class);
    }

    public function rules(): array
    {
        $service = $this->route('service');
        $unique = Rule::unique('Services', 'service_name');
        if ($service instanceof Service) {
            $unique = $unique->ignore($service->service_id, 'service_id');
        }

        return ['service_name' => ['required', 'string', 'max:100', $unique]];
    }
}
