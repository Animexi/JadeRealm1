<?php

declare(strict_types=1);

namespace Imperial\Kits\Command;

use Imperial\Kits\Main;
use Imperial\Kits\Theme;
use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use Throwable;

/**
 * ═══════════════════════════════════════════════════════════════
 *  /kit — текстовое меню и выдача китов
 * ───────────────────────────────────────────────────────────────
 *  /kit          — список всех китов: номер, название, статус, КД
 *  /kit <номер>  — получить кит (если вес ранга >= номера и КД готов)
 *  /kit help     — краткая справка
 *
 *  ⚠ LiteCore: форм НЕТ ВОВСЕ — только текст в чате.
 *  ⚠ LiteCore: чистый CommandExecutor (НЕ extends Command),
 *    4 параметра, БЕЗ type hints на $label, return ВСЕГДА true.
 * ═══════════════════════════════════════════════════════════════
 */
final class KitCommand implements CommandExecutor
{
    public function __construct(private Main $plugin)
    {
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args)
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(Theme::error('Команда доступна только игрокам!'));
            return true;
        }

        if (!$sender->hasPermission('lc.kits.use')) {
            $sender->sendMessage(Theme::error('Недостаточно прав для этой команды.'));
            return true;
        }

        $km = $this->plugin->km();

        try {
            // /kit help — краткая справка
            if (isset($args[0]) && strtolower($args[0]) === 'help') {
                $this->sendHelp($sender);
                return true;
            }

            // /kit <номер> — выдача
            if (isset($args[0]) && ctype_digit($args[0])) {
                $id  = (int) $args[0];
                $kit = $km->getKit($id);

                if ($kit === null) {
                    $sender->sendMessage(Theme::error('Кита под номером ' . $id . ' не существует.'));
                    $this->sendMenu($sender);
                    return true;
                }

                $km->give($sender, $kit);
                return true;
            }

            // /kit — меню
            $this->sendMenu($sender);
        } catch (Throwable $e) {
            $this->plugin->getLogger()->error('kit: ' . $e->getMessage());
            $sender->sendMessage(Theme::error('Ошибка базы данных. Обратитесь к администрации.'));
        }

        return true;
    }

    // ═════════════════ МЕНЮ / СПРАВКА ═════════════════

    private function sendMenu(Player $player): void
    {
        foreach ($this->plugin->km()->menuLines($player) as $line) {
            $player->sendMessage($line);
        }
    }

    private function sendHelp(Player $player): void
    {
        $player->sendMessage(Theme::header('КИТЫ — СПРАВКА'));
        $player->sendMessage(Theme::info(Theme::GOLD . '/kit' . Theme::TEXT . ' — список всех китов и их статус'));
        $player->sendMessage(Theme::info(Theme::GOLD . '/kit <номер>' . Theme::TEXT . ' — получить кит по номеру'));
        $player->sendMessage(Theme::LINE_THIN);
        $player->sendMessage(Theme::MUTED . 'Кит открывается по весу твоего ранга:');
        $player->sendMessage(Theme::MUTED . '  кит 3 доступен рангу с весом 3 и выше.');
        $player->sendMessage(Theme::MUTED . 'У каждого кита свой кулдаун — 5 часов.');
        $player->sendMessage(Theme::LINE_THIN);
        $player->sendMessage(Theme::FORM_HINT . 'Ранг повышается у администрации Империи.');
    }
}
