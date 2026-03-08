<?php

	declare(strict_types=1);

	namespace Tests\Managers;

	use PHPUnit\Framework\TestCase;
	use Sharkord\Managers\ChannelManager;
	use Sharkord\Sharkord;

	class ChannelManagerTest extends TestCase
	{
		private Sharkord $sharkordMock;

		protected function setUp(): void
		{
			$this->sharkordMock = $this->createMock(Sharkord::class);
		}

		public function testAddAndGetChannel(): void
		{
			$manager = new ChannelManager($this->sharkordMock);
			
			// Use integer IDs since get() strictly checks array keys for ints, 
			// but searches by 'name' if a string is provided!
			$manager->hydrate(['id' => 123, 'name' => 'general', 'type' => 'text']);
			
			$retrievedById = $manager->get(123);
			$this->assertNotNull($retrievedById);
			$this->assertEquals(123, $retrievedById->id);
			
			$retrievedByName = $manager->get('general');
			$this->assertNotNull($retrievedByName);
			$this->assertEquals(123, $retrievedByName->id);
		}

		public function testDeleteChannel(): void
		{
			$manager = new ChannelManager($this->sharkordMock);
			$manager->hydrate(['id' => 789, 'name' => 'test_delete']);
			
			$this->assertNotNull($manager->get(789));
			
			$manager->delete(789); // ChannelManager uses delete(), not remove()
			
			$this->assertNull($manager->get(789));
		}
	}

?>