<?php

namespace Moaction\Jsonrpc\Server;

use Moaction\Jsonrpc\Common\Response;

interface ServerInterface {
	/**
	 * @param $name
	 * @param callable $function
	 * @return self
	 */
	public function addMethod($name, Callable $function);

	/**
	 * @param $name
	 * @return self
	 */
	public function removeMethod($name);

	/**
	 * @param string  $content raw json content
	 * @return string|null
	 */
	public function run($content);
} 