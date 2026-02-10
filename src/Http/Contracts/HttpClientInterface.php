<?php

declare(strict_types=1);

namespace Synglify\Core\Http\Contracts;

/**
 * Thin HTTP client abstraction.
 *
 * Keeps the core free from any specific HTTP library dependency.
 * Framework packages can provide their own implementation (Guzzle,
 * Laravel Http, WordPress wp_remote_get, etc.) or use the default
 * cURL-based HttpClient.
 */
interface HttpClientInterface
{
    /**
     * Send a GET request.
     *
     * @param string $url     The URL to request.
     * @param array  $options Request options (headers, query params, etc.).
     * @return array{status: int, headers: array, body: string}
     */
    public function get(string $url, array $options = []): array;

    /**
     * Send a POST request.
     *
     * @param string $url     The URL to request.
     * @param array  $options Request options (headers, body, json, etc.).
     * @return array{status: int, headers: array, body: string}
     */
    public function post(string $url, array $options = []): array;

    /**
     * Send a PUT request.
     *
     * @param string $url     The URL to request.
     * @param array  $options Request options.
     * @return array{status: int, headers: array, body: string}
     */
    public function put(string $url, array $options = []): array;

    /**
     * Send a DELETE request.
     *
     * @param string $url     The URL to request.
     * @param array  $options Request options.
     * @return array{status: int, headers: array, body: string}
     */
    public function delete(string $url, array $options = []): array;
}
