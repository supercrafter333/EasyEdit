<?php

namespace platz1de\EasyEdit\task\benchmark;

use Closure;
use platz1de\EasyEdit\EasyEdit;
use platz1de\EasyEdit\result\BenchmarkTaskResult;
use platz1de\EasyEdit\session\Session;
use platz1de\EasyEdit\utils\MixedUtils;
use pocketmine\scheduler\TaskHandler;
use pocketmine\Server;
use pocketmine\utils\Utils;
use pocketmine\world\WorldCreationOptions;
use UnexpectedValueException;

class BenchmarkManager
{
	private static bool $running = false;
	private static int $autoSave;
	private static TaskHandler $task;
	private static Closure $closure;
	private static bool $cleanup;

	/**
	 * @param Session $session
	 * @param Closure $closure
	 * @param bool    $deleteWorldAfter Useful when testing core functions
	 */
	public static function start(Session $session, Closure $closure, bool $deleteWorldAfter = true): void
	{
		if (self::$running) {
			throw new UnexpectedValueException("Benchmark is already running");
		}
		Utils::validateCallableSignature(static function (float $tpsAvg, float $tpsMin, float $loadAvg, float $loadMax, int $tasks, float $time, array $results): void {}, $closure);

		self::$closure = $closure;
		self::$cleanup = $deleteWorldAfter;
		self::$running = true;
		self::$autoSave = MixedUtils::setAutoSave(PHP_INT_MAX);
		self::$task = EasyEdit::getInstance()->getScheduler()->scheduleRepeatingTask(new BenchmarkTask(), 1);

		$name = "EasyEdit-Benchmark-" . time();
		Server::getInstance()->getWorldManager()->generateWorld($name, WorldCreationOptions::create(), false);
		$session->runTask(new BenchmarkExecutor($name))->then(static function (BenchmarkTaskResult $results): void {
			self::benchmarkCallback($results->getWorld(), $results->getResults());
		})->update(static function (int $progress) use ($session): void {
			$session->sendMessage("benchmark-progress", ["{done}" => (string) $progress, "{total}" => "4"]);
		});
	}

	/**
	 * @param string                                  $worldName
	 * @param array<array{string, float, int, float}> $results
	 * @internal
	 */
	public static function benchmarkCallback(string $worldName, array $results): void
	{
		self::$task->cancel();
		/**
		 * @var BenchmarkTask         $benchmark
		 * @phpstan-var BenchmarkTask $benchmark
		 */
		$benchmark = self::$task->getTask();
		$time = array_sum(array_map(static function (array $dat): float {
			return $dat[3];
		}, $results));
		$closure = self::$closure;
		$closure($benchmark->getTpsTotal(), $benchmark->getTpsMin(), $benchmark->getLoadTotal(), $benchmark->getLoadMax(), count($results), $time, $results);

		if (self::$cleanup) {
			$world = Server::getInstance()->getWorldManager()->getWorldByName($worldName);
			if ($world === null) {
				throw new UnexpectedValueException("Couldn't clean after benchmark, world " . $worldName . " doesn't exist");
			}
			$path = $world->getProvider()->getPath();
			Server::getInstance()->getWorldManager()->unloadWorld($world);
			MixedUtils::deleteDir($path);
		}

		self::$running = false;
		MixedUtils::setAutoSave(self::$autoSave);
	}

	/**
	 * @return bool
	 */
	public static function isRunning(): bool
	{
		return self::$running;
	}
}