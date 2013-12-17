<?php

use Moaction\Jsonrpc\Common\Error;
use Moaction\Jsonrpc\Common\Request;
use Moaction\Jsonrpc\Common\Response;
use Moaction\Jsonrpc\Server\BasicServer;

class BasicServerTest extends PHPUnit_Framework_TestCase
{
	/**
	 * @param array $methods
	 * @return PHPUnit_Framework_MockObject_MockObject|BasicServer
	 */
	public function getServerMock($methods = array())
	{
		return $this->getMockBuilder('\Moaction\Jsonrpc\Server\BasicServer')
			->setMethods($methods)
			->getMock();
	}

	/**
	 * @covers \Moaction\Jsonrpc\Server\BasicServer::sortParams
	 */
	public function testSortParams()
	{
		$server = new BasicServer();

		$params = array(
			'a' => 5,
			'b' => 'string',
			'c' => array('x'),
		);
		$master = array('c', 'a', 'b');
		$expected = array(
			array('x'),
			5,
			'string',
		);

		$reflectionObj = new ReflectionObject($server);
		$reflectionMethod = $reflectionObj->getMethod('sortParams');
		$reflectionMethod->setAccessible(true);
		$result = $reflectionMethod->invoke($server, $params, $master);
		$this->assertEquals($expected, $result);

		$params = array(
			'd' => 1,
			'e' => 'error',
		);
		$master = array('d');

		$this->setExpectedException('\InvalidArgumentException', 'Unexpected parameter e');
		$reflectionMethod->invoke($server, $params, $master);
	}


	/**
	 * @covers       \Moaction\Jsonrpc\Server\BasicServer::getRequiredParams
	 * @dataProvider providerTestGetRequiredParams
	 */
	public function testGetRequiredParams($method, $expected)
	{
		$method = new ReflectionFunction($method);
		$params = $method->getParameters();

		$server = $this->getServerMock(array('getParams'));
		$server->expects($this->any())
			->method('getParams')
			->will($this->returnValue($params));

		$reflectionObj = new ReflectionObject($server);
		$reflectionMethod = $reflectionObj->getMethod('getRequiredParams');
		$reflectionMethod->setAccessible(true);
		$result = $reflectionMethod->invoke($server, 'method');
		$this->assertEquals($expected, $result);
	}

	/**
	 * @return array
	 */
	public function providerTestGetRequiredParams()
	{
		$noArgs = function () {
		};
		$optionalArg = function ($a = null) {
		};
		$oneArg = function ($b, $c = null) {
		};
		$severalArgs = function ($d, $e, $f = null) {
		};

		return array(
			'No args'      => array($noArgs, array()),
			'Optional arg' => array($optionalArg, array()),
			'One arg'      => array($oneArg, array('b')),
			'Several args' => array($severalArgs, array('d', 'e')),
		);
	}

	/**
	 * @covers \Moaction\Jsonrpc\Server\BasicServer::getAllParams
	 */
	public function testGetAllParams()
	{
		$method = new ReflectionFunction(function ($a, $b = null) {
		});
		$params = $method->getParameters();

		$server = $this->getServerMock(array('getParams'));
		$server->expects($this->any())
			->method('getParams')
			->will($this->returnValue($params));

		$reflectionObj = new ReflectionObject($server);
		$reflectionMethod = $reflectionObj->getMethod('getAllParams');
		$reflectionMethod->setAccessible(true);
		$result = $reflectionMethod->invoke($server, 'method');

		$this->assertEquals(array('a', 'b'), $result);
	}

	/**
	 * @covers \Moaction\Jsonrpc\Server\BasicServer::getParams
	 * @covers \Moaction\Jsonrpc\Server\BasicServer::addMethod
	 */
	public function testGetParams()
	{
		$method = function($a, $b = null) {};
		$reflection = new ReflectionFunction($method);

		$server = new BasicServer();
		$server->addMethod('test', $method);

		$reflectionObj = new ReflectionObject($server);
		$reflectionMethod = $reflectionObj->getMethod('getParams');
		$reflectionMethod->setAccessible(true);

		$result = $reflectionMethod->invoke($server, 'test');
		$this->assertEquals($reflection->getParameters(), $result);
	}

	/**
	 * @covers \Moaction\Jsonrpc\Server\BasicServer::addMethod
	 * @covers \Moaction\Jsonrpc\Server\BasicServer::methodExists
	 * @covers \Moaction\Jsonrpc\Server\BasicServer::removeMethod
	 */
	public function testMethodExists()
	{
		$server = new BasicServer();
		$server->addMethod('test', function() {});
		$this->assertTrue($server->methodExists('test'));

		$server->removeMethod('test');
		$this->assertFalse($server->methodExists('test'));
	}

	/**
	 * @covers \Moaction\Jsonrpc\Server\BasicServer::run
	 */
	public function testSingleRun()
	{
		$json = '{"jsonrpc": 2.0, "method": "test", "data": ["a"], "id": 3}';

		$response = new Response();
		$response->setResult('result');

		$server = $this->getServerMock(array('batchCall', 'createErrorResponse'));
		$server->expects($this->never())
			->method('createErrorResponse');
		$server->expects($this->once())
			->method('batchCall')
			->with(array(array('jsonrpc' => 2.0, 'method' => 'test', 'data' => array('a'), 'id' => 3)))
			->will($this->returnValue(array($response)));

		$result = $server->run($json);
		$this->assertEquals('{"jsonrpc":"2.0","result":"result"}', $result);
	}

	/**
	 * @covers \Moaction\Jsonrpc\Server\BasicServer::run
	 */
	public function testBatchRun()
	{
		$json = '[{"jsonrpc": 2.0, "method": "test 1", "data": ["a"], "id": 3},{"jsonrpc": 2.0, "method": "test 2", "data": ["a"], "id": 6}]';

		$response = new Response();
		$response->setResult('result 1');

		$response2 = new Response();
		$response2->setResult('result 2');

		$server = $this->getServerMock(array('batchCall', 'createErrorResponse'));
		$server->expects($this->never())
			->method('createErrorResponse');
		$server->expects($this->once())
			->method('batchCall')
			->with(array(
				array('jsonrpc' => 2.0, 'method' => 'test 1', 'data' => array('a'), 'id' => 3),
				array('jsonrpc' => 2.0, 'method' => 'test 2', 'data' => array('a'), 'id' => 6),
			))
			->will($this->returnValue(array($response, $response2)));

		$result = $server->run($json);
		$this->assertEquals('[{"jsonrpc":"2.0","result":"result 1"},{"jsonrpc":"2.0","result":"result 2"}]', $result);
	}

	/**
	 * @covers \Moaction\Jsonrpc\Server\BasicServer::run
	 */
	public function testErrorRun()
	{
		$json = '{}x';

		$server = new BasicServer();
		$result = $server->run($json);

		$this->assertEquals('{"jsonrpc":"2.0","error":{"code":-32700,"message":"Parse error"},"id":null}', $result);
	}

	/**
	 * @covers \Moaction\Jsonrpc\Server\BasicServer::singleCall
	 * @dataProvider providerTestSingleCall
	 */
	public function testSingleCall($method, $required, $sorted, $expected)
	{
		$server = $this->getServerMock(array('getRequiredParams', 'sortParams'));
		$self = $this;
		$server->addMethod('test', function() use ($self, $sorted) {
			// проверяем, что функция вызвалась со всеми необходимыми параметрами
			$sorted = array_values($sorted);
			$params = func_get_args();
			foreach ($sorted as $i => $param) {
				$self->assertEquals($param, $params[$i]);
			}
			return 'Hello world';
		});

		$server->expects($this->any())
			->method('getRequiredParams')
			->will($this->returnValue($required));

		$server->expects($this->any())
			->method('sortParams')
			->will($this->returnValue($sorted));

		$reflectionObj = new ReflectionObject($server);
		$reflectionMethod = $reflectionObj->getMethod('singleCall');
		$reflectionMethod->setAccessible(true);

		$request = new Request();
		$request->setId(1);
		$request->setMethod($method);

		$result = $reflectionMethod->invoke($server, $request);
		$this->assertEquals($expected, $result);
	}

	/**
	 * @return array
	 */
	public function providerTestSingleCall()
	{
		$invalidMethod = new Response();
		$invalidMethod->setError(new Error(Error::ERROR_METHOD_NOT_FOUND, null, array('method' => 'Invalid_method')));
		$invalidMethod->setId(1);

		$requiredParams = new Response();
		$requiredParams->setError(new Error(Error::ERROR_INVALID_PARAMS, 'Required parameter not found: param1'));
		$requiredParams->setId(1);

		$response = new Response();
		$response->setResult('Hello world');
		$response->setId(1);

		return array(
			'Invalid method' => array('Invalid_method', array(), array(), $invalidMethod),
			'Required param' => array('test', array('param1'), array(), $requiredParams),
			'Method call'    => array('test', array(), array('param1' => 'value1'), $response),
		);
	}

	/**
	 * @covers \Moaction\Jsonrpc\Server\BasicServer::singleCall
	 */
	public function testExceptionCall()
	{
		$request = new Request();
		$request->setMethod('test');

		$server = $this->getServerMock(array('getRequiredParams', 'sortParams'));
		$server->expects($this->once())
			->method('getRequiredParams')
			->will($this->returnValue(array()));
		$server->expects($this->once())
			->method('sortParams')
			->will($this->returnValue(array()));

		$server->addMethod('test', function() {
			throw new \Exception('WOOOHOO', 1133);
		});

		$reflectionObj = new ReflectionObject($server);
		$reflectionMethod = $reflectionObj->getMethod('singleCall');
		$reflectionMethod->setAccessible(true);

		// Request misses id.
		$request = new Request();
		$request->setMethod('test');

		$result = $reflectionMethod->invoke($server, $request);
		$response = new Response();
		$response->setError(new Error(1133, 'WOOOHOO'));

		$this->assertEquals($response, $result);
	}

	/**
	 * @covers \Moaction\Jsonrpc\Server\BasicServer::batchCall
	 */
	public function testBatchCall()
	{
		$batchRequest = array(
			array(
				'jsonrpc' => 2.0,
				'method' => 'test',
				'id'     => 1,
			),
			array(
				'jsonrpc' => 2.0,
				'method' => 'test2',
				'id'     => 2,
			),
			array(
				'method' => 'test3',
				'id'     => 2,
			),
			array(
				'jsonrpc' => 2.0,
				'method' => 'notification',
			),
		);

		$response1 = new Response();
		$response1->setResult('method result');
		$response1->setId(1);

		$response2 = new Response();
		$response2->setError(new Error(Error::ERROR_METHOD_NOT_FOUND, null, array('method' => 'test2')));
		$response2->setId(2);

		$response3 = new Response();
		$response3->setError(new Error(Error::ERROR_INVALID_REQUEST, 'Request is not valid JsonRPC request: missing protocol version'));

		$server = new BasicServer();
		$server->addMethod('test', function() {
			return 'method result';
		});
		$server->addMethod('notification', function() {});

		$reflectionObj = new ReflectionObject($server);
		$reflectionMethod = $reflectionObj->getMethod('batchCall');
		$reflectionMethod->setAccessible(true);

		$result = $reflectionMethod->invoke($server, $batchRequest);

		$this->assertCount(3, $result);
		$this->assertEquals($response1, $result[0]);
		$this->assertEquals($response2, $result[1]);
		$this->assertEquals($response3, $result[2]);
	}
}