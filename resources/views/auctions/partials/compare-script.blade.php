<script>
    (function () {
        const STORAGE_KEY = 'auction_compare_ids';
        const MAX_COMPARE = 4;
        const compareUrl = @js(route('auctions.compare.get'));
        const initialPollAuctions = @js($pollComparedAuctions ?? []);

        function parseIds(value) {
            if (!Array.isArray(value)) {
                return [];
            }

            return value
                .map((id) => Number(id))
                .filter((id) => Number.isInteger(id) && id > 0)
                .filter((id, index, items) => items.indexOf(id) === index)
                .slice(0, MAX_COMPARE);
        }

        function readIds() {
            try {
                return parseIds(JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'));
            } catch (_) {
                return [];
            }
        }

        function writeIds(ids) {
            const cleaned = parseIds(ids);
            localStorage.setItem(STORAGE_KEY, JSON.stringify(cleaned));
            window.dispatchEvent(new CustomEvent('auction-compare:changed', { detail: { ids: cleaned } }));
            syncToggles(cleaned);
            return cleaned;
        }

        function syncToggles(ids = readIds()) {
            document.querySelectorAll('[data-compare-toggle]').forEach((input) => {
                const id = Number(input.dataset.compareToggle);
                input.checked = ids.includes(id);
                input.disabled = !ids.includes(id) && ids.length >= MAX_COMPARE;
            });
        }

        function toast(message, type = 'info') {
            window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));
        }

        window.AuctionCompareUI = {
            read: readIds,
            write: writeIds,
            add(id) {
                const ids = readIds();
                const numericId = Number(id);

                if (!Number.isInteger(numericId) || numericId <= 0) {
                    return ids;
                }

                if (ids.includes(numericId)) {
                    return ids;
                }

                if (ids.length >= MAX_COMPARE) {
                    toast('You can compare up to 4 auctions at a time.', 'warning');
                    syncToggles(ids);
                    return ids;
                }

                const next = writeIds([...ids, numericId]);
                toast('Auction added to comparison tray.', 'success');
                return next;
            },
            remove(id, redirectIfTooFew = false) {
                const next = writeIds(readIds().filter((item) => item !== Number(id)));

                if (redirectIfTooFew && window.location.pathname === new URL(compareUrl, window.location.origin).pathname && next.length < 2) {
                    window.location.href = compareUrl;
                }

                return next;
            },
            clear() {
                writeIds([]);
            },
            open() {
                const ids = readIds();

                if (ids.length < 2) {
                    toast('Select at least 2 auctions to compare.', 'warning');
                    return;
                }

                const url = new URL(compareUrl, window.location.origin);
                ids.forEach((id) => url.searchParams.append('ids[]', String(id)));
                window.location.href = url.toString();
            },
        };

        window.auctionCompareBar = function () {
            return {
                ids: readIds(),
                init() {
                    syncToggles(this.ids);

                    window.addEventListener('auction-compare:changed', (event) => {
                        this.ids = parseIds(event.detail?.ids || []);
                    });
                },
                remove(id) {
                    window.AuctionCompareUI.remove(id);
                },
                clear() {
                    window.AuctionCompareUI.clear();
                },
                openCompare() {
                    window.AuctionCompareUI.open();
                },
            };
        };

        function attachToggleHandlers() {
            syncToggles();

            document.querySelectorAll('[data-compare-toggle]').forEach((input) => {
                if (input.dataset.compareBound === 'true') {
                    return;
                }

                input.dataset.compareBound = 'true';
                input.addEventListener('change', () => {
                    if (input.checked) {
                        const ids = window.AuctionCompareUI.add(Number(input.dataset.compareToggle));
                        input.checked = ids.includes(Number(input.dataset.compareToggle));
                        return;
                    }

                    window.AuctionCompareUI.remove(Number(input.dataset.compareToggle));
                });
            });
        }

        function updateComparedAuction(auctionId, patch) {
            if (patch.new_price !== undefined || patch.current_price !== undefined) {
                const price = Number(patch.new_price ?? patch.current_price);
                if (Number.isFinite(price)) {
                    document.querySelectorAll(`[data-field="current_price"][data-auction-id="${auctionId}"]`).forEach((el) => {
                        el.textContent = `$${price.toFixed(2)}`;
                    });
                }
            }

            if (patch.next_minimum !== undefined) {
                const nextMinimum = Number(patch.next_minimum);
                if (Number.isFinite(nextMinimum)) {
                    document.querySelectorAll(`[data-field="next_minimum"][data-auction-id="${auctionId}"]`).forEach((el) => {
                        el.textContent = `$${nextMinimum.toFixed(2)}`;
                    });
                }
            }

            if (patch.bid_count !== undefined) {
                document.querySelectorAll(`[data-field="bid_count"][data-auction-id="${auctionId}"]`).forEach((el) => {
                    el.textContent = String(patch.bid_count);
                });
            }

            if (patch.time_remaining !== undefined) {
                document.querySelectorAll(`[data-field="time_remaining"][data-auction-id="${auctionId}"]`).forEach((el) => {
                    el.textContent = String(patch.time_remaining);
                });
            }
        }

        async function pollComparedAuctions() {
            if (!Array.isArray(initialPollAuctions) || initialPollAuctions.length === 0) {
                return;
            }

            await Promise.all(initialPollAuctions.map(async (auction) => {
                try {
                    const response = await fetch(auction.live_state_url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        return;
                    }

                    const data = await response.json();
                    updateComparedAuction(auction.id, {
                        new_price: data.new_price ?? data.current_price,
                        next_minimum: data.next_minimum,
                        bid_count: data.bid_count,
                        time_remaining: data.time_remaining,
                    });
                } catch (_) {
                    // Best-effort refresh only.
                }
            }));
        }

        document.addEventListener('DOMContentLoaded', () => {
            attachToggleHandlers();
            pollComparedAuctions();
            setInterval(pollComparedAuctions, 30000);
        });

        window.addEventListener('auction-compare:bind', attachToggleHandlers);
    })();
</script>
