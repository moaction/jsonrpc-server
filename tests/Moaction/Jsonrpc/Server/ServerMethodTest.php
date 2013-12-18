<?php

use Moaction\Jsonrpc\Server\ServerMethod;

class ServerMethodTest extends PHPUnit_Framework_TestCase
{
	/**
	 * @covers \Moaction\Jsonrpc\Server\ServerMethod::__construct
	 * @covers \Moaction\Jsonrpc\Server\ServerMethod::getAllParams
	 * @covers \Moaction\Jsonrpc\Server\ServerMethod::getRequiredParams
	 */
	public function testConstructor()
	{
		$function = function($a, $b, $c = '') {};
		$method = new ServerMethod($function);

		$this->assertEquals(array('a', 'b', 'c'), $method->getAllParams());
		$this->assertEquals(array('a', 'b'), $method->getRequiredParams());
	}

	/**
	 * @covers \Moaction\Jsonrpc\Server\ServerMethod::getDefaultValue
	 * @covers \Moaction\Jsonrpc\Server\ServerMethod::getDefaultValues
	 */
	public function testGetDefaultValues()
	{
		$function = function($a, $b = 'x', $c = array()) {};
		$method = new ServerMethod($function);

		$this->assertEquals(array('b' => 'x', 'c' => array()), $method->getDefaultValues());
		$this->assertEquals(null, $method->getDefaultValue('a'));
		$this->assertEquals('x', $method->getDefaultValue('b'));
	}

	/**
	 * @dataProvider providerTestSortParams
	 * @covers \Moaction\Jsonrpc\Server\ServerMethod::sortParams
	 */
	public function testSortParams($params, $expected)
	{
		$function = function($a, $b, $c = 'c', $d = 'd') {
			return array(
				'a' => $a,
				'b' => $b,
				'c' => $c,
				'd' => $d,
			);
		};

		$method = new ServerMethod($function);
		$r = new ReflectionObject($method);
		$m = $r->getMethod('sortParams');
		$m->setAccessible(true);

		if (!$expected) {
			$this->setExpectedException('\Moaction\Jsonrpc\Server\InvalidParamException');
		}
		$result = $m->invoke($method, $params);
		$this->assertEquals($result, $expected);
	}

	/**
	 * @return array
	 */
	public function providerTestSortParams()
	{
		return array(
			'Empty params' => array(
				array(),
				false,
			),
			'Empty required param' => array(
				array('a' => 'a'),
				false,
			),
			'Required params' => array(
				array('a' => 'a', 'b' => 'b'),
				array('a' => 'a', 'b' => 'b', 'c' => 'c', 'd' => 'd'),
			),
			'Partial call' => array(
				array('a' => 'a', 'b' => 'b', 'd' => 'x'),
				array('a' => 'a', 'b' => 'b', 'c' => 'c', 'd' => 'x'),
			),
		);
	}

	/**
	 * @dataProvider providerTestCall
	 * @covers \Moaction\Jsonrpc\Server\ServerMethod::call
	 */
	public function testCall($params, $expected)
	{
		$function = function($a, $b = 'b') {
			return array(
				'a' => $a,
				'b' => $b,
			);
		};

		/** @var PHPUnit_Framework_MockObject_MockObject|ServerMethod $method */
		$method = $this->getMockBuilder('\Moaction\Jsonrpc\Server\ServerMethod')
			->setConstructorArgs(array($function))
			->setMethods(array('sortParams'))
			->getMock();

		$method->expects($this->once())
			->method('sortParams')
			->with($params)
			->will($this->returnValue($params));

		$this->assertEquals($expected, $method->call($params));
	}

	/**
	 * @return array
	 */
	public function providerTestCall()
	{
		return array(
			'Basic call' => array(
				array('a' => 1, 'b' => 2),
				array('a' => 1, 'b' => 2),
			),
			'Partial call' => array(
				array('a' => 1),
				array('a' => 1, 'b' => 'b'),
			)
		);
	}
}