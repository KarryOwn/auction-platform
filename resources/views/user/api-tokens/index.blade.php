<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            API Tokens
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(session('success'))
                <div class="rounded-2xl border border-green-200 bg-green-50 px-5 py-4 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if($newToken)
                <div class="rounded-3xl border border-amber-200 bg-amber-50 p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-700">New token</p>
                    <h3 class="mt-2 text-lg font-semibold text-amber-950">{{ $newToken['name'] }}</h3>
                    <p class="mt-1 text-sm text-amber-900">This token is shown once. Use it as a Bearer token for the public API.</p>
                    <div class="mt-4 rounded-2xl bg-slate-950 p-4 text-sm text-green-200 break-all">
                        {{ $newToken['plain_text'] }}
                    </div>
                    <p class="mt-3 text-xs text-amber-800">Abilities: {{ implode(', ', $newToken['abilities']) }}</p>
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-[1.1fr,0.9fr]">
                <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-indigo-600">Create token</p>
                            <h3 class="mt-2 text-xl font-semibold text-slate-950">Personal access token</h3>
                            <p class="mt-2 text-sm text-slate-500">Create a named token for scripts, integrations, or local development.</p>
                        </div>
                        <a href="{{ url(config('l5-swagger.documentations.v1.routes.api')) }}"
                           target="_blank"
                           rel="noopener"
                           class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            View API docs
                        </a>
                    </div>

                    <form method="POST" action="{{ route('user.api-tokens.store') }}" class="mt-6 space-y-6">
                        @csrf

                        <div>
                            <label for="name" class="block text-sm font-medium text-slate-700">Token name</label>
                            <input id="name"
                                   name="name"
                                   type="text"
                                   value="{{ old('name') }}"
                                   class="mt-2 w-full rounded-2xl border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                   placeholder="CI runner, local script, mobile app"
                                   required>
                        </div>

                        <div>
                            <p class="block text-sm font-medium text-slate-700">Abilities</p>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                @foreach($abilities as $ability)
                                    <label class="flex items-start gap-3 rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50/50">
                                        <input type="checkbox"
                                               name="abilities[]"
                                               value="{{ $ability }}"
                                               class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                               @checked(in_array($ability, old('abilities', $defaultAbilities), true))>
                                        <span>
                                            <span class="block font-medium text-slate-900">{{ $ability }}</span>
                                            <span class="mt-1 block text-xs text-slate-500">Allow this token to call the related API endpoints.</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">
                                Create token
                            </button>
                        </div>
                    </form>
                </section>

                <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
                    <div class="mb-6 rounded-2xl border border-indigo-100 bg-indigo-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-indigo-700">Developer navigation</p>
                        <div class="mt-3 grid gap-2">
                            <a href="{{ url(config('l5-swagger.documentations.v1.routes.api')) }}"
                               target="_blank"
                               rel="noopener"
                               class="inline-flex items-center justify-between rounded-xl bg-white px-4 py-3 text-sm font-semibold text-slate-800 shadow-sm hover:text-indigo-700">
                                <span>Open Swagger docs</span>
                                <span class="text-xs text-slate-400">/api/v1</span>
                            </a>
                            <a href="{{ route('user.webhooks.index') }}"
                               class="inline-flex items-center justify-between rounded-xl bg-white px-4 py-3 text-sm font-semibold text-slate-800 shadow-sm hover:text-indigo-700">
                                <span>Webhook endpoints</span>
                                <span class="text-xs text-slate-400">events</span>
                            </a>
                        </div>
                    </div>

                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Active tokens</p>
                    <h3 class="mt-2 text-xl font-semibold text-slate-950">Issued keys</h3>
                    <p class="mt-2 text-sm text-slate-500">Revoke tokens you no longer use. Expired or leaked tokens should be removed immediately.</p>

                    <div class="mt-6 space-y-4">
                        @forelse($tokens as $token)
                            <div class="rounded-2xl border border-slate-200 p-4">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h4 class="text-sm font-semibold text-slate-900">{{ $token->name }}</h4>
                                        <p class="mt-1 text-xs text-slate-500">Created {{ $token->created_at?->diffForHumans() ?? 'just now' }}</p>
                                        <p class="mt-2 text-xs text-slate-600">Abilities: {{ implode(', ', $token->abilities ?? []) }}</p>
                                    </div>
                                    <form method="POST" action="{{ route('user.api-tokens.destroy', $token) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-rose-200 px-4 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-50">
                                            Revoke
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
                                No API tokens yet.
                            </div>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
