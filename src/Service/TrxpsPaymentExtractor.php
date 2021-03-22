<?php declare(strict_types=1);

namespace Etbag\TrxpsPayments\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;

class TrxpsPaymentExtractor
{
    /**
     * method extracts last created transaction if it is a trxps payment transaction.
     *
     * @param OrderTransactionCollection $collection
     * @return OrderTransactionEntity|null
     */
    public function extractLast(?OrderTransactionCollection $collection): ?OrderTransactionEntity
    {
        if (!$collection instanceof OrderTransactionCollection) {
            return null;
        }

        if ($collection->count() === 0) {
            return null;
        }

        // only transactions with a payment method
        $collection->filter(function (OrderTransactionEntity $transaction) {
            return ($transaction->getPaymentMethod() instanceof PaymentMethodEntity);
        });

        // sort all transactions chronological
        $collection->sort(function (OrderTransactionEntity $a, OrderTransactionEntity $b) {
            return $a->getCreatedAt() > $b->getCreatedAt();
        });

        $lastTransaction = $collection->last();

        if ($this->isTrxpsPayment($lastTransaction)) {
            return $lastTransaction;
        }

        return null;
    }

    private function isTrxpsPayment(?OrderTransactionEntity $transaction): bool
    {
        if (!$transaction instanceof OrderTransactionEntity) {
            return false;
        }

        $trxpsPaymentsNamespace = 'Etbag\TrxpsPayments\Handler\Method';

        $handlerName = substr($transaction->getPaymentMethod()->getHandlerIdentifier(), 0, strlen($trxpsPaymentsNamespace));

        if ($handlerName === $trxpsPaymentsNamespace) {
            return true;
        }

        return false;
    }
}
