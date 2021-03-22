<?php

namespace Etbag\TrxpsPayments\Setting;

use Shopware\Core\Framework\Struct\Struct;

class TrxpsSettingStruct extends Struct
{
    /**
     * @var string
     */
    protected $liveApiKey;

    /**
     * @var string
     */
    protected $testApiKey;

    /**
     * @var string
     */
    protected $liveShopId;

    /**
     * @var string
     */
    protected $testShopId;

    /**
     * @var bool
     */
    protected $testMode = true;

    /**
     * @var bool
     */
    protected $debugMode = false;

    /**
     * @return string
     */
    public function getLiveApiKey(): string
    {
        return (string)$this->liveApiKey;
    }

    /**
     * @param string $liveApiKey
     *
     * @return self
     */
    public function setLiveApiKey(string $liveApiKey): self
    {
        $this->liveApiKey = $liveApiKey;
        return $this;
    }

    /**
     * @return string
     */
    public function getTestApiKey(): string
    {
        return (string)$this->testApiKey;
    }

    /**
     * @param string $testApiKey
     *
     * @return self
     */
    public function setTestApiKey(string $testApiKey): self
    {
        $this->testApiKey = $testApiKey;
        return $this;
    }

    /**
     * @return string
     */
    public function getLiveShopId(): string
    {
        return (string)$this->liveShopId;
    }

    /**
     * @param string $liveShopId
     *
     * @return self
     */
    public function setLiveShopId(string $liveShopId): self
    {
        $this->liveShopId = $liveShopId;
        return $this;
    }

    /**
     * @return string
     */
    public function getTestShopId(): string
    {
        return (string)$this->testShopId;
    }

    /**
     * @param string $testShopId
     *
     * @return self
     */
    public function setTestShopId(string $testShopId): self
    {
        $this->testShopId = $testShopId;
        return $this;
    }

    /**
     * @return bool
     */
    public function isTestMode(): bool
    {
        return (bool)$this->testMode;
    }

    /**
     * @param bool $testMode
     *
     * @return self
     */
    public function setTestMode(bool $testMode): self
    {
        $this->testMode = $testMode;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDebugMode(): bool
    {
        return (bool)$this->debugMode;
    }

    /**
     * @param bool $debugMode
     *
     * @return self
     */
    public function setDebugMode(bool $debugMode): self
    {
        $this->debugMode = $debugMode;
        return $this;
    }
}
