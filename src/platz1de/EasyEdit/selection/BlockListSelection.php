<?php

namespace platz1de\EasyEdit\selection;

use platz1de\EasyEdit\selection\cubic\CubicChunkLoader;
use platz1de\EasyEdit\selection\cubic\CubicIterator;
use platz1de\EasyEdit\task\ReferencedChunkManager;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\level\utils\SubChunkIteratorManager;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\tile\Tile;

abstract class BlockListSelection extends Selection
{
	use CubicChunkLoader;
	use CubicIterator;

	/**
	 * @var ReferencedChunkManager
	 */
	private $manager;
	/**
	 * @var SubChunkIteratorManager
	 */
	private $iterator;
	/**
	 * @var CompoundTag[]
	 */
	private $tiles = [];

	/**
	 * BlockListSelection constructor.
	 * @param string       $player
	 * @param string       $level
	 * @param Vector3|null $pos1
	 * @param Vector3|null $pos2
	 * @param bool         $piece
	 */
	public function __construct(string $player, string $level = "", ?Vector3 $pos1 = null, ?Vector3 $pos2 = null, bool $piece = false)
	{
		parent::__construct($player, $level, $pos1, $pos2, $piece);
		$this->manager = new ReferencedChunkManager($level);
		if ($pos1 instanceof Vector3 && $pos2 instanceof Vector3) {
			$this->getManager()->load($pos1, $pos2);
		}
		$this->iterator = new SubChunkIteratorManager($this->manager);
	}

	/**
	 * @return ReferencedChunkManager
	 */
	public function getManager(): ReferencedChunkManager
	{
		return $this->manager;
	}

	/**
	 * @param int  $x
	 * @param int  $y
	 * @param int  $z
	 * @param int  $id
	 * @param int  $damage
	 * @param bool $overwrite
	 */
	public function addBlock(int $x, int $y, int $z, int $id, int $damage, bool $overwrite = true): void
	{
		if ($id === 0) {
			$id = 217; //structure_void
		}
		$this->iterator->moveTo($x, $y, $z);
		if ($overwrite || $this->iterator->currentSubChunk->getBlockId($x & 0x0f, $y & 0x0f, $z & 0x0f) === 0) {
			$this->iterator->currentSubChunk->setBlock($x & 0x0f, $y & 0x0f, $z & 0x0f, $id, $damage);
		}
	}

	/**
	 * @return SubChunkIteratorManager
	 */
	public function getIterator(): SubChunkIteratorManager
	{
		return $this->iterator;
	}

	/**
	 * @param CompoundTag $tile
	 */
	public function addTile(CompoundTag $tile): void
	{
		$this->tiles[Level::blockHash($tile->getInt(Tile::TAG_X), $tile->getInt(Tile::TAG_Y), $tile->getInt(Tile::TAG_Z))] = $tile;
	}

	/**
	 * @return CompoundTag[]
	 */
	public function getTiles(): array
	{
		return $this->tiles;
	}

	/**
	 * @return array
	 */
	public function getData(): array
	{
		return array_merge([
			"chunks" => array_map(static function (Chunk $chunk) {
				return $chunk->fastSerialize();
			}, $this->getManager()->getChunks()),
			"tiles" => $this->getTiles()
		], parent::getData());
	}

	/**
	 * @param array $data
	 */
	public function setData(array $data): void
	{
		$this->manager = new ReferencedChunkManager($data["level"]);
		foreach ($data["chunks"] as $chunk) {
			$chunk = Chunk::fastDeserialize($chunk);
			$this->manager->setChunk($chunk->getX(), $chunk->getZ(), $chunk);
		}
		$this->iterator = new SubChunkIteratorManager($this->manager);
		$this->tiles = $data["tiles"];
		parent::setData($data);
	}

	public function free(): void
	{
		$this->manager->cleanChunks();
		$this->tiles = [];
	}
}