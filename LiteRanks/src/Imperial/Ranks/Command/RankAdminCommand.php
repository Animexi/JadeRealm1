<?php

declare(strict_types=1);

namespace Imperial\Ranks\Command;

use Imperial\Ranks\Main;
use Imperial\Ranks\Rank;
use Imperial\Ranks\Theme;
use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use Throwable;

/**
 * ═══════════════════════════════════════════════════════════════
 *  /rank give|remove|set|list|info — админ-команды рангов
 * ───────────────────────────────────────────────────────────────
 *  /rank list                        — все ранги (с номерами)
 *  /rank info   <№>                  — подробности о ранге
 *  /rank give  <ник> <№> [срок]      — пожаловать ранг на срок
 *  /rank set    <ник> <№>            — сменить ранг навсегда
 *  /rank remove <ник>                — снять ранг → дефолтный
 *
 *  ⚠ Ранг задаётся ЦИФРОЙ из /rank list (как в /prefix) —
 *    английские id игрокам вводить не нужно.
 *  ⚠ Право lc.ranks.admin проверяется ЗДЕСЬ, а не в plugin.yml:
 *    чужим отвечаем «Неизвестная команда», чтобы сам факт
 *    существования /rank не светился.
 *  ⚠ LiteCore: чистый CommandExecutor, 4 параметра,
 *    БЕЗ type hints на $label, return ВСЕГДА true.
 * ═══════════════════════════════════════════════════════════════
 */
final class RankAdminCommand implements CommandExecutor
{
    public function __construct(private Main $plugin)
    {
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args)
    {
        if (count($args) < 1) {
            $this->cmdList($sender);
            $this->sendUsage($sender);
            return true;
        }

        $sub = strtolower($args[0]);

        // Проверка прав ТОЛЬКО для админских подкоманд (give/set/remove).
        // list и info доступны всем — игрок вправе знать, какие привилегии есть на сервере.
        $adminOnly = in_array($sub, ['give', 'set', 'remove'], true);
        if ($adminOnly && !$sender->hasPermission('lc.ranks.admin')) {
            $sender->sendMessage(Theme::error('Недостаточно прав для этой команды.'));
            return true;
        }

        try {
            switch ($sub) {
                case 'give':
                    $this->cmdGive($sender, $args);
                    break;
                case 'remove':
                    $this->cmdRemove($sender, $args);
                    break;
                case 'set':
                    $this->cmdSet($sender, $args);
                    break;
                case 'list':
                    $this->cmdList($sender);
                    break;
                case 'info':
                    $this->cmdInfo($sender, $args);
                    break;
                default:
                    $this->cmdList($sender);
                    $this->sendUsage($sender);
                    break;
            }
        } catch (Throwable $e) {
            $this->plugin->getLogger()->error('rank: ' . $e->getMessage());
            $sender->sendMessage(Theme::error($this->plugin->rm()->msg('err-db')));
        }

        return true;
    }

    // ═════════════════ /rank give ═════════════════

    /**
     * /rank give <ник> <№ ранга> [срок]
     */
    private function cmdGive(CommandSender $sender, array $args): void
    {
        if (count($args) < 3) {
            $sender->sendMessage(Theme::error('Использование: /rank give <ник> <№ ранга> [срок]'));
            $sender->sendMessage(Theme::info('Срок (опционально): 30m, 24h, 7d, 1y'));
            $this->cmdList($sender);
            return;
        }

        $rank = $this->resolveRank($sender, $args[2]);
        if ($rank === null) {
            return;
        }

        $nick = $args[1];

        // Срок опционален: «7d», «24h», «30m», «2w», «1y» либо «null» = навсегда
        $expiresAt = null;
        if (isset($args[3])) {
            if (!$this->parseExpiry($sender, $args[3], $expiresAt)) {
                return; // неверный формат — причина уже сообщена
            }
        }

        $by = $sender instanceof Player ? $sender->getName() : 'CONSOLE';
        $this->plugin->rm()->setRank($nick, $rank->id, $by, $expiresAt);
        $this->plugin->rm()->log("GIVE: {$by} → {$nick} ({$rank->display})");

        $line = Theme::success('Ранг ' . $rank->coloredPrefix() . ' выдан игроку ' . Theme::player($nick));
        if ($expiresAt !== null) {
            $line .= "\n" . Theme::info('Истекает: ' . $expiresAt);
        }
        $sender->sendMessage($line);
        $this->refresh($nick);
    }

    // ═════════════════ /rank remove ═════════════════

    private function cmdRemove(CommandSender $sender, array $args): void
    {
        if (count($args) < 2) {
            $sender->sendMessage(Theme::error('Использование: /rank remove <ник>'));
            return;
        }

        $nick    = $args[1];
        $deleted = $this->plugin->rm()->removeRank($nick);
        $this->plugin->rm()->log("REMOVE: {$sender->getName()} → {$nick}");

        $sender->sendMessage(
            $deleted
                ? Theme::success('Ранг игрока ' . Theme::player($nick) . ' снят — возвращён «'
                    . $this->plugin->rm()->defaultRank()->coloredPrefix() . '§8»§r.')
                : Theme::info('Игрок ' . Theme::player($nick) . ' и так состоял в ранге по умолчанию.')
        );
        $this->refresh($nick);
    }

    // ═════════════════ /rank set ═════════════════

    private function cmdSet(CommandSender $sender, array $args): void
    {
        if (count($args) < 3) {
            $sender->sendMessage(Theme::error('Использование: /rank set <ник> <№ ранга>'));
            $this->cmdList($sender);
            return;
        }

        $rank = $this->resolveRank($sender, $args[2]);
        if ($rank === null) {
            return;
        }

        $nick = $args[1];
        $by   = $sender instanceof Player ? $sender->getName() : 'CONSOLE';
        $this->plugin->rm()->setRank($nick, $rank->id, $by, null);
        $this->plugin->rm()->log("SET: {$by} → {$nick} ({$rank->display})");

        $sender->sendMessage(
            Theme::success('Ранг игрока ' . Theme::player($nick) . ' установлен: ' . $rank->coloredPrefix())
        );
        $this->refresh($nick);
    }

    // ═════════════════ /rank list ═════════════════

    /**
     * Все ранги по номерам — цвета каждого ранга сохранены.
     */
    private function cmdList(CommandSender $sender): void
    {
        $rm     = $this->plugin->rm();
        $sorted = $rm->allSorted();
        $defaultId = $rm->defaultRankId();

        $sender->sendMessage(Theme::header(Theme::TITLE_RANKS));

        foreach ($sorted as $i => $rank) {
            $mark = $rank->id === $defaultId ? Theme::YELLOW . ' ★' : '';
            $sender->sendMessage(
                Theme::MUTED . '  ' . ($i + 1) . '. '
                . $rank->coloredPrefix()
                . ' ' . Theme::FRAME . Theme::ICON_ARROW . ' '
                . $rank->coloredDisplay()
                . ' ' . $rank->weightBadge()
                . $mark
            );
        }

        $sender->sendMessage(Theme::LINE);
        $sender->sendMessage(Theme::info('Всего рангов: ' . count($sorted) . '  ★ — ранг по умолчанию'));
    }

    // ═════════════════ /rank info ═════════════════

    private function cmdInfo(CommandSender $sender, array $args): void
    {
        if (count($args) < 2) {
            $sender->sendMessage(Theme::error('Использование: /rank info <№ ранга>'));
            $this->cmdList($sender);
            return;
        }

        $rank = $this->resolveRank($sender, $args[1]);
        if ($rank === null) {
            return;
        }

        $sender->sendMessage(Theme::header('ПЕЧАТЬ РАНГА'));
        $sender->sendMessage(Theme::info('Название: ' . $rank->coloredDisplay()));
        $sender->sendMessage(Theme::info('Печать: ' . Theme::bracket($rank->coloredPrefix()) . '§r'));
        $sender->sendMessage(Theme::info('Вес: ' . Theme::YELLOW . (string) $rank->weight));

        if ($rank->permissions !== []) {
            $sender->sendMessage(Theme::LINE_THIN);
            $sender->sendMessage(Theme::info('Права:'));
            foreach ($rank->permissions as $perm) {
                $sender->sendMessage(Theme::MUTED . '  • ' . $perm);
            }
        } else {
            $sender->sendMessage(Theme::LINE_THIN);
            $sender->sendMessage(Theme::FORM_HINT . '  Права не назначены');
        }

        if ($rank->limits !== []) {
            $sender->sendMessage(Theme::LINE_THIN);
            $sender->sendMessage(Theme::info('Лимиты:'));
            foreach ($rank->limits as $key => $val) {
                $sender->sendMessage(Theme::MUTED . '  • ' . $key . ': ' . Theme::SKY . $this->fmt($val));
            }
        }
    }

    // ═════════════════ ХЕЛПЕРЫ ═════════════════

    /**
     * Ранг ТОЛЬКО по номеру из /rank list (1..N) — как выбор в /prefix.
     * Английские id не принимаем: админ видит номера в списке.
     */
    private function resolveRank(CommandSender $sender, string $token): ?Rank
    {
        $sorted = $this->plugin->rm()->allSorted();

        if (!ctype_digit($token)) {
            $sender->sendMessage(Theme::error('Ранг задаётся номером из списка. Пример: /rank give Nick 5'));
            $this->cmdList($sender);
            return null;
        }

        $index = (int) $token;
        if (!isset($sorted[$index - 1])) {
            $sender->sendMessage(Theme::error('Нет ранга под номером ' . $token . '. Доступно: 1–' . count($sorted)));
            $this->cmdList($sender);
            return null;
        }

        return $sorted[$index - 1];
    }

    /**
     * «7d» / «24h» / «30m» / «2w» / «1y» → DATETIME для MySQL.
     * «null» / «0» = навсегда. Возвращает false, если срок не распознан.
     */
    private function parseExpiry(CommandSender $sender, string $raw, ?string &$out): bool
    {
        $raw = strtolower(trim($raw));
        if ($raw === 'null' || $raw === '0') {
            $out = null;
            return true;
        }

        $mult = match (substr($raw, -1)) {
            'm'    => 60,
            'h'    => 3600,
            'd'    => 86400,
            'w'    => 604800,
            'y'    => 31536000,
            default => 0,
        };

        $num = (int) substr($raw, 0, -1);
        if ($mult === 0 || $num <= 0) {
            $sender->sendMessage(
                Theme::error('Не понятен срок «' . $raw . '». Формат: 30m, 24h, 7d, 2w, 1y')
            );
            return false;
        }

        $out = date('Y-m-d H:i:s', time() + $num * $mult);
        return true;
    }

    /** Обновить nameTag онлайн-жертвы, чтобы печать сменилась сразу. */
    private function refresh(string $nick): void
    {
        $target = $this->plugin->getServer()->getPlayerExact($nick);
        if ($target !== null) {
            $this->plugin->rm()->applyNametag($target);
        }
    }

    private function sendUsage(CommandSender $sender): void
    {
        $sender->sendMessage(Theme::MUTED . '  /rank give <ник> <№> [срок] §7— пожаловать ранг на срок');
        $sender->sendMessage(Theme::MUTED . '  /rank set <ник> <№> §7— сменить ранг навсегда');
        $sender->sendMessage(Theme::MUTED . '  /rank remove <ник> §7— снять ранг');
        $sender->sendMessage(Theme::MUTED . '  /rank info <№> §7— подробности о ранге');
        $sender->sendMessage(Theme::LINE);
    }

    /** Короткое человекочитаемое значение лимита. */
    private function fmt(mixed $v): string
    {
        if (is_bool($v)) {
            return $v ? 'да' : 'нет';
        }
        if (is_array($v)) {
            return implode(', ', array_map([$this, 'fmt'], $v));
        }
        return (string) $v;
    }
}
