<?php

	declare(strict_types=1);

	namespace Tests\Models;

	use PHPUnit\Framework\TestCase;
	use Sharkord\Models\Role;
	use Sharkord\Sharkord;

	class RoleTest extends TestCase
	{
		private Sharkord $sharkordMock;

		protected function setUp(): void
		{
			$this->sharkordMock = $this->createMock(Sharkord::class);
		}

		public function testRoleCreationAndAttributeReading(): void
		{
			$rawData = ['id' => 10, 'name' => 'Admin', 'color' => '#FF0000'];
			$role = Role::fromArray($rawData, $this->sharkordMock);

			$this->assertEquals(10, $role->id);
			$this->assertEquals('Admin', $role->name);
			$this->assertEquals('#FF0000', $role->color);
			
			$this->assertEquals($rawData, $role->toArray());
		}

		public function testRoleUpdateFromArray(): void
		{
			$role = new Role($this->sharkordMock, ['id' => 10, 'color' => '#000000']);
			$role->updateFromArray(['color' => '#FFFFFF']);
			
			$this->assertEquals('#FFFFFF', $role->color);
		}
	}
	
?>