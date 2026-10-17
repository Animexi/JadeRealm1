<?php

declare(strict_types=1);

namespace Imperial\Ranks;

use pocketmine\Player;
use Throwable;

/**
 * ═══════════════════════════════════════════════════════════════
 *  RankManager — сердце плагина
 * ───────────────────────────────────────────────────────────────
 *  • Ранги берутся ИЗ КОНФИГА (не из БД) — в MySQL лежит только
 *    привязка игрока: rank_id + display_rank_id.
 *    ⇒ переименовал/перекрасил ранг в config.yml = обновился
 *      у всех игроков мгновенно, без миграций.
 *  • Кэш состояний игроков в памяти (инвалидируется при change/quit)
 *  • Публичный API для других плагинов Империи:
 *      → hasPermission(), limit(), getDisplayRank(), getRankOf()
 *
 *  ⚠ Все обращения к БД — синхронные (основной поток), поэтому
 *    вызывающий обязан перехватывать Throwable.
 * ═══════════════════════════════════════════════════════════════
 */
final class RankManager
{
    public function __construct(private Main $plugin)
    {
    }

    /** @var array<string, Rank> id => Rank (в порядке объявления конфига) */
    private array $ranks = [];

    /** @var array<string, PlayerRank> lowercase nick => состояние */
    private array $cache = [];

    // ═════════════════ ЗАГРУЗКА РАНГОВ ИЗ КОНФИГА ═════════════════

    /**
     * Перечитать секцию ranks: из config.yml.
     * Пустой/битый блок не роняет плагин — остаётся дефолтный ранг.
     */
    public function reloadRanks(): int
    {
        $raw = (array) $this->plugin->getConfig()->getNested('ranks', []);
        $default = $this->defaultRankId();

        $parsed = [];
        foreach ($raw as $id => $data) {
            $id = strtolower(trim((string) $id));
            if ($id === '' || !is_array($data)) {
                continue;
            }
            $parsed[$id] = Rank::fromArray($id, $data);
        }

        // Дефолтный ранг обязан существовать — иначе чат встанет колом
        if (!isset($parsed[$default])) {
            $parsed[$default] = new Rank($default, 'Странник', 1, '§7', 'Странник', Theme::defaultIcon($default));
            $this->plugin->getLogger()->warning(
                'Ранг по умолчанию "' . $default . '" отсутствует в конфиге — добавлена заглушка.'
            );
        }

        $this->ranks = $parsed;
        $this->cache = [];

        return count($this->ranks);
    }

    /** id ранга по умолчанию (выдаётся всем новым). */
    public function defaultRankId(): string
    {
        return strtolower((string) $this->plugin->getConfig()->getNested('settings.default-rank', 'strannik'));
    }

    public function defaultRank(): Rank
    {
        return $this->getRankOrFallback($this->defaultRankId());
    }

    /** Самый «лёгкий» существующий ранг вместо отсутствующего — UI никогда не упадёт. */
    private function getRankOrFallback(string $id): Rank
    {
        $known = $this->getRank($id);
        if ($known !== null) {
            return $known;
        }
        $sorted = $this->allSorted();
        return $sorted[0] ?? new Rank($id, $id, 0, '§f', $id, Theme::defaultIcon($id));
    }

    // ═════════════════ СПРАВОЧНИК РАНГОВ ═════════════════

    /** Ранг по id или null, если такого нет. */
    public function getRank(string $id): ?Rank
    {
        return $this->ranks[strtolower($id)] ?? null;
    }

    /** Все ранги, отсортированные по weight (возрастающий). */
    public function allSorted(): array
    {
        $list = array_values($this->ranks);
        usort($list, static fn(Rank $a, Rank $b): int => $a->weight <=> $b->weight);
        return $list;
    }

    /** @return array<string, Rank> */
    public function all(): array
    {
        return $this->ranks;
    }

    // ═════════════════ СОСТОЯНИЕ ИГРОКА ═════════════════

    /**
     * Состояние ранга игрока (кэш → БД → «по умолчанию»).
     * Истёкший временный ранг при чтении молча сбрасывается к дефолту.
     *
     * @throws Throwable при сбое MySQL
     */
    public function state(string $username): PlayerRank
    {
        $key = strtolower($username);
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $row = $this->plugin->db()->find($username);
        if ($row === null) {
            return $this->cache[$key] = PlayerRank::synthetic($username, $this->defaultRankId());
        }

        $pr = PlayerRank::fromRow($row);

        if ($pr->isExpired()) {
            $default = $this->defaultRankId();
            $this->plugin->db()->expireReset($username, $default);
            $this->plugin->getLogger()->info("Ранг игрока {$username} истёк — возвращён к «{$default}»");
            $pr = PlayerRank::synthetic($username, $default);
        }

        return $this->cache[$key] = $pr;
    }

    /** Настоящий (пожалованный) ранг игрока. */
    public function getRankOf(string $username): Rank
    {
        return $this->getRankOrFallback(strtolower($this->state($username)->rankId));
    }

    /** Ранг, чью печать показывать (display_rank_id ?: rank_id). */
    public function getDisplayRank(string $username): Rank
    {
        $st = $this->state($username);
        $wanted = strtolower((string) ($st->displayRankId ?? $st->rankId));
        return $this->getRankOrFallback($wanted);
    }

    /** Забыть кэш (quit / смена ника / раздача). */
    public function forget(string $username): void
    {
        unset($this->cache[strtolower($username)]);
    }

    // ═════════════════ ИЗМЕНЕНИЯ ═════════════════

    /**
     * Выдать ранг. $expiresAt — строка MySQL DATETIME или null (навсегда).
     * Печать сбрасывается, если выбранная стала выше нового ранга.
     *
     * @throws Throwable при сбое MySQL
     */
    public function setRank(string $username, string $rankId, ?string $grantedBy, ?string $expiresAt = null): void
    {
        $rankId = strtolower($rankId);
        $this->plugin->db()->upsertRank($username, $rankId, $grantedBy, $expiresAt);

        // Печать не имеет права быть выше настоящего ранга — проверяем до сброса кэша
        $displayId = $this->plugin->db()->find($username)['display_rank_id'] ?? null;
        if (is_string($displayId) && $displayId !== '') {
            $current = $this->getRank($rankId);
            $disp    = $this->getRank($displayId);
            if ($disp === null || ($current !== null && $disp->weight > $current->weight)) {
                $this->plugin->db()->upsertDisplay($username, null);
            }
        }

        $this->forget($username);
    }

    /**
     * Выбрать печать (префикс) из рангов не выше текущего.
     * null → сброс на «как настоящий ранг».
     *
     * @return true если принято, false если ранг недопустим/выше текущего
     * @throws Throwable при сбое MySQL
     */
    public function setDisplayRank(string $username, ?string $rankId): bool
    {
        $current = $this->getRankOf($username);

        if ($rankId === null) {
            $this->plugin->db()->upsertDisplay($username, null);
            $this->forget($username);
            return true;
        }

        $rankId = strtolower($rankId);
        $wanted = $this->getRank($rankId);
        if ($wanted === null || $wanted->weight > $current->weight) {
            return false;
        }

        $this->plugin->db()->upsertDisplay($username, $rankId);
        $this->forget($username);
        return true;
    }

    /**
     * Снять ранг совсем — игрок снова получает ранг по умолчанию.
     *
     * @throws Throwable при сбое MySQL
     */
    public function removeRank(string $username): bool
    {
        $deleted = $this->plugin->db()->delete($username);
        $this->forget($username);
        return $deleted;
    }

    private function key(string $username): string
    {
        return strtolower($username);
    }

    // ═════════════════ ПУБЛИЧНЫЙ API ДЛЯ ИМПЕРИИ ═════════════════

    /**
     * Есть ли у игрока узел права его ранга.
     * Узлы вида «ranks.kit.*» покрывают «ranks.kit.daily».
     */
    public function hasPermission(string $username, string $node): bool
    {
        return $this->getRankOf($username)->hasPermission($node);
    }

    /**
     * Лимит ранга (home-точки, дома, /fly и т.п.) для будущих плагинов.
     */
    public function limit(string $username, string $key, mixed $default = 0): mixed
    {
        return $this->getRankOf($username)->limit($key, $default);
    }

    // ═════════════════ ОТОБРАЖЕНИЕ ═════════════════

    /** Строка чата: §8[Печать§8] §fНик §8» §7текст */
    public function formatChat(string $username, string $message): string
    {
        $rank = $this->getDisplayRank($username);
        return Theme::bracket($rank->coloredPrefix())
            . ' ' . Theme::TEXT . $username . ' '
            . Theme::FRAME . Theme::ICON_ARROW . ' '
            . Theme::MUTED . $message;
    }

    /**
     * Повесить nameTag по рангу (join/respawn/смена ранга).
     * Формат идентичен чату: §8[§6♛ Принц§8] §fНик
     */
    public function applyNametag(Player $player): void
    {
        try {
            $tag = $this->getDisplayRank($player->getName())->tagFor($player->getName());
            $player->setNameTag($tag);
            $player->setNameTagAlwaysVisible(true);
            $player->setNameTagVisible(true);
        } catch (Throwable $e) {
            $this->plugin->getLogger()->debug('nameTag ' . $player->getName() . ': ' . $e->getMessage());
        }
    }

    // ═════════════════ СООБЩЕНИЯ / ЛОГ ═════════════════

    /**
     * Сообщение из config с подстановкой {PLACEHOLDER}.
     *
     * ⚠ PM3: Config::get() НЕ понимает точки — только getNested()
     */
    public function msg(string $key, array $replacements = []): string
    {
        $raw = (string) $this->plugin->getConfig()->getNested("messages.{$key}", $key);
        foreach ($replacements as $token => $value) {
            $raw = str_replace($token, $value, $raw);
        }
        return $raw;
    }

    /**
     * Журнал раздачи рангов: plugins/LiteRanks/logs/YYYY-MM-DD.log
     */
    public function log(string $line): void
    {
        try {
            $dir = $this->plugin->getDataFolder() . 'logs' . DIRECTORY_SEPARATOR;
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents(
                $dir . date('Y-m-d') . '.log',
                '[' . date('H:i:s') . '] ' . $line . PHP_EOL,
                FILE_APPEND
            );
        } catch (Throwable) {
            $this->plugin->getLogger()->warning('Не удалось записать в лог-файл: ' . $line);
        }
    }
}
