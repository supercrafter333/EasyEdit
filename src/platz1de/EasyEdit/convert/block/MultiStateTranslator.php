<?php

namespace platz1de\EasyEdit\convert\block;

use platz1de\EasyEdit\utils\BlockParser;
use pocketmine\data\bedrock\block\BlockStateData;
use UnexpectedValueException;

/**
 * Results in multiple block types, differentiated by a single state
 */
class MultiStateTranslator extends BaseStateTranslator
{
	private string $multiState;
	/**
	 * @var SingularStateTranslator[]
	 */
	private array $multiTranslations = [];

	/**
	 * @param array<string, mixed> $data
	 */
	public function __construct(array $data)
	{
		parent::__construct($data);
		if (!isset($data["multi_name"]) || !is_string($data["multi_name"])) {
			throw new UnexpectedValueException("Missing multi_name");
		}
		$this->multiState = $data["multi_name"];

		$multi = $data["multi_states"] ?? null;
		if (!is_array($multi)) {
			throw new UnexpectedValueException("Missing multi_states");
		}
		foreach ($multi as $value => $multiData) {
			$this->multiTranslations[$value] = new SingularStateTranslator($multiData);
		}
	}

	/**
	 * @param BlockStateData $state
	 * @return BlockStateData
	 */
	public function translate(BlockStateData $state): BlockStateData
	{
		$state = parent::translate($state);
		$states = $state->getStates();
		$value = BlockParser::tagToStringValue($states[$this->multiState]);
		unset($states[$this->multiState]);
		if (isset($this->multiTranslations[$value])) {
			$multi = $this->multiTranslations[$value];
		} elseif (isset($this->multiTranslations["default"])) {
			$multi = $this->multiTranslations["default"];
		} else {
			throw new UnexpectedValueException("No valid multi translation for $value found");
		}
		return $multi->translate(new BlockStateData($state->getName(), $states, $state->getVersion()));
	}
}