<?php

use Moaction\Jsonrpc\Common\Error;
use Moaction\Jsonrpc\Common\Request;
use Moaction\Jsonrpc\Common\Response;
use Moaction\Jsonrpc\Server\BasicServer;
use Moaction\Jsonrpc\Server\InvalidParamException;
use Moaction\Jsonrpc\Server\ServerMethod;

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
	 * @covers \Moaction\Jsonrpc\Server\BasicServer::addMethod
	 * @dataProvider providerTestAddMethod
	 */
	public function testAddMethod($function, $expected)
	{
		$server = new BasicServer();

		if (!$expected) {
			$this->setExpectedException('\InvalidArgumentException', 'Method is not callable: test');
		}
		$reflection = new ReflectionObject($server);
		$methods = $reflection->getProperty('methods');
		$methods->setAccessible(true);

		$server->addMethod('test', $function);

		$methods = $methods->getValue($server);
		$this->assertEquals(array('test' => $expected), $methods);
	}

	/**
	 * @return array
	 */
	public function providerTestAddMethod()
	{
		$method1 = function($a) {};
		$expected1 = new ServerMethod($method1);

		$method2 = new ServerMethod(function($b) {});

		return array(
			'Function' => array($method1, $expected1),
			'Object'   => array($method2, $method2),
			'Invalid'  => array(12345, false),
		);
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
	public function testSingleCall($requestMethod, $expected)
	{
		$server = new BasicServer();

		$reflectionObj = new ReflectionObject($server);
		$reflectionMethod = $reflectionObj->getMethod('singleCall');
		$reflectionMethod->setAccessible(true);

		/** @var PHPUnit_Framework_MockObject_MockObject|ServerMethod $method */
		$method = $this->getMockBuilder('\Moaction\Jsonrpc\Server\ServerMethod')
			->disableOriginalConstructor()
			->setMethods(array('call'))
			->getMock();

		$method->expects($this->any())
			->method('call')
			->will($this->returnValue('Hello world'));

		$server->addMethod('test', $method);

		$request = new Request();
		$request->setId(1);
		$request->setMethod($requestMethod);

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

		$response = new Response();
		$response->setResult('Hello world');
		$response->setId(1);

		return array(
			'Invalid method' => array('Invalid_method', $invalidMethod),
			'Method call'    => array('test', $response),
		);
	}

	/**
	 * @covers \Moaction\Jsonrpc\Server\BasicServer::singleCall
	 * @dataProvider providerTestExceptionCall
	 */
	public function testExceptionCall($exception, $expected)
	{
		$server = new BasicServer();

		$reflectionObj = new ReflectionObject($server);
		$reflectionMethod = $reflectionObj->getMethod('singleCall');
		$reflectionMethod->setAccessible(true);

		/** @var PHPUnit_Framework_MockObject_MockObject|ServerMethod $method */
		$method = $this->getMockBuilder('\Moaction\Jsonrpc\Server\ServerMethod')
			->disableOriginalConstructor()
			->setMethods(array('call'))
			->getMock();

		$method->expects($this->any())
			->method('call')
			->will($this->throwException($exception));

		$server->addMethod('test', $method);

		$request = new Request();
		$request->setMethod('test');

		$result = $reflectionMethod->invoke($server, $request);
		$this->assertEquals($expected, $result);
	}

	/**
	 * @return array
	 */
	public function providerTestExceptionCall()
	{
		$response1 = new Response();
		$response1->setError(new Error(Error::ERROR_INVALID_PARAMS, 'Param expected'));

		$response2 = new Response();
		$response2->setError(new Error(1122, 'Method exception'));

		return array(
			'Invalid params'    => array(new InvalidParamException('Param expected'), $response1),
			'Method exceptions' => array(new \Exception('Method exception', 1122), $response2),
		);
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