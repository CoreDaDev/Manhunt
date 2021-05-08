<?php

namespace OguzhanUmutlu\Manhunt;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

class Manhunt extends PluginBase
{
    const STATUS_SETUP = 0;
    const STATUS_STARTED = 1;
    const STATUS_ENDED = 2;

    public $game = [
        "owner" => null,
        "hunters" => [],
        "runners" => [],
        "diedrunners" => [],
        "status" => self::STATUS_ENDED
    ];

    public function onEnable()
    {
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if (!$sender->hasPermission($command->getPermission())) {
            $sender->sendMessage($command->getPermissionMessage());
            return true;
        }
        $params = ["create", "hunter", "runner", "start", "forceend"];
        if (isset($args[0]) && in_array($args[0], $params)) {
            switch (array_search($args, $params)) {
                case 0:
                    if ($this->game["status"] == self::STATUS_ENDED) {
                        $sender->sendMessage("§c> Game is already created.");
                        return true;
                    }
                    if (!$sender instanceof Player) {
                        $sender->sendMessage("§c> Use this command in-game.");
                        return true;
                    }
                    $this->game["owner"] = $sender;
                    $this->game["status"] = self::STATUS_SETUP;
                    $sender->sendMessage("§a> Now you can add hunter(s)/runner(s)!");
                    break;
                case 1:
                case 2:
                    if ($this->game["status"] != self::STATUS_SETUP) {
                        $sender->sendMessage("§c> Game is not in setup mode.");
                        return true;
                    }
                    $paramsA = ["add", "remove", "list"];
                    if (!isset($args[1]) || !in_array($args[1], $paramsA)) {
                        $sender->sendMessage("§c> Usage: /manhunt " . $args[0] . " <" . implode(", ", $paramsA) . ">");
                        return true;
                    }
                    switch ($args[1]) {
                        case 0:
                            if (!isset($args[2])) {
                                $sender->sendMessage("§c> Usage: /manhunt " . $args[0] . " " . $args[1] . " <player" . ">");
                                return true;
                            }
                            $player = $this->getServer()->getPlayer($args[2]);
                            if (!$player) {
                                $sender->sendMessage("§c> Player not found.");
                                return true;
                            }
                            if (in_array($player->getName(), array_keys($this->game["hunters"]))) {
                                $sender->sendMessage("§c> This player is already a hunter.");
                                return true;
                            }
                            if (in_array($player->getName(), array_keys($this->game["runners"]))) {
                                $sender->sendMessage("§c> This player is already a runner.");
                                return true;
                            }
                            $this->game[array_search($args, $params) == 1 ? "hunters" : "runners"][$player->getName()] = $player;
                            $sender->sendMessage("§a> Player has been added to " . (array_search($args, $params) == 1 ? "hunter" : "runner") . " list.");
                            break;
                        case 1:
                            if (!isset($args[2])) {
                                $sender->sendMessage("§c> Usage: /manhunt " . $args[0] . " " . $args[1] . " <player" . ">");
                                return true;
                            }
                            $player = $this->getServer()->getPlayer($args[2]);
                            if (!$player) {
                                $sender->sendMessage("§c> Player not found.");
                                return true;
                            }
                            if (!in_array($player->getName(), array_keys($this->game[array_search($args, $params) == 1 ? "hunters" : "runners"]))) {
                                $sender->sendMessage("§c> This player is already not a hunter.");
                                return true;
                            }
                            unset($this->game[array_search($args, $params) == 1 ? "hunters" : "runners"][$player->getName()]);
                            $sender->sendMessage("§a> Player has been removed from " . (array_search($args, $params) == 1 ? "hunter" : "runner") . " list.");
                            break;
                        case 2:
                            $sender->sendMessage("§a> " . (array_search($args, $params) == 1 ? "Hunters: " : "Runners: ") . implode(", ", array_keys($this->game[array_search($args, $params) == 1 ? "hunters" : "runners"])));
                            break;
                    }
                    break;
                case 3:
                    if($this->game["status"] != self::STATUS_SETUP) {
                        $sender->sendMessage("§c> Game should be in setup mode.");
                        return true;
                    }
                    if(!in_array($this->game["owner"]->getName(), array_keys($this->game["hunters"])) && !in_array($this->game["owner"]->getName(), array_keys($this->game["runners"]))) {
                        $sender->sendMessage("§c> Manhunt owner should be in a team.");
                        return true;
                    }
                    if(empty($this->game["hunters"])) {
                        $sender->sendMessage("§c> There are no hunters.");
                        return true;
                    }
                    if(empty($this->game["runners"])) {
                        $sender->sendMessage("§c> There are no runners.");
                        return true;
                    }
                    $this->game["status"] = self::STATUS_STARTED;
                    foreach($this->game["hunters"] as $player) {
                        if(!$player instanceof Player || $player->isClosed() || !$player->isOnline()) {
                            unset($this->game["hunters"][$player->getName()]);
                        } else {
                            $player->teleport($this->game["owner"]);
                            $player->getInventory()->setItem(8, Item::get(Item::COMPASS)->setCustomName("§r§eHunter Compass")->setLore(["§r"]));
                        }
                    }
                    foreach($this->game["runners"] as $player) {
                        if(!$player instanceof Player || $player->isClosed() || !$player->isOnline()) {
                            unset($this->game["runners"][$player->getName()]);
                        } else {
                            $player->teleport($this->game["owner"]);
                        }
                    }

                    break;
                case 4:
                    foreach(array_merge($this->game["runners"], $this->game["hunters"]) as $player) {
                        if($player instanceof Player && !$player->isClosed() && $player->isOnline()) {
                            $player->sendTitle("§cGame ended.");
                            $player->setGamemode(0);
                        }
                    }
                    $this->game = [
                        "owner" => null,
                        "hunters" => [],
                        "runners" => [],
                        "diedrunners" => [],
                        "status" => self::STATUS_ENDED
                    ];
                    break;
            }
        } else $sender->sendMessage("§c> Usage: /manhunt <" . implode(", ", $params).">");
        return true;
    }

    public static function checkEnd(array $game): bool {
        if(empty($game["runners"])) {
            foreach(array_merge($game["runners"], $game["hunters"]) as $player) {
                if($player instanceof Player && !$player->isClosed() && $player->isOnline()) {
                    $player->sendMessage("§eHunters won!");
                }
            }
            return true;
        }
        if(count($game["runners"]) == count($game["diedrunners"])) {
            foreach(array_merge($game["runners"], $game["hunters"]) as $player) {
                if($player instanceof Player && !$player->isClosed() && $player->isOnline()) {
                    $player->sendMessage("§eHunters won!");
                }
            }
            return true;
        }
        return false;
    }
}