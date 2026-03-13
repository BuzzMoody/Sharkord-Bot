<?php

	declare(strict_types=1);

	namespace Sharkord\Models;

	/**
	 * Class Attachment
	 *
	 * Represents a file or media asset stored on the Sharkord server.
	 *
	 * Returned as nested objects in payloads such as server logos and user
	 * avatars. This is a read-only data model — attachments are managed
	 * through dedicated storage endpoints, not through this class.
	 *
	 * @property-read int         $id           Unique attachment ID.
	 * @property-read string      $name         Stored filename on the server.
	 * @property-read string      $originalName The original filename as uploaded by the user.
	 * @property-read string      $md5          MD5 hash of the file contents.
	 * @property-read int         $userId       ID of the user who uploaded the file.
	 * @property-read int         $size         File size in bytes.
	 * @property-read string      $mimeType     MIME type (e.g. 'image/gif').
	 * @property-read string      $extension    File extension including the leading dot (e.g. '.gif').
	 * @property-read int         $createdAt    Upload timestamp in milliseconds.
	 * @property-read int|null    $updatedAt    Last updated timestamp in milliseconds, or null.
	 *
	 * @package Sharkord\Models
	 *
	 * @example
	 * ```php
	 * $sharkord->servers->getSettings()->then(function (\Sharkord\Models\ServerSettings $settings) {
	 *     if ($settings->logo) {
	 *         echo "Logo: {$settings->logo->originalName}\n";
	 *         echo "Type: {$settings->logo->mimeType}\n";
	 *         echo "Size: {$settings->logo->size} bytes\n";
	 *     }
	 * });
	 * ```
	 */
	class Attachment {

		/**
		 * @var array<string, mixed> Raw attachment data from the API.
		 */
		private array $attributes = [];

		/**
		 * Attachment constructor.
		 *
		 * @param array<string, mixed> $rawData Raw attachment data from the API.
		 */
		public function __construct(array $rawData) {
			$this->attributes = $rawData;
		}

		/**
		 * Factory method to create an Attachment from a raw API data array.
		 *
		 * @param array<string, mixed> $raw The raw attachment payload.
		 * @return self
		 */
		public static function fromArray(array $raw): self {
			return new self($raw);
		}

		/**
		 * Returns the attachment data as a plain array.
		 *
		 * @return array<string, mixed>
		 */
		public function toArray(): array {
			return $this->attributes;
		}

		/**
		 * Magic getter for attachment properties.
		 *
		 * @param string $name Property name.
		 * @return mixed
		 */
		public function __get(string $name): mixed {
			return $this->attributes[$name] ?? null;
		}

		/**
		 * Magic isset check.
		 *
		 * @param string $name Property name.
		 * @return bool
		 */
		public function __isset(string $name): bool {
			return isset($this->attributes[$name]);
		}

	}

?>