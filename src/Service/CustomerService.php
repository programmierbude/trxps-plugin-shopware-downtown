<?php

namespace Etbag\TrxpsPayments\Service;

use Exception;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CustomerService
{
    public const CUSTOM_FIELDS_KEY_TRXPS_CUSTOMER_ID = 'customer_id';
    public const CUSTOM_FIELDS_KEY_CREDIT_CARD_TOKEN = 'credit_card_token';

    /** @var EntityRepositoryInterface */
    private $countryRepository;

    /** @var EntityRepositoryInterface */
    private $customerRepository;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /** @var LoggerInterface */
    private $logger;

    /** @var SalesChannelContextPersister */
    private $salesChannelContextPersister;

    /** @var EntityRepositoryInterface */
    private $salutationRepository;

    /** @var string */
    private $shopwareVersion;

    /**
     * Creates a new instance of the customer service.
     *
     * @param EntityRepositoryInterface $countryRepository
     * @param EntityRepositoryInterface $customerRepository
     * @param EventDispatcherInterface $eventDispatcher
     * @param LoggerInterface $logger
     * @param SalesChannelContextPersister $salesChannelContextPersister
     * @param EntityRepositoryInterface $salutationRepository
     * @param string $shopwareVersion
     */
    public function __construct(
        EntityRepositoryInterface $countryRepository,
        EntityRepositoryInterface $customerRepository,
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface $logger,
        SalesChannelContextPersister $salesChannelContextPersister,
        EntityRepositoryInterface $salutationRepository,
        string $shopwareVersion
    )
    {
        $this->countryRepository = $countryRepository;
        $this->customerRepository = $customerRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
        $this->salesChannelContextPersister = $salesChannelContextPersister;
        $this->salutationRepository = $salutationRepository;
        $this->shopwareVersion = $shopwareVersion;
    }

    /**
     * Login the customer.
     *
     * @param CustomerEntity      $customer
     * @param SalesChannelContext $context
     *
     * @return string|null
     */
    public function customerLogin(CustomerEntity $customer, SalesChannelContext $context): ?string
    {
        /** @var string|null $newToken */
        $newToken = null;

        /** @var CustomerBeforeLoginEvent $event */
        $event = new CustomerBeforeLoginEvent($context, $customer->getEmail());

        // Dispatch the before login event
        $this->eventDispatcher->dispatch($event);

        /** @var string $newToken */
        $newToken = $this->salesChannelContextPersister->replace($context->getToken(), $context);

        // Persist the new token
        if(version_compare($this->shopwareVersion, '6.3.3', '<')) {
            // Shopware 6.3.2.x and lower
            $this->salesChannelContextPersister->save(
                $newToken,
                [
                    'customerId' => $customer->getId(),
                    'billingAddressId' => null,
                    'shippingAddressId' => null,
                ]
            );
        } else if (version_compare($this->shopwareVersion, '6.3.4', '<')
            && version_compare($this->shopwareVersion, '6.3.3', '>=')) {
            // Shopware 6.3.3.x
            $this->salesChannelContextPersister->save(
                $newToken,
                [
                    'customerId' => $customer->getId(),
                    'billingAddressId' => null,
                    'shippingAddressId' => null,
                ],
                $customer->getId()
            );
        } else {
            // Shopware 6.3.4+
            $this->salesChannelContextPersister->save(
                $newToken,
                [
                    'customerId' => $customer->getId(),
                    'billingAddressId' => null,
                    'shippingAddressId' => null,
                ],
                $context->getSalesChannel()->getId(),
                $customer->getId()
            );
        }

        /** @var CustomerLoginEvent $event */
        $event = new CustomerLoginEvent($context, $customer, $newToken);

        // Dispatch the customer login event
        $this->eventDispatcher->dispatch($event);

        return $newToken;
    }

    /**
     * Stores the custom fields.
     *
     * @param CustomerEntity $customer
     * @param array $customFields
     * @param Context $context
     *
     * @return EntityWrittenContainerEvent
     */
    public function saveCustomerCustomFields(CustomerEntity $customer, array $customFields, Context $context)
    {
        // Store the custom fields on the customer
        return $this->customerRepository->update([[
            'id' => $customer->getId(),
            'customFields' => $customFields
        ]], $context);
    }

    /**
     * Return a customer entity with address associations.
     *
     * @param string $customerId
     * @param Context $context
     * @return CustomerEntity|null
     */
    public function getCustomer(string $customerId, Context $context) : ?CustomerEntity
    {
        $customer = null;

        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('id', $customerId));
            $criteria->addAssociation('activeShippingAddress');
            $criteria->addAssociation('activeBillingAddress');
            $criteria->addAssociation('defaultShippingAddress');
            $criteria->addAssociation('defaultBillingAddress');
            $criteria->addAssociations([
                'activeShippingAddress.country',
                'activeBillingAddress.country',
                'defaultShippingAddress.country',
                'defaultBillingAddress.country',
            ]);

            /** @var CustomerEntity $customer */
            $customer = $this->customerRepository->search($criteria, $context)->first();
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), [$e]);
        }

        return $customer;
    }

    /**
     * Return an array of address data.
     *
     * @param OrderAddressEntity | CustomerAddressEntity $address
     * @param CustomerEntity $customer
     * @return array
     */
    public function getAddressArray($address, CustomerEntity $customer)
    {
        if ($address === null) {
            return [];
        }

        return [
            'title' => $address->getSalutation() !== null ? $address->getSalutation()->getDisplayName() . '.' : null,
            'givenName' => $address->getFirstName(),
            'familyName' => $address->getLastName(),
            'email' => $customer->getEmail(),
            'streetAndNumber' => $address->getStreet(),
            'streetAdditional' => $address->getAdditionalAddressLine1(),
            'postalCode' => $address->getZipCode(),
            'city' => $address->getCity(),
            'country' => $address->getCountry() !== null ? $address->getCountry()->getIso() : 'NL',
        ];
    }

    /**
     * Returns a country id by it's iso code.
     *
     * @param string  $countryCode
     * @param Context $context
     *
     * @return string|null
     */
    public function getCountryId(string $countryCode, Context $context): ?string
    {
        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('iso', strtoupper($countryCode)));

            // Get countries
            $countries = $this->countryRepository->searchIds($criteria, $context ?? Context::createDefaultContext())->getIds();

            return !empty($countries) ? $countries[0] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Returns a salutation id by it's key.
     *
     * @param Context $context
     *
     * @return string|null
     */
    public function getSalutationId(Context $context): ?string
    {
        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('salutationKey', 'not_specified'));

            // Get salutations
            $salutations = $this->salutationRepository->searchIds($criteria, $context ?? Context::createDefaultContext())->getIds();

            return !empty($salutations) ? $salutations[0] : null;
        } catch (Exception $e) {
            return null;
        }
    }
}
