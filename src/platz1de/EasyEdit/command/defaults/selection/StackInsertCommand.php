<?php

namespace platz1de\EasyEdit\command\defaults\selection;

use platz1de\EasyEdit\command\EasyEditCommand;
use platz1de\EasyEdit\command\KnownPermissions;
use platz1de\EasyEdit\selection\StackedCube;
use platz1de\EasyEdit\session\Session;
use platz1de\EasyEdit\task\editing\selection\StackTask;
use platz1de\EasyEdit\utils\ArgumentParser;

class StackInsertCommand extends EasyEditCommand
{
	public function __construct()
	{
		parent::__construct("/istack", [KnownPermissions::PERMISSION_GENERATE, KnownPermissions::PERMISSION_EDIT]);
	}

	/**
	 * @param Session  $session
	 * @param string[] $args
	 */
	public function process(Session $session, array $args): void
	{
		$selection = $session->getCube();

		StackTask::queue($session, new StackedCube($selection->getWorldName(), $selection->getPos1(), $selection->getPos2(), ArgumentParser::parseDirectionVector($session, $args[0] ?? null, $args[1] ?? null)), $session->asPlayer()->getPosition(), true);
	}
}