<?php

declare(strict_types=1);

namespace Imperial\Ranks;

/**
 * ═══════════════════════════════════════════════════════════════
 *  PlayerRank — DTO строки lc_player_ranks
 * ───────────────────────────────────────────────────────────────
 *  «Текущий ранг» (rankId) — что реально пожалован.
 *  «Печать» (displayRankId) — что показывать в чате; NULL = как rankId.
 *
 *  ⚠ PHP 8.0: БЕЗ readonly/enum — просто public-свойства.
 * ═══════════════════════════════════════════════════════════════
 */
final class PlayerRank
{
    public function __construct(
        public string $username,
        public string $rankId,
        public ?string $displayRankId,
        public ?string $grantedBy,
        public ?string $grantedAt,
        public ?string $expiresAt
    ) {
    }

    /**
     * @param array<string, mixed> $row строка из lc_player_ranks
     */
    public static function fromRow(array $row): self
    {
        $null = static fn(mixed $v): ?string => ($v === null || $v === '') ? null : (string) $v;

        return new self(
            (string) $row['username'],
            (string) $row['rank_id'],
            $null($row['display_rank_id'] ?? null),
            $null($row['granted_by'] ?? null),
            $null($row['granted_at'] ?? null),
            $null($row['expires_at'] ?? null)
        );
    }

    /**
     * Виртуальное состояние игрока, для которого строки в БД ещё нет
     * (не регистрировался в рангах) — пишем в MySQL только при реальном изменении.
     */
    public static function synthetic(string $username, string $defaultRankId): self
    {
        return new self($username, $defaultRankId, null, null, null, null);
    }

    /** Временный ранг истёк? (expires_at в прошлом) */
    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }
        $ts = strtotime($this->expiresAt);
        return $ts !== false && $ts <= time();
    }
}
