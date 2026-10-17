<?php

declare(strict_types=1);

namespace Imperial\Kits;

/**
 * ═══════════════════════════════════════════════════════════════
 *  Kit — DTO одного кита (описание из config.yml)
 * ───────────────────────────────────────────────────────────────
 *  Кит с номером N доступен игроку, если weight его ранга >= N
 *  (weight ранга берётся из публичного API LiteRanks).
 *
 *  ⚠ PHP 8.0: readonly НЕ используем — просто public-свойства,
 *    меняются только в конструкторе (договорённость об иммутабельности).
 * ═══════════════════════════════════════════════════════════════
 */
final class Kit
{
    /** Кулдаун по умолчанию — 5 часов (сек), если в конфиге не задан */
    public const DEFAULT_COOLDOWN = 18000;

    /** Символы китов по номерам — те же, что у рангов LiteRanks */
    private const DEFAULT_ICONS = [
        1  => '✧',
        2  => '❂',
        3  => '⚔',
        4  => '✥',
        5  => '✦',
        6  => '❈',
        7  => '❖',
        8  => '♛',
        9  => '♚',
        10 => '龍',
    ];

    /**
     * @param int               $id       номер кита 1–10 (он же требуемый вес)
     * @param string            $name     русское название (напр. «Странник»)
     * @param string            $color    §-цвет названия (цвет ранга)
     * @param string            $icon     личный символ кита (✧ ⚔ ♛ 龍 …)
     * @param int               $weight   требуемый вес ранга
     * @param int               $cooldown кулдаун кита в секундах
     * @param array<int, mixed> $items    сырые описания предметов из конфига
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $color,
        public string $icon,
        public int $weight,
        public int $cooldown,
        public array $items
    ) {
    }

    /**
     * Собрать Kit из строки конфига.
     *
     * @param int                $id              номер кита
     * @param array<string,mixed> $data           блок кита из config.yml
     * @param int                $defaultCooldown кулдаун из settings.default-cooldown
     */
    public static function fromArray(int $id, array $data, int $defaultCooldown = self::DEFAULT_COOLDOWN): self
    {
        // Поле icon могло прийти пустым — берём символ по номеру кита
        $icon = trim((string) ($data['icon'] ?? ''));
        if ($icon === '') {
            $icon = self::DEFAULT_ICONS[$id] ?? '❈';
        }

        return new self(
            $id,
            (string) ($data['name'] ?? ('Кит ' . $id)),
            (string) ($data['color'] ?? '§f'),
            $icon,
            max(1, (int) ($data['weight'] ?? $id)),
            max(1, (int) ($data['cooldown'] ?? $defaultCooldown)),
            (array) ($data['items'] ?? [])
        );
    }

    /**
     * Название в цвете ранга с личным символом — для меню и сообщений.
     */
    public function coloredName(): string
    {
        return $this->color . $this->icon . ' ' . $this->name;
    }

    /**
     * Есть ли у кита хоть один предмет (пустой кит выдавать нельзя).
     */
    public function hasItems(): bool
    {
        return $this->items !== [];
    }
}