<?php

namespace Moaction\Jsonrpc\Server;

use Moaction\Jsonrpc\Common\Error;
use Moaction\Jsonrpc\Common\Exception;
use Moaction\Jsonrpc\Common\Request;
use Moaction\Jsonrpc\Common\Response;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class BasicServer implements ServerInterface, LoggerAwareInterface
{
	/**
	 * @var array
	 */
	private $methods = array();

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @inheritdoc
	 */
	public function addMethod($name, Callable $function)
	{
		$this->methods[$name] = $function;
		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function removeMethod($name)
	{
		unset($this->methods[$name]);
	}

	/**
	 * @param string $method
	 * @return bool
	 */
	public function methodExists($method)
	{
		return isset($this->methods[$method]);
	}

	/**
	 * @inheritdoc
	 */
	public function run($content)
	{
		$request = json_decode($content);
		$result = null;

		if (!$request) {
			$result = $this->createErrorResponse(Error::ERROR_PARSE_ERROR)->toArray();
		}
		else {
			if (is_array($request)) {
				$result = array();
				foreach ($this->batchCall(json_decode($content, true)) as $response) {
					$result[] = $response->toArray();
				}
			}
			else {
				$results = $this->batchCall(array(json_decode($content, true)));
				if (count($results)) {
					$result = current($results)->toArray();
				}
			}
		}

		return $result ? json_encode($result) : null;
	}

	/**
	 * @param array $requests
	 * @return Response[]
	 */
	protected function batchCall(array $requests)
	{
		$responses = array();
		foreach ($requests as $request) {
			try {
				$request = $this->getRequest($request);
				$result = $this->singleCall($request);;
				if ($request->getId()) {
					$responses[] = $result;
				}
			}
			catch (Exception $e) {
				$responses[] = $this->createErrorResponse(Error::ERROR_INVALID_REQUEST, $e->getMessage());
			}
		}

		return $responses;
	}

	/**
	 * @param Request$request
	 * @return Response
	 */
	protected function singleCall(Request $request) {
		// Trying to find suitable method
		if (!isset($this->methods[$request->getMethod()])) {
			return $this->createErrorResponse(
				Error::ERROR_METHOD_NOT_FOUND,
				null,
				$request->getId(),
				array('method' => $request->getMethod())
			);
		}

		// Checking required params
		$params = $request->getParams();
		foreach ($this->getRequiredParams($request->getMethod()) as $param) {
			if (!isset($params[$param])) {
				return $this->createErrorResponse(
					Error::ERROR_INVALID_PARAMS,
					'Required parameter not found: ' . $param,
					$request->getId()
				);
			}
		}

		$params = $this->sortParams($request->getParams() ?: array(), $this->getAllParams($request->getMethod()));
		try {
			$result = call_user_func_array($this->methods[$request->getMethod()], $params);

			$response = new Response();
			$response->setResult($result);
			$response->setId($request->getId());
			return $response;
		}
		catch(\Exception $e) {
			return $this->createErrorResponse($e->getCode() ?: null, $e->getMessage() ?: null, $request->getId());
		}
	}

	/**
	 * @param string $message
	 * @param int $id
	 * @param int $code
	 * @param mixed $data
	 * @return Response
	 */
	protected function createErrorResponse($code, $message = null, $id = null, $data = null)
	{
		$response = new Response();
		$response->setError(new Error($code, $message, $data));
		$response->setId($id);

		return $response;
	}

	/**
	 * @inheritdoc
	 */
	public function setLogger(LoggerInterface $logger)
	{
		$this->logger = $logger;
	}

	/**
	 * @param string $method
	 * @return \ReflectionParameter[]
	 */
	protected function getParams($method)
	{
		$function = new \ReflectionFunction($this->methods[$method]);
		return $function->getParameters();
	}

	/**
	 * @param string $method
	 * @return array
	 */
	protected function getRequiredParams($method)
	{
		$requiredParams = array();

		foreach ($this->getParams($method) as $param) {
			if (!$param->isOptional()) {
				$requiredParams[] = $param->getName();
			}
		}

		return $requiredParams;
	}

	/**
	 * @param string $method
	 * @return array
	 */
	protected function getAllParams($method)
	{
		$params = array();
		foreach ($this->getParams($method) as $param) {
			$params[] = $param->getName();
		}
		return $params;
	}

	/**
	 * Sort method params according to function expected arguments
	 *
	 * @param array $original
	 * @param array $master
	 * @return array
	 */
	protected function sortParams($original, $master)
	{
		uksort($original, function($a, $b) use ($master) {
			$aIndex = array_search($a, $master);
			if ($aIndex === false) {
				throw new \InvalidArgumentException('Unexpected parameter ' . $a);
			}

			$bIndex = array_search($b, $master);
			if ($bIndex === false) {
				throw new \InvalidArgumentException('Unexpected parameter ' . $b);
			}

			return $aIndex < $bIndex ? -1 : 1;
		});

		return array_values($original);
	}

	/**
	 * @param array $params
	 * @return Request
	 */
	protected function getRequest(array $params)
	{
		return Request::fromArray($params);
	}
}