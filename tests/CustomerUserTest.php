<?php

declare(strict_types=1);

namespace App\Tests;

use App\Repository\CustomerUserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CustomerUserTest extends WebTestCase
{
    private const CUSTOMER_USER_RESOURCES_URI = '/foo/user';
    private const CUSTOMER_USER_RESOURCE_URI = '/foo/user/{customerUserId}';
    private const CUSTOMER_RESOURCES_URI = '/foo/kunden';
    private const CUSTOMER_RESOURCE_URI = '/foo/kunden/{customerId}';
    private const ANY_EXISTING_CUSTOMER_USER_ID = 1;
    private const ANY_EXISTING_CUSTOMER_ID = 'D5F449CE';

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

        $methods = $this->extractMethods($data, 'CustomerUser');
        $collectionMethods = $this->extractCollectionMethods($data, 'CustomerUser');

        self::assertSame([Request::METHOD_GET, Request::METHOD_PUT, Request::METHOD_DELETE], $methods);
        self::assertSame([Request::METHOD_GET, Request::METHOD_POST], $collectionMethods);
    }

    /**
     * @return iterable<non-empty-string, array{
     *     username:non-empty-string,
     *     password:non-empty-string,
     *     expectedCustomerUserId:list<int>
     * }>
     */
    public static function provideCustomerUserData(): iterable
    {
        yield 'Users of non deleted customers are not shown' => [
            'username' => 'mfindel@vp-felder.de',
            'password' => 'hommes',
            'expectedCustomerUserId' => [1], // Customer user 3 for deleted customer 80BA9796 is not shown.
        ];
        yield 'Users of other broker are shown' => [
            'username' => 'chauser@vp-felder.de',
            'password' => 'hauser',
            'expectedCustomerUserId' => [2],
        ];
        yield 'not active users are not shown' => [
            'username' => 'c_karasius@fondshaus.ag',
            'password' => 'supersicher',
            'expectedCustomerUserId' => [], // User with id 4 is not active.
        ];
    }

    /**
     * @dataProvider provideCustomerUserData
     * @param list<int> $expectedCustomerUserIds
     */
    public function testGetCustomerUsers(string $username, string $password, array $expectedCustomerUserIds): void
    {
        $token = $this->authenticate($username, $password);

        $response = $this->getJsonLd(self::CUSTOMER_USER_RESOURCES_URI, $token);

        $responseBody = $response->getContent();
        self::assertIsString($responseBody);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), $responseBody);
        $data = $this->decodeAssociativeJson($responseBody);
        $rawCustomerUserItems = $data['hydra:member'];
        self::assertIsArray($rawCustomerUserItems);
        $customerUserIds = array_column($rawCustomerUserItems, 'id');
        self::assertSame($expectedCustomerUserIds, $customerUserIds);
    }

    /**
     * @return iterable<non-empty-string, array{
     *     username:non-empty-string,
     *     password:non-empty-string,
     *     customerUserId:positive-int,
     *     isExisting:bool
     * }>
     */
    public static function provideSingleCustomerUserData(): iterable
    {
        yield 'active user of non deleted customer is shown' => [
            'username' => 'mfindel@vp-felder.de',
            'password' => 'hommes',
            'customerUserId' => 1,
            'isExisting' => true,
        ];

        yield 'active user of deleted customer is not shown' => [
            'username' => 'mfindel@vp-felder.de',
            'password' => 'hommes',
            'customerUserId' => 3,
            'isExisting' => false,
        ];

        yield 'Non existing user is not shown' => [
            'username' => 'mfindel@vp-felder.de',
            'password' => 'hommes',
            'customerUserId' => 100_000_000,
            'isExisting' => false,
        ];

        yield 'Inactive user is not shown' => [
            'username' => 'c_karasius@fondshaus.ag',
            'password' => 'supersicher',
            'customerUserId' => 4,
            'isExisting' => false,
        ];
    }

    /**
     * @param positive-int $customerUserId
     * @dataProvider provideSingleCustomerUserData
     */
    public function testGetCustomerUser(string $username, string $password, int $customerUserId, bool $isExisting): void
    {
        $token = $this->authenticate($username, $password);

        $response = $this->getJsonLd(
            $this->replace(
                self::CUSTOMER_USER_RESOURCE_URI,
                [
                    'customerUserId' => $customerUserId,
                ]
            ),
            $token
        );

        self::assertSame($isExisting ? Response::HTTP_OK : Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $responseBody = $response->getContent();
        self::assertIsString($responseBody);
    }

    public function testGetCustomerUserResponseFormat(): void
    {
        $token = $this->authenticate('mfindel@vp-felder.de', 'hommes');
        $response = $this->getJsonLd(
            $this->replace(
                self::CUSTOMER_USER_RESOURCE_URI,
                [
                    'customerUserId' => 1,
                ]
            ),
            $token
        );

        $responseBody = $response->getContent();
        self::assertIsString($responseBody);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), $responseBody);
        $data = $this->decodeAssociativeJson($responseBody);

        self::assertSame([
            '@context' => '/foo/contexts/CustomerUser',
            '@id' => $this->buildCustomerUserUri(1),
            '@type' => 'CustomerUser',
            'id' => 1,
            'username' => 'kari@example.com',
            'lastLogin' => '2025-05-01T07:58:03+00:00',
            'kunde' => $this->buildCustomerUri('D5F449CE'),
            'aktiv' => true,
        ], $data);
    }

    public function testCreateCustomerAndCustomerUser(): void
    {
        $token = $this->authenticate('mfindel@vp-felder.de', 'hommes');

        $createCustomerResponse = $this->postJsonLd(
            self::CUSTOMER_RESOURCES_URI,
            $token,
            [
                'firstName' => 'test first name',
                'lastName' => 'test last name',
                'dateOfBirth' => '1990-06-15',
            ]
        );
        $createCustomerResponseBody = $createCustomerResponse->getContent();
        self::assertIsString($createCustomerResponseBody);
        self::assertSame(Response::HTTP_CREATED, $createCustomerResponse->getStatusCode(), $createCustomerResponseBody);
        $createUserData = $this->decodeAssociativeJson($createCustomerResponseBody);
        $customerUri = $createUserData['@id'] ?? '';
        self::assertIsString($customerUri);
        self::assertGreaterThan(1, strlen($customerUri));

        $createUserResponse = $this->postJsonLd(
            self::CUSTOMER_USER_RESOURCES_URI,
            $token,
            [
                'username' => 'test@test.example.com',
                'passwd' => '123ABCabc!@#',
                'kunde' => $customerUri,
            ]
        );
        $createUserResponseBody = $createUserResponse->getContent();
        self::assertIsString($createUserResponseBody);
        self::assertSame(Response::HTTP_CREATED, $createUserResponse->getStatusCode(), $createUserResponseBody);
        $createUserData = $this->decodeAssociativeJson($createUserResponseBody);
        $customerUserId = $createUserData['id'] ?? '';
        self::assertIsInt($customerUserId);

        $response = $this->getJsonLd(
            $this->replace(
                self::CUSTOMER_USER_RESOURCE_URI,
                [
                    'customerUserId' => $customerUserId,
                ]
            ),
            $token
        );
        $responseBody = $response->getContent();
        self::assertIsString($responseBody);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), $responseBody);
        $data = $this->decodeAssociativeJson($responseBody);

        self::assertSame('test@test.example.com', $data['username']);
    }

    /**
     * @return array<non-empty-string, array{
     *     invalidData:array<non-empty-string,mixed>,
     *     expectedViolations:array<non-empty-string,list<string>>
     * }>
     */
    public function provideInvalidCustomerUserData(): iterable
    {
        yield 'Email is empty' => [
            'invalidData' => [
                'username' => '',
            ],
            'expectedViolations' => [
                'username' => [
                    'Property "email" cannot be blank',
                ],
            ],
        ];

        yield 'Email is invalid format' => [
            'invalidData' => [
                'username' => 'not-an-email',
            ],
            'expectedViolations' => [
                'username' => [
                    'Property "email" value "not-an-email" is not a valid email.',
                ],
            ],
        ];

        yield 'Password is empty' => [
            'invalidData' => [
                'passwd' => '',
            ],
            'expectedViolations' => [
                'passwd' => [
                    'Property "passwd" cannot be blank',
                    'Property "passwd" must be at least 8 characters long',
                ],
            ],
        ];

        yield 'Password is too short' => [
            'invalidData' => [
                'passwd' => 'Aa1!',
            ],
            'expectedViolations' => [
                'passwd' => [
                    'Property "passwd" must be at least 8 characters long',
                ],
            ],
        ];

        yield 'Password without uppercase letter' => [
            'invalidData' => [
                'passwd' => 'abcd123!@',
            ],
            'expectedViolations' => [
                'passwd' => [
                    'Property "passwd" must contain at least one uppercase letter, lowercase letter, digit and special'
                    . ' character',
                ],
            ],
        ];

        yield 'Password without lowercase letter' => [
            'invalidData' => [
                'passwd' => 'ABCD123!@',
            ],
            'expectedViolations' => [
                'passwd' => [
                    'Property "passwd" must contain at least one uppercase letter, lowercase letter, digit and special'
                    . ' character',
                ],
            ],
        ];

        yield 'Password without number' => [
            'invalidData' => [
                'passwd' => 'ABCDabcd!@',
            ],
            'expectedViolations' => [
                'passwd' => [
                    'Property "passwd" must contain at least one uppercase letter, lowercase letter, digit and special'
                    . ' character',
                ],
            ],
        ];

        yield 'Password without special character' => [
            'invalidData' => [
                'passwd' => 'ABCDabcd123',
            ],
            'expectedViolations' => [
                'passwd' => [
                    'Property "passwd" must contain at least one uppercase letter, lowercase letter, digit and special'
                    . ' character',
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideInvalidCustomerUserData
     * @param array<string,mixed> $invalidData
     * @param array<string,list<string>> $expectedViolations
     */
    public function testCreateCustomerUserWithInvalidData(
        array $invalidData,
        array $expectedViolations,
    ): void {
        $token = $this->authenticate('mfindel@vp-felder.de', 'hommes');

        $response = $this->postJsonLd(
            self::CUSTOMER_USER_RESOURCES_URI,
            $token,
            $this->createCustomerUserData($invalidData)
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

    public function testDeleteCustomerUser(): void
    {
        $customerUserId = self::ANY_EXISTING_CUSTOMER_USER_ID;

        $token = $this->authenticate('mfindel@vp-felder.de', 'hommes');

        $response = $this->deleteJsonLd(
            $this->replace(self::CUSTOMER_USER_RESOURCE_URI, ['customerUserId' => $customerUserId]),
            $token
        );
        $responseBody = $response->getContent();
        self::assertIsString($responseBody);
        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $responseBody);

        $customerUser = $this->getService(CustomerUserRepository::class)
            ->getById($customerUserId);
        self::assertFalse($customerUser->isActive());
    }

    public function testUpdateCustomerUser(): void
    {
        $customerUserId = self::ANY_EXISTING_CUSTOMER_USER_ID;

        $token = $this->authenticate('mfindel@vp-felder.de', 'hommes');

        $response = $this->putJsonLd(
            $this->replace(self::CUSTOMER_USER_RESOURCE_URI, ['customerUserId' => $customerUserId]),
            $token,
            [
                'username' => 'other_test@test.example.com',
                'passwd' => 'abcABC1!',
            ]
        );
        $responseBody = $response->getContent();
        self::assertIsString($responseBody);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), $responseBody);

        $fetchedCustomer = $this->getService(CustomerUserRepository::class)
            ->getById($customerUserId);
        self::assertSame('other_test@test.example.com', $fetchedCustomer->getUsername());
        $password = $fetchedCustomer->getPassword();
        self::assertNotSame('abcABC1!', $password);
        self::assertGreaterThan(1, strlen($password));
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
    private function buildCustomerUserUri(int $customerUserId): string
    {
        return $this->replace(
            self::CUSTOMER_USER_RESOURCE_URI,
            ['customerUserId' => $customerUserId]
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function getValidCustomerUserData(): array
    {
        return [
            'username' => 'valid@example.com',
            'passwd' => 'ValidP@ss123',
            'kunde' => $this->buildCustomerUri(self::ANY_EXISTING_CUSTOMER_ID),
        ];
    }

    /**
     * @param array<string,mixed> $overrideData
     * @return array<string,mixed>
     */
    private function createCustomerUserData(array $overrideData): array
    {
        return array_merge(
            $this->getValidCustomerUserData(),
            $overrideData
        );
    }
}
