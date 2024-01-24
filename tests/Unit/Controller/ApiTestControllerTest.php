<?php

declare(strict_types=1);

namespace EriveTests\Delivery\Unit\Controller;

use Erive\Delivery\Controller\ApiTestController;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class ApiTestControllerTest extends TestCase
{
    protected string $goodApiKey = 'ae87d72544706a19b39347cff7ea2650';
    protected string $failApiKey = 'ae87d72544706a19b39347cff7ea1111';
    protected string $goodBaseUrl = 'http://gth.test/api/v1';
    protected string $failBaseUrl = 'http://gth.test/fail_api/v1';

    protected ApiTestController $apiTestController;
    protected Request $request;
    protected Context $context;

    public function setUp(): void
    {
        parent::setUp();

        $this->apiTestController = new ApiTestController();
        $this->context = Context::createDefaultContext();
        $this->request = new Request();
    }

    /**
     * @test
     * @dataProvider apiTestCasesProvider
     */
    public function isApiTestRunning(string $baseUrl, string $apiKey, int $status, string $message): void
    {
        $request = new Request(request: [
            'baseUrl' => $baseUrl,
            'apiKey' => $apiKey
        ]);

        $responseMessage = new JsonResponse(data: [
            "status" => $status ,
            "success" => ($status === 200) ,
            "message" => $message
        ]);

        $this->assertEquals($responseMessage->getContent(), $this->apiTestController->apiEndpointTest($request, $this->context)->getContent());
    }

    public static function apiTestCasesProvider(): array
    {
        return [
            ['http://gth.test/api/v1', 'ae87d72544706a19b39347cff7ea2650', 200, 'Authorized'],
            ['http://gth.test/api/v1', 'ae87d72544706a19b39347cff7ea1111', 401, 'Unauthorized'],
            ['http://gth.test/api/v1', '', 400, 'no apiKey set'],
            ['', 'ae87d72544706a19b39347cff7ea1111', 400, 'no baseUrl set'],
            ['http://gth.test/fail_api/v1', 'ae87d72544706a19b39347cff7ea2650', 404, 'Client error'],
        ];
    }
}
