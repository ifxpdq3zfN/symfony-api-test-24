<?php

declare(strict_types=1);

namespace App\Tests;

use App\Repository\AddressRepository;
use App\State;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AddressTest extends WebTestCase
{
    private const ADDRESS_RESOURCES_URI = '/foo/adressen';
    private const ADDRESS_RESOURCE_URI = "/foo/adressen/{addressId}";
    private const CUSTOMER_RESOURCE_URI = "/foo/kunden/{customerId}";
    private const CUSTOMER_DETAIL_RESOURCE_URI = '/foo/kunden/{customerId}/adressen/{addressId}/details';
    private const ANY_EXISTING_CUSTOMER_ID = 'D5F449CE';
    private const ANY_EXISTING_ADDRESS_ID = 1;

    public function testOptions(): void
    {
        $token = $this->authenticate('mfindel@vp-felder.de', 'hommes');

        $uri = '/foo/docs.jsonld';

        $response = $this->getJsonLd($uri, $token);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $responseBody = $response->getContent();
        self::assertIsString($responseBody);
        $data = $this->decodeAssociativeJson($responseBody);

        $methods = $this->extractMethods($data, 'Address');
        $collectionMethods = $this->extractCollectionMethods($data, 'Address');

        self::assertSame([Request::METHOD_GET, Request::METHOD_PUT, Request::METHOD_DELETE], $methods);
        self::assertSame([Request::METHOD_GET, Request::METHOD_POST], $collectionMethods);
    }

    public static function provideAddressData(): iterable
    {
        yield 'Marcus Findel non deleted customer addresses are shown' => [
            'username' => 'mfindel@vp-felder.de',
            'password' => 'hommes',
            'expectedAddressIds' => [1, 2], // 5 is not contained because customer for address is deleted
        ];
        yield 'Christian Hauser customer addresses are shown but not deleted addresses' => [
            'username' => 'chauser@vp-felder.de',
            'password' => 'hauser',
            'expectedAddressIds' => [4], // 3 is not contained because address is deleted
        ];
    }

    /**
     * @dataProvider provideAddressData
     * @param list<int> $expectedAddressIds
     */
    public function testGetAddresses(string $username, string $password, array $expectedAddressIds): void
    {
        $token = $this->authenticate($username, $password);

        $response = $this->getJsonLd(self::ADDRESS_RESOURCES_URI, $token);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $responseBody = $response->getContent();
        self::assertIsString($responseBody);
        $data = $this->decodeAssociativeJson($responseBody);
        $addressIds = array_column($data['hydra:member'], 'id');
        self::assertSame($expectedAddressIds, $addressIds);
    }

    public static function provideSingleAddressData(): iterable
    {
        yield 'non deleted customer addresses is shown' => [
            'username' => 'mfindel@vp-felder.de',
            'password' => 'hommes',
            'addressId' => 1,
            'isExisting' => true,
        ];
        yield 'address for deleted customer is not shown' => [
            'username' => 'mfindel@vp-felder.de',
            'password' => 'hommes',
            'addressId' => 5,
            'isExisting' => false,
        ];
        yield 'address of customer not assigned to broker is not shown ...' => [
            'username' => 'mfindel@vp-felder.de',
            'password' => 'hommes',
            'addressId' => 4,
            'isExisting' => false,
        ];
        yield '... but to the assigned broker its shown' => [
            'username' => 'chauser@vp-felder.de',
            'password' => 'hauser',
            'expectedAddressIds' => 4,
            'isExisting' => true,
        ];
        yield 'deleted address should be not shown' => [
            'username' => 'chauser@vp-felder.de',
            'password' => 'hauser',
            'expectedAddressIds' => 3,
            'isExisting' => false,
        ];
        yield 'non existing address should be not shown' => [
            'username' => 'chauser@vp-felder.de',
            'password' => 'hauser',
            'expectedAddressIds' => -1,
            'isExisting' => false,
        ];
    }

    /**
     * @dataProvider provideSingleAddressData
     */
    public function testGetAddress(string $username, string $password, int $addressId, bool $isExisting): void
    {
        $token = $this->authenticate($username, $password);

        $response = $this->getJsonLd(
            $this->replace(
                self::ADDRESS_RESOURCE_URI,
                [
                    'addressId' => $addressId,
                ]
            ),
            $token
        );

        self::assertSame($isExisting ? Response::HTTP_OK : Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testGetAddressFormat(): void
    {
        $customerId = 'D5F449CE';
        $addressId = 2;

        $token = $this->authenticate('mfindel@vp-felder.de', 'hommes');

        $response = $this->getJsonLd(
            $this->replace(self::ADDRESS_RESOURCE_URI, ['addressId' => $addressId]),
            $token
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $responseBody = $response->getContent();
        self::assertIsString($responseBody);
        $data = $this->decodeAssociativeJson($responseBody);
        self::assertSame(
            [
                '@context' => '/foo/contexts/Address',
                '@id' => $this->replace(self::ADDRESS_RESOURCE_URI, ['addressId' => $addressId]),
                '@type' => 'Address',
                'id' => 2,
                'strasse' => 'Berliner Str. 12',
                'ort' => 'Zossen',
                'bundesland' => 'BB',
                'customerAddressDetails' => [
                    [
                        '@id' => $this->replace(
                            self::CUSTOMER_DETAIL_RESOURCE_URI,
                            ['customerId' => $customerId, 'addressId' => $addressId]
                        ),
                        '@type' => 'CustomerAddressDetail',
                        'kunde' => $this->replace(self::CUSTOMER_RESOURCE_URI, ['customerId' => $customerId]),
                        'geschaeftlich' => true,
                        'rechnungsadresse' => false,
                    ],
                ],
            ],
            $data
        );
    }

    public function testCreateAddressForBroker(): void
    {
        $token = $this->authenticate('mfindel@vp-felder.de', 'hommes');

        $response = $this->postJsonLd(
            self::ADDRESS_RESOURCES_URI,
            $token,
            [
                'strasse' => 'test street',
                'plz' => '13055',
                'ort' => 'Berlin',
                'bundesland' => 'BE',
                'customerAddressDetail' => [
                    'kunde' => $this->replace(
                        self::CUSTOMER_RESOURCE_URI,
                        ['customerId' => self::ANY_EXISTING_CUSTOMER_ID]
                    ),
                    'geschaeftlich' => true,
                    'rechnungsadresse' => true,
                ],
            ]
        );
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $responseBody = $response->getContent();

        self::assertIsString($responseBody);
        $data = $this->decodeAssociativeJson($responseBody);
        self::assertArrayHasKey('id', $data);
        $addressId = $data['id'] ?? '';
        self::assertIsInt($addressId);

        $fetchedAddress = $this->getService(AddressRepository::class)
            ->getById($addressId);

        self::assertSame('test street', $fetchedAddress->getStreet());
        self::assertSame('13055', $fetchedAddress->getZipCode());
        self::assertSame('Berlin', $fetchedAddress->getLocation());
        self::assertSame(State::BE, $fetchedAddress->getState());

        $fetchedCustomerAddressDetails = $fetchedAddress->getCustomerAddressDetails()->filter(
            fn ($customerAddressDetail) => $customerAddressDetail->getCustomer()->getId()
                === self::ANY_EXISTING_CUSTOMER_ID
        );
        self::assertCount(1, $fetchedCustomerAddressDetails);
        $fetchedCustomerAddressDetail = $fetchedCustomerAddressDetails->first();

        self::assertSame(self::ANY_EXISTING_CUSTOMER_ID, $fetchedCustomerAddressDetail->getCustomer()->getId());
        self::assertTrue($fetchedCustomerAddressDetail->isBusiness());
        self::assertTrue($fetchedCustomerAddressDetail->isBillingAddress());
        self::assertFalse($fetchedCustomerAddressDetail->isDeleted());
    }

    public function testCreateAddressWithInvalidData(): void
    {
        $token = $this->authenticate('mfindel@vp-felder.de', 'hommes');

        $response = $this->postJsonLd(
            self::ADDRESS_RESOURCES_URI,
            $token,
            [
                'strasse' => '',
                'plz' => '',
                'ort' => '',
                'bundesland' => '',
//                'bundesland' => State::BE->value,
                'customerAddressDetail' => [
                    'kunde' => $this->replace(
                        self::CUSTOMER_RESOURCE_URI,
                        ['customerId' => self::ANY_EXISTING_CUSTOMER_ID]
                    ),
                    'geschaeftlich' => false,
                    'rechnungsadresse' => false,
                ],
            ]
        );
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

        $responseBody = $response->getContent();
        self::assertIsString($responseBody);
        $data = $this->decodeAssociativeJson($responseBody);
        $violationItems = $data['violations'] ?? [];
        $violations = array_reduce(
            $violationItems,
            static function (array $accumulator, array $violation): array {
                $accumulator[$violation['propertyPath']][] = $violation['message'];

                return $accumulator;
            },
            []
        );
        self::assertSame(
            [
                'strasse' => [
                    'Property "strasse" cannot be blank',
                ],
                'plz' => [
                    'Property "plz" cannot be blank',
                ],
                'ort' => [
                    'Property "ort" cannot be blank',
                ],
                'bundesland' => [
                    'Property "bundesland" cannot be blank',
                ],
            ],
            $violations
        );
    }

    public function testUpdateAddress(): void
    {
        $addressId = self::ANY_EXISTING_ADDRESS_ID;

        $token = $this->authenticate('mfindel@vp-felder.de', 'hommes');

        $response = $this->putJsonLd(
            $this->replace(self::ADDRESS_RESOURCE_URI, ['addressId' => $addressId]),
            $token,
            [
                'strasse' => 'other test street',
                'plz' => '04315',
                'ort' => 'Leipzig',
                'bundesland' => 'SN',
                'customerAddressDetail' => [
                    'kunde' => $this->buildCustomerUri(self::ANY_EXISTING_CUSTOMER_ID),
                    'adresse' => $this->buildAddressUri($addressId),
                    'geschaeftlich' => true,
                    'rechnungsadresse' => true,
                ],
            ]
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $fetchedAddress = $this->getService(AddressRepository::class)
            ->getById($addressId);

        self::assertSame('other test street', $fetchedAddress->getStreet());
        self::assertSame('04315', $fetchedAddress->getZipCode());
        self::assertSame('Leipzig', $fetchedAddress->getLocation());
        self::assertSame(State::SN, $fetchedAddress->getState());
    }

    public function testDeleteAddress(): void
    {
        $addressId = self::ANY_EXISTING_ADDRESS_ID;

        $token = $this->authenticate('mfindel@vp-felder.de', 'hommes');

        $response = $this->deleteJsonLd(
            $this->replace(self::ADDRESS_RESOURCE_URI, ['addressId' => $addressId]),
            $token
        );
        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $fetchedAddress = $this->getService(AddressRepository::class)
            ->getById($addressId);
        $customerAddressDetails = $fetchedAddress->getCustomerAddressDetails();
        foreach ($customerAddressDetails as $customerAddressDetail) {
            self::assertTrue($customerAddressDetail->isDeleted());
        }
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
}
