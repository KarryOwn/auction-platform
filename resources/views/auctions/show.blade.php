<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Auction #{{ $auction->id }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <div>
                        <h1 class="text-3xl font-bold mb-4">{{ $auction->title }}</h1>
                        <p class="text-gray-700 mb-6">{{ $auction->description }}</p>
                        <div class="bg-gray-100 p-4 rounded mb-4">
                            <span class="block text-sm text-gray-500">Time Remaining</span>
                            <span class="text-xl font-bold">{{ $auction->end_time->diffForHumans() }}</span>
                        </div>
                    </div>

                    <div class="border-l pl-6">
                        <div class="mb-8">
                            <span class="block text-sm text-gray-500">Current Price</span>
                            <span id="price-display" class="text-5xl font-black text-green-600">
                                ${{ number_format($auction->current_price, 2) }}
                            </span>
                        </div>

                        <div id="error-message" class="hidden bg-red-100 text-red-700 p-3 rounded mb-4"></div>
                        <div id="success-message" class="hidden bg-green-100 text-green-700 p-3 rounded mb-4"></div>

                        <form id="bid-form" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Your Bid</label>
                                <input type="number" id="bid-amount" step="0.01" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-xl" 
                                       placeholder="{{ $auction->current_price + 10 }}">
                            </div>

                            <button type="submit" 
                                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded-lg transition duration-150 ease-in-out">
                                Place Bid
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('bid-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const amount = document.getElementById('bid-amount').value;
            const errorDiv = document.getElementById('error-message');
            const successDiv = document.getElementById('success-message');
            
            // Clear messages
            errorDiv.classList.add('hidden');
            successDiv.classList.add('hidden');

            try {
                const response = await fetch("{{ route('auctions.bid', $auction) }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': "{{ csrf_token() }}"
                    },
                    body: JSON.stringify({ amount: amount })
                });

                const data = await response.json();

                if (response.ok) {
                    successDiv.textContent = data.message;
                    successDiv.classList.remove('hidden');
                    // Update price instantly (Later, WebSockets will do this)
                    document.getElementById('price-display').innerText = '$' + parseFloat(data.new_price).toFixed(2);
                } else {
                    errorDiv.textContent = data.message;
                    errorDiv.classList.remove('hidden');
                }
            } catch (error) {
                console.error('Error:', error);
            }
        });
    </script>
</x-app-layout>