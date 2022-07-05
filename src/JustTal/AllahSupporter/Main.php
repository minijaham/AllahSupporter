<?php

declare(strict_types=1);

namespace JustTal\AllahSupporter;

use JsonException;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\object\ExperienceOrb;
use pocketmine\entity\object\ItemEntity;
use pocketmine\entity\projectile\Projectile;
use pocketmine\entity\Skin;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\world\Explosion;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\plugin\PluginBase;
use pocketmine\resourcepacks\ZippedResourcePack;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionException;

class Main extends PluginBase implements Listener {

    /** @var Skin $skin */
    public Skin $skin;

    /** @var string[] $prayers */
    public array $prayers = [
        "allah hu akbar",
        "allah akbar",
        "praise allah",
        "osama bin laden is hot",
        "osama bin laden is sexy"
    ];

	/**
	 * @throws JsonException
	 * @throws ReflectionException
	 */
	public function onEnable() : void {
        	$this->saveResource("skin.png", true);
        	$this->saveResource("geometry.json", true);
        	$this->saveResource("resource.mcpack", true);

        	$this->loadPack();

        	$this->skin = new Skin("penguin", $this->toBytes(imagecreatefrompng($this->getDataFolder() . "skin.png")), "", "geometry.penguin", file_get_contents($this->getDataFolder() . "geometry.json"));

        	$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$entityClass = ArabicLightning::class;// more to come! >:]
		(new EntityFactory)->register($entityClass, function(World $world, CompoundTag $nbt) :  ArabicLightning{
			return new ArabicLightning(EntityDataHelper::parseLocation($nbt, $world), $nbt);
		}, ['Lightning', 'minecraft:bat']);
	}

	/**
	 * @throws ReflectionException
	 */
	public function loadPack() : void {
        	$manager = $this->getServer()->getResourcePackManager();
        	$pack = new ZippedResourcePack($this->getDataFolder() . "resource.mcpack");

        	$reflection = new ReflectionClass($manager);

        	$property = $reflection->getProperty("resourcePacks");
        	$property->setAccessible(true);

        	$currentResourcePacks = $property->getValue($manager);
        	$currentResourcePacks[] = $pack;
        	$property->setValue($manager, $currentResourcePacks);

        	$property = $reflection->getProperty("uuidList");
        	$property->setAccessible(true);
        	$currentUUIDPacks = $property->getValue($manager);
        	$currentUUIDPacks[strtolower($pack->getPackId())] = $pack;
        	$property->setValue($manager, $currentUUIDPacks);

        	$property = $reflection->getProperty("serverForceResources");
        	$property->setAccessible(true);
        	$property->setValue($manager, true);
    }

    public function onJoin(PlayerJoinEvent $event) : void {
        $player = $event->getPlayer();

        $player->setSkin($this->skin);
        $player->sendSkin();
        $player->setScale(5);
    }

    public function onChat(PlayerChatEvent $event) : void {
        if (in_array(strtolower($event->getMessage()), $this->prayers)) {
            $player = $event->getPlayer();
            $player->setImmobile();

            $packet = new PlaySoundPacket();
            $packet->soundName = "block.beehive.shear";
            $packet->x = $player->getPosition()->getX();
            $packet->y = $player->getPosition()->getY();
            $packet->z = $player->getPosition()->getZ();
            $packet->volume = 100;
            $packet->pitch = 1;
			
	    $entities = $player->getWorld()->getNearbyEntities($player->getBoundingBox()->expandedCopy(20, 20, 20), $player);
            foreach ($entities as $entity) {
                if ($entity instanceof Projectile) {
			if ($entity->getOwningEntity() !== $player) {
                		$entity->setMotion($entity->getMotion()->multiply(-1));
            		}
		} else {
			if (!$entity instanceof ItemEntity && !$entity instanceof ExperienceOrb && !isset($entity->saveNBT()->getValue()["SlapperVersion"])) {
				$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player, $entity) : void {
					$lightning = new ArabicLightning($player->getLocation());
					$lightning->setOwningEntity($player);
					$lightning->spawnToAll();
				}), 30);
                    }
                }
            }

            foreach ($player->getServer()->getOnlinePlayers() as $p) {
                $p->getNetworkSession()->sendDataPacket($packet);
            }

            $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player) : void {
                $explosion = new Explosion($player->getPosition(), 12, $player);
                $player->setImmobile(false);
                $player->kill();
                $explosion->explodeA();
                $explosion->explodeB();
            }), 30);
        }
    }

    public static function toBytes($img) : string {
        $bytes = "";
        for ($y = 0; $y < imagesy($img); $y++) {
            for ($x = 0; $x < imagesx($img); $x++) {
                $rgba = @imagecolorat($img, $x, $y);
                $a = ((~($rgba >> 24)) << 1) & 0xff;
                $r = ($rgba >> 16) & 0xff;
                $g = ($rgba >> 8) & 0xff;
                $b = $rgba & 0xff;
                $bytes .= chr($r) . chr($g) . chr($b) . chr($a);
            }
        }
        @imagedestroy($img);
        return $bytes;
    }
}
