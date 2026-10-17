<?php

declare(strict_types=1);

namespace Imperial\Register;

use Imperial\Register\Command\ChangePasswordCommand;
use Imperial\Register\Command\LoginCommand;
use Imperial\Register\Command\ReloadCommand;
use Imperial\Register\Command\RegisterCommand;
use Imperial\Register\Command\UnregisterCommand;
use pocketmine\plugin\PluginBase;
use Throwable;

/**
 * ═══════════════════════════════════════════════════════════════
 *  Main — точка входа плагина «Небесная Империя»
 * ───────────────────────────────────────────────────────────────
 *  LiteCore v1.0.9 / PocketMine-MP API 3.0.0 / PHP 8.0
 *
 *  Порядок включения:
 *  1. Конфиг (resources/config.yml → data folder)
 *  2. MySQL (PDO, prepared statements)
 *  3. Автосоздание таблиц lc_users / lc_meta
 *  4. Менеджеры: AuthManager, FormManager
 *  5. Команды и слушатели событий
 *
 *  Архитектура:
 *  • Theme         — ЕДИНЫЙ стиль (цвета/звуки/рамки), переиспользуется
 *  • Database      — PDO-обёртка (синглтон)
 *  • AuthManager   — сессии, валидация, БД-операции, журнал
 *  • FormManager   — все UI-формы
 *  • PlayerListener— блокировки до авторизации
 * ═══════════════════════════════════════════════════════════════
 */
final class Main extends PluginBase
{
    private ?Database $db = null;
    private ?AuthManager $am = null;
    private ?FormManager $fm = null;

    // ═════════════════ ВКЛЮЧЕНИЕ ═════════════════

    public function onEnable(): void
    {
        $this->getLogger()->info(Theme::GOLD . '✦ Небесная Империя: пробуждение LiteRegister…');

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
        $this->am = new AuthManager($this);
        $this->fm = new FormManager($this);

        // 4) Слушатели
        $this->getServer()->getPluginManager()->registerEvents(new PlayerListener($this), $this);

        // 5) Команды (экзекьюторы вешаем на команды из plugin.yml)
        $this->getCommand('register')?->setExecutor(new RegisterCommand($this));
        $this->getCommand('login')?->setExecutor(new LoginCommand($this));
        $this->getCommand('changepassword')?->setExecutor(new ChangePasswordCommand($this));
        $this->getCommand('unregister')?->setExecutor(new UnregisterCommand($this));
        $this->getCommand('lcreload')?->setExecutor(new ReloadCommand($this));

        $this->getLogger()->info(
            Theme::success('LiteRegister v' . $this->getDescription()->getVersion() . ' активирован')
        );
    }

    // ═════════════════ ВЫКЛЮЧЕНИЕ ═════════════════

    public function onDisable(): void
    {
        Database::close();
        $this->getLogger()->info(Theme::info('LiteRegister усыплён. До встречи в Поднебесной.'));
    }

    // ═════════════════ ДОСТУП К МЕНЕДЖЕРАМ ═════════════════

    public function db(): Database
    {
        return $this->db ?? throw new \RuntimeException('Database не инициализирован');
    }

    public function am(): AuthManager
    {
        return $this->am ?? throw new \RuntimeException('AuthManager не инициализирован');
    }

    public function fm(): FormManager
    {
        return $this->fm ?? throw new \RuntimeException('FormManager не инициализирован');
    }
}