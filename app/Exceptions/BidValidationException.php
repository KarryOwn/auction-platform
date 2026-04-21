<?php

namespace App\Exceptions;

use RuntimeException;

class BidValidationException extends RuntimeException
{
    public const AUCTION_NOT_ACTIVE  = 'auction_not_active';
    public const AUCTION_ENDED      = 'auction_ended';
    public const AUCTION_PAUSED     = 'auction_paused';
    public const BID_TOO_LOW        = 'bid_too_low';
    public const SELF_BID           = 'self_bid';
    public const USER_BANNED        = 'user_banned';
    public const RATE_LIMITED       = 'rate_limited';
    public const INSUFFICIENT_FUNDS = 'insufficient_funds';

    protected string $errorCode;
    protected array $context;

    public function __construct(string $message, string $errorCode, array $context = [], int $httpStatus = 422)
    {
        parent::__construct($message, $httpStatus);
        $this->errorCode = $errorCode;
        $this->context   = $context;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'error'   => $this->errorCode,
            'message' => $this->getMessage(),
            'context' => $this->context,
        ], $this->getCode());
    }

    // ── Named Constructors ──────────────────────

    public static function auctionNotActive(): static
    {
        return new static('This auction is not currently active.', self::AUCTION_NOT_ACTIVE);
    }

    public static function auctionEnded(): static
    {
        return new static('This auction has already ended.', self::AUCTION_ENDED);
    }

    public static function auctionPaused(): static
    {
        return new static('This auction is temporarily paused while the seller is on vacation.', self::AUCTION_PAUSED);
    }

    public static function bidTooLow(float $currentPrice, float $minimumBid): static
    {
        $minimumBid = round($minimumBid, 2);
        $currentPrice = round($currentPrice, 2);

        return new static(
            'Bid must be at least $'.number_format($minimumBid, 2).'. Current price is $'.number_format($currentPrice, 2).'.',
            self::BID_TOO_LOW,
            ['current_price' => $currentPrice, 'minimum_bid' => $minimumBid],
        );
    }

    public static function selfBid(): static
    {
        return new static('You cannot bid on your own auction.', self::SELF_BID, [], 403);
    }

    public static function userBanned(): static
    {
        return new static('Your account is banned and cannot place bids.', self::USER_BANNED, [], 403);
    }

    public static function rateLimited(int $retryAfterSeconds = 5): static
    {
        return new static(
            "You are bidding too fast. Please wait {$retryAfterSeconds} seconds.",
            self::RATE_LIMITED,
            ['retry_after' => $retryAfterSeconds],
            429,
        );
    }

    public static function insufficientFunds(float $required, float $available): static
    {
        return new static(
            'Insufficient wallet balance.',
            self::INSUFFICIENT_FUNDS,
            ['required' => $required, 'available' => $available],
            402,
        );
    }
}
