<?php

namespace OguzhanUmutlu\Manhunt;

use pocketmine\block\EndPortal;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\Compass;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\SetSpawnPositionPacket;
use pocketmine\Player;

class EventListener implements Listener
{
    /*** @var Manhunt */
    private $plugin;

    public function __construct(Manhunt $plugin)
    {
        $this->plugin = $plugin;
    }

    public function onInvTransaction(InventoryTransactionEvent $e)
    {
        $transaction = $e->getTransaction();
        $player = $transaction->getSource();
        if (!$this->plugin->game["status"] != Manhunt::STATUS_STARTED) return;
        if (!in_array($player->getName(), array_keys($this->plugin->game["hunters"]))) return;
        foreach ($transaction->getActions() as $action) {
            if ($action->getSourceItem() instanceof Compass && $action->getSourceItem()->getLore() && $action->getSourceItem()->getLore()[0] == "§r") {
                $e->setCancelled(true);
            }
        }
    }

    public function onInvPickupItem(InventoryPickupItemEvent $e)
    {
        if (!$this->plugin->game["status"] != Manhunt::STATUS_STARTED) return;
        foreach ($e->getViewers() as $player) {
            if ($e->getItem()->getItem() instanceof Compass && $e->getItem()->getItem()->getLore() && $e->getItem()->getItem()->getLore()[0] == "§r") {
                if (!in_array($player->getName(), array_keys($this->plugin->game["hunters"]))) $e->setCancelled(true);
            }
        }
    }

    public function onInvDropItem(PlayerDropItemEvent $e)
    {
        $player = $e->getPlayer();
        if (!$this->plugin->game["status"] != Manhunt::STATUS_STARTED) return;
        if (!in_array($player->getName(), array_keys($this->plugin->game["hunters"]))) return;
        if ($e->getItem() instanceof Compass && $e->getItem()->getLore() && $e->getItem()->getLore()[0] == "§r") {
            $e->setCancelled(true);
        }
    }

    public function onAttackPlayer(EntityDamageEvent $e)
    {
        if (!$e instanceof EntityDamageByEntityEvent || !$e->getDamager() instanceof Player || !$e->getEntity() instanceof Player) return;
        $player = $e->getEntity();
        $damager = $e->getDamager();
        if (in_array($player->getName(), array_keys($this->plugin->game["hunters"])) && in_array($damager->getName(), array_keys($this->plugin->game["hunters"]))) $e->setCancelled(true);
        if (in_array($player->getName(), array_keys($this->plugin->game["runners"])) && in_array($damager->getName(), array_keys($this->plugin->game["runners"]))) $e->setCancelled(true);
    }

    public function onMove(PlayerMoveEvent $e) {
        $player = $e->getPlayer();
        if (!$this->plugin->game["status"] != Manhunt::STATUS_STARTED) return;
        if($e->getPlayer()->getLevel()->getBlock($player) instanceof EndPortal && $e->getPlayer()->getLevel()->getProvider()->getGenerator() == "end") {
            foreach(array_merge($this->plugin->game["runners"], $this->plugin->game["hunters"]) as $player) {
                if($player instanceof Player && !$player->isClosed() && $player->isOnline()) {
                    $player->sendMessage("§eRunners won!");
                }
            }
            return true;
        }
        if (!in_array($player->getName(), array_keys($this->plugin->game["hunters"]))) return;
        if(!$player->getInventory()->getItemInHand() instanceof Compass || !$player->getInventory()->getItemInHand()->getLore() || $player->getInventory()->getItemInHand()->getLore()[0] != "§r") return;
        $selected = [
            "distance" => null,
            "player" => null
        ];
        foreach ($this->plugin->game["runners"] as $runner) {
            if (!$runner instanceof Player || $runner->isClosed() || !$runner->isOnline()) {
                unset($this->plugin->game["hunters"][$runner->getName()]);
            } else if ($runner->getLevel()->getFolderName() == $player->getLevel()->getFolderName() && !$selected["distance"] || $runner->distance($player) < $selected["distance"]) $selected = [
                "distance" => $runner->distance($player),
                "player" => $runner
            ];
        }
        if(!$selected["player"]) {
            $player->sendPopup("§cThere are no runners in this dimension!");
            return;
        }
        $player->sendPopup("§aLocating §b".$selected["player"]->getName()." §e- §b".$selected["distance"]." meters");
        $pk = new SetSpawnPositionPacket();
        $pk->x = $pk->x2 = $selected["player"]->getFloorX();
        $pk->y = $pk->y2 = $selected["player"]->getFloorY();
        $pk->z = $pk->z2 = $selected["player"]->getFloorZ();
        $pk->dimension = 0;
        $pk->spawnType = SetSpawnPositionPacket::TYPE_PLAYER_SPAWN;
        $player->dataPacket($pk);
    }

    public function onDeath(PlayerDeathEvent $e) {
        $player = $e->getPlayer();
        if (in_array($player->getName(), array_keys($this->plugin->game["hunters"]))) {
            $drops = $e->getDrops();
            foreach($drops as $i => $drop) {
                if($player->getInventory()->getItemInHand() instanceof Compass && $player->getInventory()->getItemInHand()->getLore() && $player->getInventory()->getItemInHand()->getLore()[0] == "§r") {
                    $drops[$i] = Item::get(Item::AIR);
                }
            }
            $e->setDrops($drops);
        } else if(in_array($player->getName(), array_keys($this->plugin->game["runners"]))) {
            $player->setGamemode(3);
            $this->plugin->game["diedrunners"][$player->getName()] = $player;
            foreach(array_merge($this->plugin->game["runners"], $this->plugin->game["hunters"]) as $player) {
                if($player instanceof Player && !$player->isClosed() && $player->isOnline()) {
                    $player->sendMessage("§c> §b".$player->getName()."the runner§c died.");
                    $e->setDeathMessage("");
                }
            }
        }
        if(Manhunt::checkEnd($this->plugin->game)) $this->plugin->game = ["owner" => null, "hunters" => [], "runners" => [], "diedrunners" => [], "status" => Manhunt::STATUS_ENDED];
    }

    public function onQuit(PlayerQuitEvent $e) {
        $player = $e->getPlayer();
        if (!$this->plugin->game["status"] != Manhunt::STATUS_STARTED) return;
        if (!in_array($player->getName(), array_keys($this->plugin->game["hunters"]))) {
            unset($this->plugin->game["hunters"][$player->getName()]);
            foreach(array_merge($this->plugin->game["runners"], $this->plugin->game["hunters"]) as $player) {
                if($player instanceof Player && !$player->isClosed() && $player->isOnline()) {
                    $player->sendMessage("§c> §b".$player->getName()."the hunter§c left from manhunt.");
                    $e->setQuitMessage("");
                }
            }
        }
        if (!in_array($player->getName(), array_keys($this->plugin->game["runners"]))) {
            unset($this->plugin->game["runners"][$player->getName()]);
            if(isset($this->plugin->game["diedrunners"][$player->getName()]))unset($this->plugin->game["diedrunners"][$player->getName()]);
            foreach(array_merge($this->plugin->game["runners"], $this->plugin->game["hunters"]) as $player) {
                if($player instanceof Player && !$player->isClosed() && $player->isOnline()) {
                    $player->sendMessage("§c> §b".$player->getName()."the runner§c left from manhunt.");
                    $e->setQuitMessage("");
                }
            }
        }
        if(Manhunt::checkEnd($this->plugin->game)) $this->plugin->game = ["owner" => null, "hunters" => [], "runners" => [], "diedrunners" => [], "status" => Manhunt::STATUS_ENDED];
    }
}