const toNumber = (value) => {
  const number = Number(value);
  return Number.isFinite(number) ? number : null;
};

const formatMoney = (value) => {
  const amount = toNumber(value);
  if (amount === null) {
    return null;
  }

  if (typeof formatCurrency === 'function') {
    return formatCurrency(amount);
  }

  return '$' + amount.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

const formatAmount = (value) => {
  const amount = toNumber(value);
  if (amount === null) {
    return null;
  }

  return amount.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

const escapeHtml = (value) => String(value)
  .replace(/&/g, '&amp;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;')
  .replace(/'/g, '&#039;');

const resolveBidderName = (data) => data?.user_name ?? data?.bidder_name ?? 'Unknown';
const resolveBidCount = (data) => data?.bids_count ?? data?.bid_count;

const resolveNextMinimum = (data, amount) => {
  const explicitNextMinimum = toNumber(data?.next_minimum ?? data?.nextMinimum);
  if (explicitNextMinimum !== null) {
    return explicitNextMinimum;
  }

  const minIncrement = toNumber(document.getElementById('bid-amount')?.dataset?.increment);
  if (amount !== null && minIncrement !== null) {
    return amount + minIncrement;
  }

  return null;
};

const renderBidHistoryEntry = (data, amount) => {
  const bidderName = resolveBidderName(data);
  const bidderInitial = (bidderName.trim().charAt(0) || '?').toUpperCase();
  const bidType = String(data?.bid_type ?? '').toLowerCase();
  const isAuto = bidType === 'auto';
  const isSnipe = Boolean(data?.is_snipe_bid);
  const timestamp = data?.created_at_human ?? 'just now';
  const amountLabel = formatMoney(amount) ?? '$0.00';
  const bidId = toNumber(data?.bid_id);
  const bidIdAttr = bidId !== null ? ` data-bid-id="${bidId}"` : '';

  return `
    <div class="flex items-center justify-between py-3 bid-entry"${bidIdAttr}>
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-700 font-semibold text-sm">
          ${escapeHtml(bidderInitial)}
        </div>
        <div>
          <span class="text-sm font-medium text-gray-900">${escapeHtml(bidderName)}</span>
          ${isAuto ? '<span class="ml-1.5 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">Auto</span>' : ''}
          ${isSnipe ? '<span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">Snipe</span>' : ''}
        </div>
      </div>
      <div class="text-right">
        <span class="text-sm font-bold text-gray-900">${amountLabel}</span>
        <span class="block text-xs text-gray-400">${escapeHtml(timestamp)}</span>
      </div>
    </div>
  `;
};

window.addEventListener('bid:placed', (e) => {
  const data = e?.detail ?? {};
  const amount = toNumber(data?.amount ?? data?.newPrice ?? data?.new_price);

  const priceDisplay = document.getElementById('price-display');
  if (priceDisplay && amount !== null) {
    const amountLabel = formatMoney(amount);
    if (amountLabel) {
      priceDisplay.textContent = amountLabel;
    }
  }

  const nextMinimum = resolveNextMinimum(data, amount);

  const minBid = document.getElementById('min-bid');
  if (minBid && nextMinimum !== null) {
    const minLabel = formatAmount(nextMinimum);
    if (minLabel) {
      minBid.textContent = minLabel;
    }
  }

  const bidAmount = document.getElementById('bid-amount');
  if (bidAmount && nextMinimum !== null) {
    const normalizedMin = nextMinimum.toFixed(2);
    bidAmount.min = normalizedMin;
    const currentValue = toNumber(bidAmount.value);
    if (currentValue === null || currentValue < nextMinimum) {
      bidAmount.value = normalizedMin;
    }
  }

  const bidCount = document.getElementById('bid-count');
  const bidCountValue = resolveBidCount(data);
  if (bidCount && bidCountValue !== undefined && bidCountValue !== null) {
    bidCount.textContent = String(bidCountValue);
  }

  const bidderName = resolveBidderName(data);
  if (bidderName) {
    let highestBidder = document.getElementById('highest-bidder');

    if (!highestBidder) {
      const priceSection = document.getElementById('price-display')?.parentElement;
      if (priceSection) {
        const bidderLine = document.createElement('p');
        bidderLine.className = 'text-xs text-gray-400 mt-1';
        bidderLine.innerHTML = 'by <span id="highest-bidder" class="font-medium"></span>';
        priceSection.appendChild(bidderLine);
        highestBidder = bidderLine.querySelector('#highest-bidder');
      }
    }

    if (highestBidder) {
      highestBidder.textContent = bidderName;
    }
  }

  const bidHistory = document.getElementById('bid-history');
  if (bidHistory) {
    const emptyState = bidHistory.querySelector('.empty-state');
    if (emptyState) {
      emptyState.remove();
    }

    const incomingBidId = toNumber(data?.bid_id);
    if (incomingBidId !== null && bidHistory.querySelector(`.bid-entry[data-bid-id="${incomingBidId}"]`)) {
      return;
    }

    if (typeof data?.html === 'string' && data.html.trim().length > 0) {
      bidHistory.insertAdjacentHTML('afterbegin', data.html);
    } else if (amount !== null) {
      bidHistory.insertAdjacentHTML('afterbegin', renderBidHistoryEntry(data, amount));
    }

    const entries = bidHistory.querySelectorAll('.bid-entry');
    if (entries.length > 10) {
      entries[entries.length - 1].remove();
    }
  }
});

window.addEventListener('price:updated', (e) => {
  const data = e?.detail ?? {};
  const latestPrice = toNumber(data?.newPrice ?? data?.new_price ?? data?.amount);

  const priceDisplay = document.getElementById('price-display');
  if (priceDisplay && latestPrice !== null) {
    const amountLabel = formatMoney(latestPrice);
    if (amountLabel) {
      priceDisplay.textContent = amountLabel;
    }
  }

  const nextMinimum = resolveNextMinimum(data, latestPrice);
  if (nextMinimum !== null) {
    const minBid = document.getElementById('min-bid');
    if (minBid) {
      const minLabel = formatAmount(nextMinimum);
      if (minLabel) {
        minBid.textContent = minLabel;
      }
    }

    const bidAmount = document.getElementById('bid-amount');
    if (bidAmount) {
      bidAmount.min = nextMinimum.toFixed(2);
    }
  }
});

window.addEventListener('auction:closed', () => {
  const bidForm = document.getElementById('bid-form');
  if (bidForm) {
    bidForm.style.opacity = '0.5';
    const inputs = bidForm.querySelectorAll('input, button');
    inputs.forEach((input) => {
      input.disabled = true;
    });
  }

  const countdown = document.getElementById('countdown');
  if (countdown) {
    countdown.textContent = 'Ended';
    countdown.classList.add('text-gray-400');
    countdown.classList.remove('text-indigo-600', 'text-orange-600');
  }
});

window.quickBid = function ({ minBid, currentPrice, increment }) {
  return {
    min: parseFloat(minBid),
    current: parseFloat(currentPrice),
    inc: parseFloat(increment),
    init() {
      const syncFromEvent = (detail) => {
        const nextAmount = toNumber(detail?.amount ?? detail?.newPrice ?? detail?.new_price);
        if (nextAmount === null) {
          return;
        }

        this.current = nextAmount;
        this.min = this.current + this.inc;
      };

      window.addEventListener('bid:placed', (e) => syncFromEvent(e?.detail));
      window.addEventListener('price:updated', (e) => syncFromEvent(e?.detail));
    },
    get minLabel() {
      return `Min ($${this.min.toFixed(2)})`;
    },
    get plus5Label() {
      return `+5% ($${(this.current * 1.05).toFixed(2)})`;
    },
    get plus10Label() {
      return `+10% ($${(this.current * 1.10).toFixed(2)})`;
    },
    setMin() {
      const input = document.getElementById('bid-amount');
      if (input) {
        input.value = this.min.toFixed(2);
      }
    },
    setPlus5() {
      const input = document.getElementById('bid-amount');
      if (input) {
        input.value = (this.current * 1.05).toFixed(2);
      }
    },
    setPlus10() {
      const input = document.getElementById('bid-amount');
      if (input) {
        input.value = (this.current * 1.10).toFixed(2);
      }
    },
  };
};