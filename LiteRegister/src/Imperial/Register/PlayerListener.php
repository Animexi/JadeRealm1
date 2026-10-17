<?php

declare(strict_types=1);

namespace Imperial\Register;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerPickupExpOrbEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\Player;
use pocketmine\scheduler\CallbackTask;
use Throwable;

/**
 * ═══════════════════════════════════════════════════════════════
 *  PlayerListener — вход/выход и блокировки до авторизации
 * ───────────────────────────────────────────────────────────────
 *  • Вход: проверка блокировки за брутфорс → есть ник в БД?
 *    да → форма входа (чат-подсказка), нет → форма регистрации
 *  • До авторизации: запрет чата/команд/блоков/инвентаря/движения
 *  • Выход: сброс сессии (блокировка за брутфорс сохраняется)
 * ═══════════════════════════════════════════════════════════════
 */
final class PlayerListener implements Listener
{
    public function __construct(private Main $plugin)
    {
    }

    // ═════════════════ ВХОД ═════════════════

    public function onJoin(PlayerJoinEvent $e): void
    {
        $player = $e->getPlayer();
        $nick   = $player->getName();

        // 1) Блокировка за брутфорс — выгоняем на следующем тике (кик в join нестабилен)
        $left = $this->plugin->am()->blockedSecondsLeft($nick);
        if ($left > 0) {
            $e->setJoinMessage('');
            $msg = str_replace('{SECONDS}', (string) $left, $this->plugin->am()->msg('kick-blocked'));
            $player->getServer()->getScheduler()->scheduleDelayedTask(
                new CallbackTask(
                    static function () use ($player, $msg): void {
                        if ($player->isConnected()) {
                            $player->kick($msg, false);
                        }
                    }
                ),
                1
            );
            return;
        }

        // 2) Игрок уже авторизован (crash-restore) — не трогаем
        if ($this->plugin->am()->isAuthenticated($player)) {
            return;
        }

        // 2.5) Автовход по совпадению IP (опция конфига)
        $am = $this->plugin->am();
        try {
            if ($am->tryAutoLogin($player)) {
                $e->setJoinMessage('');
                $player->sendMessage(Theme::success(
                    $am->msg('ok-autologin', ['{PLAYER}' => Theme::player($nick)])
                ));
                Theme::screen(
                    $player,
                    $am->msg('title-back'),
                    $am->msg('title-sub', ['{PLAYER}' => Theme::player($nick)])
                );
                Theme::sound($player, Theme::SND_LOGIN_OK, 1.5);
                return;
            }
        } catch (Throwable $err) {
            $this->plugin->getLogger()->error("Автовход {$nick}: {$err->getMessage()}");
            $player->kick($am->msg('kick-db-error'), false);
            return;
        }

        // 3) Блокируем до авторизации
        $e->setJoinMessage('');

        // 4) Есть ли аккаунт в БД? (кэш на сессию) → вход, иначе — регистрация.
        try {
            $registered = $am->isRegistered($nick);
        } catch (Throwable $err) {
            $this->plugin->getLogger()->error("Проверка {$nick} в БД: {$err->getMessage()}");
            $player->kick($am->msg('kick-db-error'), false);
            return;
        }

        // Понятная инструкция + панель помощи
        $player->sendMessage(Theme::header($registered ? Theme::TITLE_LOGIN : Theme::TITLE_REGISTER));
        $player->sendMessage(Theme::info(
            $registered
                ? $am->msg('hint-login')
                : $am->msg('hint-register', ['{PLAYER}' => Theme::player($nick)])
        ));
        $player->sendMessage(Theme::MUTED . '➤ ' . $am->msg(
            $registered ? 'cmd-login-usage' : 'cmd-register-usage',
            ['{MIN}' => (string) $am->minPasswordLength()]
        ));
        $this->plugin->fm()->sendHelp($player);
    }

    // ═════════════════ ВЫХОД ═════════════════

    public function onQuit(PlayerQuitEvent $e): void
    {
        $e->setQuitMessage('');
        $this->plugin->am()->forget($e->getPlayer()->getName());
    }

    // ═════════════════ БЛОКИРОВКИ ДО АВТОРИЗАЦИИ ═════════════════

    /**
     * Универсальная отмена события через setCancelled (как в LiteCore).
     */
    private function silence(object $e): bool
    {
        if (method_exists($e, 'setCancelled')) {
            $e->setCancelled(true);
            return true;
        }
        return false;
    }

    /** Чат: отмена + одна подсказка (nag) без дубля сообщения */
    public function onChat(PlayerChatEvent $e): void
    {
        $player = $e->getPlayer();
        if (!$this->isBlocked($player)) {
            return;
        }
        $this->silence($e);
        $this->plugin->fm()->nag($player, $this->plugin->am()->isRegistered($player->getName()));
    }

    /** Команды: отмена + игрок видит подсказку */
    public function onCommand(PlayerCommandPreprocessEvent $e): void
    {
        $player = $e->getPlayer();
        if (!$this->isBlocked($player)) {
            return;
        }

        // Белый список: /register и /login — всегда (это способ входа),
        // плюс команды из конфига
        $cmd = strtolower(explode(' ', trim($e->getMessage(), '/'))[0]);
        if (in_array($cmd, ['register', 'reg', 'zareg', 'login', 'l', 'vho'], true)) {
            return;
        }
        $allowed = array_map('strtolower', (array) $this->plugin->getConfig()->getNested('settings.allowed-commands', []));
        if (in_array($cmd, $allowed, true)) {
            return;
        }

        if (!$this->silence($e)) {
            // fallback: подменяем на несуществующую
            if (method_exists($e, 'setMessage')) {
                $e->setMessage('/lc-noauth');
            }
        }
        $this->plugin->fm()->nag($player, $this->plugin->am()->isRegistered($player->getName()));
    }

    /** Взаимодействие с блоками */
    public function onInteract(PlayerInteractEvent $e): void
    {
        if ($this->isBlocked($e->getPlayer())) {
            $this->silence($e);
        }
    }

    /** Смена слота */
    public function onItemHeld(PlayerItemHeldEvent $e): void
    {
        if ($this->isBlocked($e->getPlayer())) {
            $this->silence($e);
        }
    }

    /** Дроп предмета */
    public function onDropItem(PlayerDropItemEvent $e): void
    {
        if ($this->isBlocked($e->getPlayer())) {
            $this->silence($e);
        }
    }

    /** Подбор опыта */
    public function onPickupExpOrb(PlayerPickupExpOrbEvent $e): void
    {
        if ($this->isBlocked($e->getPlayer())) {
            $this->silence($e);
        }
    }

    /** Блокировка движения (setImmobile в LiteCore нет — отменяем событие) */
    public function onMove(PlayerMoveEvent $e): void
    {
        if (!$this->isBlocked($e->getPlayer())) {
            return;
        }
        // Блокируем любое движение
        $this->silence($e);
    }

    // ═════════════════ ХЕЛПЕР ═════════════════

    /** Игрок не авторизован (заблокирован)? */
    private function isBlocked(Player $player): bool
    {
        return !$this->plugin->am()->isAuthenticated($player);
    }
}
