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
        $methods = array_values(array_unique($this->extractOperationMethods($supportedClass)));
        foreach ($methods as $method) {
            self::assertIsString($method);
            self::assertNotSame('', $method);
        }

        /** @var list<non-empty-string> */
        return $methods;
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
        $methods = array_values(array_unique($this->extractOperationMethods($supportedProperty)));
        foreach ($methods as $method) {
            self::assertIsString($method);
            self::assertNotSame('', $method);
        }

        /** @var list<non-empty-string> */
        return $methods;
    }

    /**
     * @param non-empty-string $id
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function extractSupportedClass(array $data, string $id): array
    {
        $supportedClasses = $data['hydra:supportedClass'] ?? [];
        $this->assertIsListOfArray($supportedClasses);
        $filteredSupportedClasses = array_filter(
            $supportedClasses,
            static fn (array $supportedClass) => $supportedClass['@id'] === "#{$id}"
        );
        self::assertCount(1, $filteredSupportedClasses);
        $supportedClasses = $filteredSupportedClasses[array_key_first($filteredSupportedClasses)] ?? null;
        self::assertNotNull($supportedClasses);
        $this->assertIsAssociativeArray($supportedClasses);

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
        self::assertIsArray($supportedProperties);
        $nestedSupportedProperties = array_column($supportedProperties, 'hydra:property');
        $this->assertIsListOfArray($nestedSupportedProperties);
        $filteredSupportedProperties = array_filter(
            $nestedSupportedProperties,
            static fn (array $supportedProperty) => $supportedProperty['@id'] === "#{$id}"
        );
        self::assertCount(1, $filteredSupportedProperties);
        $supportedProperty = $filteredSupportedProperties[array_key_first($filteredSupportedProperties)] ?? null;
        self::assertNotNull($supportedProperty);
        $this->assertIsAssociativeArray($supportedProperty);

        return $supportedProperty;
    }

    /**
     * @param array<string,mixed> $data
     * @return list<string>
     */
    protected function extractOperationMethods(array $data): array
    {
        $supportedOperations = $data['hydra:supportedOperation'] ?? [];
        self::assertIsArray($supportedOperations);

        $methods = array_column($supportedOperations, 'hydra:method');
        $this->assertIsStringList($methods);

        return $methods;
    }

    /**
     * @return non-empty-string
     */
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
        $responseBody = $response->getContent();
        self::assertIsString($responseBody);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), $responseBody);
        $data = $this->decodeAssociativeJson($responseBody);
        self::assertArrayHasKey('token', $data);
        $token = $data['token'] ?? '';
        self::assertIsString($token);
        self::assertNotSame('', $token);

        self::ensureKernelShutdown();

        return $token;
    }

    /**
     * @param non-empty-string $string
     * @param array<non-empty-string,string|int> $replacements
     * @return non-empty-string
     */
    protected function replace(string $string, array $replacements): string
    {
        self::assertNotSame('', $string);
        $replacements = array_map(
            static fn (string|int $value) => is_string($value) ? $value : (string) $value,
            $replacements
        );
        $replacementKeys = array_keys($replacements);
        $matches = [];
        $result = preg_match_all('%\{(?P<names>[a-zA-Z0-9]+)\}%', $string, $matches);
        self::assertIsInt($result);
        self::assertSame($replacementKeys, $matches['names'], 'Not all or too much placeholders are given for string: ' . $string);

        $replacedString = str_replace(
            array_map(
                static fn (string $key): string => '{' . $key . '}',
                $replacementKeys,
            ),
            array_values($replacements),
            $string
        );
        self::assertNotSame('', $replacedString);

        return $replacedString;
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

    /**
     * @phpstan-assert list<array<array-key,mixed>> $values
     */
    protected function assertIsListOfArray(mixed $values): void
    {
        self::assertIsArray($values);
        self::assertTrue(array_is_list($values));
        foreach ($values as $value) {
            self::assertIsArray($value);
        }
    }

    /**
     * @phpstan-assert list<non-empty-string> $values
     */
    protected function assertIsStringList(mixed $values): void
    {
        self::assertIsArray($values);
        self::assertTrue(array_is_list($values));
        foreach ($values as $value) {
            self::assertIsString($value);
            self::assertNotSame('', $value);
        }
    }

    /**
     * @phpstan-assert array<string,mixed> $values
     */
    protected function assertIsAssociativeArray(mixed $values): void
    {
        self::assertIsArray($values);
        foreach ($values as $key => $value) {
            self::assertIsString($key);
        }
    }

    /**
     * @param list<array<array-key,mixed>> $violationItems
     * @return array<non-empty-string,list<non-empty-string>>
     */
    protected function collectViolations(array $violationItems): mixed
    {
        return array_reduce(
            $violationItems,
            static function (array $accumulator, array $violation): array {
                $propertyPath = $violation['propertyPath'] ?? '';
                $message = $violation['message'] ?? '';
                self::assertNotSame('', $propertyPath);
                self::assertNotSame('', $message);
                $accumulator[$propertyPath] ??= [];
                self::assertIsArray($accumulator[$propertyPath]);
                $accumulator[$propertyPath][] = $message;

                /** @var array<non-empty-string,list<non-empty-string>> */
                return $accumulator;
            },
            []
        );
    }
}
