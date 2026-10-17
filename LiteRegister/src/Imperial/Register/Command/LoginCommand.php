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
 * /login <пароль> — вход в существующий аккаунт.
 * 3 неверные попытки подряд → блокировка (брут-защита).
 *
 * ⚠ LiteCore: формы в ядре нет — вход идёт через чат-команду.
 */
final class LoginCommand implements CommandExecutor
{
    public function __construct(private Main $plugin)
    {
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args)
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(Theme::error($this->plugin->am()->msg('err-console')));
            return true;
        }

        $am   = $this->plugin->am();
        $nick = $sender->getName();

        // Уже вошёл
        if ($am->isAuthenticated($sender)) {
            $am->tell($sender, $am->msg('err-already-auth'), false);
            return true;
        }

        // Блокировка за брутфорс активна (переживает реконнект)
        $left = $am->blockedSecondsLeft($nick);
        if ($left > 0) {
            $am->tell(
                $sender,
                str_replace('{SECONDS}', (string) $left, $am->msg('kick-blocked')),
                false
            );
            Theme::sound($sender, Theme::SND_WRONG_PASS, 0.8);
            return true;
        }

        // Мало аргументов — показать «форму»-инструкцию
        if (count($args) < 1) {
            $this->plugin->fm()->showLogin($sender);
            return true;
        }

        // Аккаунта нет — подсказать регистрацию
        try {
            if (!$this->plugin->db()->userExists($nick)) {
                $am->tell($sender, $am->msg('err-not-registered', ['{PLAYER}' => Theme::player($nick)]), false);
                $sender->sendMessage(Theme::MUTED . '➤ ' . $am->msg(
                    'cmd-register-usage',
                    ['{MIN}' => (string) $am->minPasswordLength()]
                ));
                Theme::sound($sender, Theme::SND_BAD_FORMAT, 0.7);
                return true;
            }
        } catch (Throwable) {
            $am->tell($sender, $am->msg('err-db'), false);
            return true;
        }

        $pass = $args[0];

        try {
            $ok = $am->tryLogin($sender, $pass);
        } catch (Throwable) {
            $am->tell($sender, $am->msg('err-db'), false);
            $sender->kick($am->msg('kick-db-error'), false);
            return true;
        }

        if ($ok) {
            $am->tell($sender, $am->msg('ok-login', ['{PLAYER}' => Theme::player($nick)]), true);
            Theme::screen(
                $sender,
                $am->msg('title-back'),
                $am->msg('title-sub', ['{PLAYER}' => Theme::player($nick)])
            );
            Theme::sound($sender, Theme::SND_LOGIN_OK, 1.5);
            return true;
        }

        // Неверный пароль
        Theme::sound($sender, Theme::SND_WRONG_PASS, 0.8);
        $left = $am->attemptsLeft($nick);
        if ($left <= 0) {
            $sender->kick(
                str_replace(
                    '{SECONDS}',
                    (string) $am->blockedSecondsLeft($nick),
                    $am->msg('kick-blocked')
                ),
                false
            );
            return true;
        }

        $am->tell($sender, $am->msg('err-wrong-pass', ['{LEFT}' => (string) $left]), false);
        return true;
    }
}
