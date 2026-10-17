<?php

declare(strict_types=1);

namespace Imperial\Register;

use pocketmine\Player;

/**
 * ═══════════════════════════════════════════════════════════════
 *  FormManager — интерфейс «Небесной Империи» в чате
 * ───────────────────────────────────────────────────────────────
 *  ⚠ LiteCore НЕ содержит pocketmine\form\* и ModalForm-пакетов —
 *    весь UI строится сообщениями в чат
 *    по единой палитре Theme, ввод идёт командами:
 *      /register <пароль> <повтор>
 *      /login <пароль>
 * ═══════════════════════════════════════════════════════════════
 */
final class FormManager
{
    public function __construct(private Main $plugin)
    {
    }

    /**
     * «Форма регистрации» — красивая инструкция в чате.
     */
    public function showRegistration(Player $player): void
    {
        Theme::sound($player, Theme::SND_FORM_OPEN, 1.2);

        $am = $this->plugin->am();
        $player->sendMessage(Theme::header(Theme::TITLE_REGISTER));
        $player->sendMessage(Theme::info(
            $am->msg('hint-register', ['{PLAYER}' => Theme::player($player->getName())])
        ));
        $player->sendMessage(
            Theme::MUTED . '➤ ' . $am->msg('cmd-register-usage',
                ['{MIN}' => (string) $am->minPasswordLength()])
        );
        $this->sendHelp($player);
    }

    /**
     * «Форма входа» — инструкция в чате.
     */
    public function showLogin(Player $player): void
    {
        Theme::sound($player, Theme::SND_FORM_OPEN, 1.2);

        $am = $this->plugin->am();
        $player->sendMessage(Theme::header(Theme::TITLE_LOGIN));
        $player->sendMessage(Theme::info($am->msg('hint-login')));
        $player->sendMessage(Theme::MUTED . '➤ ' . $am->msg('cmd-login-usage'));
        $this->sendHelp($player);
    }

    /**
     * Панель помощи: что делать неавторизованному игроку.
     */
    public function sendHelp(Player $player): void
    {
        $panel = $this->plugin->am()->msg('help-panel', [
            '{MIN}'   => (string) $this->plugin->am()->minPasswordLength(),
            '{BLOCK}' => (string) $this->plugin->getConfig()->getNested('settings.block-seconds', 60),
        ]);
        foreach (explode("\n", $panel) as $line) {
            $player->sendMessage($line);
        }
    }

    /**
     * Напоминание при попытке писать в чат/команды до входа.
     */
    public function nag(Player $player, bool $registered): void
    {
        $am = $this->plugin->am();
        $player->sendMessage(Theme::error($am->msg('err-not-auth-chat')));
        $player->sendMessage(Theme::MUTED . '➤ ' . $am->msg(
            $registered ? 'cmd-login-usage' : 'cmd-register-usage',
            ['{MIN}' => (string) $am->minPasswordLength()]
        ));
    }
}
