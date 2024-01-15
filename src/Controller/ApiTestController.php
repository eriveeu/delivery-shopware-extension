<?php

namespace Erive\Delivery\Controller;

use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ReturnRouteController
 * @package Erive\Delivery\Controller
 */
class ApiTestController extends AbstractController
{
    /** @Route("/api/endpoint/test", name="api.endpoint.test", methods={"POST"}, defaults={"XmlHttpRequest"=true, "_routeScope"={"api"}}) */
    public function apiEndpointTest(Request $request, Context $context)
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

        $apiKeyAccepted = false;
        $status = 401;
        $message = "Unauthorized";

        try {
            $res = $this->apiTest($baseUrl, $apiKey); // test API key
            $apiKeyAccepted = $res === 200;
        } catch (\Throwable $th) {
            $message = $th->getMessage();
        }

        if ($apiKeyAccepted) {
            $status = 200;
            $message = "Authorized";
        }

        return new JsonResponse([
            "status" => $status,
            "success" => $status === 200,
            "message" => $message
        ]);
    }

    protected function apiTest(string $url, string $apiKey)
    {
        $client = new \GuzzleHttp\Client();
        $res = $client->request('GET', $url . '?key=' . $apiKey);
        return $res->getStatusCode();
    }
}
