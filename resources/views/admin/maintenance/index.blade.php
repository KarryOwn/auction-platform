<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Scheduled Maintenance</h2>
                <p class="mt-1 text-sm text-gray-500">Schedule downtime in advance and share the admin bypass link for emergency access.</p>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <div class="font-semibold">Admin bypass URL</div>
                <div class="mt-1 break-all">{{ $bypassUrl }}</div>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[1.1fr,1.4fr] lg:px-8">
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">Schedule Window</h3>
                <p class="mt-1 text-sm text-gray-500">Maintenance starts automatically at the scheduled time and ends when the window expires or is cancelled.</p>

                @if(session('success'))
                    <div class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                        {{ session('success') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.maintenance.store') }}" class="mt-6 space-y-4">
                    @csrf
                    <div>
                        <label for="scheduled_start" class="block text-sm font-medium text-gray-700">Scheduled start</label>
                        <input type="datetime-local" id="scheduled_start" name="scheduled_start" value="{{ old('scheduled_start') }}" class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                    </div>
                    <div>
                        <label for="scheduled_end" class="block text-sm font-medium text-gray-700">Scheduled end</label>
                        <input type="datetime-local" id="scheduled_end" name="scheduled_end" value="{{ old('scheduled_end') }}" class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                    </div>
                    <div>
                        <label for="message" class="block text-sm font-medium text-gray-700">Maintenance message</label>
                        <textarea id="message" name="message" rows="4" maxlength="500" class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>{{ old('message', 'Scheduled maintenance. Back soon.') }}</textarea>
                    </div>
                    @if($errors->any())
                        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ $errors->first() }}
                        </div>
                    @endif
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        Schedule Maintenance
                    </button>
                </form>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">Windows</h3>
                <div class="mt-4 space-y-4">
                    @forelse($windows as $window)
                        <article class="rounded-xl border border-gray-200 p-4">
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-sm font-semibold text-gray-900">
                                            {{ $window->scheduled_start->format('M d, Y H:i') }} to {{ $window->scheduled_end->format('H:i') }}
                                        </h4>
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold
                                            {{ $window->status === 'scheduled' ? 'bg-blue-100 text-blue-800' : '' }}
                                            {{ $window->status === 'active' ? 'bg-amber-100 text-amber-800' : '' }}
                                            {{ $window->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                                            {{ $window->status === 'cancelled' ? 'bg-gray-100 text-gray-700' : '' }}">
                                            {{ ucfirst($window->status) }}
                                        </span>
                                    </div>
                                    <p class="mt-2 text-sm text-gray-600">{{ $window->message }}</p>
                                    <p class="mt-2 text-xs text-gray-400">Created by {{ $window->creator?->name ?? 'Unknown' }}</p>
                                </div>

                                @if(in_array($window->status, ['scheduled', 'active'], true))
                                    <form method="POST" action="{{ route('admin.maintenance.cancel', $window) }}">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100">
                                            Cancel
                                        </button>
                                    </form>
                                @endif
                            </div>

                            @if($window->status === 'scheduled')
                                <form method="POST" action="{{ route('admin.maintenance.update', $window) }}" class="mt-4 grid gap-3 border-t border-gray-100 pt-4 md:grid-cols-2">
                                    @csrf
                                    @method('PATCH')
                                    <div>
                                        <label class="block text-xs font-medium uppercase tracking-wide text-gray-500">Start</label>
                                        <input type="datetime-local" name="scheduled_start" value="{{ $window->scheduled_start->format('Y-m-d\TH:i') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium uppercase tracking-wide text-gray-500">End</label>
                                        <input type="datetime-local" name="scheduled_end" value="{{ $window->scheduled_end->format('Y-m-d\TH:i') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-medium uppercase tracking-wide text-gray-500">Message</label>
                                        <textarea name="message" rows="3" class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ $window->message }}</textarea>
                                    </div>
                                    <div class="md:col-span-2">
                                        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                                            Update Window
                                        </button>
                                    </div>
                                </form>
                            @endif
                        </article>
                    @empty
                        <div class="rounded-xl border border-dashed border-gray-300 px-6 py-10 text-center text-sm text-gray-500">
                            No maintenance windows scheduled yet.
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
