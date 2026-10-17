<?php

declare(strict_types=1);

namespace Imperial\Kits;

use Imperial\Kits\Command\KitCommand;
use pocketmine\plugin\PluginBase;
use Throwable;

/**
 * ═══════════════════════════════════════════════════════════════
 *  Main — точка входа плагина «Небесная Империя» — LiteKits
 * ───────────────────────────────────────────────────────────────
 *  LiteCore v1.0.9 / PocketMine-MP API 3.0.0 / PHP 8.0
 *  Игра: Bedrock 1.1.5 (Discovery Update) — без зелий и элитр!
 *
 *  Порядок включения:
 *  1. Конфиг (resources/config.yml → data folder)
 *  2. MySQL (PDO, prepared statements)
 *  3. Автосоздание таблицы lc_kits_cooldowns
 *  4. Загрузка китов из config.yml (KitManager)
 *  5. Команда /kit
 *
 *  Интеграция:
 *  • softdepend LiteRanks — доступ к киту по weight ранга.
 *    Если LiteRanks не найден: WARNING в лог, киты только у
 *    lc.kits.admin (режим без рангов).
 *  • softdepend LiteRegister — только для порядка загрузки (стиль).
 * ═══════════════════════════════════════════════════════════════
 */
final class Main extends PluginBase
{
    private ?Database $db = null;
    private ?KitManager $km = null;

    // ═════════════════ ВКЛЮЧЕНИЕ ═════════════════

    public function onEnable(): void
    {
        $this->getLogger()->info(Theme::GOLD . '✦ Небесная Империя: пробуждение LiteKits…');

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

        // 3) Менеджер китов
        $this->km = new KitManager($this);
        $count = $this->km->reloadKits();
        $this->getLogger()->info(Theme::success('Загружено ' . $count . ' кит(ов) из config.yml'));

        // 4) Интеграция с рангами — предупреждение, если сервиса нет
        if ($this->km->ranksAvailable()) {
            $this->getLogger()->info(Theme::success('LiteRanks найден — доступ к китам по весу ранга'));
        } else {
            $this->getLogger()->warning('LiteRanks НЕ найден — киты доступны только с правом lc.kits.admin');
        }

        // 5) Команда
        $this->getCommand('kit')?->setExecutor(new KitCommand($this));

        $this->getLogger()->info(
            Theme::success('LiteKits v' . $this->getDescription()->getVersion() . ' активирован')
        );
    }

    // ═════════════════ ВЫКЛЮЧЕНИЕ ═════════════════

    public function onDisable(): void
    {
        Database::close();
        $this->getLogger()->info(Theme::info('LiteKits усыплён. До встречи в Поднебесной.'));
    }

    // ═════════════════ ДОСТУП К МЕНЕДЖЕРАМ ═════════════════

    public function db(): Database
    {
        return $this->db ?? throw new \RuntimeException('Database не инициализирован');
    }

    public function km(): KitManager
    {
        return $this->km ?? throw new \RuntimeException('KitManager не инициализирован');
    }
}
