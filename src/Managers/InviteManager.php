<?php

	declare(strict_types=1);

	namespace Sharkord\Managers;

	use React\Promise\PromiseInterface;
	use Sharkord\Sharkord;
	use Sharkord\Collections\Invites as InvitesCollection;
	use Sharkord\Models\Invite;

	/**
	 * Class InviteManager
	 *
	 * Manages server invite links via the Sharkord API.
	 *
	 * Accessible via `$sharkord->invites`.
	 *
	 * Invite codes may be generated automatically or supplied manually.
	 * Use `getAll()` to list and cache existing invites, and `create()` to
	 * produce new ones. Deletion is handled on the {@see Invite} model itself
	 * via `$invite->delete()`.
	 *
	 * @package Sharkord\Managers
	 *
	 * @example
	 * ```php
	 * // List all existing invites
	 * $sharkord->invites->getAll()->then(function (array $invites) {
	 *     foreach ($invites as $invite) {
	 *         echo "{$invite->code} — created by {$invite->creator?->name}\n";
	 *     }
	 * });
	 *
	 * // Create an unlimited, non-expiring invite for a specific role
	 * $sharkord->invites->create(roleId: 2)->then(function (\Sharkord\Models\Invite $invite) {
	 *     echo "New invite code: {$invite->code}\n";
	 * });
	 *
	 * // Create a 10-use invite with a specific code and expiry
	 * $sharkord->invites->create(
	 *     maxUses: 10,
	 *     expiresAt: strtotime('+7 days') * 1000,
	 *     code: 'my.custom.code',
	 * )->then(function (\Sharkord\Models\Invite $invite) {
	 *     echo "Invite expires at: " . date('Y-m-d', (int) ($invite->expiresAt / 1000)) . "\n";
	 * });
	 * ```
	 */
	class InviteManager {

		/**
		 * @var InvitesCollection Local cache of invite models.
		 */
		private InvitesCollection $cache;

		/**
		 * InviteManager constructor.
		 *
		 * @param Sharkord $sharkord The main bot instance.
		 */
		public function __construct(
			private readonly Sharkord $sharkord
		) {
			$this->cache = new InvitesCollection($this->sharkord);
		}

		/**
		 * Fetches all server invites from the API and refreshes the local cache.
		 *
		 * The cache is cleared and repopulated on each call to ensure it reflects
		 * the current state of invites on the server.
		 *
		 * @return PromiseInterface Resolves with an array of {@see Invite} objects, rejects on failure.
		 *
		 * @example
		 * ```php
		 * $sharkord->invites->getAll()->then(function (array $invites) {
		 *     echo count($invites) . " invite(s) active.\n";
		 *
		 *     foreach ($invites as $invite) {
		 *         $uses   = $invite->maxUses !== null ? "{$invite->uses}/{$invite->maxUses}" : "{$invite->uses}/∞";
		 *         $expiry = $invite->expiresAt !== null
		 *             ? date('Y-m-d', (int) ($invite->expiresAt / 1000))
		 *             : 'never';
		 *
		 *         echo "  [{$invite->code}] — used {$uses} — expires {$expiry}\n";
		 *     }
		 * });
		 * ```
		 */
		public function getAll(): PromiseInterface {

			return $this->sharkord->gateway->sendRpc("query", [
				"path" => "invites.getAll",
			])->then(function (array $response): array {

				$rawList = $response['data']
					?? throw new \RuntimeException("invites.getAll response missing 'data'.");

				$this->cache->clear();

				foreach ($rawList as $raw) {
					$this->cache->add($raw);
				}

				return array_values($this->cache->all());

			});

		}

		/**
		 * Creates a new server invite via the `invites.add` RPC.
		 *
		 * If no `$code` is provided a cryptographically random 24-character
		 * alphanumeric code is generated automatically.
		 *
		 * @param int         $maxUses   Maximum number of uses. Pass `0` for unlimited.
		 * @param int|null    $expiresAt Expiry timestamp in milliseconds, or null for no expiry.
		 * @param string|null $code      A custom invite code, or null to auto-generate one.
		 * @param int|null    $roleId    ID of the role to assign on join, or null for the server default.
		 * @return PromiseInterface Resolves with the new {@see Invite} object, rejects on failure.
		 *
		 * @example
		 * ```php
		 * // Auto-generated code, unlimited uses, no expiry, default role
		 * $sharkord->invites->create()->then(function (\Sharkord\Models\Invite $invite) {
		 *     echo "Created: {$invite->code}\n";
		 * });
		 *
		 * // Custom code, 5 uses, expires in 24 hours, specific role
		 * $sharkord->invites->create(
		 *     maxUses: 5,
		 *     expiresAt: (time() + 86400) * 1000,
		 *     code: 'welcome.2026',
		 *     roleId: 3,
		 * )->then(function (\Sharkord\Models\Invite $invite) {
		 *     echo "Invite ready: {$invite->code}\n";
		 * });
		 * ```
		 */
		public function create(
			int     $maxUses   = 0,
			?int    $expiresAt = null,
			?string $code      = null,
			?int    $roleId    = null,
		): PromiseInterface {

			$code ??= $this->generateCode();

			$input = [
				'maxUses'   => $maxUses,
				'expiresAt' => $expiresAt ?? 0,
				'code'      => $code,
			];

			if ($roleId !== null) {
				$input['roleId'] = $roleId;
			}

			return $this->sharkord->gateway->sendRpc("mutation", [
				"input" => $input,
				"path"  => "invites.add",
			])->then(function (array $response): Invite {

				$raw = $response['data']
					?? throw new \RuntimeException("invites.add response missing 'data'.");

				$this->cache->add($raw);

				return $this->cache->get((int) $raw['id'])
					?? throw new \RuntimeException("Failed to retrieve newly created invite from cache.");

			});

		}

		/**
		 * Retrieves a cached invite by ID without making an API request.
		 *
		 * Returns null if the invite is not in the local cache. Call
		 * `getAll()` first to populate the cache if needed.
		 *
		 * @param int $id The invite ID.
		 * @return Invite|null The cached Invite model, or null if not found.
		 *
		 * @example
		 * ```php
		 * $sharkord->invites->getAll()->then(function () use ($sharkord) {
		 *     $invite = $sharkord->invites->get(5);
		 *
		 *     if ($invite) {
		 *         echo "Found: {$invite->code}\n";
		 *     }
		 * });
		 * ```
		 */
		public function get(int $id): ?Invite {

			return $this->cache->get($id);

		}

		/**
		 * Returns the underlying Invites collection.
		 *
		 * @return InvitesCollection
		 *
		 * @example
		 * ```php
		 * foreach ($sharkord->invites->collection() as $id => $invite) {
		 *     echo "{$id}: {$invite->code}\n";
		 * }
		 * ```
		 */
		public function collection(): InvitesCollection {

			return $this->cache;

		}

		/**
		 * Generates a cryptographically random alphanumeric invite code.
		 *
		 * @param int $length The desired code length. Defaults to 24.
		 * @return string
		 */
		private function generateCode(int $length = 24): string {

			$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
			$code  = '';
			$bytes = random_bytes($length);

			for ($i = 0; $i < $length; $i++) {
				$code .= $chars[ord($bytes[$i]) % 62];
			}

			return $code;

		}

	}

?>