<?php

declare(strict_types=1);

namespace Imperial\Ranks;

use Imperial\Ranks\Command\MyRankCommand;
use Imperial\Ranks\Command\PrefixCommand;
use Imperial\Ranks\Command\RankAdminCommand;
use pocketmine\plugin\PluginBase;
use Throwable;

/**
 * ═══════════════════════════════════════════════════════════════
 *  Main — точка входа плагина «Небесная Империя» — LiteRanks
 * ───────────────────────────────────────────────────────────────
 *  LiteCore v1.0.9 / PocketMine-MP API 3.0.0-3.0.1 / PHP 8.0
 *
 *  Порядок включения:
 *  1. Конфиг (resources/config.yml → data folder)
 *  2. MySQL (PDO, prepared statements)
 *  3. Автосоздание таблицы lc_player_ranks
 *  4. Загрузка рангов из config.yml (RankManager)
 *  5. Менеджеры: FormManager
 *  6. Команды и слушатели событий
 *
 *  Архитектура:
 *  • Theme         — ЕДИНЫЙ стиль (цвета/рамки/заголовки)
 *  • Database      — PDO-обёртка (синглтон)
 *  • RankManager   — ранги из конфига + кэш состояний игроков
 *  • FormManager   — текстовые меню (prefix, myrank)
 *  • ChatListener  — формат чата «§8[Печать§8] §fНик §8» §7текст»
 *  • NameTagListener — nameTag при join/respawn
 *
 *  Ранги живут в config.yml, в БД — только привязка игрока.
 *  softdepend на LiteRegister (плагин работает и без него).
 * ═══════════════════════════════════════════════════════════════
 */
final class Main extends PluginBase
{
    private ?Database $db = null;
    private ?RankManager $rm = null;
    private ?FormManager $fm = null;

    // ═════════════════ ВКЛЮЧЕНИЕ ═════════════════

    public function onEnable(): void
    {
        $this->getLogger()->info(Theme::GOLD . '✦ Небесная Империя: пробуждение LiteRanks…');

        // 1) Конфиг
        $this->saveDefaultConfig();

        // 2) База данных
        $dbConf = (array) $this->getConfig()->get('database', []);
        $prefix = (string) $this->getConfig()->getNested('settings.table-prefix', 'lc_');
        try {
            $this->db = Database::init(
                (string) ($dbConf['host'] ?? 'localhost'),
                (int) ($dbConf['port'] ?? 3306),
                (string) ($dbConf['user'] ?? 'root'),
                (string) ($dbConf['password'] ?? ''),
                (string) ($dbConf['name'] ?? 'gs148051'),
                $prefix
            );
            $this->db->createTables();
            $this->getLogger()->info(Theme::success('База данных готова (префикс: ' . $prefix . ')'));
        } catch (Throwable $e) {
            $this->getLogger()->critical('MySQL недоступен: ' . $e->getMessage());
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        // 3) Менеджеры
        $this->rm = new RankManager($this);
        $count = $this->rm->reloadRanks();
        $this->getLogger()->info(Theme::success('Загружено ' . $count . ' ранг(ов) из config.yml'));
        $this->fm = new FormManager($this);

        // 4) Слушатели
        $this->getServer()->getPluginManager()->registerEvents(new ChatListener($this), $this);
        $this->getServer()->getPluginManager()->registerEvents(new NameTagListener($this), $this);

        // 5) Команды
        $this->getCommand('prefix')?->setExecutor(new PrefixCommand($this));
        $this->getCommand('myrank')?->setExecutor(new MyRankCommand($this));
        $this->getCommand('rank')?->setExecutor(new RankAdminCommand($this));

        $this->getLogger()->info(
            Theme::success('LiteRanks v' . $this->getDescription()->getVersion() . ' активирован')
        );
    }

    // ═════════════════ ВЫКЛЮЧЕНИЕ ═════════════════

    public function onDisable(): void
    {
        Database::close();
        $this->getLogger()->info(Theme::info('LiteRanks усыплён. До встречи в Поднебесной.'));
    }

    // ═════════════════ ДОСТУП К МЕНЕДЖЕРАМ ═════════════════

    public function db(): Database
    {
        return $this->db ?? throw new \RuntimeException('Database не инициализирован');
    }

    public function rm(): RankManager
    {
        return $this->rm ?? throw new \RuntimeException('RankManager не инициализирован');
    }

    public function fm(): FormManager
    {
        return $this->fm ?? throw new \RuntimeException('FormManager не инициализирован');
    }

    /**
     * Обёртка для сообщений из config.yml (как в LiteRegister).
     */
    public function am(): RankManager
    {
        return $this->rm;
    }
}
