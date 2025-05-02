<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApiTest extends WebTestCase
{
    private const CUSTOMER_RESOURCES_URI = '/foo/kunden';

    public function testAuthenticate(): void
    {
        $client = self::createClient();
        $client->request(
            method: Request::METHOD_POST,
            uri: '/authentication',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: $this->encodeJson(
                [
                    'username' => 'mfindel@vp-felder.de',
                    'password' => 'hommes',
                ]
            )
        );
        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $responseBody = $response->getContent();
        self::assertIsString($responseBody);
        $data = $this->decodeAssociativeJson($responseBody);
        self::assertArrayHasKey('token', $data);
        $token = $data['token'] ?? '';
        self::assertIsString($token);
        [$headerBase64, $payloadBase64, $signature] = explode('.', $token);

        self::assertIsString($headerBase64);
        $headerJson = base64_decode($headerBase64);
        self::assertJson($headerJson);
        $headers = $this->decodeAssociativeJson($headerJson);
        self::assertSame(
            [
                'typ' => 'JWT',
                'alg' => 'RS256',
            ],
            $headers
        );

        self::assertIsString($payloadBase64);
        $payloadJson = base64_decode($payloadBase64);
        self::assertJson($payloadJson);
        $payload = $this->decodeAssociativeJson($payloadJson);
        self::assertSame('mfindel@vp-felder.de', $payload['username'] ?? '');

        self::assertIsString($signature);
    }

    public function testNotAuthenticated(): void
    {
        $response = $this->getJsonLd(self::CUSTOMER_RESOURCES_URI, null);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }
}
