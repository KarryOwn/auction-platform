document.addEventListener('bid:placed', (e) => {
  const data = e.detail;

  const priceDisplay = document.getElementById('price-display');
  if (priceDisplay) {
    if (typeof formatCurrency === 'function') {
        priceDisplay.textContent = formatCurrency(data.amount);
    } else {
        priceDisplay.textContent = '$' + Number(data.amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
  }

  const minBid = document.getElementById('min-bid');
  if (minBid) {
      if (typeof formatCurrency === 'function' && data.next_minimum) {
          minBid.textContent = formatCurrency(data.next_minimum);
      } else if (data.next_minimum) {
          minBid.textContent = '$' + Number(data.next_minimum).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
      }
  }

  const bidAmount = document.getElementById('bid-amount');
  if (bidAmount && data.next_minimum) {
    bidAmount.min = data.next_minimum;
    if (parseFloat(bidAmount.value) < data.next_minimum) {
      bidAmount.value = data.next_minimum;
    }
  }

  const bidCount = document.getElementById('bid-count');
  if (bidCount && data.bids_count !== undefined) {
    bidCount.textContent = data.bids_count;
  }

  const highestBidder = document.getElementById('highest-bidder');
  if (highestBidder && data.user_name) {
    highestBidder.textContent = data.user_name;
  }

  const bidHistory = document.getElementById('bid-history');
  if (bidHistory && data.html) {
      // Remove empty state if present
      const emptyState = bidHistory.querySelector('.empty-state');
      if (emptyState) emptyState.remove();
      
      bidHistory.insertAdjacentHTML('afterbegin', data.html);
  }
});

document.addEventListener('price:updated', (e) => {
  const data = e.detail;
  
  const priceDisplay = document.getElementById('price-display');
  if (priceDisplay && data.newPrice) {
      if (typeof formatCurrency === 'function') {
        priceDisplay.textContent = formatCurrency(data.newPrice);
      } else {
        priceDisplay.textContent = '$' + Number(data.newPrice).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
      }
  }
});

document.addEventListener('auction:closed', (e) => {
  const data = e.detail;

  const bidForm = document.getElementById('bid-form');
  if (bidForm) {
      bidForm.style.opacity = '0.5';
      const inputs = bidForm.querySelectorAll('input, button');
      inputs.forEach(input => input.disabled = true);
  }

  const countdown = document.getElementById('countdown');
  if (countdown) {
      countdown.textContent = 'Ended';
      countdown.classList.add('text-gray-400');
      countdown.classList.remove('text-indigo-600', 'text-orange-600');
  }
});