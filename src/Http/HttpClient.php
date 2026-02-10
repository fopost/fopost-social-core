<?php

declare(strict_types=1);

namespace Synglify\Core\Http;

use Synglify\Core\Http\Contracts\HttpClientInterface;
use Synglify\Core\Exceptions\SynglifyException;

/**
 * Default cURL-based HTTP client implementation.
 *
 * Zero framework dependencies. Uses only PHP's cURL extension.
 */
class HttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 10,
        private readonly bool $verifySsl = true,
        private readonly ?array $proxy = null,
    ) {
    }

    public function get(string $url, array $options = []): array
    {
        return $this->request('GET', $url, $options);
    }

    public function post(string $url, array $options = []): array
    {
        return $this->request('POST', $url, $options);
    }

    public function put(string $url, array $options = []): array
    {
        return $this->request('PUT', $url, $options);
    }

    public function delete(string $url, array $options = []): array
    {
        return $this->request('DELETE', $url, $options);
    }

    /**
     * Execute an HTTP request using cURL.
     *
     * @param string $method  HTTP method.
     * @param string $url     Request URL.
     * @param array  $options Request options.
     * @return array{status: int, headers: array, body: string}
     * @throws SynglifyException On cURL errors.
     */
    private function request(string $method, string $url, array $options = []): array
    {
        $ch = curl_init();

        // Set URL with query parameters for GET requests
        if (isset($options['query'])) {
            $url .= '?' . http_build_query($options['query']);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySsl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySsl ? 2 : 0);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        // Collect response headers
        $responseHeaders = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$responseHeaders) {
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))][] = trim($parts[1]);
            }
            return strlen($header);
        });

        // Build request headers array
        $requestHeaders = [];
        if (isset($options['headers'])) {
            foreach ($options['headers'] as $key => $value) {
                $requestHeaders[] = "{$key}: {$value}";
            }
        }

        // Set request body
        if (isset($options['multipart'])) {
            $postFields = $this->buildMultipartBody($options['multipart']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            // cURL sets Content-Type with boundary automatically for array postfields
        } elseif (isset($options['json'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($options['json']));
            $requestHeaders[] = 'Content-Type: application/json';
        } elseif (isset($options['body'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $options['body']);
        } elseif (isset($options['form_params'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($options['form_params']));
        }

        if ($requestHeaders !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
        }

        // Set proxy if configured
        if ($this->proxy) {
            $this->applyProxy($ch);
        }

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new SynglifyException("HTTP request failed: {$error}");
        }

        return [
            'status' => (int) $status,
            'headers' => $responseHeaders,
            'body' => (string) $body,
        ];
    }

    /**
     * Build a multipart/form-data body for cURL.
     *
     * Each element in $parts should be an array with keys:
     *   - 'name'     => field name (required)
     *   - 'contents' => field value or file contents (required)
     *   - 'filename' => original filename (optional, triggers file upload)
     *   - 'headers'  => ['Content-Type' => 'image/jpeg'] (optional)
     *
     * @param array $parts Multipart field definitions.
     * @return array cURL-compatible postfields array.
     */
    private function buildMultipartBody(array $parts): array
    {
        $postFields = [];

        foreach ($parts as $part) {
            $name = $part['name'];
            $contents = $part['contents'];

            if (isset($part['filename'])) {
                // File upload — write contents to a temp file for CURLFile
                $tmpFile = tempnam(sys_get_temp_dir(), 'synglify_');
                file_put_contents($tmpFile, $contents);

                $mimeType = $part['headers']['Content-Type']
                    ?? $part['content_type']
                    ?? 'application/octet-stream';

                $postFields[$name] = new \CURLFile($tmpFile, $mimeType, $part['filename']);
            } else {
                $postFields[$name] = $contents;
            }
        }

        return $postFields;
    }

    /**
     * Apply proxy settings to a cURL handle.
     *
     * @param \CurlHandle $ch The cURL handle.
     */
    private function applyProxy(\CurlHandle $ch): void
    {
        if (isset($this->proxy['hostname'])) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy['hostname']);
        }
        if (isset($this->proxy['port'])) {
            curl_setopt($ch, CURLOPT_PROXYPORT, $this->proxy['port']);
        }
        if (isset($this->proxy['type'])) {
            curl_setopt($ch, CURLOPT_PROXYTYPE, $this->proxy['type']);
        }
        if (isset($this->proxy['username'], $this->proxy['password'])) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->proxy['username'] . ':' . $this->proxy['password']);
        }
    }
}
