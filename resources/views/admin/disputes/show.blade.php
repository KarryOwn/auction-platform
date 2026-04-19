<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dispute #{{ $dispute->id }}</h2>
            <a href="{{ route('admin.disputes.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">&larr; Back to Disputes</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-4">Dispute Details</h3>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3">
                        <dt class="text-sm font-medium text-gray-500">Auction</dt>
                        <dd class="text-sm text-gray-900">
                            @if($dispute->auction)
                                <a href="{{ route('admin.auctions.show', $dispute->auction) }}" class="text-indigo-600 hover:underline">
                                    {{ $dispute->auction->title }}
                                </a>
                            @else
                                Deleted auction
                            @endif
                        </dd>

                        <dt class="text-sm font-medium text-gray-500">Type</dt>
                        <dd class="text-sm text-gray-900">{{ ucfirst(str_replace('_', ' ', $dispute->type)) }}</dd>

                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="text-sm text-gray-900">{{ $dispute->status_label }}</dd>

                        <dt class="text-sm font-medium text-gray-500">Claimant</dt>
                        <dd class="text-sm text-gray-900">
                            {{ $dispute->claimant?->name ?? 'Unknown' }}
                            @if($dispute->claimant?->email)
                                <span class="text-gray-500">({{ $dispute->claimant->email }})</span>
                            @endif
                        </dd>

                        <dt class="text-sm font-medium text-gray-500">Respondent</dt>
                        <dd class="text-sm text-gray-900">
                            {{ $dispute->respondent?->name ?? 'Unknown' }}
                            @if($dispute->respondent?->email)
                                <span class="text-gray-500">({{ $dispute->respondent->email }})</span>
                            @endif
                        </dd>

                        <dt class="text-sm font-medium text-gray-500">Description</dt>
                        <dd class="text-sm text-gray-900 col-span-2 whitespace-pre-line">{{ $dispute->description }}</dd>

                        <dt class="text-sm font-medium text-gray-500">Evidence</dt>
                        <dd class="text-sm text-gray-900 col-span-2">
                            @if(!empty($dispute->evidence_urls))
                                <ul class="list-disc pl-5 space-y-1">
                                    @foreach($dispute->evidence_urls as $url)
                                        <li>
                                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline break-all">
                                                {{ $url }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <span class="text-gray-400">No evidence submitted.</span>
                            @endif
                        </dd>
                    </dl>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-4">Resolution</h3>
                    <form method="POST" action="{{ route('admin.disputes.update', $dispute) }}" class="space-y-3">
                        @csrf
                        @method('PATCH')

                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Decision Status</label>
                            <select id="status" name="status" class="w-full rounded-md border-gray-300 text-sm" required>
                                @foreach(['open', 'under_review', 'resolved_buyer', 'resolved_seller', 'closed'] as $status)
                                    <option value="{{ $status }}" @selected(old('status', $dispute->status) === $status)>
                                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="resolution_notes" class="block text-sm font-medium text-gray-700 mb-1">Resolution Notes</label>
                            <textarea id="resolution_notes"
                                      name="resolution_notes"
                                      rows="6"
                                      required
                                      class="w-full rounded-md border-gray-300 text-sm"
                                      placeholder="Provide your decision rationale and next steps for both parties.">{{ old('resolution_notes', $dispute->resolution_notes) }}</textarea>
                            @error('resolution_notes')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700">
                            Save Decision
                        </button>
                    </form>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Timeline</h3>
                <ol class="space-y-3 text-sm text-gray-700">
                    <li>
                        <span class="font-medium">Dispute opened:</span>
                        {{ $dispute->created_at->format('M d, Y H:i') }}
                    </li>
                    @if($dispute->resolved_at)
                        <li>
                            <span class="font-medium">Last resolution update:</span>
                            {{ $dispute->resolved_at->format('M d, Y H:i') }} by {{ $dispute->resolver?->name ?? 'Unknown admin' }}
                        </li>
                        <li>
                            <span class="font-medium">Current status:</span>
                            {{ $dispute->status_label }}
                        </li>
                    @else
                        <li>
                            <span class="font-medium">Current status:</span>
                            {{ $dispute->status_label }}
                        </li>
                    @endif
                </ol>
            </div>
        </div>
    </div>
</x-app-layout>
