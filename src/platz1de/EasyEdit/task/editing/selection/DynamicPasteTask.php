<?php

namespace platz1de\EasyEdit\task\editing\selection;

use platz1de\EasyEdit\selection\BlockListSelection;
use platz1de\EasyEdit\selection\DynamicBlockListSelection;
use platz1de\EasyEdit\selection\Selection;
use platz1de\EasyEdit\selection\SelectionContext;
use platz1de\EasyEdit\selection\StaticBlockListSelection;
use platz1de\EasyEdit\task\editing\EditTaskHandler;
use platz1de\EasyEdit\task\editing\type\PastingNotifier;
use platz1de\EasyEdit\thread\input\TaskInputData;
use platz1de\EasyEdit\utils\AdditionalDataManager;
use platz1de\EasyEdit\utils\ExtendedBinaryStream;
use platz1de\EasyEdit\utils\TileUtils;
use pocketmine\math\Vector3;
use pocketmine\world\Position;

class DynamicPasteTask extends SelectionEditTask
{
	use PastingNotifier;

	/**
	 * @var DynamicBlockListSelection
	 */
	protected Selection $current;
	/**
	 * @var DynamicBlockListSelection
	 */
	protected Selection $selection;

	protected bool $insert;

	/**
	 * @param string                $owner
	 * @param string                $world
	 * @param AdditionalDataManager $data
	 * @param Selection             $selection
	 * @param Vector3               $position
	 * @param Vector3               $splitOffset
	 * @param bool                  $insert
	 * @return DynamicPasteTask
	 */
	public static function from(string $owner, string $world, AdditionalDataManager $data, Selection $selection, Vector3 $position, Vector3 $splitOffset, bool $insert = false): DynamicPasteTask
	{
		$instance = new self($owner);
		SelectionEditTask::initSelection($instance, $owner, $world, $data, $selection, $position, $splitOffset);
		$instance->insert = $insert;
		return $instance;
	}

	/**
	 * @param DynamicBlockListSelection $selection
	 * @param Position                  $place
	 * @param bool                      $insert
	 */
	public static function queue(DynamicBlockListSelection $selection, Position $place, bool $insert = false): void
	{
		TaskInputData::fromTask(self::from($selection->getPlayer(), $place->getWorld()->getFolderName(), new AdditionalDataManager(true, true), $selection, $place->asVector3(), $place->asVector3(), $insert));
	}

	/**
	 * @return string
	 */
	public function getTaskName(): string
	{
		return "dynamic_paste";
	}

	public function executeEdit(EditTaskHandler $handler): void
	{
		$selection = $this->current;
		$place = $this->getPosition()->subtractVector($selection->getPoint());
		if ($this->insert) {
			$selection->useOnBlocks($place, function (int $x, int $y, int $z) use ($handler, $selection, $place): void {
				$block = $selection->getIterator()->getBlockAt($x - $place->getFloorX(), $y - $place->getFloorY(), $z - $place->getFloorZ());
				if (Selection::processBlock($block) && $handler->getBlock($x, $y, $z) === 0) {
					$handler->changeBlock($x, $y, $z, $block);
				}
			}, SelectionContext::full(), $this->getTotalSelection());
		} else {
			$selection->useOnBlocks($place, function (int $x, int $y, int $z) use ($handler, $selection, $place): void {
				$block = $selection->getIterator()->getBlockAt($x - $place->getFloorX(), $y - $place->getFloorY(), $z - $place->getFloorZ());
				if (Selection::processBlock($block)) {
					$handler->changeBlock($x, $y, $z, $block);
				}
			}, SelectionContext::full(), $this->getTotalSelection());
		}

		foreach ($selection->getTiles() as $tile) {
			$handler->addTile(TileUtils::offsetCompound($tile, $place));
		}
	}

	/**
	 * @return StaticBlockListSelection
	 */
	public function getUndoBlockList(): BlockListSelection
	{
		return new StaticBlockListSelection($this->getOwner(), $this->getWorld(), $this->selection->getPos1()->addVector($this->getPosition())->subtractVector($this->selection->getPoint()), $this->selection->getPos2()->addVector($this->getPosition())->subtractVector($this->selection->getPoint()));
	}

	public function putData(ExtendedBinaryStream $stream): void
	{
		parent::putData($stream);
		$stream->putBool($this->insert);
	}

	public function parseData(ExtendedBinaryStream $stream): void
	{
		parent::parseData($stream);
		$this->insert = $stream->getBool();
	}
}