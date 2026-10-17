<?php

declare(strict_types=1);

namespace Imperial\Register\Command;

use Imperial\Register\Main;
use Imperial\Register\Theme;
use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;

/**
 * /lcreload — перезагрузка config.yml без рестарта сервера.
 * (право lc.register.admin)
 *
 * ⚠ LiteCore API 3.x: чистый CommandExecutor (НЕ extends Command).
 */
final class ReloadCommand implements CommandExecutor
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

        $this->plugin->reloadConfig();
        $sender->sendMessage(Theme::success($this->plugin->am()->msg('ok-reloaded')));
        $this->plugin->getLogger()->info("Конфиг перезагружен: {$sender->getName()}");
        return true;
    }
}