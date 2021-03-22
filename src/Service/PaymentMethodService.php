<?php

namespace Etbag\TrxpsPayments\Service;

use Etbag\TrxpsPayments\Api\Exceptions\ApiException;
use Etbag\TrxpsPayments\Api\TrxpsApiClient;
use Etbag\TrxpsPayments\Api\Resources\Method;
use Etbag\TrxpsPayments\Api\Resources\MethodCollection;
use Etbag\TrxpsPayments\Handler\Method\BankTransferPayment;
use Etbag\TrxpsPayments\Handler\Method\CreditCardPayment;
use Etbag\TrxpsPayments\Handler\Method\iDealPayment;
use Etbag\TrxpsPayments\Handler\Method\PayDirektPayment;
use Etbag\TrxpsPayments\Handler\Method\PayPalPayment;
use Etbag\TrxpsPayments\Handler\Method\SofortPayment;
use Etbag\TrxpsPayments\Handler\Method\TrxpsPayment;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;

class PaymentMethodService
{
    /** @var MediaService */
    private $mediaService;

    /** @var EntityRepositoryInterface */
    private $paymentRepository;

    /** @var PluginIdProvider */
    private $pluginIdProvider;

    /** @var EntityRepositoryInterface */
    private $mediaRepository;

    /** @var string */
    private $className;

    /**
     * PaymentMethodHelper constructor.
     *
     * @param MediaService              $mediaService
     * @param EntityRepositoryInterface $mediaRepository
     * @param EntityRepositoryInterface $paymentRepository
     * @param PluginIdProvider          $pluginIdProvider
     * @param null                      $className
     */
    public function __construct(
        MediaService $mediaService,
        EntityRepositoryInterface $mediaRepository,
        EntityRepositoryInterface $paymentRepository,
        PluginIdProvider $pluginIdProvider,
        $className = null
    )
    {
        $this->mediaService = $mediaService;
        $this->mediaRepository = $mediaRepository;
        $this->paymentRepository = $paymentRepository;
        $this->pluginIdProvider = $pluginIdProvider;
        $this->className = $className;
    }

    /**
     * Returns the payment repository.
     *
     * @return EntityRepositoryInterface
     */
    public function getRepository(): EntityRepositoryInterface
    {
        return $this->paymentRepository;
    }

    /**
     * Sets the classname.
     *
     * @param string $className
     *
     * @return PaymentMethodService
     */
    public function setClassName(string $className): self
    {
        $this->className = $className;
        return $this;
    }

    /**
     * @param Context $context
     */
    public function addPaymentMethods(Context $context) : void
    {
        // Get the plugin ID
        $pluginId = $this->pluginIdProvider->getPluginIdByBaseClass($this->className, $context);

        // Variables
        $paymentData = [];
        $paymentMethods = $this->getPaymentMethods($context);

        foreach ($paymentMethods as $paymentMethod) {
            // Upload icon to the media repository
            $mediaId = $this->getMediaId($paymentMethod, $context);

            // Build array of payment method data
            $paymentMethodData = [
                'handlerIdentifier' => $paymentMethod['handler'],
                'name' => $paymentMethod['description'],
                'description' => '',
                'pluginId' => $pluginId,
                'mediaId' => $mediaId,
                'afterOrderEnabled' => true,
                'customFields' => [
                    'trxps_payment_method_name' => $paymentMethod['name'],
                ],
            ];

            // Get existing payment method so we can update it by it's ID
            try {
                $existingPaymentMethodId = $this->getPaymentMethodId(
                    $paymentMethodData['handlerIdentifier'],
                    $paymentMethodData['name']
                );
            } catch (InconsistentCriteriaIdsException $e) {
                // On error, we assume the payment method doesn't exist
            }

            if (isset($existingPaymentMethodId) && $existingPaymentMethodId !== null) {
                $paymentMethodData['id'] = $existingPaymentMethodId;
            }

            // Add payment method data to array of payment data
            $paymentData[] = $paymentMethodData;
        }

        // Insert or update payment data
        if (count($paymentData)) {
            $this->paymentRepository->upsert($paymentData, $context);
        }
    }

    /**
     * Activate payment methods in Shopware, based on Trxps.
     *
     * @param TrxpsApiClient $apiClient
     * @param Context         $context
     *
     * @throws ApiException
     */
    public function activatePaymentMethods(TrxpsApiClient $apiClient, Context $context): void
    {
        /** @var MethodCollection $methods */
        $methods = $apiClient->methods->allActive();

        /** @var array $paymentMethods */
        $paymentMethods = $this->getPaymentMethods();

        $handlers = [];

        if ($methods->count) {
            /** @var Method $method */
            foreach ($methods as $method) {
                foreach ($paymentMethods as $paymentMethod) {
                    if ($paymentMethod['name'] === $method->id) {
                        $handlers[] = [
                            'class' => $paymentMethod['handler'],
                            'name' => $paymentMethod['description'],
                        ];
                    }
                }
            }
        }

        if (!empty($handlers)) {
            foreach ($handlers as $handler) {
                /** @var string|null $paymentMethodId */
                $paymentMethodId = $this->getPaymentMethodId($handler['class'], $handler['name']);

                /** @var PaymentMethodEntity $paymentMethod */
                $paymentMethod = null;

                if ((string) $paymentMethodId !== '') {
                    $this->activatePaymentMethod($paymentMethodId, true, $context);
                }
            }
        }
    }

    /**
     * Activates a payment method in Shopware
     *
     * @param string       $paymentMethodId
     * @param bool         $active
     * @param Context|null $context
     *
     * @return EntityWrittenContainerEvent
     */
    public function activatePaymentMethod(
        string $paymentMethodId,
        bool $active = true,
        Context $context = null
    ): EntityWrittenContainerEvent
    {
        return $this->paymentRepository->upsert([[
            'id' => $paymentMethodId,
            'active' => $active
        ]], $context ?? Context::createDefaultContext());
    }

    /**
     * Get payment method by ID.
     *
     * @param $id
     * @return PaymentMethodEntity
     * @throws InconsistentCriteriaIdsException
     */
    public function getPaymentMethodById($id) : ?PaymentMethodEntity
    {
        // Fetch ID for update
        $paymentCriteria = new Criteria();
        $paymentCriteria->addFilter(new EqualsFilter('id', $id));

        // Get payment methods
        $paymentMethods = $this->paymentRepository->search($paymentCriteria, Context::createDefaultContext());

        if ($paymentMethods->getTotal() === 0) {
            return null;
        }

        return $paymentMethods->first();
    }

    /**
     * Get payment method ID by name.
     *
     * @param $handlerIdentifier
     * @param $name
     *
     * @return string|null
     */
    private function getPaymentMethodId($handlerIdentifier, $name) : ?string
    {
        // Fetch ID for update
        $paymentCriteria = new Criteria();
        $paymentCriteria->addFilter(new EqualsFilter('handlerIdentifier', $handlerIdentifier));
        $paymentCriteria->addFilter(new EqualsFilter('name', $name));

        // Get payment IDs
        $paymentIds = $this->paymentRepository->searchIds($paymentCriteria, Context::createDefaultContext());

        if ($paymentIds->getTotal() === 0) {
            return null;
        }

        return $paymentIds->getIds()[0];
    }

    /**
     * Get an array of available payment methods from the Trxps API.
     *
     * @param Context|null $context
     * @return array
     */
    private function getPaymentMethods(?Context $context = null) : array
    {
        // Variables
        $paymentMethods = [];
        $availableMethods = $this->getPaymentHandlers();

        // Add payment methods to array
        if ($availableMethods !== null) {
            foreach ($availableMethods as $availableMethod) {
                $paymentMethods[] = [
                    'name' => constant($availableMethod . '::PAYMENT_METHOD_NAME'),
                    'description' => constant($availableMethod . '::PAYMENT_METHOD_DESCRIPTION'),
                    'handler' => $availableMethod,
                ];
            }
        }

        return $paymentMethods;
    }

    /**
     * Returns an array of payment handlers.
     *
     * @return array
     */
    public function getPaymentHandlers()
    {
        return [
            // BankTransferPayment::class,
            // CreditCardPayment::class,
            // iDealPayment::class,
            // PayDirektPayment::class,
            // PayPalPayment::class,
            // SofortPayment::class,
            TrxpsPayment::class,
        ];
    }

    /**
     * Retrieve the icon from the database, or add it.
     *
     * @param array   $paymentMethod
     * @param Context $context
     *
     * @return string
     */
    private function getMediaId(array $paymentMethod, Context $context): string
    {
        /** @var string $mediaId */
        $mediaId = '';

        /** @var string $fileName */
        $fileName = $paymentMethod['name'] . '-icon';

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('fileName', $fileName));

        /** @var MediaCollection $icons */
        $icons = $this->mediaRepository->search($criteria, $context);

        if ($icons->count() && $icons->first() !== null) {
            $mediaId = $icons->first()->getId();
        } else {
            // Add icon to the media library

            $iconBlob = base64_decode("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");

            $iconMime = 'image/png';
            $iconExt = 'png';
            $mediaId = $this->mediaService->saveFile(
                $iconBlob,
                $iconExt,
                $iconMime,
                $fileName,
                $context,
                'Trxps Payments - Icons',
                null,
                false
            );
        }

        return $mediaId;
    }
}
