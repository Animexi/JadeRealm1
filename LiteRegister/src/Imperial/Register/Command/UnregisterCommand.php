<?php

declare(strict_types=1);

namespace Imperial\Register\Command;

use Imperial\Register\Main;
use Imperial\Register\Theme;
use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use Throwable;

/**
 * /unregister <ник> — удаление аккаунта (право lc.register.admin).
 *
 * ⚠ LiteCore API 3.x: чистый CommandExecutor (НЕ extends Command).
 */
final class UnregisterCommand implements CommandExecutor
{
    public function __construct(private Main $plugin)
    {
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args)
    {
        if (!$sender->hasPermission('lc.register.admin')) {
            $sender->sendMessage(Theme::error('Недостаточно прав для указа Императора.'));
            return true;
        }

        if (count($args) < 1) {
            $sender->sendMessage(Theme::info('Использование: /unregister <ник>'));
            return true;
        }

        $nick = $args[0];

        try {
            $deleted = $this->plugin->am()->unregister($nick);
        } catch (Throwable) {
            $sender->sendMessage(Theme::error($this->plugin->am()->msg('err-db')));
            return true;
        }

        if (!$deleted) {
            $sender->sendMessage(
                Theme::error($this->plugin->am()->msg('err-not-registered', ['{PLAYER}' => Theme::player($nick)]))
            );
            return true;
        }

        $sender->sendMessage(
            Theme::success($this->plugin->am()->msg('ok-unregistered', ['{PLAYER}' => Theme::player($nick)]))
        );
        $this->plugin->getLogger()->info("Аккаунт {$nick} удалён администратором {$sender->getName()}");
        return true;
    }
}