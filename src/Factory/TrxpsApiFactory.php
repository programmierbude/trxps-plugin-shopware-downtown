<?php

namespace Etbag\TrxpsPayments\Factory;

use Exception;
use Etbag\TrxpsPayments\Service\ConfigService;
use Etbag\TrxpsPayments\Service\SettingsService;
use Etbag\TrxpsPayments\Setting\TrxpsSettingStruct;
use Etbag\TrxpsPayments\Api\Exceptions\IncompatiblePlatform;
use Etbag\TrxpsPayments\Api\TrxpsApiClient;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Kernel;

class TrxpsApiFactory
{
    /** @var TrxpsApiClient */
    private $apiClient;

    /** @var SettingsService */
    private $settingsService;

    /** @var LoggerInterface */
    private $logger;

    /**
     * Create a new instance of TrxpsApiFactory.
     *
     * @param SettingsService $settingsService
     * @param LoggerInterface $logger
     */
    public function __construct(
        SettingsService $settingsService,
        LoggerInterface $logger
    )
    {
        $this->settingsService = $settingsService;
        $this->logger = $logger;
    }

    /**
     * Create a new instance of the Trxps API client.
     *
     * @param string|null $salesChannelId
     *
     * @return TrxpsApiClient
     * @throws IncompatiblePlatform
     */
    public function createClient(?string $salesChannelId = null): TrxpsApiClient
    {
        if ($this->apiClient === null) {
            $this->apiClient = $this->getClient($salesChannelId);
        }

        return $this->apiClient;
    }

    /**
     * Returns a new instance of the Trxps API client.
     *
     * @param string|null  $salesChannelId
     * @param Context|null $context
     *
     * @return TrxpsApiClient
     * @throws IncompatiblePlatform
     */
    public function getClient(?string $salesChannelId = null, ?Context $context = null): TrxpsApiClient
    {
        /** @var TrxpsApiClient apiClient */
        $this->apiClient = new TrxpsApiClient();

        /** @var TrxpsSettingStruct $settings */
        $settings = $this->settingsService->getSettings($salesChannelId, $context);

        try {
            // Set the API key
            $this->apiClient->setApiKey(
                $settings->isTestMode() ? $settings->getTestApiKey() : $settings->getLiveApiKey()
            );
            // Set the Shop Id
            $this->apiClient->setShopId(
                $settings->isTestMode() ? $settings->getTestShopId() : $settings->getLiveShopId()
            );
            $this->apiClient->setApiTestmode($settings->isTestMode());

            // Add platform data
            $this->apiClient->addVersionString(
                'Shopware/' .
                Kernel::SHOPWARE_FALLBACK_VERSION
            );

            // @todo Add plugin version variable
            $this->apiClient->addVersionString(
                'TrxpsShopware6/1.1.1'
            );
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), [$e]);
        }

        return $this->apiClient;
    }
}
