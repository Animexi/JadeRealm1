<?php

declare(strict_types=1);

namespace Imperial\Register\Command;

use Imperial\Register\Main;
use Imperial\Register\Theme;
use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use Throwable;

/**
 * /changepassword <старый> <новый> — смена пароля.
 * Без аргументов — красивая форма. С аргументами — мгновенная смена.
 *
 * ⚠ LiteCore API 3.x: чистый CommandExecutor (НЕ extends Command),
 *   иначе конфликт абстрактного Command::execute().
 */
final class ChangePasswordCommand implements CommandExecutor
{
    public function __construct(private Main $plugin)
    {
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args)
    {
        // Право из plugin.yml (экзекьютор проверяет сам — надёжнее)
        if (!$sender->hasPermission('lc.register.changepassword')) {
            $sender->sendMessage(Theme::error('Недостаточно прав.'));
            return true;
        }

        if (!$sender instanceof Player) {
            $sender->sendMessage(Theme::error($this->plugin->am()->msg('err-console')));
            return true;
        }

        // Не авторизован — сначала войди!
        if (!$this->plugin->am()->isAuthenticated($sender)) {
            $sender->sendMessage(Theme::error($this->plugin->am()->msg('kick-not-auth')));
            return true;
        }

        // Без аргументов — инструкция по обряду
        if (count($args) < 2) {
            $sender->sendMessage(Theme::header(Theme::TITLE_CHANGE_PASS));
            $sender->sendMessage(Theme::MUTED . '➤ ' . $this->plugin->am()->msg(
                'cmd-changepassword-usage',
                ['{MIN}' => (string) $this->plugin->am()->minPasswordLength()]
            ));
            Theme::sound($sender, Theme::SND_FORM_OPEN, 1.2);
            return true;
        }

        [$old, $new] = [$args[0], $args[1]];

        $min = $this->plugin->am()->minPasswordLength();
        if (strlen($new) < $min) {
            $this->plugin->am()->tell(
                $sender,
                $this->plugin->am()->msg('err-short', ['{MIN}' => (string) $min]),
                false
            );
            Theme::sound($sender, Theme::SND_BAD_FORMAT, 0.7);
            return true;
        }

        try {
            $ok = $this->plugin->am()->changePassword($sender, $old, $new);
        } catch (Throwable) {
            $this->plugin->am()->tell($sender, $this->plugin->am()->msg('err-db'), false);
            return true;
        }

        if (!$ok) {
            $this->plugin->am()->tell($sender, $this->plugin->am()->msg('err-old-pass'), false);
            Theme::sound($sender, Theme::SND_WRONG_PASS, 0.8);
            return true;
        }

        $this->plugin->am()->tell($sender, $this->plugin->am()->msg('ok-changed'), true);
        return true;
    }
}