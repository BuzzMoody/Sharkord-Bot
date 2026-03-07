<?php

	declare(strict_types=1);

	namespace Tests\Models;

	use PHPUnit\Framework\TestCase;
	use Sharkord\Models\Server;
	use Sharkord\Sharkord;

	class ServerTest extends TestCase
	{
		private Sharkord $sharkordMock;

		protected function setUp(): void
		{
			$this->sharkordMock = $this->createMock(Sharkord::class);
		}

		public function testServerCreationAndIdAlias(): void
		{
			$rawData = ['serverId' => 'srv_789', 'name' => 'My Server', 'region' => 'us-east'];
			$server = Server::fromArray($rawData, $this->sharkordMock);

			// Test the custom alias logic in __get
			$this->assertEquals('srv_789', $server->id);
			
			// Test standard attributes
			$this->assertEquals('srv_789', $server->serverId);
			$this->assertEquals('My Server', $server->name);
			
			$this->assertEquals($rawData, $server->toArray());
		}
	}
	
?>