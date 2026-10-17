<?php

declare(strict_types=1);

namespace Imperial\Ranks;

/**
 * ═══════════════════════════════════════════════════════════════
 *  Rank — DTO одного ранга (иммутабельное описание из config.yml)
 * ───────────────────────────────────────────────────────────────
 *  Только class + типизированные свойства (PHP 8.0). БЕЗ readonly/enum!
 * ═══════════════════════════════════════════════════════════════
 */
final class Rank
{
    /**
     * @param string               $id          латиница, ключ в БД/конфиге
     * @param string               $display     русское имя
     * @param int                  $weight      вес: чем больше — тем главнее
     * @param string               $color       §-цвет префикса
     * @param string               $prefix      текст печати (без цвета рамки)
     * @param string               $icon        личный символ ранга (✧ ⚔ ♛ 龍 …)
     * @param array<int, string>   $permissions узлы прав ядра (заглушки)
     * @param array<string, mixed> $limits      лимиты под будущие плагины
     *
     * ⚠ PHP 8.0: readonly НЕ используем — просто public-свойства,
     *   меняются только в конструкторе (договорённость об иммутабельности).
     */
    public function __construct(
        public string $id,
        public string $display,
        public int $weight,
        public string $color,
        public string $prefix,
        public string $icon = '✧',
        public array $permissions = [],
        public array $limits = []
    ) {
    }

    /**
     * Собрать Rank из строки конфига.
     *
     * @param string             $id    ключ ранга
     * @param array<string,mixed> $data блок ранга из config.yml
     */
    public static function fromArray(string $id, array $data): self
    {
        // Поле icon могло прийти пустым (в старом конфиге его просто нет) —
        // берём символ по id, иначе все ранги пойдут с одним значком.
        $icon = trim((string) ($data['icon'] ?? ''));
        if ($icon === '') {
            $icon = Theme::defaultIcon($id);
        }

        return new self(
            $id,
            (string) ($data['display'] ?? $id),
            (int) ($data['weight'] ?? 0),
            (string) ($data['color'] ?? '§f'),
            (string) ($data['prefix'] ?? $id),
            $icon,
            (array) ($data['permissions'] ?? []),
            (array) ($data['limits'] ?? [])
        );
    }

    /**
     * Цветная печать ранга: §6♛ Принц (готовая строка для §8[..§8]).
     * Символ ранга всегда идёт перед названием и красится в цвет ранга.
     */
    public function coloredPrefix(): string
    {
        return $this->color . $this->icon . ' ' . $this->prefix;
    }

    /**
     * Русское название в цвете ранга — для списков (не серое!).
     */
    public function coloredDisplay(): string
    {
        return $this->color . $this->display;
    }

    /**
     * Печать + ник (§f белый) — ЕДИНЫЙ формат для чата и nameTag:
     * §8[§6♛ Принц§8] §fNick
     */
    public function tagFor(string $nick): string
    {
        return Theme::bracket($this->coloredPrefix()) . ' ' . Theme::TEXT . $nick;
    }

    /**
     * Есть ли у ранга узел права (с поддержкой '*' и префиксов 'a.b.*').
     */
    public function hasPermission(string $node): bool
    {
        if (in_array('*', $this->permissions, true)) {
            return true;
        }
        if (in_array($node, $this->permissions, true)) {
            return true;
        }
        // Поддержка wc-узлов: «ranks.kit.*» покрывает «ranks.kit.daily»
        foreach ($this->permissions as $perm) {
            if (str_ends_with($perm, '.*')) {
                $base = substr($perm, 0, -2);
                if ($base !== '' && str_starts_with($node, $base . '.')) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Значение лимита по ключу (или $default).
     */
    public function limit(string $key, mixed $default = 0): mixed
    {
        return $this->limits[$key] ?? $default;
    }

    /**
     * Строка веса для списков.
     */
    public function weightBadge(): string
    {
        return Theme::MUTED . 'вес ' . $this->weight;
    }
}
