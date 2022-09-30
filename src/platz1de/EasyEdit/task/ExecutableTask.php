<?php

namespace platz1de\EasyEdit\task;

use InvalidArgumentException;
use platz1de\EasyEdit\thread\EditThread;
use platz1de\EasyEdit\thread\output\OutputData;
use platz1de\EasyEdit\utils\ExtendedBinaryStream;
use Thread;

abstract class ExecutableTask
{
	private static int $id = 0;
	private int $taskId;
	private bool $defaultHandler = true;

	public function __construct()
	{
		$this->taskId = Thread::getCurrentThread() instanceof EditThread ? -1 : ++self::$id;
	}

	/**
	 * @param ExecutableTask $task
	 * @param bool           $useDefaultHandler
	 */
	public function executeAssociated(ExecutableTask $task, bool $useDefaultHandler = true): void
	{
		$this->taskId = $task->getTaskId();
		$this->defaultHandler = $useDefaultHandler;
		$this->execute();
	}

	abstract public function execute(): void;

	/**
	 * @param ExtendedBinaryStream $stream
	 */
	abstract public function putData(ExtendedBinaryStream $stream): void;

	/**
	 * @param ExtendedBinaryStream $stream
	 */
	abstract public function parseData(ExtendedBinaryStream $stream): void;

	/**
	 * @param OutputData $data
	 */
	public function sendOutputPacket(OutputData $data): void
	{
		if ($this->taskId === -1) {
			throw new InvalidArgumentException("Cannot send output data for executor tasks");
		}
		$data->setTaskId($this->taskId);
		if ($data->checkSend()) {
			EditThread::getInstance()->sendOutput($data);
		}
		EditThread::getInstance()->getStats()->updateProgress($this->getProgress());
	}

	/**
	 * @return string
	 */
	public function fastSerialize(): string
	{
		$stream = new ExtendedBinaryStream();
		$stream->putString(igbinary_serialize($this) ?? "");
		$this->putData($stream);
		return $stream->getBuffer();
	}

	/**
	 * @param string $data
	 * @return ExecutableTask
	 */
	public static function fastDeserialize(string $data): ExecutableTask
	{
		$stream = new ExtendedBinaryStream($data);
		/** @var ExecutableTask $task */
		$task = igbinary_unserialize($stream->getString());
		$task->parseData($stream);
		return $task;
	}

	/**
	 * @return array{int}
	 */
	public function __serialize(): array
	{
		return [$this->taskId];
	}

	/**
	 * @param array{int} $data
	 */
	public function __unserialize(array $data): void
	{
		$this->taskId = $data[0];
	}

	/**
	 * @return string
	 */
	abstract public function getTaskName(): string;

	/**
	 * @return float
	 */
	abstract public function getProgress(): float;

	/**
	 * @return int
	 */
	public function getTaskId(): int
	{
		return $this->taskId;
	}

	/**
	 * @return bool
	 */
	public function useDefaultHandler(): bool
	{
		return $this->defaultHandler;
	}
}