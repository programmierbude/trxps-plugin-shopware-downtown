<?php declare(strict_types=1);

namespace Etbag\TrxpsPayments\Service;

use Etbag\TrxpsPayments\Setting\TrxpsSettingStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SettingsService
{
    public const SYSTEM_CONFIG_DOMAIN = 'TrxpsPayments.config.';

    /** @var SystemConfigService */
    protected $systemConfigService;

    public function __construct(
        SystemConfigService $systemConfigService
    )
    {
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * Get Trxps settings from configuration.
     *
     * @param string|null  $salesChannelId
     * @param Context|null $context
     *
     * @return TrxpsSettingStruct
     */
    public function getSettings(?string $salesChannelId = null, ?Context $context = null): TrxpsSettingStruct
    {
        $structData = [];
        $systemConfigData = $this->systemConfigService->getDomain(self::SYSTEM_CONFIG_DOMAIN, $salesChannelId, true);

        foreach ($systemConfigData as $key => $value) {
            if (stripos($key, self::SYSTEM_CONFIG_DOMAIN) !== false) {
                $structData[substr($key, strlen(self::SYSTEM_CONFIG_DOMAIN))] = $value;
            } else {
                $structData[$key] = $value;
            }
        }

        return (new TrxpsSettingStruct())->assign($structData);
    }
}