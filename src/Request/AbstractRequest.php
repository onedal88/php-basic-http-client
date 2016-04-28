<?php

namespace BasicHttpClient\Request;

use BasicHttpClient\Request\Authentication\AuthenticationInterface;
use BasicHttpClient\Request\Message\MessageInterface;
use BasicHttpClient\Request\Message\Header\Header;
use BasicHttpClient\Request\Transport\TransportInterface;
use BasicHttpClient\Request\Transport\HttpsTransport;
use BasicHttpClient\Request\Transport\HttpTransport;
use BasicHttpClient\Response\ResponseInterface;
use BasicHttpClient\Util\UrlUtil;
use CommonException\NetworkException\Base\NetworkException;
use CommonException\NetworkException\ConnectionTimeoutException;

/**
 * Class Request
 *
 * TODO: Add query params to be concatenated to the endpoints URL for performing GET, HEAD and DELETE requests.
 *
 * @package BasicHttpClient\Request
 */
abstract class AbstractRequest implements RequestInterface
{

	/**
	 * @var string
	 */
	private $userAgent = 'PHP Basic HTTP Client 1.0';

	/**
	 * @var string
	 */
	private $endpoint;

	/**
	 * @var int
	 */
	private $port;

	/**
	 * @var string
	 */
	private $method = self::REQUEST_METHOD_GET;

	/**
	 * @var string[]
	 */
	private $queryParameters = array();

	/**
	 * @var TransportInterface
	 */
	private $transport;

	/**
	 * @var AuthenticationInterface[]
	 */
	private $authentications = array();

	/**
	 * @var MessageInterface
	 */
	private $message;

	/**
	 * @var ResponseInterface
	 */
	private $response;

	/**
	 * @var string
	 */
	private $effectiveStatus;

	/**
	 * @var string
	 */
	private $effectiveEndpoint;

	/**
	 * @var string
	 */
	private $effectiveRawHeader;

	/**
	 * @var Header[]
	 */
	private $effectiveHeaders = array();

	/**
	 * Request constructor.
	 */
	public function __construct()
	{
		$this->transport = new HttpTransport();
	}

	/**
	 * @return string
	 */
	public function getUserAgent()
	{
		return $this->userAgent;
	}

	/**
	 * @param string $userAgent
	 * @return $this
	 */
	public function setUserAgent($userAgent)
	{
		$this->userAgent = $userAgent;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getEndpoint()
	{
		return $this->endpoint;
	}

	/**
	 * @param string $endpoint
	 * @return $this
	 */
	public function setEndpoint($endpoint)
	{
		$urlUtil = new UrlUtil();
		if (!$urlUtil->validateUrl($endpoint)) {
			throw new \InvalidArgumentException('The given endpoint is not a valid URL');
		}
		$this->endpoint = $endpoint;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getPort()
	{
		return $this->port;
	}

	/**
	 * @return bool
	 */
	public function hasPort()
	{
		return !is_null($this->port);
	}

	/**
	 * @param int $port
	 * @return $this
	 */
	public function setPort($port)
	{
		$this->port = $port;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getMethod()
	{
		return $this->method;
	}

	/**
	 * @param string $method
	 * @return $this
	 */
	public function setMethod($method)
	{
		$this->method = $method;
		return $this;
	}

	/**
	 * @return string[]
	 */
	public function getQueryParameters()
	{
		return $this->queryParameters;
	}

	/**
	 * @param string $parameterName
	 * @param string $parameterValue
	 * @return $this
	 * @internal param string $queryParameter
	 */
	public function addQueryParameter($parameterName, $parameterValue)
	{
		if (!is_string($parameterName) || !is_string($parameterValue)) {
			throw new \InvalidArgumentException('Query parameter names and values have to be a string.');
		}
		$this->queryParameters[$parameterName] = $parameterValue;
		return $this;
	}

	/**
	 * @param string[] $queryParameters
	 * @return $this
	 */
	public function setQueryParameters($queryParameters)
	{
		foreach ($queryParameters as $parameterName => $parameterValue) {
			if (!is_string($parameterName) || !is_string($parameterValue)) {
				throw new \InvalidArgumentException('Query parameters have to be an associative array.');
			}
		}
		$this->queryParameters = $queryParameters;
		return $this;
	}

	/**
	 * @return TransportInterface
	 */
	public function getTransport()
	{
		return $this->transport;
	}

	/**
	 * @param TransportInterface $transport
	 * @return $this
	 */
	public function setTransport(TransportInterface $transport)
	{
		$this->transport = $transport;
		return $this;
	}

	/**
	 * @return MessageInterface
	 */
	public function getMessage()
	{
		return $this->message;
	}

	/**
	 * @param MessageInterface $message
	 * @return $this
	 */
	public function setMessage(MessageInterface $message)
	{
		$this->message = $message;
		return $this;
	}

	/**
	 * @return AuthenticationInterface[]
	 */
	public function getAuthentications()
	{
		return $this->authentications;
	}

	/**
	 * @param AuthenticationInterface[] $authentications
	 * @return $this
	 */
	public function setAuthentications(array $authentications)
	{
		$this->authentications = $authentications;
		return $this;
	}

	/**
	 * @param AuthenticationInterface $authentication
	 * @return $this
	 */
	public function addAuthentication(AuthenticationInterface $authentication)
	{
		if (!$this->hasAuthentication($authentication)) {
			$this->authentications[] = $authentication;
		}
		return $this;
	}

	/**
	 * @param AuthenticationInterface $authentication
	 * @return $this
	 */
	public function removeAuthentication(AuthenticationInterface $authentication)
	{
		for ($i = 0; $i < count($this->authentications); $i++) {
			if ($this->authentications[$i] == $authentication) {
				unset($this->authentications[$i]);
				return $this;
			}
		}
		return $this;
	}

	/**
	 * @param AuthenticationInterface $authentication
	 * @return bool
	 */
	public function hasAuthentication(AuthenticationInterface $authentication)
	{
		foreach ($this->authentications as $existingAuthentication) {
			if ($authentication == $existingAuthentication) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return bool
	 */
	public function hasAuthentications()
	{
		return count($this->authentications) > 0;
	}

	/**
	 * @return int
	 */
	public function countAuthentications()
	{
		return count($this->authentications);
	}

	/**
	 * @param resource $curl
	 * @return $this
	 */
	public function configureCurl($curl)
	{
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLINFO_HEADER_OUT, true);
		curl_setopt($curl, CURLOPT_USERAGENT, $this->getUserAgent());
		curl_setopt($curl, CURLOPT_URL, $this->calculateEndpoint());
		if ($this->hasPort()) {
			curl_setopt($curl, CURLOPT_PORT, $this->getPort());
		}
		// Request method
		curl_setopt($curl, CURLOPT_HTTPGET, true);
		if ($this->getMethod() != self::REQUEST_METHOD_GET) {
			curl_setopt($curl, CURLOPT_HTTPGET, false);
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $this->getMethod());
		}
		return $this;
	}

	/**
	 * @return $this
	 * @throws ConnectionTimeoutException
	 * @throws NetworkException
	 * @throws \Exception
	 */
	public function perform()
	{
		// Reset former result
		$this->response = null;
		// Perform hook
		$this->prePerform();
		// Curl basic setup
		$curl = curl_init();
		$this->configureCurl($curl);
		$this->getTransport()->configureCurl($curl);
		$this->getMessage()->configureCurl($curl);
		for ($i = 0; $i < count($this->authentications); $i++) {
			$this->authentications[$i]->configureCurl($curl);
		}
		// Execute request
		$responseBody = curl_exec($curl);
		$curlErrorCode = curl_errno($curl);
		$curlErrorMessage = curl_error($curl);
		if ($curlErrorCode === CURLE_OK) {
			$this->response = $this->buildResponse();
			$this->response->populateFromCurlResult($curl, $responseBody);
			$this->setEffectiveProperties($curl);
			return $this;
		}
		curl_close($curl);
		switch ($curlErrorCode) {
			case CURLE_OPERATION_TIMEOUTED:
				throw new ConnectionTimeoutException('The request timed out with message: ' . $curlErrorMessage);
				break;
			default:
				throw new NetworkException('The request failed with message: ' . $curlErrorMessage);
				break;
		}
	}

	/**
	 * @return ResponseInterface
	 */
	abstract protected function buildResponse();

	/**
	 * @return ResponseInterface
	 */
	public function getResponse()
	{
		return $this->response;
	}

	/**
	 * @return string
	 */
	public function getEffectiveStatus()
	{
		return $this->effectiveStatus;
	}

	/**
	 * @return string
	 */
	public function getEffectiveEndpoint()
	{
		return $this->effectiveEndpoint;
	}

	/**
	 * @return string
	 */
	public function getEffectiveRawHeader()
	{
		return $this->effectiveRawHeader;
	}

	/**
	 * @return Header[]
	 */
	public function getEffectiveHeaders()
	{
		return $this->effectiveHeaders;
	}

	/**
	 * @return string
	 */
	protected function calculateEndpoint()
	{
		$endpoint = $this->getEndpoint();
		$glueCharacter = (strpos($endpoint, '?') === false) ? '?' : '&';
		$endpoint .= $glueCharacter . http_build_query($this->getQueryParameters());
		return $endpoint;
	}

	/**
	 * @throws \Exception
	 * @return void
	 */
	protected function prePerform()
	{
		$urlUtil = new UrlUtil();
		if ($urlUtil->getScheme($this->getEndpoint()) == 'HTTPS' && !$this->getTransport() instanceof HttpsTransport) {
			throw new \Exception('Transport misconfiguration. Use HttpsTransport for HTTPS requests.');
		}
	}

	/**
	 * @param resource $curl
	 * @return $this
	 */
	private function setEffectiveProperties($curl)
	{
		$this->effectiveEndpoint = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
		$this->effectiveRawHeader = curl_getinfo($curl, CURLINFO_HEADER_OUT);
		// Build effective request headers
		$requestHeaders = preg_split(
			'/\r\n/',
			$this->effectiveRawHeader,
			null,
			PREG_SPLIT_NO_EMPTY
		);
		foreach ($requestHeaders as $requestHeader) {
			if (strpos($requestHeader, ':') !== false) {
				$headerName = mb_substr($requestHeader, 0, strpos($requestHeader, ':'));
				$headerValue = mb_substr($requestHeader, strpos($requestHeader, ':') + 1);
				$headerValues = explode(',', $headerValue);
				$this->effectiveHeaders[] = new Header($headerName, $headerValues);
			} else {
				$this->effectiveStatus = $requestHeader;
			}
		}
		return $this;
	}

}
