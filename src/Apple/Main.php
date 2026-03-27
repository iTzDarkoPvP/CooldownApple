<?php

namespace Apple;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\item\Item;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\PluginTask;

class Main extends PluginBase implements Listener{

    private $cooldowns = [];
    private $cooldownDuration;
    private $messages;
    private $enabledWorlds;

    public function onEnable(){
        $this->getLogger()->info(TextFormat::GREEN . "GoldenAppleCooldown ha sido habilitado!");
        $this->saveDefaultConfig();
        $this->reloadConfig();

        $this->cooldownDuration = $this->getConfig()->get("cooldown-duration", 30);
        $this->messages = [
            "cooldown-message" => $this->getConfig()->get("cooldown-message", "§cDebes esperar {seconds}s para comer otra manzana dorada."),
            "cooldown-end-message" => $this->getConfig()->get("cooldown-end-message", "§aYa puedes comer otra manzana dorada.")
        ];
        $this->enabledWorlds = $this->getConfig()->get("enabled-worlds", []);
        
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new CooldownTask($this), 20);
    }
    
    public function getCooldowns(){
        return $this->cooldowns;
    }
    
    public function unsetCooldown($playerName){
        unset($this->cooldowns[$playerName]);
    }
    
    public function getMessages(){
        return $this->messages;
    }

    public function onPlayerItemConsume(PlayerItemConsumeEvent $event){
        $player = $event->getPlayer();
        
        if (!in_array($player->getLevel()->getName(), $this->enabledWorlds)){
            return;
        }

        $item = $event->getItem();
        $item->getName();
        $itemName = $item->getName();

        if ($itemName === "Golden Apple" || $itemName === "Enchanted Golden Apple"){
            $playerName = $player->getName();
            $currentTime = time();
            
            if (isset($this->cooldowns[$playerName]) && $this->cooldowns[$playerName] > $currentTime){
                $remainingTime = $this->cooldowns[$playerName] - $currentTime;
                $event->setCancelled();
                $player->sendPopup(str_replace("{seconds}", $remainingTime, $this->messages["cooldown-message"]));
            } else {
                $this->cooldowns[$playerName] = $currentTime + $this->cooldownDuration;
            }
        }
    }
}

class CooldownTask extends PluginTask{

    public function onRun($currentTick){
        $plugin = $this->getOwner();
        $cooldowns = $plugin->getCooldowns();
        
        foreach ($plugin->getServer()->getOnlinePlayers() as $player){
            $playerName = $player->getName();
            
            if (isset($cooldowns[$playerName])){
                $endTime = $cooldowns[$playerName];
                $remainingTime = $endTime - time();

                if ($remainingTime > 0){
                    $popupMessage = str_replace("{seconds}", $remainingTime, $plugin->getMessages()["cooldown-message"]);
                    $player->sendPopup($popupMessage);
                } else {
                    $player->sendMessage($plugin->getMessages()["cooldown-end-message"]);
                    $plugin->unsetCooldown($playerName);
                }
            }
        }
    }
}
