<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class WebTestCase extends \Symfony\Bundle\FrameworkBundle\Test\WebTestCase
{
    private ?KernelBrowser $client = null;

    /**
     * @template T of object
     *
     * @param class-string<T> $type
     *
     * @return T&object
     */
    protected function getService(string $type, ?string $serviceName = null): object
    {
        $service = self::getContainer()->get($serviceName ?? $type);
        self::assertInstanceOf($type, $service);

        return $service;
    }

    /**
     * @param array<array-key,mixed>|object $values
     */
    public function encodeJson(array|object $values): string
    {
        return json_encode($values, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<array-key,mixed>
     */
    public function decodeAssociativeJson(string $json): array
    {
        $data = json_decode(
            json: $json,
            associative: true,
            depth: 512,
            flags: JSON_THROW_ON_ERROR
        );
        self::assertIsArray($data);

        return $data;
    }

    /**
     * @return object|array<array-key,mixed>
     */
    public function decodeObjectJson(string $json): object|array
    {
        $objectGraph = json_decode(
            json: $json,
            associative: false,
            depth: 512,
            flags: JSON_THROW_ON_ERROR
        );
        self::assertTrue(is_object($objectGraph) || is_array($objectGraph));

        return $objectGraph;
    }

    public function formatJson(string $json): string
    {
        return json_encode(
            $this->decodeAssociativeJson($json),
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT
        );
    }

    /**
     * @param array<string,mixed> $data
     * @param non-empty-string $id
     * @return list<non-empty-string>
     */
    protected function extractMethods(array $data, string $id): array
    {
        $supportedClass = $this->extractSupportedClass($data, $id);

        return array_unique($this->extractOperationMethods($supportedClass));
    }

    /**
     * @param array<string,mixed> $data
     * @param non-empty-string $id
     * @return list<non-empty-string>
     */
    protected function extractCollectionMethods(array $data, string $id): array
    {
        $id = lcfirst($id);
        $supportedClass = $this->extractSupportedClass($data, 'Entrypoint');
        $supportedProperty = $this->extractedSupportedProperty($supportedClass, "Entrypoint/{$id}");

        return array_unique($this->extractOperationMethods($supportedProperty));
    }

    /**
     * @param non-empty-string $id
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function extractSupportedClass(array $data, string $id): array
    {
        $supportedClasses = $data['hydra:supportedClass'] ?? [];
        $filteredSupportedClasses = array_filter(
            $supportedClasses,
            static fn (array $supportedClass) => $supportedClass['@id'] === "#{$id}"
        );
        self::assertCount(1, $filteredSupportedClasses);
        $supportedClasses = $filteredSupportedClasses[array_key_first($filteredSupportedClasses)] ?? null;
        self::assertNotNull($supportedClasses);

        return $supportedClasses;
    }

    /**
     * @param non-empty-string $id
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function extractedSupportedProperty(array $data, string $id): array
    {
        $supportedProperties = $data['hydra:supportedProperty'] ?? [];
        $nestedSupportedProperties = array_column($supportedProperties, 'hydra:property');
        $filteredSupportedProperties = array_filter(
            $nestedSupportedProperties,
            static fn (array $supportedProperty) => $supportedProperty['@id'] === "#{$id}"
        );
        self::assertCount(1, $filteredSupportedProperties);
        $supportedProperty = $filteredSupportedProperties[array_key_first($filteredSupportedProperties)] ?? null;
        self::assertNotNull($supportedProperty);

        return $supportedProperty;
    }

    /**
     * @param array<string,mixed> $data
     * * @return array<string,mixed>
     */
    protected function extractOperationMethods(array $data): array
    {
        $supportedOperations = $data['hydra:supportedOperation'] ?? [];
        $methods = array_column($supportedOperations, 'hydra:method');
        self::assertIsArray($methods);

        return $methods;
    }

    protected function authenticate(string $username, string $password): string
    {
        $client = $this->client ??= self::createClient();
        $client->request(
            method: Request::METHOD_POST,
            uri: '/authentication',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: $this->encodeJson(
                [
                    'username' => $username,
                    'password' => $password,
                ]
            )
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $responseBody = $response->getContent();
        $data = $this->decodeAssociativeJson($responseBody);
        self::assertArrayHasKey('token', $data);
        $token = $data['token'] ?? '';
        self::assertGreaterThan(1, $token);

        self::ensureKernelShutdown();

        return $token;
    }

    /**
     * @param array<non-empty-string,string> $replacements
     */
    protected function replace(string $string, array $replacements): string
    {
        $replacementKeys = array_keys($replacements);
        $matches = [];
        $result = preg_match_all('%\{(?P<names>[a-zA-Z0-9]+)\}%', $string, $matches);
        self::assertIsInt($result);
        self::assertSame(
            $replacementKeys,
            $matches['names'],
            'Not all or too much placeholders are given for string: ' . $string
        );
        $outputString = str_replace(
            array_map(
                static fn (string $key): string => '{' . $key . '}',
                $replacementKeys,
            ),
            array_values($replacements),
            $string
        );
        self::assertIsString($outputString);

        return $outputString;
    }

    /**
     * @param non-empty-string $uri
     * @param non-empty-string $token
     * @param array<string,mixed> $data
     */
    protected function postJsonLd(string $uri, string $token, array $data): Response
    {
        $client = $this->client ??= self::createClient();
        $client->request(
            method: Request::METHOD_POST,
            uri: $uri,
            server: [
                'CONTENT_TYPE' => 'application/ld+json',
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            content: $this->encodeJson($data)
        );

        return $client->getResponse();
    }

    /**
     * @param non-empty-string $uri
     */
    protected function getJsonLd(string $uri, ?string $token): Response
    {
        $client = $this->client ??= self::createClient();
        $server = [
            'HTTP_ACCEPT' => 'application/ld+json',
        ];
        if ($token !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }
        $client->request(
            method: Request::METHOD_GET,
            uri: $uri,
            server: $server
        );

        return $client->getResponse();
    }

    /**
     * @param non-empty-string $uri
     * @param non-empty-string $token
     * @param array<string,mixed> $data
     */
    protected function putJsonLd(string $uri, string $token, array $data): Response
    {
        $client = $this->client ??= self::createClient();
        $client->request(
            method: Request::METHOD_PUT,
            uri: $uri,
            server: [
                'HTTP_ACCEPT' => 'application/ld+json',
                'CONTENT_TYPE' => 'application/ld+json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            content: $this->encodeJson($data)
        );

        return $client->getResponse();
    }

    /**
     * @param non-empty-string $uri
     * @param non-empty-string $token
     */
    protected function deleteJsonLd(string $uri, string $token): Response
    {
        $client = $this->client ??= self::createClient();
        $client->request(
            method: Request::METHOD_DELETE,
            uri: $uri,
            server: [
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        return $client->getResponse();
    }
}
