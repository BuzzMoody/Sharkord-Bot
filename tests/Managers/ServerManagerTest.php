<?php
	
	declare(strict_types=1);

	namespace Tests\Managers;

	use PHPUnit\Framework\TestCase;
	use Sharkord\Managers\ServerManager;
	use Sharkord\Models\Server;
	use Sharkord\Sharkord;

	class ServerManagerTest extends TestCase
	{
		private Sharkord $sharkordMock;

		protected function setUp(): void
		{
			$this->sharkordMock = $this->createMock(Sharkord::class);
		}

		public function testHydrateCreatesNewServer(): void
		{
			$manager = new ServerManager($this->sharkordMock);
			
			$manager->hydrate([
				'serverId' => 'srv_123',
				'name' => 'My Cool Server'
			]);
			
			$server = $manager->get('srv_123');
			
			$this->assertNotNull($server);
			$this->assertInstanceOf(Server::class, $server);
			
			// Testing the magic __get routing $server->id to serverId
			$this->assertEquals('srv_123', $server->id); 
			$this->assertEquals('My Cool Server', $server->name);
		}

		public function testHydrateUpdatesExistingServer(): void
		{
			$manager = new ServerManager($this->sharkordMock);
			
			// First hydrate creates it
			$manager->hydrate(['serverId' => 'srv_456', 'name' => 'Original Name']);
			// Second hydrate should update the existing instance
			$manager->hydrate(['serverId' => 'srv_456', 'name' => 'Updated Name', 'memberCount' => 50]);
			
			$server = $manager->get('srv_456');
			
			$this->assertEquals('Updated Name', $server->name);
			$this->assertEquals(50, $server->memberCount);
			
			// Make sure it didn't create a duplicate
			$this->assertSame($server, $manager->getFirst()); 
		}

		public function testUpdateServer(): void
		{
			$manager = new ServerManager($this->sharkordMock);
			$manager->hydrate(['serverId' => 'srv_789', 'name' => 'Test Server']);
			
			// Ensure update only affects the specified keys
			$manager->update(['serverId' => 'srv_789', 'status' => 'offline']);
			
			$server = $manager->get('srv_789');
			$this->assertEquals('Test Server', $server->name);
			$this->assertEquals('offline', $server->status);
		}

		public function testDeleteServer(): void
		{
			$manager = new ServerManager($this->sharkordMock);
			$manager->hydrate(['serverId' => 'srv_999']);
			
			$this->assertNotNull($manager->get('srv_999'));
			
			$manager->delete('srv_999');
			$this->assertNull($manager->get('srv_999'), 'Server should be removed from cache');
		}

		public function testGetFirstReturnsNullWhenEmpty(): void
		{
			$manager = new ServerManager($this->sharkordMock);
			$this->assertNull($manager->getFirst());
		}
	}
	
?>