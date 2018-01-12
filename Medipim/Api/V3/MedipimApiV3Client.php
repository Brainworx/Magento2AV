<?php

require_once 'MedipimApiV3Error.php';

class MedipimApiV3Client
{
    /** @var int */
    private $keyId;
    /** @var string */
    private $keySecret;
    /** @var string */
    private $baseUrl = "https://api.medipim.be";

    /**
     * @param int $apiKeyId
     * @param string $apiKeySecret
     */
    public function __construct($apiKeyId, $apiKeySecret)
    {
        $this->keyId = $apiKeyId;
        $this->keySecret = $apiKeySecret;
    }

    /**
     * @param string $baseUrl
     */
    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * @param string $path
     * @param array|null $query
     * @return array
     * @throws MedipimApiV3Error
     */
    public function get($path, array $query = null)
    {
        $request = new _RequestDto("GET", $path);
        $request->query = $query;
        return $this->request($request);
    }

    /**
     * @param string $path
     * @param array|null $body
     * @return array|\Iterator The response body, or an iterator of results if the response is streaming
     * @throws MedipimApiV3Error
     */
    public function post($path, array $body)
    {
        $request = new _RequestDto("POST", $path);
        $request->bodyJson = $body;
        return $this->request($request);
    }

    /**
     * @param string $path
     * @param array $body
     * @param callable $callback
     * @return void
     * @deprecated Use ->post(...) instead (automatically returns an \Iterator for streaming endpoints)
     */
    public function stream($path, array $body, callable $callback)
    {
        $request = new _RequestDto("POST", $path);
        $request->bodyJson = $body;
        foreach ($this->request($request) as $result) {
            call_user_func($callback, $result);
        }
    }

    /**
     * @param string $path
     * @param string $file
     * @param string|null $mimeType
     * @return array
     */
    public function upload($path, $file, $mimeType = null)
    {
        $request = new _RequestDto("POST", $path);
        $request->bodyFile = $file;
        $request->bodyFileType = $mimeType ?: mime_content_type($file);
        return $this->request($request);
    }

    /**
     * @param string $id Product ID
     * @return string
     */
    public function createProductEmbedUrl($id)
    {
        $timestamp = time() + 90;
        $message = "embed:" . $id . ":" . $timestamp;
        $mac = hash_hmac("sha256", $message, $this->keySecret);

        $query = http_build_query([
            "id" => $id,
            "t" => $timestamp,
            "key" => $this->keyId,
            "mac" => $mac,
        ]);
        return $this->baseUrl . "/v3/products/embed?" . $query;
    }

    /**
     * @param _RequestDto $request
     * @return mixed|\Iterator
     */
    private function request(_RequestDto $request)
    {
        $retries = 0;
        retry:

        $url = $this->baseUrl . "/" . ltrim($request->path, "/");
        if (isset($request->query)) {
            $url .= "?" . http_build_query($request->query);
        }

        $handle = curl_init($url);

        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_USERPWD, "{$this->keyId}:{$this->keySecret}");
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $request->method);
        if (isset($request->bodyFile)) {
            curl_setopt($handle, CURLOPT_HTTPHEADER, ["Content-type: " . $request->bodyFileType]);
            curl_setopt($handle, CURLOPT_UPLOAD, true);
            curl_setopt($handle, CURLOPT_INFILE, fopen($request->bodyFile, "rb"));
            curl_setopt($handle, CURLOPT_INFILESIZE, filesize($request->bodyFile));
        } else if (isset($request->bodyJson)) {
            curl_setopt($handle, CURLOPT_HTTPHEADER, ["Content-type: application/json"]);
//             $filter = json_encode($request->bodyJson);
//             echo $filter;
            curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($request->bodyJson));
        }

        $this->throttle();

        $rawResponse = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($handle, CURLINFO_CONTENT_TYPE);

        if ($rawResponse === false) {
            throw new MedipimApiV3Error(curl_error($handle));
        }

        if ($status === 429) {
            if ($retries < 3) {
                $retries++;
                sleep($retries);
                goto retry;
            }
        }

        if ($contentType === "application/x-json-stream") {
            return $this->parseStreamResponse($rawResponse);
        } else {
            return $this->parseResponse($rawResponse);
        }
    }

    /** @var int[] */
    private $requests = [];
    /** @var float */
    private $throttle = 0.0;

    private function throttle()
    {
        $window = 20;
        $rate = 100/60;

        while ($this->requests && $this->requests[0] < time() - $window) {
            array_shift($this->requests);
        }

        if (count($this->requests)/$window > $rate) {
            $this->throttle += 0.05;
        } else if ($this->throttle > 0) {
            $this->throttle = max($this->throttle - 0.02, 0);
        }

        usleep($this->throttle * 1e6);

        $this->requests[] = time();
    }

    private function parseStreamResponse($rawResponse)
    {
        $i = 0;
        while ($i < strlen($rawResponse)) {
            $nextNewline = strpos($rawResponse, "\n", $i);
            $line = substr($rawResponse, $i, $nextNewline - $i);
            yield $this->parseResponse($line);
            $i = $nextNewline + 1;
        }
    }

    private function parseResponse($rawResponse)
    {
        $response = json_decode($rawResponse, true);

        if (json_last_error()) {
            throw new MedipimApiV3Error("Error parsing response: " . json_last_error_msg());
        }

        if (isset($response["error"])) {
            throw new MedipimApiV3Error(
                $response["error"]["message"] ?: $response["error"]["code"],
                $response["error"]
            );
        }

        return $response;
    }
}

/**
 * @internal
 */
class _RequestDto
{
    /** @var string */
    public $method;
    /** @var string */
    public $path;
    /** @var mixed|null */
    public $query;
    /** @var mixed|null */
    public $bodyJson;
    /** @var string|null */
    public $bodyFile;
    /** @var string|null */
    public $bodyFileType;
    /** @var bool */
    public $stream = false;

    public function __construct($method, $path)
    {
        $this->method = $method;
        $this->path = $path;
    }
}
