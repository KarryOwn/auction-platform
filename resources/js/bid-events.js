class BidEventBus {
  constructor() {
    this._channels = new Map();
  }

  subscribe(auctionId) {
    if (this._channels.has(auctionId)) return;

    const channel = window.Echo.channel(`auctions.${auctionId}`);
    this._channels.set(auctionId, channel);

    channel.listen('.bid.placed', (e) => {
      window.dispatchEvent(new CustomEvent('bid:placed', { detail: { auctionId, ...e } }));
    });

    channel.listen('.price-updated', (e) => {
      window.dispatchEvent(new CustomEvent('price:updated', { detail: { auctionId, newPrice: e.newPrice } }));
    });

    channel.listen('.auction.closed', (e) => {
      window.dispatchEvent(new CustomEvent('auction:closed', { detail: { auctionId, ...e } }));
    });
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