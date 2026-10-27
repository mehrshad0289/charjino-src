<?php

namespace App\Modules\Codm;

/**
 * Outcome of a CODM (Activision) account-region probe against the official store validator.
 *
 * status:
 *  - match    : the UID exists and belongs to the requested variant country (nickname/shortId known)
 *  - mismatch : the UID exists but its home country differs (accountCountry = real country)
 *  - unknown  : could not decide (disabled, timeout, 5xx, unexpected body, player not found…)
 */
final class CodmRegionResult
{
    public const MATCH = 'match';

    public const MISMATCH = 'mismatch';

    public const UNKNOWN = 'unknown';

    /**
     * @param  array<string, mixed>|null  $raw  decoded validator body (for audit/logging only, never shown to customers)
     */
    public function __construct(
        public readonly string $status,
        public readonly string $variantCountry,
        public readonly ?string $nickname = null,
        public readonly ?string $shortId = null,
        public readonly ?string $accountCountry = null,
        public readonly ?string $suggestedVariant = null,
        public readonly ?string $reason = null,
        public readonly ?int $httpStatus = null,
        public readonly ?array $raw = null,
        public readonly bool $fromCache = false,
    ) {}

    public static function unknown(string $variantCountry, string $reason, ?int $httpStatus = null, ?array $raw = null): self
    {
        return new self(self::UNKNOWN, $variantCountry, reason: $reason, httpStatus: $httpStatus, raw: $raw);
    }

    public function isMatch(): bool
    {
        return $this->status === self::MATCH;
    }

    public function isMismatch(): bool
    {
        return $this->status === self::MISMATCH;
    }

    public function isUnknown(): bool
    {
        return $this->status === self::UNKNOWN;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'variantCountry' => $this->variantCountry,
            'nickname' => $this->nickname,
            'shortId' => $this->shortId,
            'accountCountry' => $this->accountCountry,
            'suggestedVariant' => $this->suggestedVariant,
            'reason' => $this->reason,
            'httpStatus' => $this->httpStatus,
            'raw' => $this->raw,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, bool $fromCache = false): self
    {
        return new self(
            (string) ($data['status'] ?? self::UNKNOWN),
            (string) ($data['variantCountry'] ?? ''),
            isset($data['nickname']) ? (string) $data['nickname'] : null,
            isset($data['shortId']) ? (string) $data['shortId'] : null,
            isset($data['accountCountry']) ? (string) $data['accountCountry'] : null,
            isset($data['suggestedVariant']) ? (string) $data['suggestedVariant'] : null,
            isset($data['reason']) ? (string) $data['reason'] : null,
            isset($data['httpStatus']) ? (int) $data['httpStatus'] : null,
            is_array($data['raw'] ?? null) ? $data['raw'] : null,
            $fromCache,
        );
    }

    /**
     * Customer-safe payload (no raw supplier body).
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'status' => $this->status,
            'variantCountry' => $this->variantCountry,
            'nickname' => $this->nickname,
            'shortId' => $this->shortId,
            'accountCountry' => $this->accountCountry,
            'suggestedVariant' => $this->suggestedVariant,
            'suggestedHandle' => CodmRegionCheck::handleForVariant($this->suggestedVariant),
            'variantHandle' => CodmRegionCheck::handleForVariant($this->variantCountry),
        ];
    }
}
