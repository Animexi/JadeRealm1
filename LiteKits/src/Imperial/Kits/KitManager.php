<?php

declare(strict_types=1);

namespace Imperial\Kits;

use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use Throwable;

/**
 * ═══════════════════════════════════════════════════════════════
 *  KitManager — сердце плагина LiteKits
 * ───────────────────────────────────────────────────────────────
 *  • Киты берутся ИЗ КОНФИГА (config.yml), кулдауны — из БД
 *  • Доступ к киту N: weight ранга игрока (LiteRanks) >= N
 *  • Кулдаун — 5 часов на КАЖДЫЙ кит отдельно (пара user+kit)
 *  • Обход веса и кулдауна — право lc.kits.admin
 *  • Без LiteRanks все киты доступны только админам (режим без рангов)
 *
 *  ⚠ Элитра в 1.1.5 НЕ существует (добавлена в 1.2.0) — в ките 10
 *    вместо неё 64 алмазных блока.
 *  ⚠ Зачарования «Починка» (26) и «Ледоход» (25) добавлены в 1.1.0,
 *    но констант в ядре LiteCore нет — объекты создаются вручную
 *    в enchantment(), чтобы NBT ушёл с корректным id.
 * ═══════════════════════════════════════════════════════════════
 */
final class KitManager
{
    /** Имя плагина рангов, к которому цепляемся */
    private const RANKS_PLUGIN = 'LiteRanks';

    /** Полное имя класса точки входа LiteRanks (для проверки API) */
    private const RANKS_MAIN_CLASS = 'Imperial\\Ranks\\Main';

    /** @var array<int, Kit> id => Kit (отсортировано по номеру) */
    private array $kits = [];

    /** Кэш доступности LiteRanks */
    private ?bool $ranksAvailable = null;

    public function __construct(private Main $plugin)
    {
    }

    // ═════════════════ ЗАГРУЗКА КИТОВ ИЗ КОНФИГА ═════════════════

    /**
     * Перечитать секцию kits: из config.yml.
     * Битые блоки пропускаются, пустой конфиг не роняет плагин.
     */
    public function reloadKits(): int
    {
        $raw       = (array) $this->plugin->getConfig()->getNested('kits', []);
        $defaultCd = max(1, (int) $this->plugin->getConfig()->getNested('settings.default-cooldown', Kit::DEFAULT_COOLDOWN));

        $parsed = [];
        foreach ($raw as $id => $data) {
            $id = (int) $id;
            if ($id < 1 || !is_array($data)) {
                continue;
            }
            $parsed[$id] = Kit::fromArray($id, $data, $defaultCd);
        }

        ksort($parsed);
        $this->kits = $parsed;

        return count($this->kits);
    }

    /** Кит по номеру или null. */
    public function getKit(int $id): ?Kit
    {
        return $this->kits[$id] ?? null;
    }

    /** @return array<int, Kit> все киты по номерам */
    public function all(): array
    {
        return $this->kits;
    }

    // ═════════════════ ИНТЕГРАЦИЯ С LITERANKS ═════════════════

    /** Плагин рангов онлайн (загружен и включён)? */
    private function ranksPlugin(): ?Plugin
    {
        $plugin = $this->plugin->getServer()->getPluginManager()->getPlugin(self::RANKS_PLUGIN);
        return ($plugin !== null && $plugin->isEnabled()) ? $plugin : null;
    }

    /**
     * RankManager LiteRanks или null, если интеграция недоступна.
     * Безопасно и без class_exists-фаталов: класс мог не загрузиться.
     */
    private function rankManager(): ?object
    {
        $plugin = $this->ranksPlugin();
        if ($plugin === null || !class_exists(self::RANKS_MAIN_CLASS)) {
            return null;
        }
        if (!$plugin instanceof \Imperial\Ranks\Main) {
            return null;
        }
        try {
            return $plugin->rm();
        } catch (Throwable $e) {
            $this->plugin->getLogger()->warning('LiteRanks: ' . $e->getMessage());
            return null;
        }
    }

    /** Доступен ли сервис рангов (кэшируется на сессию). */
    public function ranksAvailable(): bool
    {
        return $this->ranksAvailable ??= $this->rankManager() !== null;
    }

    /**
     * Вес ранга игрока; 0 — если рангов нет или сбой БД.
     */
    public function playerWeight(string $nick): int
    {
        $rm = $this->rankManager();
        if ($rm === null) {
            return 0;
        }
        try {
            return $rm->getRankOf($nick)->weight;
        } catch (Throwable $e) {
            $this->plugin->getLogger()->warning('Вес ранга ' . $nick . ': ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Название ранга игрока в цвете — для меню.
     */
    public function playerRankLabel(string $nick): string
    {
        $rm = $this->rankManager();
        if ($rm === null) {
            return Theme::MUTED . '—';
        }
        try {
            return $rm->getRankOf($nick)->coloredDisplay();
        } catch (Throwable) {
            return Theme::MUTED . '—';
        }
    }

    // ═════════════════ КУЛДАУНЫ ═════════════════

    /**
     * Остаток кулдауна кита (сек); 0 = готов.
     *
     * @throws Throwable при сбое MySQL (вызывающий обязан перехватить)
     */
    public function cooldownLeft(string $nick, Kit $kit): int
    {
        $last = $this->plugin->db()->getLastUsed($nick, $kit->id);
        if ($last === null) {
            return 0;
        }
        return max(0, $last + $kit->cooldown - time());
    }

    // ═════════════════ ТЕКСТОВОЕ МЕНЮ ═════════════════

    /**
     * Строки меню /kit (форм в PM3/1.1.5 нет — только текст).
     *
     * @return string[]
     */
    public function menuLines(Player $player): array
    {
        $nick     = $player->getName();
        $isAdmin  = $player->hasPermission('lc.kits.admin');
        $hasRanks = $this->ranksAvailable();
        $weight   = $hasRanks ? $this->playerWeight($nick) : 0;

        try {
            $lastUsed = $this->plugin->db()->getAllLastUsed($nick);
        } catch (Throwable $e) {
            $this->plugin->getLogger()->error('Меню китов ' . $nick . ': ' . $e->getMessage());
            $lastUsed = [];
        }

        $lines = [];
        $lines[] = Theme::header(Theme::TITLE_KITS);

        foreach ($this->kits as $kit) {
            $unlocked = $isAdmin || ($hasRanks && $weight >= $kit->weight);

            if (!$unlocked) {
                $lines[] = Theme::MUTED . ' ' . $kit->id . '. ' . Theme::FRAME . $kit->icon . ' ' . $kit->name
                    . ' §8» ' . Theme::ALERT . 'Заблокирован '
                    . Theme::FRAME . '(' . Theme::MUTED . 'нужен вес ' . Theme::YELLOW . $kit->weight . Theme::FRAME . ')';
                continue;
            }

            $left   = isset($lastUsed[$kit->id])
                ? max(0, $lastUsed[$kit->id] + $kit->cooldown - time())
                : 0;
            $status = $left > 0
                ? Theme::YELLOW . 'КД: ' . Theme::duration($left)
                : Theme::JADE . 'КД: ' . Theme::MUTED . 'готов';

            $lines[] = Theme::MUTED . ' ' . $kit->id . '. ' . $kit->coloredName()
                . ' §8» ' . Theme::JADE . 'Доступен '
                . Theme::FRAME . '| ' . $status;
        }

        $lines[] = Theme::LINE_THIN;
        $lines[] = Theme::YELLOW . 'Введи ' . Theme::GOLD . '/kit <номер>' . Theme::YELLOW . ', чтобы получить кит.';

        if ($isAdmin) {
            $lines[] = Theme::MUTED . 'Твой ранг: ' . Theme::ROYAL . 'обход прав (lc.kits.admin)';
        } elseif ($hasRanks) {
            $lines[] = Theme::MUTED . 'Твой ранг: ' . $this->playerRankLabel($nick)
                . Theme::FRAME . ' (weight: ' . Theme::YELLOW . $weight . Theme::FRAME . ')';
        } else {
            $lines[] = Theme::MUTED . 'Твой ранг: ' . Theme::ALERT . 'сервис рангов недоступен';
        }

        return $lines;
    }

    // ═════════════════ ВЫДАЧА КИТА ═════════════════

    /**
     * Выдать кит игроку. Все причины отказа сообщаются в чат.
     * Порядок: вес ранга → кулдаун → сборка предметов → место → выдача → кулдаун в БД.
     *
     * @return bool true — кит выдан
     */
    public function give(Player $player, Kit $kit): bool
    {
        $nick    = $player->getName();
        $isAdmin = $player->hasPermission('lc.kits.admin');

        // 1) Вес ранга (админ обходит)
        if (!$isAdmin) {
            if (!$this->ranksAvailable()) {
                $player->sendMessage(Theme::error('Сервис рангов недоступен — киты временно закрыты.'));
                return false;
            }

            $weight = $this->playerWeight($nick);
            if ($weight < $kit->weight) {
                $player->sendMessage(Theme::error(
                    'Кит ' . $kit->coloredName() . Theme::ALERT . ' закрыт для тебя.'
                ));
                $player->sendMessage(Theme::info(
                    'Нужен вес ранга ' . Theme::YELLOW . $kit->weight
                    . Theme::TEXT . ' или выше. Твой вес: ' . Theme::YELLOW . $weight
                ));
                return false;
            }
        }

        // 2) Кулдаун (админ обходит)
        if (!$isAdmin) {
            try {
                $left = $this->cooldownLeft($nick, $kit);
            } catch (Throwable $e) {
                $this->plugin->getLogger()->error('Кулдаун ' . $nick . ': ' . $e->getMessage());
                $player->sendMessage(Theme::error('Ошибка базы данных. Попробуйте позже.'));
                return false;
            }

            if ($left > 0) {
                $player->sendMessage(Theme::error(
                    'Кит ' . $kit->coloredName() . Theme::ALERT . ' ещё остывает.'
                ));
                $player->sendMessage(Theme::info('Осталось: ' . Theme::YELLOW . Theme::duration($left)));
                return false;
            }
        }

        // 3) Собрать предметы
        $items = $this->buildItems($kit);
        if ($items === []) {
            $this->plugin->getLogger()->warning('Кит ' . $kit->id . ' пуст — выдача отменена.');
            $player->sendMessage(Theme::error('Кит пуст. Сообщите администрации.'));
            return false;
        }

        // 4) Место в инвентаре (грубая проверка слотов)
        if (!$this->hasRoom($player, $items)) {
            $player->sendMessage(Theme::error('Недостаточно места в инвентаре.'));
            $player->sendMessage(Theme::info('Освободите место и повторите: ' . Theme::GOLD . '/kit ' . $kit->id));
            return false;
        }

        // 5) Выдача (остатки, если не влезли, — под ноги)
        $leftover = $player->getInventory()->addItem(...$items);
        foreach ($leftover as $rest) {
            $player->getLevel()->dropItem($player, $rest);
        }

        // 6) Кулдаун в БД (админу не нужен)
        if (!$isAdmin) {
            try {
                $this->plugin->db()->touch($nick, $kit->id);
            } catch (Throwable $e) {
                $this->plugin->getLogger()->error('Запись кулдауна ' . $nick . ': ' . $e->getMessage());
                $player->sendMessage(Theme::error('Кит выдан, но кулдаун не записан. Сообщите администрации.'));
            }
        }

        $player->sendMessage(Theme::success('Кит ' . $kit->coloredName() . Theme::JADE . ' получен!'));
        if (!$isAdmin) {
            $player->sendMessage(Theme::info('Следующий раз — через ' . Theme::YELLOW . Theme::duration($kit->cooldown)));
        }

        Theme::screen($player, Theme::GOLD . Theme::BOLD . 'КИТ ПОЛУЧЕН', $kit->coloredName());

        return true;
    }

    // ═════════════════ СБОРКА ПРЕДМЕТОВ ═════════════════

    /**
     * @return Item[]
     */
    private function buildItems(Kit $kit): array
    {
        $out = [];
        foreach ($kit->items as $spec) {
            if (!is_array($spec)) {
                continue;
            }
            $item = $this->buildItem($spec);
            if ($item !== null) {
                $out[] = $item;
            }
        }
        return $out;
    }

    /**
     * Собрать предмет из спецификации конфига:
     *   id, count, meta, name, ench: {ID: уровень}
     */
    private function buildItem(array $spec): ?Item
    {
        $id = (int) ($spec['id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        $count = max(1, (int) ($spec['count'] ?? 1));
        $meta  = max(0, (int) ($spec['meta'] ?? 0));

        $item = Item::get($id, $meta, $count);

        $name = trim((string) ($spec['name'] ?? ''));
        if ($name !== '') {
            $item->setCustomName($name);
        }

        $ench = $spec['ench'] ?? [];
        if (is_array($ench)) {
            foreach ($ench as $enchId => $level) {
                $item->addEnchantment($this->enchantment((int) $enchId, max(1, (int) $level)));
            }
        }

        return $item;
    }

    /**
     * Объект зачарования по ID Bedrock.
     * «Ледоход» (25) и «Починка» (26) в ядре LiteCore неизвестны —
     * создаём вручную, чтобы NBT писался с корректным id.
     */
    private function enchantment(int $id, int $level): Enchantment
    {
        $ench = Enchantment::getEnchantment($id);
        if ($ench->getId() === Enchantment::TYPE_INVALID) {
            $ench = new Enchantment(
                $id,
                'unknown',
                Enchantment::RARITY_RARE,
                Enchantment::ACTIVATION_EQUIP,
                Enchantment::SLOT_ALL
            );
        }
        $ench->setLevel($level);
        return $ench;
    }

    /**
     * Грубая проверка места: свободные слоты >= суммарного числа стаков.
     * Точную раскладку делает addItem; остатки дропаются под ноги.
     *
     * @param Item[] $items
     */
    private function hasRoom(Player $player, array $items): bool
    {
        $inv  = $player->getInventory();
        $free = 0;
        for ($i = 0; $i < $inv->getSize(); $i++) {
            if ($inv->getItem($i)->getId() === Item::AIR) {
                $free++;
            }
        }

        $need = 0;
        foreach ($items as $item) {
            $need += (int) ceil($item->getCount() / max(1, $item->getMaxStackSize()));
        }

        return $need <= $free;
    }
}
