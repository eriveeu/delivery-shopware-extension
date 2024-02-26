<?php

namespace Erive\Delivery\Controller;

use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ApiTestController extends AbstractController
{
    public function apiEndpointTest(Request $request, Context $context): JsonResponse
    {
        $req = $request->request->all();
        if (empty($req)) {
            return $this->error('no data is passed');
        }
        if  (!array_key_exists('baseUrl', $req) || empty($req['baseUrl'])) {
            return $this->error('no baseUrl set');
        }
        if (!array_key_exists('apiKey', $req) || empty($req['apiKey'])) {
            return $this->error('no apiKey set');
        }

        $baseUrl = $req['baseUrl'] . '/company/parcelsFrom';
        $apiKey = $req['apiKey'];

        try {
            $status = $this->apiTest($baseUrl, $apiKey); // test API key
            $message = $status === 200 ? "Authorized" : "Unauthorized";
        } catch (\Throwable $th) {
            $status = $th->getCode();
            switch ($status) {
                case 400:
                    $message = 'Client error';
                    break;
                case 401:
                    $message = 'Unauthorized';
                    break;
                default:
                    $msg = $th->getMessage();
                    $message = substr($msg, 0, strpos($msg, ': ') ?: strlen($msg));
            }
        }

        return new JsonResponse([
            "status" => $status,
            "success" => $status === 200,
            "message" => $message
        ]);
    }

    protected function apiTest(string $url, string $apiKey): int
    {
        $client = new \GuzzleHttp\Client();
        $res = $client->request('GET', $url . '?key=' . $apiKey);
        return $res->getStatusCode();
    }

    protected function error(string $message): JsonResponse
    {
        return new JsonResponse([
            "status" => 400,
            "success" => false,
            "message" => $message
        ]);
    }
}
