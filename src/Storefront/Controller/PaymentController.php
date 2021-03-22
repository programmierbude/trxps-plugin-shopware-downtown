<?php

namespace Etbag\TrxpsPayments\Storefront\Controller;

use Exception;
use Etbag\TrxpsPayments\Event\PaymentPageFailEvent;
use Etbag\TrxpsPayments\Event\PaymentPageRedirectEvent;
use Etbag\TrxpsPayments\Helper\OrderStateHelper;
use Etbag\TrxpsPayments\Helper\PaymentStatusHelper;
use Etbag\TrxpsPayments\Service\LoggerService;
use Etbag\TrxpsPayments\Service\SettingsService;
use Etbag\TrxpsPayments\Service\TransactionService;
use Etbag\TrxpsPayments\Setting\TrxpsSettingStruct;
use Etbag\TrxpsPayments\Api\Exceptions\ApiException;
use Etbag\TrxpsPayments\Api\TrxpsApiClient;
use Etbag\TrxpsPayments\Api\Resources\Order;
use Etbag\TrxpsPayments\Api\Types\PaymentStatus;
use RuntimeException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Event\BusinessEventDispatcher;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

class PaymentController extends StorefrontController
{
    /** @var RouterInterface */
    private $router;

    /** @var TrxpsApiClient */
    private $apiClient;

    /** @var BusinessEventDispatcher */
    private $eventDispatcher;

    /** @var OrderStateHelper */
    private $orderStateHelper;

    /** @var PaymentStatusHelper */
    private $paymentStatusHelper;

    /** @var SettingsService */
    private $settingsService;

    /** @var TransactionService */
    private $transactionService;

    /** @var LoggerService */
    private $logger;

    public function __construct(
        RouterInterface $router,
        TrxpsApiClient $apiClient,
        BusinessEventDispatcher $eventDispatcher,
        OrderStateHelper $orderStateHelper,
        PaymentStatusHelper $paymentStatusHelper,
        SettingsService $settingsService,
        TransactionService $transactionService,
        LoggerService $logger
    )
    {
        $this->router = $router;
        $this->apiClient = $apiClient;
        $this->eventDispatcher = $eventDispatcher;
        $this->orderStateHelper = $orderStateHelper;
        $this->paymentStatusHelper = $paymentStatusHelper;
        $this->settingsService = $settingsService;
        $this->transactionService = $transactionService;
        $this->logger = $logger;
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/trxps/payment/{transactionId}", defaults={"csrf_protected"=false}, name="frontend.trxps.payment",
     *                                           options={"seo"="false"}, methods={"GET", "POST"})
     *
     * @param SalesChannelContext $context
     * @param                     $transactionId
     *
     * @return Response|RedirectResponse
     * @throws ApiException
     */
    public function payment(SalesChannelContext $context, $transactionId): ?Response
    {
        $criteria = null;
        $customFields = null;
        $errorMessage = null;
        $trxpsOrder = null;
        $trxpsOrderId = null;
        $order = null;
        $paymentFailed = false;
        $paymentStatus = null;
        $redirectUrl = null;
        $transaction = null;

        /** @var TrxpsSettingStruct $settings */
        $settings = $this->settingsService->getSettings(
            $context->getSalesChannel()->getId(),
            $context->getContext()
        );

        // Add a message to the log that the webhook has been triggered.
        if ($settings->isDebugMode()) {
            $this->logger->addEntry(
                sprintf('Payment return for transaction %s is triggered.', $transactionId),
                $context->getContext(),
                null,
                [
                    'transactionId' => $transactionId,
                ]
            );
        }

        /**
         * Get the transaction from the order transaction repository. With the
         * transaction we can fetch the order from the database.
         *
         * @var OrderTransactionEntity $transaction
         */
        $transaction = $this->transactionService->getTransactionById(
            $transactionId,
            null,
            $context->getContext()
        );

        /**
         * Get the order entity from the transaction. With the order entity, we can
         * retrieve the Trxps ID from it's custom fields and fetch the payment
         * status from Trxps's Orders API.
         *
         * @var OrderEntity $order
         */
        if ($transaction !== null) {
            $order = $transaction->getOrder();
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
        } else {
            $errorMessage = $errorMessage ?? 'No order found for transaction with ID ' . $transactionId . '.';
        }

        /**
         * Set the API keys at Trxps based on the current context.
         */
        $this->setApiKeysBySalesChannelContext($context);

        /**
         * The transaction return URL is used for redirecting the customer to the checkout
         * finish page.
         *
         * @var $trxpsOrder
         */
        if (is_array($customFields)) {
            if (isset($customFields['trxps_payments']['order_id'])) {
                /** @var string $trxpsOrderId */
                $trxpsOrderId = $customFields['trxps_payments']['order_id'];

                /** @var Order $trxpsOrder */
                try {
                    $trxpsOrder = $this->apiClient->performHttpCall("GET", "checkouts/".$trxpsOrderId);
                } catch (Exception $e) {
                    $errorMessage = $errorMessage ?? $e->getMessage();
                }
            }

            if (isset($customFields['trxps_payments']['transactionReturnUrl'])) {
                $redirectUrl = $customFields['trxps_payments']['transactionReturnUrl'];
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
                    'function' => 'payment',
                ]
            );
        }

        if (
            $paymentStatus !== null
            && (
                $paymentStatus === "cancelled"
                || $paymentStatus === "failed"
            )
        ) {
            $redirectUrl = $trxpsOrder->payment_url;

            $paymentPageFailEvent = new PaymentPageFailEvent(
                $context->getContext(),
                $order,
                $trxpsOrder,
                $context->getSalesChannel()->getId(),
                $redirectUrl
            );

            $this->eventDispatcher->dispatch($paymentPageFailEvent, $paymentPageFailEvent::EVENT_NAME);

            return $this->renderStorefront('@Storefront/storefront/page/checkout/payment/failed.html.twig', [
                'redirectUrl' => $this->router->generate('frontend.trxps.payment.retry', [
                    'transactionId' => $transactionId,
                    'redirectUrl' => urlencode($redirectUrl),
                ]),
                'displayUrl' => $redirectUrl,
            ]);
        }

        $paymentPageRedirectEvent = new PaymentPageRedirectEvent(
            $context->getContext(),
            $order,
            $trxpsOrder,
            $context->getSalesChannel()->getId(),
            $redirectUrl
        );

        $this->eventDispatcher->dispatch($paymentPageRedirectEvent, $paymentPageRedirectEvent::EVENT_NAME);
        return new RedirectResponse($redirectUrl);
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/trxps/payment/retry/{transactionId}/{redirectUrl}", defaults={"csrf_protected"=false},
     *                                                               name="frontend.trxps.payment.retry",
     *                                                               options={"seo"="false"}, methods={"GET", "POST"})
     *
     * @param SalesChannelContext $context
     * @param                     $transactionId
     *
     * @param                     $redirectUrl
     *
     * @return Response|RedirectResponse
     * @throws Exception
     */
    public function retry(SalesChannelContext $context, $transactionId, $redirectUrl): RedirectResponse
    {
        /** @var string $redirectUrl */
        $redirectUrl = urldecode($redirectUrl);

        if (!filter_var($redirectUrl, FILTER_VALIDATE_URL)) {
            throw new Exception('The redirect URL is invalid.');
        }

        /**
         * Get the transaction from the order transaction repository. With the
         * transaction we can fetch the order from the database.
         *
         * @var OrderTransactionEntity $transaction
         */
        $transaction = $this->transactionService->getTransactionById(
            $transactionId,
            null,
            $context->getContext()
        );

        /**
         * Get the order entity from the transaction. With the order entity, we can
         * retrieve the Trxps ID from it's custom fields and fetch the payment
         * status from Trxps's Orders API.
         *
         * @var OrderEntity $order
         */
        if ($transaction !== null) {
            $order = $transaction->getOrder();
        }

        // Throw an error if the order is not found
        if (!isset($order)) {
            throw new OrderNotFoundException($transaction->getOrderId());
        }

        // Reopen the order
        $this->orderStateHelper->setOrderState(
            $order,
            OrderStates::STATE_OPEN,
            $context->getContext()
        );

        // Reopen the order transaction
        try {
            $this->paymentStatusHelper->getOrderTransactionStateHandler()->reopen(
                $transactionId,
                $context->getContext()
            );
        } catch (Exception $e) {
            $this->logger->addEntry(
                $e->getMessage(),
                $context->getContext(),
                $e,
                [
                    'function' => 'payment-set-transaction-state'
                ]
            );
        }

        // If we redirect to the payment screen, set the transaction to in progress
        try {
            $this->paymentStatusHelper->getOrderTransactionStateHandler()->process(
                $transactionId,
                $context->getContext()
            );
        } catch (Exception $e) {
            $this->logger->addEntry(
                $e->getMessage(),
                $context->getContext(),
                $e,
                [
                    'function' => 'payment-set-transaction-state'
                ]
            );
        }

        return new RedirectResponse($redirectUrl);
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
