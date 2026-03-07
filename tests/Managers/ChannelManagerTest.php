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
			
			// Use hydrate instead of add
			$manager->hydrate(['id' => 'chan_123', 'name' => 'general', 'type' => 'text']);
			
			$retrieved = $manager->get('chan_123');
			
			$this->assertNotNull($retrieved);
			$this->assertEquals('chan_123', $retrieved->id);
			$this->assertEquals('general', $retrieved->name);
		}

		public function testHasChannel(): void
		{
			$manager = new ChannelManager($this->sharkordMock);
			$manager->hydrate(['id' => 'chan_456', 'name' => 'test']);
			
			$this->assertTrue($manager->has('chan_456'));
			$this->assertFalse($manager->has('chan_999'));
		}

		public function testRemoveChannel(): void
		{
			$manager = new ChannelManager($this->sharkordMock);
			$manager->hydrate(['id' => 'chan_789', 'name' => 'test2']);
			
			$this->assertTrue($manager->has('chan_789'));
			
			$manager->remove('chan_789');
			$this->assertFalse($manager->has('chan_789'));
		}
	}

?>