<?php

	declare(strict_types=1);

	namespace Tests\Managers;

	use PHPUnit\Framework\TestCase;
	use Sharkord\Managers\ChannelManager;
	use Sharkord\Models\Channel;
	use Sharkord\Sharkord;

	class ChannelManagerTest extends TestCase
	{
		private Sharkord $sharkordMock;

		protected function setUp(): void
		{
			// Mock the main Sharkord instance to pass into the manager and models
			$this->sharkordMock = $this->createMock(Sharkord::class);
		}

		public function testAddAndGetChannel(): void
		{
			$manager = new ChannelManager($this->sharkordMock);
			
			$channelData = ['id' => 'chan_123', 'name' => 'general', 'type' => 'text'];
			$channel = new Channel($this->sharkordMock, $channelData);
			
			$manager->add($channel);
			
			$retrieved = $manager->get('chan_123');
			
			$this->assertNotNull($retrieved);
			$this->assertEquals('chan_123', $retrieved->id);
			$this->assertEquals('general', $retrieved->name);
		}

		public function testHasChannel(): void
		{
			$manager = new ChannelManager($this->sharkordMock);
			$channel = new Channel($this->sharkordMock, ['id' => 'chan_456']);
			
			$manager->add($channel);
			
			$this->assertTrue($manager->has('chan_456'));
			$this->assertFalse($manager->has('chan_999'), 'Manager should return false for non-existent channels');
		}

		public function testRemoveChannel(): void
		{
			$manager = new ChannelManager($this->sharkordMock);
			$channel = new Channel($this->sharkordMock, ['id' => 'chan_789']);
			
			$manager->add($channel);
			$this->assertTrue($manager->has('chan_789'));
			
			$manager->remove('chan_789');
			$this->assertFalse($manager->has('chan_789'), 'Channel should be removed from the manager');
		}
	}
	
?>