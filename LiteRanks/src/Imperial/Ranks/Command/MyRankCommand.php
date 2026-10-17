<?php

declare(strict_types=1);

namespace Imperial\Ranks\Command;

use Imperial\Ranks\Main;
use Imperial\Ranks\Theme;
use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use Throwable;

/**
 * ═══════════════════════════════════════════════════════════════
 *  /myrank — свой ранг, печать, кто выдал и срок действия
 * ───────────────────────────────────────────────────────────────
 *  ⚠ LiteCore: чистый CommandExecutor, return ВСЕГДА true.
 * ═══════════════════════════════════════════════════════════════
 */
final class MyRankCommand implements CommandExecutor
{
    public function __construct(private Main $plugin)
    {
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args)
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(Theme::error($this->plugin->rm()->msg('err-console')));
            return true;
        }

        try {
            $this->plugin->fm()->showMyRank($sender);
        } catch (Throwable $e) {
            $this->plugin->getLogger()->error('myrank: ' . $e->getMessage());
            $sender->sendMessage(Theme::error($this->plugin->rm()->msg('err-db')));
        }

        return true;
    }
}