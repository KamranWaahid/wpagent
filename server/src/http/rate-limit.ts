export type RateLimitResult = {
  ok: boolean;
  remaining: number;
  retryAfterMs: number;
};

type Bucket = {
  count: number;
  resetAt: number;
};

export class RateLimiter {
  private readonly buckets = new Map<string, Bucket>();

  constructor(private readonly now: () => number = () => Date.now()) {}

  hit(key: string, limit: number, windowMs: number): RateLimitResult {
    const now = this.now();
    const existing = this.buckets.get(key);
    if (!existing || existing.resetAt <= now) {
      this.buckets.set(key, { count: 1, resetAt: now + windowMs });
      return { ok: true, remaining: limit - 1, retryAfterMs: windowMs };
    }
    existing.count += 1;
    const ok = existing.count <= limit;
    return {
      ok,
      remaining: Math.max(0, limit - existing.count),
      retryAfterMs: Math.max(0, existing.resetAt - now),
    };
  }

  prune(): void {
    const now = this.now();
    for (const [key, bucket] of this.buckets) {
      if (bucket.resetAt <= now) {
        this.buckets.delete(key);
      }
    }
  }
}
