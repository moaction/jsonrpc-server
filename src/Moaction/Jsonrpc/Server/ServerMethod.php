<?php

namespace Moaction\Jsonrpc\Server;

class ServerMethod {
	/**
	 * @var array
	 */
	private $requiredParams = array();

	/**
	 * @var array
	 */
	private $allParams = array();

	/**
	 * @var array
	 */
	private $defaultValues = array();

	/**
	 * @var callable
	 */
	private $callable;

	/**
	 * @param callable $callable
	 * @throws \InvalidArgumentException
	 */
	public function __construct($callable)
	{
		if (is_array($callable)) {
			if (!count($callable) === 2) {
				throw new \InvalidArgumentException('Invalid callable supplied');
			}
			$callable = array_values($callable);
			$object = new \ReflectionObject($callable[0]);
			$function = $object->getMethod($callable[1]);
		}
		else {
			$function = new \ReflectionFunction($callable);
		}

		foreach ($function->getParameters() as $param) {
			$this->allParams[] = $param->getName();

			if ($param->isOptional()) {
				$this->defaultValues[$param->getName()] = $param->getDefaultValue();
			}
			else {
				$this->requiredParams[] = $param->getName();
			}
		}

		$this->callable = $callable;
	}

	/**
	 * @return array
	 */
	public function getAllParams()
	{
		return $this->allParams;
	}

	/**
	 * @return array
	 */
	public function getRequiredParams()
	{
		return $this->requiredParams;
	}

	/**
	 * @return array
	 */
	public function getDefaultValues()
	{
		return $this->defaultValues;
	}

	/**
	 * @param string $param
	 * @return mixed
	 */
	public function getDefaultValue($param)
	{
		return isset($this->defaultValues[$param]) ? $this->defaultValues[$param] : null;
	}

	/**
	 * @param array $params
	 * @return mixed
	 */
	public function call(array $params = array())
	{
		$params = $this->sortParams($params);
		return call_user_func_array($this->callable, $params);
	}

	/**
	 * @param array $params
	 * @throws InvalidParamException
	 * @return array
	 */
	protected function sortParams(array $params)
	{
		foreach ($this->requiredParams as $param) {
			if (!isset($params[$param])) {
				throw new InvalidParamException('Required parameter `' . $param . '` expected');
			}
		}

		$sortedParams = array();
		foreach ($this->getAllParams() as $param) {
			$sortedParams[$param] = isset($params[$param]) ? $params[$param] : $this->getDefaultValue($param);
		}

		return $sortedParams;
	}
}