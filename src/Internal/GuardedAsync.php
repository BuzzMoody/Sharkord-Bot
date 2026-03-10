<?php

	declare(strict_types=1);

	namespace Sharkord\Internal;

	use React\Promise\PromiseInterface;
	use function React\Promise\reject;

	/**
	 * Trait GuardedAsync
	 *
	 * Provides a wrapper that executes an async callable and converts any
	 * RuntimeException thrown by Guard checks into a rejected Promise,
	 * keeping model methods free of repetitive try/catch boilerplate.
	 *
	 * @package Sharkord\Internal
	 */
	trait GuardedAsync {

		/**
		 * Executes a callable and converts any RuntimeException into a rejected PromiseInterface.
		 *
		 * The callable should perform Guard checks and return a PromiseInterface.
		 * Any RuntimeException thrown — typically from Guard — will be caught and
		 * returned as a rejected Promise rather than propagating as a thrown exception.
		 *
		 * @param callable(): PromiseInterface $fn The async callable to execute.
		 * @return PromiseInterface Resolves with the callable's result, or rejects on Guard failure.
		 */
		private function guardedAsync(callable $fn): PromiseInterface {

			try {
				return $fn();
			} catch (\Throwable $e) {
				return reject($e);
			}

		}

	}
	
?>