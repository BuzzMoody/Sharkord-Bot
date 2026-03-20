<?php

	declare(strict_types=1);

	namespace Sharkord;

	use React\EventLoop\LoopInterface;
	use React\EventLoop\TimerInterface;

	/**
	 * Class Scheduler
	 *
	 * Named, cancellable timer registry built on top of ReactPHP's event loop.
	 *
	 * All timers are identified by a unique string name. Registering a new timer
	 * under an existing name silently cancels the previous one first, preventing
	 * duplicate timers from accumulating across reconnects or re-registrations.
	 *
	 * Available as {@see \Sharkord\Sharkord::$scheduler} on the bot instance.
	 *
	 * @package Sharkord
	 *
	 * @example
	 * ```php
	 * // Broadcast a message every 60 seconds.
	 * $sharkord->on(\Sharkord\Events::READY, function() use ($sharkord): void {
	 *     $sharkord->scheduler->every(60.0, 'heartbeat', function() use ($sharkord): void {
	 *         $sharkord->channels->get('status')?->sendMessage('Still alive!');
	 *     });
	 * });
	 *
	 * // Cancel the timer later.
	 * $sharkord->scheduler->cancel('heartbeat');
	 * ```
	 */
	final class Scheduler {

		/**
		 * @var array<string, TimerInterface> Active timers keyed by name.
		 */
		private array $timers = [];

		/**
		 * Scheduler constructor.
		 *
		 * @param LoopInterface $loop The ReactPHP event loop.
		 */
		public function __construct(
			private readonly LoopInterface $loop
		) {}

		/**
		 * Registers a repeating timer that fires every N seconds.
		 *
		 * If a timer with the given name already exists, it is cancelled before
		 * the new one is registered. The callback receives no arguments.
		 *
		 * @param float    $seconds  Interval between invocations in seconds.
		 * @param string   $name     Unique identifier for this timer.
		 * @param callable $callback The function to invoke on each tick.
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->scheduler->every(30.0, 'status-ping', function() use ($sharkord): void {
		 *     $sharkord->channels->get('general')?->sendMessage('Bot is running.');
		 * });
		 * ```
		 */
		public function every(float $seconds, string $name, callable $callback): void {

			$this->cancel($name);

			$this->timers[$name] = $this->loop->addPeriodicTimer($seconds, $callback);

		}

		/**
		 * Registers a one-shot timer that fires once after N seconds.
		 *
		 * The timer is automatically removed from the registry once it fires.
		 * If a timer with the given name already exists, it is cancelled first.
		 *
		 * @param float    $seconds  Delay before the callback fires in seconds.
		 * @param string   $name     Unique identifier for this timer.
		 * @param callable $callback The function to invoke once.
		 * @return void
		 *
		 * @example
		 * ```php
		 * // Send a reminder 5 minutes after the bot starts.
		 * $sharkord->scheduler->after(300.0, 'startup-notice', function() use ($sharkord): void {
		 *     $sharkord->channels->get('general')?->sendMessage('Bot has been running for 5 minutes!');
		 * });
		 * ```
		 */
		public function after(float $seconds, string $name, callable $callback): void {

			$this->cancel($name);

			$this->timers[$name] = $this->loop->addTimer($seconds, function() use ($name, $callback): void {
				unset($this->timers[$name]);
				($callback)();
			});

		}

		/**
		 * Cancels a registered timer by name.
		 *
		 * Does nothing if no timer with the given name is registered.
		 *
		 * @param string $name The timer name to cancel.
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->scheduler->cancel('status-ping');
		 * ```
		 */
		public function cancel(string $name): void {

			if (!isset($this->timers[$name])) {
				return;
			}

			$this->loop->cancelTimer($this->timers[$name]);

			unset($this->timers[$name]);

		}

		/**
		 * Returns whether a timer with the given name is currently active.
		 *
		 * @param string $name The timer name to check.
		 * @return bool
		 *
		 * @example
		 * ```php
		 * if (!$sharkord->scheduler->has('status-ping')) {
		 *     $sharkord->scheduler->every(60.0, 'status-ping', $callback);
		 * }
		 * ```
		 */
		public function has(string $name): bool {

			return isset($this->timers[$name]);

		}

		/**
		 * Returns the names of all currently active timers.
		 *
		 * @return string[]
		 *
		 * @example
		 * ```php
		 * foreach ($sharkord->scheduler->active() as $name) {
		 *     echo "Active timer: {$name}\n";
		 * }
		 * ```
		 */
		public function active(): array {

			return array_keys($this->timers);

		}

		/**
		 * Cancels all active timers.
		 *
		 * @return void
		 *
		 * @example
		 * ```php
		 * // Tear everything down cleanly on disconnect.
		 * $sharkord->scheduler->cancelAll();
		 * ```
		 */
		public function cancelAll(): void {

			foreach (array_keys($this->timers) as $name) {
				$this->cancel($name);
			}

		}

	}

?>