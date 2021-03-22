<?php


namespace Etbag\TrxpsPayments\Service;


/**
 * @copyright 2021 dasistweb GmbH (https://www.dasistweb.de)
 */
interface CustomFieldsInterface
{

    public const TRXPS_KEY = 'trxps_payments';

    public const ORDER_KEY = 'order_id';

    public const DELIVERY_SHIPPED = 'is_shipped';
}
