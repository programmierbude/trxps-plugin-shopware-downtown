<?php

namespace Etbag\TrxpsPayments\Handler\Method;

use Etbag\TrxpsPayments\Handler\PaymentHandler;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PayDirektPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = "Paydirekt";
    public const PAYMENT_METHOD_DESCRIPTION = 'Paydirekt';

    /** @var string */
    protected $paymentMethod = self::PAYMENT_METHOD_NAME;

    protected function processPaymentMethodSpecificParameters(
        array $orderData,
        SalesChannelContext $salesChannelContext,
        CustomerEntity $customer,
        LocaleEntity $locale
    ): array
    {
        return $orderData;
    }
}