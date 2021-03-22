<?php

namespace Etbag\TrxpsPayments\Subscriber;

use Etbag\TrxpsPayments\Service\TransactionService;
use Etbag\TrxpsPayments\Api\Exceptions\ApiException;
use Etbag\TrxpsPayments\Api\TrxpsApiClient;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PaymentStateSubscriber implements EventSubscriberInterface
{
    /** @var TrxpsApiClient $apiClient */
    private $apiClient;

    /** @var TransactionService */
    private $transactionService;

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     * * The method name to call (priority defaults to 0)
     * * An array composed of the method name to call and the priority
     * * An array of arrays composed of the method names to call and respective
     *   priorities, or 0 if unset
     *
     * For instance:
     *
     * * array('eventName' => 'methodName')
     * * array('eventName' => array('methodName', $priority))
     * * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return [
            OrderEvents::ORDER_TRANSACTION_WRITTEN_EVENT => 'onOrderTransactionWritten'
        ];
    }

    /**
     * Creates a new instance of PaymentMethodSubscriber.
     *
     * @param TrxpsApiClient $apiClient
     * @param TransactionService $transactionService
     */
    public function __construct(
        TrxpsApiClient $apiClient,
        TransactionService $transactionService
    )
    {
        $this->apiClient = $apiClient;
        $this->transactionService = $transactionService;
    }

    /**
     * Refunds the transaction at Trxps if the payment state is refunded.
     *
     * @param EntityWrittenEvent $args
     * @throws ApiException
     */
    public function onOrderTransactionWritten(EntityWrittenEvent $args): void
    {
    }
}