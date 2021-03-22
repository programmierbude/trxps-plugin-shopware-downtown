<?php


namespace Etbag\TrxpsPayments\Subscriber;


use Etbag\TrxpsPayments\Service\CustomFieldService;
use Etbag\TrxpsPayments\Service\OrderService;
use Etbag\TrxpsPayments\Service\PaymentMethodService;
use Etbag\TrxpsPayments\Api\TrxpsApiClient;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderStateSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            'state_machine.order.state_changed' => ['onKlarnaOrderCancelledAsAdmin']
        ];
    }

    /** @var TrxpsApiClient $apiClient */
    private $apiClient;

    /** @var OrderService */
    private $orderService;

    /** @var PaymentMethodService */
    private $paymentMethodService;

    public function __construct(
        TrxpsApiClient $apiClient,
        OrderService $orderService,
        PaymentMethodService $paymentMethodService
    )
    {
        $this->apiClient = $apiClient;
        $this->orderService = $orderService;
        $this->paymentMethodService = $paymentMethodService;
    }

    public function onKlarnaOrderCancelledAsAdmin(StateMachineStateChangeEvent $event)
    {
        if(!($event->getContext()->getSource() instanceof AdminApiSource)) {
            return;
        }

        // Build order state change to cancelled event name
        $orderStateCancelled = implode('.', [
            StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER,
            OrderStates::STATE_MACHINE,
            OrderStates::STATE_CANCELLED
        ]);

        if($event->getStateEventName() !== $orderStateCancelled) {
            return;
        }

        $order = $this->orderService->getOrder($event->getTransition()->getEntityId(), $event->getContext());

        // use filterByState(OrderTransactionStates::STATE_OPEN)?
        $lastTransaction = $order->getTransactions()->last();

        $paymentMethod = $lastTransaction->getPaymentMethod();

        if (is_null($paymentMethod) && !is_null($lastTransaction->getPaymentMethodId())) {
            $paymentMethod = $this->paymentMethodService->getPaymentMethodById($lastTransaction->getPaymentMethodId());
        }

        $trxpsPaymentMethod = null;

        if (!is_null($paymentMethod) && !is_null($paymentMethod->getCustomFields())
            && array_key_exists('trxps_payment_method_name', $paymentMethod->getCustomFields())) {
            $trxpsPaymentMethod = $paymentMethod->getCustomFields()['trxps_payment_method_name'];
        }

        if (is_null($trxpsPaymentMethod) ||
            !in_array($trxpsPaymentMethod, ['klarnapaylater', 'klarnasliceit'])) {
            return;
        }

        $customFields = $order->getCustomFields();

        $trxpsOrderId = null;

        if (!is_null($customFields) &&
            array_key_exists(CustomFieldService::CUSTOM_FIELDS_KEY_TRXPS_PAYMENTS, $customFields) &&
            array_key_exists('order_id', $customFields[CustomFieldService::CUSTOM_FIELDS_KEY_TRXPS_PAYMENTS])) {
            $trxpsOrderId = $customFields[CustomFieldService::CUSTOM_FIELDS_KEY_TRXPS_PAYMENTS]['order_id'];
        }

        if (is_null($trxpsOrderId)) {
            return;
        }

        $trxpsOrder = $this->apiClient->orders->get($trxpsOrderId);

        if (in_array($trxpsOrder->status, ['created', 'authorized', 'shipping'])) {
            $this->apiClient->orders->cancel($trxpsOrderId);
        }
    }
}
