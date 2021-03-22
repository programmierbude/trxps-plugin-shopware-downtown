<?php

namespace Etbag\TrxpsPayments\Handler\Method;

use Etbag\TrxpsPayments\Handler\PaymentHandler;
use Etbag\TrxpsPayments\Api\Types\PaymentMethod;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PayPalPayment extends PaymentHandler
{
    public const PAYMENT_METHOD_NAME = PaymentMethod::PAYPAL;
    public const PAYMENT_METHOD_DESCRIPTION = 'PayPal';

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