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
 * /register <пароль> <повтор> — регистрация аккаунта.
 *
 * ⚠ LiteCore: формы в ядре нет — регистрация идёт через чат-команду.
 */
final class RegisterCommand implements CommandExecutor
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

        $am = $this->plugin->am();

        // Уже вошёл — регистрация не нужна
        if ($am->isAuthenticated($sender)) {
            $am->tell($sender, $this->plugin->am()->msg('err-already-auth'), false);
            return true;
        }

        // Мало аргументов — показать «форму»-инструкцию
        if (count($args) < 2) {
            $this->plugin->fm()->showRegistration($sender);
            return true;
        }

        // Ник уже в Империи — подсказать вход
        try {
            if ($this->plugin->db()->userExists($sender->getName())) {
                $am->tell($sender, $this->plugin->am()->msg('err-registered-use-login'), false);
                Theme::sound($sender, Theme::SND_NICK_TAKEN, 1.2);
                return true;
            }
        } catch (Throwable) {
            $am->tell($sender, $this->plugin->am()->msg('err-db'), false);
            return true;
        }

        $pass  = $args[0];
        $pass2 = $args[1];

        $err = $am->validateRegistration($pass, $pass2);
        if ($err !== null) {
            [$code, $repl] = $err;
            $am->tell($sender, $am->msg($code, $repl), false);
            Theme::sound(
                $sender,
                $code === 'err-mismatch' ? Theme::SND_BAD_FORMAT : Theme::SND_EMPTY_FIELD,
                $code === 'err-mismatch' ? 0.7 : 0.5
            );
            return true;
        }

        try {
            $ok = $am->register($sender, $pass);
        } catch (Throwable) {
            $am->tell($sender, $this->plugin->am()->msg('err-db'), false);
            $sender->kick($this->plugin->am()->msg('kick-db-error'), false);
            return true;
        }

        if (!$ok) {
            $am->tell(
                $sender,
                $am->msg('err-taken', ['{PLAYER}' => Theme::player($sender->getName())]),
                false
            );
            Theme::sound($sender, Theme::SND_NICK_TAKEN, 1.2);
            return true;
        }

        // Успех
        $am->tell(
            $sender,
            $am->msg('ok-register', ['{PLAYER}' => Theme::player($sender->getName())]),
            true
        );
        Theme::screen(
            $sender,
            $am->msg('title-welcome'),
            $am->msg('title-sub', ['{PLAYER}' => Theme::player($sender->getName())])
        );
        Theme::sound($sender, Theme::SND_REGISTER_OK, 1.0);
        return true;
    }
}
