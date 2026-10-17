<?php

declare(strict_types=1);

namespace Imperial\Ranks;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\scheduler\CallbackTask;
use Throwable;

/**
 * ═══════════════════════════════════════════════════════════════
 *  NameTagListener — печать ранга над головой
 * ───────────────────────────────────────────────────────────────
 *  join / respawn → nameTag = «§8[Печать§8] §fНик»
 *  quit           → сброс кэша состояния (MySQL не дёргаем зря)
 *
 *  ⚠ LiteCore: nameTag применяется ядром при спавне игрока,
 *    поэтому ставим сразу И дублируем через 2 тика — иначе
 *    сервер перезапишет нашу строку обычным ником.
 *  ⚠ setNameTag()/setNameTagAlwaysVisible() — из entity\Entity.
 *  ⚠ MySQL в момент join трогать нельзя (блокирует поток) —
 *    чтение состояния идёт в try/catch и только при необходимости.
 * ═══════════════════════════════════════════════════════════════
 */
final class NameTagListener implements Listener
{
    public function __construct(private Main $plugin)
    {
    }

    public function onJoin(PlayerJoinEvent $e): void
    {
        $player = $e->getPlayer();
        $this->apply($player);

        $plugin = $this->plugin;
        $player->getServer()->getScheduler()->scheduleDelayedTask(
            new CallbackTask(
                static function () use ($plugin, $player): void {
                    if ($player->isConnected()) {
                        $plugin->rm()->applyNametag($player);
                    }
                }
            ),
            2
        );
    }

    public function onRespawn(PlayerRespawnEvent $e): void
    {
        $this->apply($e->getPlayer());
    }

    public function onQuit(PlayerQuitEvent $e): void
    {
        $this->plugin->rm()->forget($e->getPlayer()->getName());
    }

    /** Первая попытка nameTag — сбой БД не должен мешать входу. */
    private function apply(\pocketmine\Player $player): void
    {
        try {
            $this->plugin->rm()->applyNametag($player);
        } catch (Throwable $err) {
            $this->plugin->getLogger()->error('nameTag ' . $player->getName() . ': ' . $err->getMessage());
        }
    }
}