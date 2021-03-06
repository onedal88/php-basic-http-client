<?php

namespace BasicHttpClient\Request;

use BasicHttpClient\Response\Response;
use BasicHttpClient\Response\ResponseInterface;

/**
 * Class Request
 *
 * @package BasicHttpClient\Request
 */
class Request extends AbstractRequest
{

	/**
	 * @return ResponseInterface
	 */
	protected function buildResponse()
	{
		return new Response($this);
	}

}
