<?php

namespace Etbag\TrxpsPayments\Api\Types;

class PaymentMethod
{
    /**
     * @link https://www.Trxps.com/en/payments/bank-transfer
     */
    const BANKTRANSFER = "banktransfer";


    /**
     * @link https://www.Trxps.com/en/payments/credit-card
     */
    const CREDITCARD = "creditcard";

    /**
     * @link https://www.Trxps.com/en/payments/paypal
     */
    const PAYPAL = "paypal";

    /**
     * @link https://www.Trxps.com/en/payments/sofort
     */
    const SOFORT = "sofort";
}
