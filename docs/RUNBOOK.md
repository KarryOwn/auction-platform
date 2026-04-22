# Operations Runbook

## Redis Outage & Graceful Degradation

### Overview
The Auction Platform is designed for high availability. By default, it uses a high-performance Redis engine (`RedisAtomicEngine`) for rapid bidding and rate-limiting operations.

If the Redis infrastructure becomes unavailable, the platform will automatically degrade gracefully:
1. It immediately falls back to a database-backed engine (`PessimisticSqlEngine`).
2. Rate limits failover seamlessly to standard database persistence (`BidRateLimit` model).
3. The platform continues to accept bids, though maximum concurrency limits might be observed under severe load due to Postgres row-level locking.
4. An automated `CRITICAL` email is dispatched to the operations team (configured via `OPS_EMAIL`).

### Resolution & Reconciliation
During the period where Redis was unreachable, new bids and current auction prices were safely committed to the Postgres database. However, the Redis state will be stale once it is restored.

**When Redis is fully online and reachable again, you MUST execute the following recovery steps:**

1. **Reconcile Price Data**
   Run the following Artisan command to re-sync all active auction prices from Postgres back into Redis.
   ```bash
   sail artisan auction:sync-prices
   ```

2. **Restart Queue Workers**
   Because background workers (Horizon/Queue) may have cached stale connections or states, restart them to ensure they cleanly reconnect to Redis.
   ```bash
   sail artisan horizon:terminate
   # OR if not using Horizon
   sail artisan queue:restart
   ```

3. **Verify**
   Check the system logs (`storage/logs/laravel.log`) to confirm there are no lingering connection errors and that the `AppServiceProvider` is successfully binding the `RedisAtomicEngine` again.