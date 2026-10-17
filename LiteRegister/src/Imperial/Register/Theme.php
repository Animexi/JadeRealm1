<?php

declare(strict_types=1);

namespace Imperial\Register;

use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\Player;
use Throwable;

/**
 * ═══════════════════════════════════════════════════════════════
 *  Theme — ЕДИННЫЙ СТИЛЬ «НЕБЕСНОЙ ИМПЕРИИ»
 * ───────────────────────────────────────────────────────────────
 *  Ядро визуального стиля ВСЕХ плагинов Империи.
 *  Никаких магических строк в других классах — только Theme::*.
 *
 *  Палитра (§-коды):
 *   §6/§e золото • §a/§2 нефрит • §b/§3 небо • §d/§5 пурпур
 *   §c/§4 тревога • §f текст • §7 подсказки • §8 рамки
 *
 *  ⚠ PHP 8.0: только class + const, БЕЗ enum!
 * ═══════════════════════════════════════════════════════════════
 */
final class Theme
{
    // ═════════════════ ЦВЕТА ═════════════════

    /** Золото — основной акцент заголовков */
    public const GOLD        = '§6';
    /** Жёлтый — блик золота */
    public const YELLOW      = '§e';
    /** Нефрит — успех */
    public const JADE        = '§a';
    /** Тёмный нефрит — фон успеха */
    public const JADE_DARK   = '§2';
    /** Небо — информация */
    public const SKY         = '§b';
    /** Тёмное небо */
    public const SKY_DARK    = '§3';
    /** Императорский пурпур — важное */
    public const ROYAL       = '§d';
    /** Тёмный пурпур */
    public const ROYAL_DARK  = '§5';
    /** Тревога — ошибки */
    public const ALERT       = '§c';
    /** Тёмная тревога */
    public const ALERT_DARK  = '§4';
    /** Основной текст */
    public const TEXT        = '§f';
    /** Приглушённый / подсказки */
    public const MUTED       = '§7';
    /** Разделители / рамки */
    public const FRAME       = '§8';

    // ═════════════════ СТИЛИ ═════════════════

    public const BOLD      = '§l';
    public const ITALIC    = '§o';
    public const RESET_FMT = '§r';

    // ═════════════════ ЗНАКИ ═════════════════

    public const ICON_CREST   = '⚜'; // герб — успех
    public const ICON_X       = '✖'; // ошибка
    public const ICON_STAR    = '✦'; // искра — заголовки
    public const ICON_DRAGON  = '龍'; // дракон — особые события
    public const ICON_LOTUS   = '❈'; // лотос — информация
    public const BRACKET_L    = '[';
    public const BRACKET_R    = ']';

    // ═════════════════ РАМКИ / ШАБЛОНЫ ═════════════════

    /** Полная рамка-разделитель */
    public const LINE       = '§8▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬';
    /** Тонкая рамка */
    public const LINE_THIN  = '§8───────';
    /** Шаблон заголовка: {TITLE} — внутрь вставлять готовый заголовок */
    public const HEADER = self::LINE . "\n" . self::GOLD . self::ICON_STAR . ' ' . self::YELLOW . self::BOLD . '{TITLE}' . ' ' . self::GOLD . self::ICON_STAR . "\n" . self::LINE;
    /** Метка-вступление в форме */
    public const FORM_HINT  = self::MUTED . self::ITALIC;

    // ═════════════════ ГОТОВЫЕ ЗАГОЛОВКИ ═════════════════

    public const TITLE_REGISTER = 'НЕБЕСНАЯ РЕГИСТРАЦИЯ';
    public const TITLE_LOGIN    = 'ВРАТА ПОДНЕБЕСНОЙ';
    public const TITLE_CHANGE_PASS = 'СМЕНА ПАРОЛЯ';

    // ═════════════════ ЗВУКИ (Bedrock) ═════════════════

    public const SND_FORM_OPEN    = 'random.orb';        // pitch 1.2
    public const SND_REGISTER_OK  = 'random.levelup';    // pitch 1.0
    public const SND_LOGIN_OK     = 'random.orb';        // pitch 1.5
    public const SND_WRONG_PASS   = 'mob.villager.no';   // pitch 0.8
    public const SND_EMPTY_FIELD  = 'note.bass';         // pitch 0.5
    public const SND_NICK_TAKEN   = 'mob.villager.no';   // pitch 1.2
    public const SND_BAD_FORMAT   = 'note.pling';        // pitch 0.7
    public const SND_ACTION_OK    = 'random.orb';        // pitch 1.8

    /** Рандомизация pitch для живости, ±0.1 */
    public const PITCH_JITTER = 0.1;

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
     * Кнопка-подтверждение: «§a§l✔ {label}»
     */
    public static function buttonOk(string $label): string
    {
        return self::JADE . self::BOLD . '✔ ' . $label;
    }

    /**
     * Кнопка-отмена: «§c§l✖ {label}»
     */
    public static function buttonCancel(string $label): string
    {
        return self::ALERT . self::BOLD . '✖ ' . $label;
    }

    /**
     * Прогон звука ИСКЛЮЧИТЕЛЬНО игроку (никогда не Level!),
     * с лёгкой рандомизацией pitch ±0.1.
     *
     * ⚠ LiteCore: Player::playSound() отсутствует — шлём PlaySoundPacket
     *   напрямую в соединение. Поля пакета: sound, x/y/z, volume, float(pitch).
     */
    public static function sound(Player $player, string $sound, float $pitch): void
    {
        if (!$player->isConnected()) {
            return;
        }

        try {
            $jitter = mt_rand(-10, 10) / 100; // ±0.1 для живости
            $pkt = new PlaySoundPacket();
            $pkt->sound  = $sound;
            $pkt->x      = (float) $player->x;
            $pkt->y      = (float) $player->y;
            $pkt->z      = (float) $player->z;
            $pkt->volume = 1.0;
            $pkt->float  = max(0.1, $pitch + $jitter);
            $player->dataPacket($pkt);
        } catch (Throwable) {
            // Звук — украшение, ронять им логику не вправе
        }
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