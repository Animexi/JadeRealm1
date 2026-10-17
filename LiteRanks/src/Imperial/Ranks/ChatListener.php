<?php

declare(strict_types=1);

namespace Imperial\Ranks;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\Player;
use Throwable;

/**
 * ═══════════════════════════════════════════════════════════════
 *  ChatListener — формат чата «Небесной Империи»
 * ───────────────────────────────────────────────────────────────
 *  §8[Печать§8] §fНик §8» §7текст
 *
 *  Схема (LiteCore, API 3.0.1):
 *   1. Собираем «свой» текст по рангу.
 *   2. setCancelled(true) — ядро больше не шлёт своё
 *      «chat.type.text» (иначе получили бы дубль).
 *   3. Server::broadcastMessage($text, $recipients) — шлём
 *      ровно тем же получателям, что назначило ядро/другие плагины.
 *
 *  Сбой MySQL НЕ должен глушить чат: при исключении берём
 *  ранг по умолчанию и всё равно отправляем сообщение.
 * ═══════════════════════════════════════════════════════════════
 */
final class ChatListener implements Listener
{
    public function __construct(private Main $plugin)
    {
    }

    public function onChat(PlayerChatEvent $e): void
    {
        $player = $e->getPlayer();
        $nick   = $player->getName();

        try {
            $line = $this->plugin->rm()->formatChat($nick, $e->getMessage());
        } catch (Throwable $err) {
            $this->plugin->getLogger()->error('Формат чата ' . $nick . ': ' . $err->getMessage());
            // Чат живёт независимо от базы — печатаем без печати
            $line = Theme::bracket($this->plugin->rm()->defaultRank()->coloredPrefix())
                . ' ' . Theme::TEXT . $nick . ' '
                . Theme::FRAME . Theme::ICON_ARROW . ' '
                . Theme::MUTED . $e->getMessage();
        }

        $recipients = $this->players($e->getRecipients());

        $e->setCancelled(true);

        // Пустой список = никто не должен увидеть (намеренно, не «всем»)
        if ($recipients === []) {
            return;
        }

        $this->plugin->getServer()->broadcastMessage($line, $recipients);
    }

    /**
     * Из подписчиков права — только онлайн-игроки.
     *
     * @param array<mixed> $recipients
     * @return array<int, Player>
     */
    private function players(array $recipients): array
    {
        $out = [];
        foreach ($recipients as $r) {
            if ($r instanceof Player && $r->isConnected()) {
                $out[] = $r;
            }
        }
        return $out;
    }
}