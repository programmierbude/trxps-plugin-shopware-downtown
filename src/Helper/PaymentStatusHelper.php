<?php

namespace Etbag\TrxpsPayments\Helper;

use Exception;
use Etbag\TrxpsPayments\Service\LoggerService;
use Etbag\TrxpsPayments\Service\SettingsService;
use Etbag\TrxpsPayments\Setting\TrxpsSettingStruct;
use Etbag\TrxpsPayments\Api\Resources\Order;
use Etbag\TrxpsPayments\Api\Resources\Payment;
use Etbag\TrxpsPayments\Api\Types\PaymentStatus;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

class PaymentStatusHelper
{
    /** @var LoggerService */
    protected $logger;

    /** @var OrderStateHelper */
    protected $orderStateHelper;

    /** @var OrderTransactionStateHandler */
    protected $orderTransactionStateHandler;

    /** @var SettingsService */
    protected $settingsService;

    /** @var StateMachineRegistry */
    protected $stateMachineRegistry;

    /** @var EntityRepositoryInterface */
    protected $paymentMethodRepository;

    /** @var EntityRepositoryInterface */
    protected $orderTransactionRepository;

    /**
     * PaymentStatusHelper constructor.
     *
     * @param LoggerService $logger
     * @param OrderTransactionStateHandler $orderTransactionStateHandler
     * @param SettingsService $settingsService
     * @param StateMachineRegistry $stateMachineRegistry
     */
    public function __construct(
        LoggerService $logger,
        OrderStateHelper $orderStateHelper,
        OrderTransactionStateHandler $orderTransactionStateHandler,
        SettingsService $settingsService,
        StateMachineRegistry $stateMachineRegistry,
        EntityRepositoryInterface $paymentMethodRepository,
        EntityRepositoryInterface $orderTransactionRepository
    )
    {
        $this->logger = $logger;
        $this->orderStateHelper = $orderStateHelper;
        $this->orderTransactionStateHandler = $orderTransactionStateHandler;
        $this->settingsService = $settingsService;
        $this->stateMachineRegistry = $stateMachineRegistry;

        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->orderTransactionRepository = $orderTransactionRepository;
    }

    /**
     * Order transaction state handler.
     *
     * @return OrderTransactionStateHandler
     */
    public function getOrderTransactionStateHandler(): OrderTransactionStateHandler
    {
        return $this->orderTransactionStateHandler;
    }

    /**
     * Processes the payment status for a Trxps Order. Uses the transaction state handler
     * to handle the transaction to a new status.
     *
     * @param OrderTransactionEntity $transaction
     * @param OrderEntity $order
     * @param Order $trxpsOrder
     * @param Context $context
     * @param string|null $salesChannelId
     *
     * @return string
     */
    public function processPaymentStatus(
        OrderTransactionEntity $transaction,
        OrderEntity $order,
        $trxpsOrder,
        Context $context,
        ?string $salesChannelId = null
    ): string
    {
        if ($trxpsOrder->paid) {
            if (method_exists($this->orderTransactionStateHandler, 'paid')) {
                $this->orderTransactionStateHandler->paid($transaction->getId(), $context);
            } else {
                $this->orderTransactionStateHandler->pay($transaction->getId(), $context);
            }
            return PaymentStatus::STATUS_PAID;
        }
        if ($trxpsOrder->canceled) {
            return PaymentStatus::STATUS_CANCELED;
        }
        return PaymentStatus::STATUS_OPEN;
    }
}
