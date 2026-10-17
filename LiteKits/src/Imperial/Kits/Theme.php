<?php

declare(strict_types=1);

namespace Imperial\Kits;

use pocketmine\Player;

/**
 * ═══════════════════════════════════════════════════════════════
 *  Theme — ЕДИНЫЙ СТИЛЬ «НЕБЕСНОЙ ИМПЕРИИ»
 * ───────────────────────────────────────────────────────────────
 *  Синхронизирован с LiteRegister / LiteRanks 1-в-1.
 *  Ядро визуального стиля ВСЕХ плагинов Империи.
 *  Никаких магических строк в других классах — только Theme::*.
 *
 *  ⚠ В LiteKits ЗВУКОВ НЕТ — всё общение только через чат.
 *  ⚠ PHP 8.0: только class + const, БЕЗ enum!
 * ═══════════════════════════════════════════════════════════════
 */
final class Theme
{
    // ═════════════════ ЦВЕТА ═════════════════

    public const GOLD        = '§6';
    public const YELLOW      = '§e';
    public const JADE        = '§a';
    public const JADE_DARK   = '§2';
    public const SKY         = '§b';
    public const SKY_DARK    = '§3';
    public const ROYAL       = '§d';
    public const ROYAL_DARK  = '§5';
    public const ALERT       = '§c';
    public const ALERT_DARK  = '§4';
    public const TEXT        = '§f';
    public const MUTED       = '§7';
    public const FRAME       = '§8';

    // ═════════════════ СТИЛИ ═════════════════

    public const BOLD      = '§l';
    public const ITALIC    = '§o';
    public const RESET_FMT = '§r';

    // ═════════════════ ЗНАКИ ═════════════════

    public const ICON_CREST   = '⚜';
    public const ICON_X       = '✖';
    public const ICON_STAR    = '✦';
    public const ICON_LOTUS   = '❈';
    public const ICON_ARROW   = '»';
    public const ICON_CHECK   = '✔';
    public const BRACKET_L    = '[';
    public const BRACKET_R    = ']';

    // ═════════════════ РАМКИ / ШАБЛОНЫ ═════════════════

    public const LINE       = '§8▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬';
    public const LINE_THIN  = '§8───────';
    public const HEADER     = self::LINE . "\n" . self::GOLD . self::ICON_STAR . ' ' . self::YELLOW . self::BOLD . '{TITLE}' . ' ' . self::GOLD . self::ICON_STAR . "\n" . self::LINE;
    public const FORM_HINT  = self::MUTED . self::ITALIC;

    // ═════════════════ ГОТОВЫЕ ЗАГОЛОВКИ ═════════════════

    public const TITLE_KITS = 'КИТЫ ПОДНЕБЕСНОЙ';

    // ═════════════════ TITLE (экран) ═════════════════

    public const SCREEN_FADE_IN  = 10;
    public const SCREEN_STAY     = 40;
    public const SCREEN_FADE_OUT = 15;

    // ═════════════════ ХЕЛПЕРЫ ═════════════════

    /**
     * Рамка + заголовок в едином стиле.
     */
    public static function header(string $title): string
    {
        return str_replace('{TITLE}', $title, self::HEADER);
    }

    /**
     * Строка успеха: §8[§6⚜§8] §a§l{message}
     */
    public static function success(string $message): string
    {
        return self::FRAME . self::BRACKET_L . self::GOLD . self::ICON_CREST . self::FRAME . self::BRACKET_R
            . ' ' . self::JADE . self::BOLD . $message;
    }

    /**
     * Строка ошибки: §8[§4✖§8] §c§l{message}
     */
    public static function error(string $message): string
    {
        return self::FRAME . self::BRACKET_L . self::ALERT_DARK . self::ICON_X . self::FRAME . self::BRACKET_R
            . ' ' . self::ALERT . self::BOLD . $message;
    }

    /**
     * Информационная строка: §8[§3❈§8] §f{message}
     */
    public static function info(string $message): string
    {
        return self::FRAME . self::BRACKET_L . self::SKY_DARK . self::ICON_LOTUS . self::FRAME . self::BRACKET_R
            . ' ' . self::TEXT . $message;
    }

    /**
     * Ник игрока в тексте — §b§l{nick}
     */
    public static function player(string $nick): string
    {
        return self::SKY . self::BOLD . $nick;
    }

    /**
     * Печать-рамка: §8[<inner>§8] (внешний цвет задаёт содержимое)
     */
    public static function bracket(string $inner): string
    {
        return self::FRAME . self::BRACKET_L . $inner . self::FRAME . self::BRACKET_R;
    }

    /**
     * Человекочитаемая длительность: 5ч 0м / 2ч 15м / 45м 10с / 12с.
     * $seconds <= 0 → «готов».
     */
    public static function duration(int $seconds): string
    {
        if ($seconds <= 0) {
            return 'готов';
        }

        $hours   = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs    = $seconds % 60;

        if ($hours > 0) {
            return $hours . 'ч ' . $minutes . 'м';
        }
        if ($minutes > 0) {
            return $minutes . 'м ' . $secs . 'с';
        }
        return $secs . 'с';
    }

    /**
     * Title + subtitle на экране игрока.
     *
     * ⚠ LiteCore: sendTitle($title, $subtitle, $fadein, $fadeout, $duration).
     */
    public static function screen(Player $player, string $title, string $subtitle): void
    {
        if (!$player->isConnected()) {
            return;
        }
        $player->sendTitle($title, $subtitle, self::SCREEN_FADE_IN, self::SCREEN_FADE_OUT, self::SCREEN_STAY);
    }

    /** Запрет инстанцирования — только константы и статика. */
    private function __construct()
    {
    }
}
