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
 *  /prefix [номер|reset] — выбор отображаемой печати (префикса)
 * ───────────────────────────────────────────────────────────────
 *  Можно поставить печать ЛЮБОГО ранга с весом не выше текущего:
 *  Император вправе ходить «Странником», Странник — нет.
 *
 *  ⚠ LiteCore: чистый CommandExecutor (НЕ extends Command),
 *    4 параметра, БЕЗ type hints на $label, return ВСЕГДА true.
 * ═══════════════════════════════════════════════════════════════
 */
final class PrefixCommand implements CommandExecutor
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

        $rm = $this->plugin->rm();
        $fm = $this->plugin->fm();

        try {
            // /prefix reset — вернуть печать текущего ранга
            if (isset($args[0]) && strtolower($args[0]) === 'reset') {
                $fm->resetPrefix($sender);
                return true;
            }

            // /prefix <номер>
            if (isset($args[0]) && ctype_digit($args[0])) {
                if (!$fm->selectPrefix($sender, (int) $args[0])) {
                    $fm->showPrefixList($sender);
                }
                return true;
            }

            // /prefix — список печатей
            $fm->showPrefixList($sender);
        } catch (Throwable $e) {
            $this->plugin->getLogger()->error('prefix: ' . $e->getMessage());
            $sender->sendMessage(Theme::error($rm->msg('err-db')));
        }

        return true;
    }
}