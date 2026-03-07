<?php

	declare(strict_types=1);

	namespace Tests\HTTP;

	use PHPUnit\Framework\TestCase;
	use Sharkord\HTTP\Client;
	use React\EventLoop\LoopInterface;
	use React\Http\Browser;
	use React\Promise\Promise;
	use Psr\Log\LoggerInterface;
	use Psr\Http\Message\ResponseInterface;
	use Psr\Http\Message\StreamInterface;

	class ClientTest extends TestCase
	{
		private array $config;
		private $loopMock;
		private $loggerMock;

		protected function setUp(): void
		{
			$this->config = [
				'host' => 'sharkord.example.com',
				'identity' => 'test_user',
				'password' => 'secret123'
			];
			
			$this->loopMock = $this->createMock(LoopInterface::class);
			$this->loggerMock = $this->createMock(LoggerInterface::class);
		}

		/**
		 * Helper method to inject a mocked Browser into the Client 
		 * since it is instantiated directly in the constructor.
		 */
		private function injectBrowserMock(Client $client, $browserMock): void
		{
			$reflection = new \ReflectionClass($client);
			$property = $reflection->getProperty('browser');
			$property->setAccessible(true);
			$property->setValue($client, $browserMock);
		}

		public function testAuthenticateSuccessReturnsToken(): void
		{
			$client = new Client($this->config, $this->loopMock, $this->loggerMock);
			$browserMock = $this->createMock(Browser::class);
			
			$responseMock = $this->createMock(ResponseInterface::class);
			$streamMock = $this->createMock(StreamInterface::class);
			
			// Mock the PSR-7 Stream interface to return our fake JSON response
			$streamMock->method('__toString')->willReturn(json_encode(['token' => 'valid_token_123']));
			$responseMock->method('getBody')->willReturn($streamMock);

			// Expect the exact POST request payload your code generates
			$browserMock->expects($this->once())
				->method('post')
				->with(
					'https://sharkord.example.com/login',
					['Content-Type' => 'application/json'],
					json_encode(['identity' => 'test_user', 'password' => 'secret123'])
				)
				->willReturn(new Promise(function($resolve) use ($responseMock) {
					$resolve($responseMock);
				}));

			$this->injectBrowserMock($client, $browserMock);

			$promise = $client->authenticate();
			
			$retrievedToken = null;
			$promise->then(function($token) use (&$retrievedToken) {
				$retrievedToken = $token;
			});

			// The promise should have resolved synchronously in our test with the token
			$this->assertEquals('valid_token_123', $retrievedToken);
		}

		public function testAuthenticateThrowsExceptionOnMissingToken(): void
		{
			$client = new Client($this->config, $this->loopMock, $this->loggerMock);
			$browserMock = $this->createMock(Browser::class);
			
			$responseMock = $this->createMock(ResponseInterface::class);
			$streamMock = $this->createMock(StreamInterface::class);
			
			// Return JSON without the 'token' key
			$streamMock->method('__toString')->willReturn(json_encode(['error' => 'Invalid credentials']));
			$responseMock->method('getBody')->willReturn($streamMock);

			$browserMock->method('post')->willReturn(new Promise(function($resolve) use ($responseMock) {
				$resolve($responseMock);
			}));

			$this->injectBrowserMock($client, $browserMock);

			$promise = $client->authenticate();
			
			$expectedException = null;
			$promise->then(null, function(\Exception $e) use (&$expectedException) {
				$expectedException = $e;
			});

			$this->assertInstanceOf(\RuntimeException::class, $expectedException);
			$this->assertEquals('No token in response', $expectedException->getMessage());
		}

		public function testAuthenticateHandlesHttpErrors(): void
		{
			$client = new Client($this->config, $this->loopMock, $this->loggerMock);
			$browserMock = $this->createMock(Browser::class);
			
			// Simulate an HTTP rejection (e.g. 401 Unauthorized or network down)
			$browserMock->method('post')->willReturn(new Promise(function($resolve, $reject) {
				$reject(new \RuntimeException('HTTP 401 Unauthorized'));
			}));

			// Your code specifically logs an error on failure, let's test that!
			$this->loggerMock->expects($this->once())
				->method('error')
				->with($this->stringContains('HTTP Auth Failed:'));

			$this->injectBrowserMock($client, $browserMock);

			$promise = $client->authenticate();
			
			$expectedException = null;
			$promise->then(null, function(\Exception $e) use (&$expectedException) {
				$expectedException = $e;
			});

			$this->assertInstanceOf(\RuntimeException::class, $expectedException);
			$this->assertEquals('HTTP 401 Unauthorized', $expectedException->getMessage());
		}
	}

?>