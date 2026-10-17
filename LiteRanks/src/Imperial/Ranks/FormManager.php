<?php

declare(strict_types=1);

namespace Imperial\Ranks;

use pocketmine\Player;
use Throwable;

/**
 * ═══════════════════════════════════════════════════════════════
 *  FormManager — текстовые меню плагина (без GUI, только чат)
 * ───────────────────────────────────────────────────────────────
 *  /prefix          — выбор печати (цифрой)
 *  /prefix reset    — сброс на «как настоящий ранг»
 *  /myrank          — показать текущий ранг + печать
 *
 *  ⚠ LiteCore: форм НЕТ ВОВСЕ — делаем текстовые списки с
 *    выбором цифрой из чата.
 * ═══════════════════════════════════════════════════════════════
 */
final class FormManager
{
    public function __construct(private Main $plugin)
    {
    }

    // ═════════════════ /PREFIX ═════════════════

    /**
     * Показать список доступных печатей (все с weight <= текущего).
     */
    public function showPrefixList(Player $player): void
    {
        $rm = $this->plugin->rm();
        $current = $rm->getRankOf($player->getName());
        $available = array_values(array_filter($rm->allSorted(), static function (Rank $r) use ($current): bool {
            return $r->weight <= $current->weight;
        }));

        if (empty($available)) {
            $player->sendMessage(Theme::error('Нет доступных печатей.'));
            return;
        }

        $player->sendMessage(Theme::header(Theme::TITLE_PREFIX));

        $displayId = $rm->getDisplayRank($player->getName())->id;
        foreach ($available as $i => $rank) {
            $selected = $displayId === $rank->id ? Theme::JADE . '✔ ' : '  ';
            $player->sendMessage(
                Theme::MUTED . '  ' . ($i + 1) . '. ' . $selected
                . $rank->coloredPrefix()
                . ' ' . Theme::FRAME . Theme::ICON_ARROW . ' '
                . $rank->coloredDisplay()
            );
        }

        $player->sendMessage(Theme::LINE_THIN);
        $player->sendMessage(Theme::FORM_HINT . 'Напишите: /prefix <номер>');
        $player->sendMessage(Theme::FORM_HINT . 'Или: /prefix reset — сбросить на ранг по умолчанию');
    }

    /**
     * Выбрать печать по номеру из списка.
     *
     * @return true если успешно, false если номер неверен
     * @throws Throwable при сбое БД
     */
    public function selectPrefix(Player $player, int $index): bool
    {
        $rm = $this->plugin->rm();
        $current = $rm->getRankOf($player->getName());
        $available = array_values(array_filter($rm->allSorted(), static function (Rank $r) use ($current): bool {
            return $r->weight <= $current->weight;
        }));

        if ($index < 1 || $index > count($available)) {
            $player->sendMessage(Theme::error('Неверный номер. Доступно: ' . count($available)));
            return false;
        }

        $chosen = $available[$index - 1];
        $ok = $rm->setDisplayRank($player->getName(), $chosen->id);

        if (!$ok) {
            $player->sendMessage(Theme::error('Невозможно выбрать эту печать.'));
            return false;
        }

        $player->sendMessage(Theme::success('Печать изменена на: ' . $chosen->coloredPrefix()));
        $rm->applyNametag($player);

        Theme::screen($player, Theme::TITLE_PREFIX, $chosen->coloredPrefix());

        return true;
    }

    /**
     * Сброс печати на «как настоящий ранг».
     *
     * @throws Throwable при сбое БД
     */
    public function resetPrefix(Player $player): void
    {
        $rm = $this->plugin->rm();
        $ok = $rm->setDisplayRank($player->getName(), null);

        if (!$ok) {
            $player->sendMessage(Theme::error('Не удалось сбросить печать.'));
            return;
        }

        $player->sendMessage(Theme::success('Печать сброшена на текущий ранг.'));
        $rm->applyNametag($player);
    }

    // ═════════════════ /MYRANK ═════════════════

    /**
     * Показать текущий ранг, печать, время выдачи.
     */
    public function showMyRank(Player $player): void
    {
        $rm = $this->plugin->rm();
        $st = $rm->state($player->getName());
        $rank = $rm->getRankOf($player->getName());
        $display = $rm->getDisplayRank($player->getName());

        $player->sendMessage(Theme::header(Theme::TITLE_MYRANK));

        $player->sendMessage(Theme::info('Настоящий ранг: ' . $rank->coloredPrefix() . ' ' . Theme::FRAME . Theme::ICON_ARROW . ' ' . $rank->coloredDisplay()));
        $player->sendMessage(Theme::info('Печать: ' . Theme::bracket($display->coloredPrefix()) . '§r ' . Theme::FRAME . Theme::ICON_ARROW . ' ' . $display->coloredDisplay()));

        if ($st->grantedBy !== null) {
            $player->sendMessage(Theme::info('Кто выдал: ' . $st->grantedBy));
        }

        if ($st->grantedAt !== null) {
            $player->sendMessage(Theme::info('Выдан: ' . $st->grantedAt));
        }

        if ($st->expiresAt !== null) {
            $player->sendMessage(Theme::info('Истекает: ' . $st->expiresAt));
        }
    }
}
