<?php

namespace Etbag\TrxpsPayments\Storefront\Controller;

use Exception;
use Etbag\TrxpsPayments\Api\Exceptions\ApiException;
use Etbag\TrxpsPayments\Api\Exceptions\IncompatiblePlatform;
use Etbag\TrxpsPayments\Api\TrxpsApiClient;
use Etbag\TrxpsPayments\Api\Resources\Profile;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ConfigController extends StorefrontController
{
    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/v{version}/_action/trxps/config/test-api-keys",
     *         defaults={"auth_enabled"=true}, name="api.action.trxps.config.test-api-keys", methods={"POST"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function testApiKeys(Request $request): JsonResponse
    {
        // Get the live API key
        $liveApiKey = $request->get('liveApiKey');

        // Get the test API key
        $testApiKey = $request->get('testApiKey');

        /** @var array $keys */
        $keys = [
            [
                'key' => $liveApiKey,
                'mode' => 'live',
            ],
            [
                'key' => $testApiKey,
                'mode' => 'test',
            ]
        ];

        /** @var array $results */
        $results = [];

        foreach ($keys as $key) {
            $result = [
                'key' => $key['key'],
                'mode' => $key['mode'],
                'valid' => false,
            ];

            try {
                /** @var TrxpsApiClient $apiClient */
                $apiClient = new TrxpsApiClient();

                // Set the current API key
                $apiClient->setApiKey($key['key']);

                $result['valid'] = true;
            } catch (Exception $e) {
                // No need to handle this exception
            }

            $results[] = $result;
        }

        return new JsonResponse([
            'results' => $results
        ]);
    }
}