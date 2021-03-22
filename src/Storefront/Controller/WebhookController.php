<?php

namespace Etbag\TrxpsPayments\Storefront\Controller;

use Exception;
use Etbag\TrxpsPayments\Helper\DeliveryStateHelper;
use Etbag\TrxpsPayments\Helper\PaymentStatusHelper;
use Etbag\TrxpsPayments\Service\LoggerService;
use Etbag\TrxpsPayments\Service\SettingsService;
use Etbag\TrxpsPayments\Setting\TrxpsSettingStruct;
use Etbag\TrxpsPayments\Api\Exceptions\ApiException;
use Etbag\TrxpsPayments\Api\TrxpsApiClient;
use Monolog\Logger;
use RuntimeException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

class WebhookController extends StorefrontController
{
    /** @var RouterInterface */
    private $router;

    /** @var EntityRepositoryInterface */
    private $orderRepository;

    /** @var TrxpsApiClient */
    private $apiClient;

    /** @var DeliveryStateHelper */
    private $deliveryStateHelper;

    /** @var PaymentStatusHelper */
    private $paymentStatusHelper;

    /** @var SettingsService */
    private $settingsService;

    /** @var LoggerService */
    private $logger;

    public function __construct(
        RouterInterface $router,
        EntityRepositoryInterface $orderRepository,
        TrxpsApiClient $apiClient,
        DeliveryStateHelper $deliveryStateHelper,
        PaymentStatusHelper $paymentStatusHelper,
        SettingsService $settingsService,
        LoggerService $logger
    )
    {
        $this->router = $router;
        $this->orderRepository = $orderRepository;
        $this->apiClient = $apiClient;
        $this->deliveryStateHelper = $deliveryStateHelper;
        $this->paymentStatusHelper = $paymentStatusHelper;
        $this->settingsService = $settingsService;
        $this->logger = $logger;
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/trxps/webhook", defaults={"csrf_protected"=false}, name="frontend.trxps.webhook",
     *                                           options={"seo"="false"}, methods={"GET", "POST"})
     *
     * @param SalesChannelContext $context
     *
     * @return JsonResponse
     */
    public function webhookCall(Request $request, SalesChannelContext $context): JsonResponse
    {
        $params = json_decode($request->getContent());

        $trxpsOrderId = $params->object->checkout_id;

        try {
            $trxpsOrder = $this->apiClient->performHttpCall("GET", "checkouts/".$trxpsOrderId);
        } catch (ApiException $e) {
            $this->logger->addEntry(
                $e->getMessage(),
                $context->getContext(),
                $e,
                [
                    'function' => 'get-trxps-order',
                ],
                Logger::ERROR
            );
        }

        $orderNumber = $trxpsOrder->reference;

        $criteria = null;
        $transaction = null;
        $order = null;
        $customFields = null;
        $trxpsOrder = null;
        $trxpsOrderId = null;
        $paymentStatus = null;
        $errorMessage = null;

        /** @var TrxpsSettingStruct $settings */
        $settings = $this->settingsService->getSettings(
            $context->getSalesChannel()->getId(),
            $context->getContext()
        );

        // Add a message to the log that the webhook has been triggered.
        if ($settings->isDebugMode()) {
            $this->logger->addEntry(
                sprintf('Webhook for order %s is triggered.', $orderNumber),
                $context->getContext(),
                null,
                [
                    'orderId' => $orderNumber,
                ]
            );
        }

        /**
         * Create a search criteria to find the transaction by it's ID in the
         * transaction repository.
         *
         * @var $criteria
         */
        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber));
            $criteria->addAssociation('transactions');
        } catch (InconsistentCriteriaIdsException $e) {
                $errorMessage = $errorMessage ?? $e->getMessage();
        }

        /**
         * Get the transaction from the order transaction repository. With the
         * transaction we can fetch the order from the database.
         *
         * @var OrderEntity $order
         */
        if ($criteria !== null) {
            try {
                $order = $this->orderRepository->search($criteria, $context->getContext())->first();
            } catch (Exception $e) {
                echo "exce ".$e->getMessage();
                $errorMessage = $errorMessage ?? $e->getMessage();
            }
        }

        /**
         * Get the custom fields from the order. These custom fields are used to
         * retrieve the order ID of Trxps's order. With this ID, we can fetch the
         * order from Trxps's Orders API.
         *
         * @var $customFields
         */
        if ($order !== null) {
            $customFields = $order->getCustomFields();
            $transaction = $order->getTransactions()->last();
        } else {
            $errorMessage = $errorMessage ?? 'No order found for ID ' . $orderNumber . '.';
        }

        /**
         * Set the API keys at Trxps based on the current context.
         */
        try {
            $this->setApiKeysBySalesChannelContext($context);
        } catch (Exception $e) {
            $this->logger->addEntry(
                $e->getMessage(),
                $context->getContext(),
                $e,
                [
                    'function' => 'webhook-set-api-keys'
                ]
            );
        }

        /**
         * With the order ID from the custom fields, we fetch the order from Trxps's
         * Orders API.
         *
         * @var $trxpsOrder
         */
        if (is_array($customFields) && isset($customFields['trxps_payments']['order_id'])) {
            /** @var string $trxpsOrderId */
            $trxpsOrderId = $customFields['trxps_payments']['order_id'];

            /** @var Order $trxpsOrder */
            try {
                $trxpsOrder = $this->apiClient->performHttpCall("GET", "checkouts/".$trxpsOrderId);
            } catch (ApiException $e) {
                $errorMessage = $errorMessage ?? $e->getMessage();
            }
        }

        /**
         * The payment status of the order is fetched from Trxps's Orders API. We
         * use this payment status to set the status in Shopware.
         */
        if ($trxpsOrder !== null) {
            try {
                $paymentStatus = $this->paymentStatusHelper->processPaymentStatus(
                    $transaction,
                    $order,
                    $trxpsOrder,
                    $context->getContext()
                );
            } catch (Exception $e) {
                $errorMessage = $errorMessage ?? $e->getMessage();
            }

            // @todo Handle partial shipments better and make shipping status configurable
//            try {
//                $this->deliveryStateHelper->shipDelivery(
//                    $order,
//                    $trxpsOrder,
//                    $context->getContext()
//                );
//            } catch (Exception $e) {
//                $errorMessage = $errorMessage ?? $e->getMessage();
//            }
        } else {
            $errorMessage = $errorMessage ?? 'No order found in the Orders API with ID ' . $trxpsOrderId ?? '<unknown>';
        }

        /**
         * If the payment status is null, no status could be set.
         */
        if ($paymentStatus === null) {
            $errorMessage = $errorMessage ?? 'The payment status has not been set for order with ID ' . $trxpsOrderId ?? '<unknown>';
        }

        /**
         * If any errors occurred during the webhook call, we return an error message.
         */
        if ($errorMessage !== null) {
            $this->logger->addEntry(
                $errorMessage,
                $context->getContext(),
                null,
                [
                    'function' => 'webhook',
                ]
            );

            return new JsonResponse([
                'success' => false,
                'error' => $errorMessage
            ], 422);
        }

        /**
         * If no errors occurred during the webhook call, we return a success message.
         */
        return new JsonResponse([
            'success' => true
        ]);
    }

    /**
     * Sets the API keys for Trxps based on the current context.
     *
     * @param SalesChannelContext $context
     *
     * @throws ApiException
     */
    private function setApiKeysBySalesChannelContext(SalesChannelContext $context): void
    {
        try {
            /** @var TrxpsSettingStruct $settings */
            $settings = $this->settingsService->getSettings($context->getSalesChannel()->getId());

            /** @var string $apiKey */
            $apiKey = $settings->isTestMode() === false ? $settings->getLiveApiKey() : $settings->getTestApiKey();
            $shopId = $settings->isTestMode() === false ? $settings->getLiveShopId() : $settings->getTestShopId();

            // Log the used API keys
            if ($settings->isDebugMode()) {
                $this->logger->addEntry(
                    sprintf('Selected API key %s for sales channel %s (%s) | Selected Shop ID %s for sales channel %s (%s)', $apiKey, $context->getSalesChannel()->getName(), $settings->isTestMode() ? 'test-mode' : 'live-mode', $shopId, $context->getSalesChannel()->getName(), $settings->isTestMode() ? 'test-mode' : 'live-mode'),
                    $context->getContext(),
                    null,
                    [
                        'apiKey' => $apiKey,
                        'shopId' => $shopId,
                    ]
                );
            }

            // Set the API key
            $this->apiClient->setApiKey($apiKey);
            $this->apiClient->setShopId($shopId);
            $this->apiClient->setApiTestmode($settings->isTestMode());
        } catch (InconsistentCriteriaIdsException $e) {
            $this->logger->addEntry(
                $e->getMessage(),
                $context->getContext(),
                $e,
                [
                    'function' => 'set-trxps-api-key',
                ]
            );

            throw new RuntimeException(sprintf('Could not set Trxps Api Key, error: %s', $e->getMessage()));
        }
    }
}
