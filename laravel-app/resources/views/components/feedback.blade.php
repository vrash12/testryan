@if ($errors->any())
    <x-alert tone="error" class="mb-6">
        <p class="font-semibold">Please check the following fields.</p>
        <ul class="mt-2 list-disc pl-5">
            @foreach ($errors->getMessages() as $field => $messages)
                <li><a href="#{{ $field }}" class="underline">{{ $messages[0] }}</a></li>
            @endforeach
        </ul>
    </x-alert>
@endif
@if (session('status')) <x-alert class="mb-6">{{ session('status') }}</x-alert> @endif
@if (session('error')) <x-alert tone="error" class="mb-6">{{ session('error') }}</x-alert> @endif
