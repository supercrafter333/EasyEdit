<?php

namespace platz1de\EasyEdit\task\editing\selection\pattern;

use Generator;
use platz1de\EasyEdit\pattern\functional\GravityPattern;
use platz1de\EasyEdit\selection\BinaryBlockListStream;
use platz1de\EasyEdit\selection\BlockListSelection;
use platz1de\EasyEdit\selection\constructor\ShapeConstructor;
use platz1de\EasyEdit\selection\LinearSelection;
use platz1de\EasyEdit\selection\VerticalStaticBlockListSelection;
use platz1de\EasyEdit\task\editing\EditTaskHandler;
use platz1de\EasyEdit\task\editing\selection\cubic\CubicStaticUndo;
use platz1de\EasyEdit\world\HeightMapCache;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;

class SetTask extends PatternedEditTask
{
	use CubicStaticUndo {
		CubicStaticUndo::createUndoBlockList as private getDefaultBlockList;
	}

	/**
	 * @return string
	 */
	public function getTaskName(): string
	{
		return "set";
	}

	/**
	 * @param EditTaskHandler $handler
	 * @return Generator<ShapeConstructor>
	 */
	public function prepareConstructors(EditTaskHandler $handler): Generator
	{
		$selection = $this->selection;
		$pattern = $this->getPattern();
		$updateHeightMap = $pattern->contains(GravityPattern::class);
		yield from $selection->asShapeConstructors(function (int $x, int $y, int $z) use ($updateHeightMap, $pattern, $selection, $handler): void {
			$block = $pattern->getFor($x, $y, $z, $handler->getOrigin(), $selection);
			if ($block !== -1) {
				$handler->changeBlock($x, $y, $z, $block);
				if ($updateHeightMap) {
					HeightMapCache::setBlockAt($x, $y, $z, $block >> Block::INTERNAL_STATE_DATA_BITS === BlockTypeIds::AIR);
				}
			}
		}, $this->context);
	}

	/**
	 * @return BlockListSelection
	 */
	public function createUndoBlockList(): BlockListSelection
	{
		if ($this->selection instanceof LinearSelection) {
			return new BinaryBlockListStream($this->getWorld());
		}
		if ($this->getPattern()->contains(GravityPattern::class)) {
			return new VerticalStaticBlockListSelection($this->getWorld(), $this->getSelection()->getPos1(), $this->getSelection()->getPos2());
		}
		return $this->getDefaultBlockList();
	}
}