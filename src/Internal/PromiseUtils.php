<?php

	declare(strict_types=1);

	namespace Sharkord\Internal;

	/**
	 * Class PromiseUtils
	 *
	 * Utility helpers for working with ReactPHP Promises.
	 *
	 * @package Sharkord\Internal
	 */
	class PromiseUtils {

		/**
		 * Converts any Promise rejection reason to a human-readable string.
		 *
		 * Promise rejections may be Throwable instances, plain strings, or arbitrary
		 * values. This method normalises all cases for safe logging.
		 *
		 * @param mixed $reason The rejection reason.
		 * @return string A human-readable representation of the rejection reason.
		 */
		public static function reasonToString(mixed $reason): string {

			return match (true) {
				$reason instanceof \Throwable => $reason->getMessage(),
				is_string($reason)            => $reason,
				default                       => json_encode($reason),
			};

		}

	}
	
?>