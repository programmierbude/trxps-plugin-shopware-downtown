<?php declare(strict_types=1);

namespace Etbag\TrxpsPayments\Service;

use Exception;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CustomFieldService
{
    public const CUSTOM_FIELDS_KEY = 'customFields';
    public const CUSTOM_FIELDS_KEY_TRXPS_PAYMENTS = 'trxps_payments';

    /** @var ContainerInterface */
    private $container;

    /** @var EntityRepositoryInterface */
    private $customFieldSetRepository;

    /**
     * CustomFieldService constructor.
     *
     * @param ContainerInterface $container
     * @param EntityRepositoryInterface $customFieldSetRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        $container,
        EntityRepositoryInterface $customFieldSetRepository
    )
    {
        $this->container = $container;
        $this->customFieldSetRepository = $customFieldSetRepository;
    }

    public function addCustomFields(Context $context)
    {
        try {
            $trxpsOrderFieldId = Uuid::randomHex();
            $trxpsCustomerFieldId = Uuid::randomHex();

            $this->customFieldSetRepository->upsert([[
                'id' => Uuid::randomHex(),
                'name' => 'trxps_payments',
                'config' => [
                    'label' => [
                        'en-GB' => 'Trxps'
                    ]
                ],
                'customFields' => [
                    [
                        'id' => $trxpsOrderFieldId,
                        'name' => 'order_id',
                        'type' => CustomFieldTypes::TEXT,
                        'config' => [
                            'componentName' => 'sw-field',
                            'customFieldType' => CustomFieldTypes::TEXT,
                            'customFieldPosition' => 1,
                            'label' => [
                                'en-GB' => 'Trxps transaction ID',
                                'nl-NL' => 'Trxps transactienummer'
                            ]
                        ]
                    ]
                ],
                'relations' => [
                    [
                        'id' => $trxpsCustomerFieldId,
                        'entityName' => CustomerDefinition::ENTITY_NAME
                    ],
                    [
                        'id' => $trxpsOrderFieldId,
                        'entityName' => OrderDefinition::ENTITY_NAME
                    ]
                ]
            ]], $context);
        } catch (Exception $e) {
            // @todo Handle Exception
        }
    }
}
