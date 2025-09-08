<?php

declare(strict_types=1);

namespace App\Tests;

use App\Repository\CustomerRepository;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CustomerTest extends WebTestCase
{
    private const CUSTOMER_RESOURCES_URI = '/foo/kunden';
    private const CUSTOMER_RESOURCE_URI = '/foo/kunden/{customerId}';
    private const CUSTOMER_USER_RESOURCE_URI = '/foo/user/{customerUserId}';
    private const CUSTOMER_USER_SUBRESOURCE_URI = 'foo/kunden/{customerId}/user';
    private const CUSTOMER_ADDRESS_RESOURCE_URI = 'foo/kunden/{customerId}/adressen';
    private const CUSTOMER_ADDRESS_DETAIL_RESOURCE_URI = '/foo/kunden/{customerId}/adressen/{addressId}/details';
    private const ADDRESS_RESOURCE_URI = '/foo/adressen/{addressId}';
    private const ANY_EXISTING_CUSTOMER_ID = 'D5F449CE';
    private const ANY_EXISTING_CUSTOMER_USER_ID = 1;
    private const ANY_EXISTING_ADDRESS_ID = 1;

    public function testOptions(): void
    {
        $token = $this->authenticate('mfindel@vp-felder.de', 'hommes');

        $uri = '/foo/docs.jsonld';

        $response = $this->getJsonLd($uri, $token);

        $responseBody = $response->getContent();
        self::assertIsString($responseBody);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), $responseBody);

        $data = $this->decodeAssociativeJson($responseBody);
        $this->assertIsAssociativeArray($data);

        $methods = $this->extractMethods($data, 'Customer');
        $collectionMethods = $this->extractCollectionMethods($data, 'Customer');

        self::assertSame([Request::METHOD_GET, Request::METHOD_PUT, Request::METHOD_DELETE], $methods);
        self::assertSame([Request::METHOD_GET, Request::METHOD_POST], $collectionMethods);
    }

    /**
     * @return array<non-empty-string,array{
     *     username:non-empty-string,
     *     password:non-empty-string,
     *     expectedCustomerIds:list<non-empty-string>
     * }>
     */
    public static function provideCustomerData(): iterable
    {
        yield 'Marcus Findel customers' => [
            'username' => 'mfindel@vp-felder.de',
            'password' => 'hommes',
            'expectedCustomerIds' => [
                'D5F449CE',
            ],
        ];
        yield 'Christian Karasius customers' => [
            'username' => 'c_karasius@fondshaus.ag',
            'password' => 'supersicher',
            'expectedCustomerIds' => [
                '80B4F645',
            ],
        ];
    }

    /**
     * @param list<string> $expectedCustomerIds
     * @dataProvider provideCustomerData
     */
    public function testGetCustomers(string $username, string $password, array $expectedCustomerIds): void
    {
        $token = $this->authenticate($username, $password);

        $response = $this->getJsonLd(self::CUSTOMER_RESOURCES_URI, $token);

        $responseBody = $response->getContent();
        self::assertIsString($responseBody);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), $responseBody);

        $data = $this->decodeAssociativeJson($responseBody);
        $rawCustomerItems = $data['hydra:member'];
        self::assertIsArray($rawCustomerItems);
        $customerIds = array_column($rawCustomerItems, 'id');
        self::assertSame($expectedCustomerIds, $customerIds);
    }

    public function testGetCustomer(): void
    {
        $customerId = self::ANY_EXISTING_CUSTOMER_ID;

        $token = $this->authenticate('mfindel@vp-felder.de', 'hommes');

        $response = $this->getJsonLd(
            $this->replace(self::CUSTOMER_RESOURCE_URI, ['customerId' => $customerId]),
            $token
        );

        $responseBody = $response->getContent();
        self::assertIsString($responseBody);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), $responseBody);

        $data = $this->decodeAssociativeJson($responseBody);
        self::assertSame([
            '@context' => '/foo/contexts/Customer',
            '@id' => $this->buildCustomerUri('D5F449CE'),
            '@type' => 'Customer',
            'id' => 'D5F449CE',
            'vorname' => 'Bertram',
            'name' => 'Meier',
            'firma' => null,
            'geburtsdatum' => '1973-03-06',
            'geschlecht' => null,
            'email' => 'mebe@example.org',
            'user' => [
                '@id' => $this->buildCustomerUserUrl(1),
                '@type' => 'CustomerUser',
                'username' => 'kari@example.com',
                'lastLogin' => '2025-05-01T07:58:03+00:00',
                'aktiv' => true,
            ],
            'vermittlerId' => 1000,
            'adressen' => [
                [
                    '@id' => $this->buildAddressUri(1),
                    '@type' => 'Address',
                    'strasse' => 'Invalidenstr. 23',
                    'plz' => '10115',
                    'ort' => 'Berlin',
                    'bundesland' => 'BE',
                    'details' => [
                        '@id' => $this->buildCustomerAddressDetailUrl('D5F449CE', 1),
                        '@type' => 'CustomerAddressDetail',
                        'geschaeftlich' => false,
                        'rechnungsadresse' => true,
                    ],
                    'adresseId' => 1,
                ],
                [
                    '@id' => $this->buildAddressUri(2),
                    '@type' => 'Address',
                    'strasse' => 'Berliner Str. 12',
                    'plz' => '',
                    'ort' => 'Zossen',
                    'bundesland' => 'BB',
                    'details' => [
                        '@id' => $this->buildCustomerAddressDetailUrl('D5F449CE', 2),
                        '@type' => 'CustomerAddressDetail',
                        'geschaeftlich' => true,
                        'rechnungsadresse' => false,
                    ],
                    'adresseId' => 2,
                ],
            ],
        ], $data);
    }

    public function testCreateCustomerForBroker(): void
    {
        $token = $this->authenticate('mfindel@vp-felder.de', 'hommes');
        $response = $this->postJsonLd(
            self::CUSTOMER_RESOURCES_URI,
            $token,
            [
                'vorname' => 'test',
                'name' => 'test',
                'geburtsdatum' => '1980-01-01',
            ]
        );
        $responseBody = $response->getContent();
        self::assertIsString($responseBody);
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), $responseBody);
        $data = $this->decodeAssociativeJson($responseBody);
        self::assertArrayHasKey('id', $data);
        $customerId = $data['id'] ?? '';
        self::assertIsString($customerId);
        self::assertNotSame('', $customerId);
        self::assertMatchesRegularExpression('/[A-Z0-9]{8}/', $customerId);

        $fetchedCustomer = $this->getService(CustomerRepository::class)
            ->getById($customerId);

        self::assertSame('test', $fetchedCustomer->getFirstName());
        self::assertSame('test', $fetchedCustomer->getLastName());
        self::assertEquals(new DateTimeImmutable('1980-01-01'), $fetchedCustomer->getDateOfBirth());
    }

    /**
     * @return array<non-empty-string,array{
     *     invalidData:array<non-empty-string,mixed>,
     *     expectedViolations:array<non-empty-string,list<non-empty-string>>
     * }>
     */
    public function provideInvalidCustomerData(): iterable
    {
        yield 'First name is empty' => [
            'invalidData' => [
                'vorname' => '',
            ],
            'expectedViolations' => [
                'vorname' => [
                    'Property "vorname" cannot be blank',
                    'Property "vorname" must be at least 2 characters long',
                ],
            ],
        ];

        yield 'Last name is empty' => [
            'invalidData' => [
                'name' => '',
            ],
            'expectedViolations' => [
                'name' => [
                    'Property "name" cannot be blank',
                    'Property "name" must be at least 2 characters long',
                ],
            ],
        ];

        yield 'Date of birth is null' => [
            'invalidData' => [
                'dateOfBirth' => null,
            ],
            'expectedViolations' => [
                'geburtsdatum' => [
                    'Property "geburtstag" cannot be blank',
                ],
            ],
        ];

        yield 'Email is invalid' => [
            'invalidData' => [
                'email' => 'invalid-email',
            ],
            'expectedViolations' => [
                'email' => [
                    'Property "email" value "invalid-email" is not a valid email.',
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideInvalidCustomerData
     * @param array<non-empty-string,mixed> $invalidData
     * @param array<non-empty-string,non-empty-string> $expectedViolations
     */
    public function testCreateCustomerWithInvalidData(
        array $invalidData,
        array $expectedViolations,
    ): void {
        $token = $this->authenticate('mfindel@vp-felder.de', 'hommes');
        $response = $this->postJsonLd(
            self::CUSTOMER_RESOURCES_URI,
            $token,
            $this->createCustomerData($invalidData)
        );
        $responseBody = $response->getContent();
        self::assertIsString($responseBody);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), $responseBody);
        $data = $this->decodeAssociativeJson($responseBody);
        $violationItems = $data['violations'] ?? [];
        $this->assertIsListOfArray($violationItems);
        $violations = array_reduce(
            $violationItems,
            static function (array $accumulator, array $violation): array {
                $propertyPath = $violation['propertyPath'] ?? '';
                self::assertNotSame('', $propertyPath);
                $accumulator[$propertyPath] ??= [];
                self::assertIsArray($accumulator[$propertyPath]);
                $accumulator[$propertyPath][] = $violation['message'];

                return $accumulator;
            },
            []
        );
        self::assertSame($expectedViolations, $violations);
    }

    public function testCreateCustomerWithExtraFieldsResultInBadRequest(): void
    {
        $token = $this->authenticate('mfindel@vp-felder.de', 'hommes');
        $response = $this->postJsonLd(
            self::CUSTOMER_RESOURCES_URI,
            $token,
            [
                'unknown-property' => 'unknown-value',
            ]
        );
        $responseBody = $response->getContent();
        self::assertIsString($responseBody);
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), $responseBody);

        self::assertIsString($responseBody);
        $data = $this->decodeAssociativeJson($responseBody);

        self::assertSame('/foo/contexts/Error', $data['@context'] ?? '');
        self::assertSame('hydra:Error', $data['@type'] ?? '');
        self::assertSame('An error occurred', $data['hydra:title'] ?? '');
        self::assertSame(
            'Extra attributes are not allowed ("unknown-property" is unknown).',
            $data['hydra:description'] ?? ''
        );
    }

    public function testDeleteCustomer(): void
    {
        $customerId = self::ANY_EXISTING_CUSTOMER_ID;

        $token = $this->authenticate('mfindel@vp-felder.de', 'hommes');

        $response = $this->deleteJsonLd(
            $this->replace(self::CUSTOMER_RESOURCE_URI, ['customerId' => $customerId]),
            $token
        );

        $responseBody = $response->getContent();
        self::assertIsString($responseBody);
        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $responseBody);

        $fetchedCustomer = $this->getService(CustomerRepository::class)
            ->getById($customerId);
        self::assertTrue($fetchedCustomer->isDeleted());
    }

    public function testUpdateCustomer(): void
    {
        $customerId = self::ANY_EXISTING_CUSTOMER_ID;

        $token = $this->authenticate('mfindel@vp-felder.de', 'hommes');

        $response = $this->putJsonLd(
            $this->replace(self::CUSTOMER_RESOURCE_URI, ['customerId' => $customerId]),
            $token,
            [
                'firstName' => 'test2',
                'lastName' => 'test2',
                'dateOfBirth' => '1985-12-31',
            ]
        );
        $responseBody = $response->getContent();
        self::assertIsString($responseBody);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), $responseBody);

        $fetchedCustomer = $this->getService(CustomerRepository::class)
            ->getById($customerId);
        self::assertSame('test2', $fetchedCustomer->getFirstName());
        self::assertSame('test2', $fetchedCustomer->getLastName());
        self::assertEquals(new DateTimeImmutable('1985-12-31'), $fetchedCustomer->getDateOfBirth());
    }

    public function testGetUserOfCustomer(): void
    {
        $uri = self::CUSTOMER_USER_SUBRESOURCE_URI;
        $customerId = self::ANY_EXISTING_CUSTOMER_ID;

        $token = $this->authenticate('mfindel@vp-felder.de', 'hommes');

        $response = $this->getJsonLd(
            $this->replace($uri, ['customerId' => $customerId]),
            $token
        );

        $responseBody = $response->getContent();
        self::assertIsString($responseBody);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), $responseBody);
        $data = $this->decodeAssociativeJson($responseBody);

        self::assertSame(self::ANY_EXISTING_CUSTOMER_USER_ID, $data['id'] ?? '');
        self::assertSame('kari@example.com', $data['username'] ?? '');
    }

    /**
     * @return array<non-empty-string,array{
     *     username:non-empty-string,
     *     password:non-empty-string,
     *     customerId:non-empty-string,
     *     expectedCustomerAddressDetailIds:list<positive-int>
     * }>
     */
    public static function provideCustomerAddressData(): iterable
    {
        yield 'Addresses are listed' => [
            'username' => 'mfindel@vp-felder.de',
            'password' => 'hommes',
            'customerId' => 'D5F449CE',
            'expectedCustomerAddressDetailIds' => [1, 2],
        ];
        yield 'Addresses of deleted customer are not listed' => [
            'username' => 'mfindel@vp-felder.de',
            'password' => 'hommes',
            'customerId' => '80BA9796',
            'expectedCustomerAddressDetailIds' => [],
        ];
        yield 'Deleted addresses of non-deleted customer are listed' => [
            'username' => 'chauser@vp-felder.de',
            'password' => 'hauser',
            'customerId' => 'E97DEF37',
            'expectedCustomerAddressDetailIds' => [4], // 3 is not listed
        ];
    }

    /**
     * @dataProvider provideCustomerAddressData
     * @param non-empty-string $customerId
     * @param list<int> $expectedCustomerAddressDetailIds
     */
    public function testGetAddresses(
        string $username,
        string $password,
        string $customerId,
        array $expectedCustomerAddressDetailIds,
    ): void {
        $token = $this->authenticate($username, $password);

        $response = $this->getJsonLd(
            $this->replace(self::CUSTOMER_ADDRESS_RESOURCE_URI, ['customerId' => $customerId]),
            $token
        );

        $responseBody = $response->getContent();
        self::assertIsString($responseBody);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), $responseBody);
        $data = $this->decodeAssociativeJson($responseBody);

        $rawCustomerAddressItems = $data['hydra:member'] ?? [];
        self::assertIsArray($rawCustomerAddressItems);
        $customerAddressDetailIds = array_column($rawCustomerAddressItems, 'id');
        self::assertSame($expectedCustomerAddressDetailIds, $customerAddressDetailIds);
    }

    public function testCustomerAddressDetailsOfCustomer(): void
    {
        $customerId = self::ANY_EXISTING_CUSTOMER_ID;
        $addressId = self::ANY_EXISTING_ADDRESS_ID;
        $token = $this->authenticate('mfindel@vp-felder.de', 'hommes');

        $uri = $this->replace(
            self::CUSTOMER_ADDRESS_DETAIL_RESOURCE_URI,
            ['customerId' => $customerId, 'addressId' => $addressId]
        );
        $response = $this->getJsonLd($uri, $token);
        $responseBody = $response->getContent();
        self::assertIsString($responseBody);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), $responseBody);
        $data = $this->decodeAssociativeJson($responseBody);

        self::assertSame(
            [
                '@context' => '/foo/contexts/CustomerAddressDetail',
                '@id' => '/foo/kunden/D5F449CE/adressen/1/details',
                '@type' => 'CustomerAddressDetail',
                'kunde' => '/foo/kunden/D5F449CE',
                'adresse' => '/foo/adressen/1',
                'geschaeftlich' => false,
                'rechnungsadresse' => true,
            ],
            $data
        );
    }

    /**
     * @param array<non-empty-string,mixed> $overrideData
     * @return array<non-empty-string,mixed>
     */
    private function createCustomerData(array $overrideData = []): array
    {
        return array_merge(
            $this->getValidCustomerData(),
            $overrideData
        );
    }

    /**
     * @return array<non-empty-string,mixed>
     */
    private function getValidCustomerData(): array
    {
        return [
            'vorname' => 'Donald',
            'name' => 'Biden',
            'geburtsdatum' => '1903-05-21',
        ];
    }

    /**
     * @param non-empty-string $customerId
     * @param positive-int $addressId
     * @return non-empty-string
     */
    private function buildCustomerAddressDetailUrl(string $customerId, int $addressId): string
    {
        return $this->replace(
            self::CUSTOMER_ADDRESS_DETAIL_RESOURCE_URI,
            ['customerId' => $customerId, 'addressId' => $addressId]
        );
    }

    /**
     * @param positive-int $addressId
     * @return non-empty-string
     */
    private function buildAddressUri(int $addressId): string
    {
        return $this->replace(self::ADDRESS_RESOURCE_URI, ['addressId' => $addressId]);
    }

    /**
     * @param non-empty-string $customerId
     * @return non-empty-string
     */
    private function buildCustomerUri(string $customerId): string
    {
        return $this->replace(self::CUSTOMER_RESOURCE_URI, ['customerId' => $customerId]);
    }

    /**
     * @param positive-int $customerUserId
     * @return non-empty-string
     */
    private function buildCustomerUserUrl(int $customerUserId): string
    {
        return $this->replace(
            self::CUSTOMER_USER_RESOURCE_URI,
            ['customerUserId' => $customerUserId]
        );
    }
}
