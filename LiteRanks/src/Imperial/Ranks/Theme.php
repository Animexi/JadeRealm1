<?php

declare(strict_types=1);

namespace Imperial\Ranks;

use pocketmine\Player;

/**
 * ═══════════════════════════════════════════════════════════════
 *  Theme — ЕДИННЫЙ СТИЛЬ «НЕБЕСНОЙ ИМПЕРИИ»
 * ───────────────────────────────────────────────────────────────
 *  Синхронизирован с LiteRegister 1-в-1. Ядро визуального стиля
 *  ВСЕХ плагинов Империи. Никаких магических строк — только Theme::*.
 *
 *  ⚠ В LiteRanks ЗВУКОВ НЕТ — всё общение только через чат.
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
    public const ICON_DRAGON  = '龍';
    public const ICON_LOTUS   = '❈';
    public const ICON_CROWN   = '♛';
    public const ICON_ARROW   = '»';
    public const BRACKET_L    = '[';
    public const BRACKET_R    = ']';

    // ═════════════════ СИМВОЛЫ РАНГОВ (поле icon: в config.yml) ═════════════════

    /** Странник — странствующая искра */
    public const RANK_ICON_SPARKLE = '✧';
    /** Послушник — печать ученичества */
    public const RANK_ICON_SEAL    = '❂';
    /** Воин — скрещённые мечи */
    public const RANK_ICON_SWORDS  = '⚔';
    /** Страж — охранительный знак */
    public const RANK_ICON_SIGIL   = '✥';
    /** Мастер — путеводная звезда */
    public const RANK_ICON_MASTER  = '✦';
    /** Наставник — лотос знания */
    public const RANK_ICON_SAGE    = '❈';
    /** Хранитель — самоцвет */
    public const RANK_ICON_GEM     = '❖';
    /** Принц — корона */
    public const RANK_ICON_PRINCE  = '♛';
    /** Император — корона Империи */
    public const RANK_ICON_EMPEROR = '♚';
    /** Небесный Дракон */
    public const RANK_ICON_DRAGON  = '龍';

    // ═════════════════ РАМКИ / ШАБЛОНЫ ═════════════════

    public const LINE       = '§8▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬';
    public const LINE_THIN  = '§8───────';
    public const HEADER     = self::LINE . "\n" . self::GOLD . self::ICON_STAR . ' ' . self::YELLOW . self::BOLD . '{TITLE}' . ' ' . self::GOLD . self::ICON_STAR . "\n" . self::LINE;
    public const FORM_HINT  = self::MUTED . self::ITALIC;

    // ═════════════════ ГОТОВЫЕ ЗАГОЛОВКИ ═════════════════

    public const TITLE_RANKS  = 'ИЕРАРХИЯ ПОДНЕБЕСНОЙ';
    public const TITLE_PREFIX = 'ВЫБОР ПЕЧАТИ';
    public const TITLE_MYRANK = 'МОЙ РАНГ';

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
     * Печать-рамка: §8[<prefix>§8] (внешний цвет задаёт ранг)
     */
    public static function bracket(string $inner): string
    {
        return self::FRAME . self::BRACKET_L . $inner . self::FRAME . self::BRACKET_R;
    }

    /**
     * Символ ранга по его id — страховка на случай, если в config.yml
     * поле icon: отсутствует или пустое. Иначе ВСЕ ранги получили бы
     * один и тот же дефолтный значок.
     */
    public static function defaultIcon(string $rankId): string
    {
        return match (strtolower($rankId)) {
            'strannik'        => self::RANK_ICON_SPARKLE,
            'poslushnik'      => self::RANK_ICON_SEAL,
            'voin'            => self::RANK_ICON_SWORDS,
            'strazh'          => self::RANK_ICON_SIGIL,
            'master'          => self::RANK_ICON_MASTER,
            'nastavnik'       => self::RANK_ICON_SAGE,
            'khranitel'       => self::RANK_ICON_GEM,
            'prints'          => self::RANK_ICON_PRINCE,
            'imperator'       => self::RANK_ICON_EMPEROR,
            'nebesnyy_drakon' => self::RANK_ICON_DRAGON,
            default           => self::ICON_STAR,
        };
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