<?php

namespace platz1de\EasyEdit\task\editing\selection;

use Generator;
use platz1de\EasyEdit\selection\constructor\ShapeConstructor;
use platz1de\EasyEdit\task\editing\EditTaskHandler;
use platz1de\EasyEdit\task\editing\selection\cubic\CubicStaticUndo;
use platz1de\EasyEdit\world\HeightMapCache;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\math\Axis;
use pocketmine\math\Vector3;
use pocketmine\world\World;

class SmoothTask extends SelectionEditTask
{
	use CubicStaticUndo;

	/**
	 * @return string
	 */
	public function getTaskName(): string
	{
		return "smooth";
	}

	/**
	 * This is pretty much magic code, so better don't touch it
	 * @param EditTaskHandler $handler
	 * @return Generator<ShapeConstructor>
	 */
	public function prepareConstructors(EditTaskHandler $handler): Generator
	{
		HeightMapCache::load($handler->getOrigin(), $this->selection);
		$currentX = null;
		$currentZ = null;
		$map = [];
		$reference = [];
		yield from $this->selection->asShapeConstructors(function (int $x, int $y, int $z) use (&$currentX, &$currentZ, &$map, &$reference, $handler): void {
			if ($currentX !== $x || $currentZ !== $z) {
				//Prepare data sets for all y-values
				$currentX = $x;
				$currentZ = $z;
				$map = $this->modifyDepthMap(HeightMapCache::generateFullDepthMap($x, $z));
				$reference = $map;
				for ($i = -1; $i <= 1; $i++) {
					for ($j = -1; $j <= 1; $j++) {
						if ($i === 0 && $j === 0) {
							continue;
						}
						foreach ($this->modifyDepthMap(HeightMapCache::generateFullDepthMap($x + $i, $z + $j)) as $height => $depth) {
							$reference[$height] += $depth;
						}
					}
				}
				$start = -1;
				foreach ($reference as $height => $depth) {
					if ($depth < -4.5) {
						$reference[$height] = -1;
						if ($start !== -1) {
							for ($i = $start; $i < $height; $i++) {
								$reference[$i] = min($i - $start, $height - $i - 1);
							}
							$start = -1;
						}
						continue;
					}
					if ($start === -1) {
						$start = $height;
					}
				}
				if ($start !== -1) {
					for ($i = $start; $i < World::Y_MAX; $i++) {
						$reference[$i] = min($i - $start, World::Y_MAX - $i - 1);
					}
				}
			}
			if ($map[$y] === $reference[$y]) {
				return;
			}
			if ($reference[$y] <= 0) {
				if ($y < (World::Y_MAX - 1)) {
					$b = $handler->getBlock($x, $y + 1, $z);
					if ($b >> Block::INTERNAL_STATE_DATA_BITS === BlockTypeIds::WATER || $b >> Block::INTERNAL_STATE_DATA_BITS === BlockTypeIds::LAVA) {
						$handler->changeBlock($x, $y, $z, $b);
						return;
					}
				}
				for ($i = -1; $i <= 1; $i++) {
					for ($j = -1; $j <= 1; $j++) {
						if ($i === 0 && $j === 0) {
							continue;
						}
						$b = $handler->getBlock($x + $i, $y, $z + $j);
						if ($b >> Block::INTERNAL_STATE_DATA_BITS === BlockTypeIds::WATER || $b >> Block::INTERNAL_STATE_DATA_BITS === BlockTypeIds::LAVA) {
							$handler->changeBlock($x, $y, $z, $b);
							return;
						}
					}
				}
				$handler->changeBlock($x, $y, $z, 0);
				return;
			}

			$multiplier = ($reference[$y] < $reference[$y + 1]) ? 1 : -1;

			for ($i = 0; $i < 3; $i++) { //search 2 blocks up/downwards
				if ($map[$y + $multiplier * $i] !== 0) {
					break;
				}
			}
			if (($i ?? 0) === 3) {
				//no blocks found, setting from neighbours
				foreach ((new Vector3($x, $y, $z))->sidesAroundAxis(Axis::Y) as $side) {
					if (($block = $handler->getBlock($side->getFloorX(), $side->getFloorY(), $side->getFloorZ())) !== 0) {
						$handler->changeBlock($x, $y, $z, $block);
						return;
					}
				}
				return;
			}
			$i = 0;
			while ($i < ($multiplier === 1 ? World::Y_MAX : $y)) {
				if ($map[$y + $multiplier * ($i + 1)] <= $map[$y + $multiplier * $i]) {
					break;
				}
				$i++;
			}
			//original blocks
			$oMax = $y + $multiplier * $i;
			$oMin = $y + $multiplier * ($i - $map[$y + $multiplier * $i] + 1);

			$i = 0;
			while ($i < ($multiplier === 1 ? World::Y_MAX - $y - 1 : $y)) {
				if ($reference[$y + $multiplier * ($i + 1)] <= $reference[$y + $multiplier * $i]) {
					break;
				}
				$i++;
			}
			$nMax = $y + $multiplier * $i;
			$nMin = $y + $multiplier * ($i - $reference[$y + $multiplier * $i] + 1);

			$anchor = $multiplier === 1 ? max($nMax, $oMax) : min($nMax, $oMax);

			$position = ($anchor - $y + $multiplier * 1) / ($anchor - $nMin + $multiplier * 1);
			//absolutely no idea why this can exceed world limitations, but it does?
			$target = (int) min(World::Y_MAX - 1, max(World::Y_MIN, round($anchor + $multiplier * $position * ($anchor - $oMin))));

			if ($map[$target] < 1) {
				return; //avoid populating with air due to merging anchor points
			}

			$handler->copyBlock($x, $y, $z, $x, $target, $z);
		}, $this->context);
	}

	/**
	 * @param int[] $map
	 * @return int[]
	 */
	private function modifyDepthMap(array $map): array
	{
		$start = -1;
		foreach ($map as $y => $depth) {
			if ($depth !== 0) {
				if ($start !== -1) {
					for ($i = $start; $i < $y; $i++) {
						$map[$i] = (int) max(-1, $start - $i, $i - $y + 1);
					}
				}
				$start = -1;
				continue;
			}
			if ($start === -1) {
				$start = $y;
			}
		}
		if ($start !== -1) {
			for ($i = $start; $i < World::Y_MAX; $i++) {
				$map[$i] = (int) max(-1, $start - $i);
			}
		}
		return $map;
	}
}