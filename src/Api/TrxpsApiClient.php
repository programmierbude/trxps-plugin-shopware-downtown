<?php

namespace Etbag\TrxpsPayments\Api;

use Composer\CaBundle\CaBundle;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions as GuzzleRequestOptions;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Etbag\TrxpsPayments\Api\Exceptions\ApiException;
use Exception;

class TrxpsApiClient
{
    /**
     * Version of our client.
     */
    const CLIENT_VERSION = "2.30.0";

    /**
     * Endpoint of the remote API.
     */
    const API_ENDPOINT = "https://api.trxps.com";


    /**
     * HTTP Methods
     */
    const HTTP_GET = "GET";
    const HTTP_POST = "POST";
    const HTTP_DELETE = "DELETE";
    const HTTP_PATCH = "PATCH";

    /**
     * HTTP status codes
     */
    const HTTP_NO_CONTENT = 204;

    /**
     * Default response timeout (in seconds).
     */
    const TIMEOUT = 10;

    /**
     * Default connect timeout (in seconds).
     */
    const CONNECT_TIMEOUT = 2;

    /**
     * @var ClientInterface
     */
    protected $httpClient;

    /**
     * @var string
     */
    protected $apiEndpoint = self::API_ENDPOINT;

    /**
     * @var string
     */
    protected $apiPrefix = "test";

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @var array
     */
    protected $versionStrings = [];

    /**
     * @var int
     */
    protected $lastHttpResponseStatusCode;

    protected $shopId;
    /**
     * @param ClientInterface $httpClient
     *
     * @throws IncompatiblePlatform
     */
    public function __construct(ClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient;

        if (! $this->httpClient) {
            $this->httpClient = new Client([
                GuzzleRequestOptions::VERIFY => CaBundle::getBundledCaBundlePath(),
                GuzzleRequestOptions::TIMEOUT => self::TIMEOUT,
                GuzzleRequestOptions::CONNECT_TIMEOUT => self::CONNECT_TIMEOUT,
            ]);
        }

        $this->addVersionString("Trxps/" . self::CLIENT_VERSION);
        $this->addVersionString("PHP/" . phpversion());
    }

    /**
     * @param string $url
     *
     * @return TrxpsApiClient
     */
    public function setApiEndpoint($url)
    {
        $this->apiEndpoint = rtrim(trim($url), '/');

        return $this;
    }

    /**
     * @return string
     */
    public function getApiEndpoint()
    {
        return $this->apiEndpoint;
    }

    /**
     * @param bool $testmode
     *
     * @return TrxpsApiClient
     */
    public function setApiTestmode($testmode)
    {
        if ($testmode) {
            $this->setApiPrefix("test");
        } else {
            $this->setApiPrefix("live");
        }
        return $this;
    }

    /**
     * @param string $prefix
     *
     * @return TrxpsApiClient
     */
    public function setApiPrefix($prefix)
    {
        $this->apiPrefix = $prefix;
        return $this;
    }

    /**
     * @param string $apiKey The Trxps API key
     *
     * @return TrxpsApiClient
     */
    public function setApiKey($apiKey)
    {
        $apiKey = trim($apiKey);
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * @param string $shopId The Trxps shopID
     *
     * @return TrxpsApiClient
     */
    public function setShopId($shopId)
    {
        $shopId = trim($shopId);

        $this->shopId = $shopId;
        return $this;
    }

    /**
     * @param string $versionString
     *
     * @return TrxpsApiClient
     */
    public function addVersionString($versionString)
    {
        $this->versionStrings[] = str_replace([" ", "\t", "\n", "\r"], '-', $versionString);

        return $this;
    }

    /**
     * Perform an http call. This method is used by the resource specific classes. Please use the $payments property to
     * perform operations on payments.
     *
     * @param string $httpMethod
     * @param string $apiMethod
     * @param string|null|resource|StreamInterface $httpBody
     *
     * @return \stdClass
     * @throws ApiException
     *
     * @codeCoverageIgnore
     */
    public function performHttpCall($httpMethod, $apiMethod, $httpBody = null)
    {
        if (!in_array($apiMethod, ['refunds'])) {
            $httpBody['shop_id'] = $this->shopId;
        }
        $url = $this->apiEndpoint . "/" . $this->apiPrefix . "/" . $apiMethod;
        $ret = $this->performHttpCallToFullUrl($httpMethod, $url, $httpBody);
        return $ret;
    }

    /**
     * Perform an http call to a full url. This method is used by the resource specific classes.
     *
     * @see $payments
     * @see $isuers
     *
     * @param string $httpMethod
     * @param string $url
     * @param string|null|resource|StreamInterface $httpBody
     *
     * @return \stdClass|null
     * @throws ApiException
     *
     * @codeCoverageIgnore
     */
    public function performHttpCallToFullUrl($httpMethod, $url, $httpBody = null)
    {
        if (empty($this->apiKey)) {
            throw new ApiException("You have not set an API key. Please use setApiKey() to set the API key.");
        }
        if (empty($this->shopId)) {
            throw new ApiException("You have not set a shopId. Please use setShopId() to set the API key.");
        }

        $userAgent = implode(' ', $this->versionStrings);

        $headers = [
            'Accept' => "application/json",
            'Authorization' => "{$this->apiKey}",
            'User-Agent' => $userAgent,
        ];

        if (function_exists("php_uname")) {
            $headers['X-Trxps-Client-Info'] = php_uname();
        }

        try {
            $response = $this->httpClient->request($httpMethod, $url, [
                'json' => $httpBody,
                'headers' => $headers
            ]);
        } catch (GuzzleException $e) {
            throw ApiException::createFromGuzzleException($e);
        }

        if (! $response) {
            throw new ApiException("Did not receive API response.", 0, null);
        }

        return $this->parseResponseBody($response);
    }

    /**
     * Parse the PSR-7 Response body
     *
     * @param ResponseInterface $response
     * @return \stdClass|null
     * @throws ApiException
     */
    private function parseResponseBody(ResponseInterface $response)
    {
        $body = (string) $response->getBody();
        if (empty($body)) {
            if ($response->getStatusCode() === self::HTTP_NO_CONTENT) {
                return null;
            }

            throw new ApiException("No response body found.");
        }

        $object = @json_decode($body);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException("Unable to decode Trxps response: '{$body}'.");
        }

        if ($response->getStatusCode() >= 400) {
            throw ApiException::createFromResponse($response, null);
        }

        return $object;
    }

    /**
     * Serialization can be used for caching. Of course doing so can be dangerous but some like to live dangerously.
     *
     * \serialize() should be called on the collections or object you want to cache.
     *
     * We don't need any property that can be set by the constructor, only properties that are set by setters.
     *
     * Note that the API key is not serialized, so you need to set the key again after unserializing if you want to do
     * more API calls.
     *
     * @deprecated
     * @return string[]
     */
    public function __sleep()
    {
        return ["apiEndpoint"];
    }

    /**
     * When unserializing a collection or a resource, this class should restore itself.
     *
     * Note that if you use a custom GuzzleClient, this client is lost. You can't re set the Client, so you should
     * probably not use this feature.
     *
     * @throws IncompatiblePlatform If suddenly unserialized on an incompatible platform.
     */
    public function __wakeup()
    {
        $this->__construct();
    }
}
