class BidEventBus {
  constructor() {
    this._channels = new Map();
  }

  _normalizeEventName(eventName) {
    return String(eventName ?? '').replace(/^\./, '');
  }

  _handleAuctionEvent(auctionId, eventName, payload) {
    const normalized = this._normalizeEventName(eventName);

    if (normalized === 'bid.placed' || normalized.endsWith('BidPlaced')) {
      window.dispatchEvent(new CustomEvent('bid:placed', { detail: { auctionId, ...payload } }));
      return;
    }

    if (normalized === 'price-updated' || normalized.endsWith('PriceUpdated')) {
      const newPrice = payload?.newPrice ?? payload?.new_price ?? payload?.amount;
      window.dispatchEvent(new CustomEvent('price:updated', {
        detail: {
          auctionId,
          ...payload,
          newPrice,
          new_price: payload?.new_price ?? newPrice,
        },
      }));
      return;
    }

    if (normalized === 'auction.closed' || normalized.endsWith('AuctionClosed')) {
      window.dispatchEvent(new CustomEvent('auction:closed', { detail: { auctionId, ...payload } }));
    }
  }

  subscribe(auctionId) {
    if (this._channels.has(auctionId)) return;

    const channel = window.Echo.channel(`auctions.${auctionId}`);
    this._channels.set(auctionId, channel);

    if (typeof channel.listenToAll === 'function') {
      channel.listenToAll((eventName, payload) => {
        this._handleAuctionEvent(auctionId, eventName, payload);
      });
      return;
    }

    // Fallback for channel implementations without listenToAll.
    channel.listen('.bid.placed', (e) => this._handleAuctionEvent(auctionId, '.bid.placed', e));
    channel.listen('bid.placed', (e) => this._handleAuctionEvent(auctionId, 'bid.placed', e));
    channel.listen('.price-updated', (e) => this._handleAuctionEvent(auctionId, '.price-updated', e));
    channel.listen('price-updated', (e) => this._handleAuctionEvent(auctionId, 'price-updated', e));
    channel.listen('.auction.closed', (e) => this._handleAuctionEvent(auctionId, '.auction.closed', e));
    channel.listen('auction.closed', (e) => this._handleAuctionEvent(auctionId, 'auction.closed', e));
  }

  unsubscribe(auctionId) {
    if (this._channels.has(auctionId)) {
      window.Echo.leaveChannel(`auctions.${auctionId}`);
      this._channels.delete(auctionId);
    }
  }

  unsubscribeAll() {
    this._channels.forEach((_, id) => this.unsubscribe(id));
  }
}

window.BidEventBus = new BidEventBus();
export default window.BidEventBus;