<?php


namespace Etbag\TrxpsPayments\Helper;


use Etbag\TrxpsPayments\Setting\TrxpsSettingStruct;
use Etbag\TrxpsPayments\Api\TrxpsApiClient;

class ModeHelper
{
    public static function addModeToData(array &$data, TrxpsApiClient $apiClient, TrxpsSettingStruct $settings): void
    {
        if ($apiClient->usesOAuth() === true && $settings->isTestMode() === true) {
            $data['testmode'] = true;
        }

        if ($apiClient->usesOAuth() === false) {
            if ($settings->isTestMode() === true) {
                $data['mode'] = 'test';
            } else {
                $data['mode'] = 'live';
            }
        }
    }
}