<?php

namespace Moaction\Jsonrpc\Server;

interface ServerInterface {
	/**
	 * @param $name
	 * @param callable|ServerMethod $method
	 * @return self
	 */
	public function addMethod($name, $method);

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