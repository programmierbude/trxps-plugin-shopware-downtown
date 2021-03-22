<?php


namespace Etbag\TrxpsPayments\Helper;


use Etbag\TrxpsPayments\Setting\TrxpsSettingStruct;
use Etbag\TrxpsPayments\Api\Exceptions\ApiException;
use Etbag\TrxpsPayments\Api\TrxpsApiClient;
use Etbag\TrxpsPayments\Api\Resources\Profile;

class ProfileHelper
{
    /**
     * Returns the current profile for Trxps.
     *
     * @param array               $data
     * @param TrxpsApiClient     $apiClient
     * @param TrxpsSettingStruct $settings
     */
    public static function addProfileToData(array &$data, TrxpsApiClient $apiClient, TrxpsSettingStruct $settings): void
    {
        $profile = self::getProfile($apiClient, $settings);

        if ($profile !== null && isset($profile->id)) {
            $data['profileId'] = $profile->id;
        }
    }

    /**
     * Returns the current profile for Trxps's API.
     *
     * @param TrxpsApiClient     $apiClient
     * @param TrxpsSettingStruct $settings
     *
     * @return Profile|null
     */
    public static function getProfile(TrxpsApiClient $apiClient, TrxpsSettingStruct $settings): ?Profile
    {
        /** @var Profile $profile */
        $profile = null;

        try {
            if ($apiClient->usesOAuth() === false) {
                $profile = $apiClient->profiles->getCurrent();

                if ($settings->getProfileId() !== null) {
                    $profile = $apiClient->profiles->get($settings->getProfileId());
                }
            }

            if ($apiClient->usesOAuth() === true) {
                if ($apiClient->profiles->page()->count > 0) {
                    $offset = $apiClient->profiles->page()->count - 1;

                    if ($apiClient->profiles->page()->offsetExists($offset)) {
                        $profile = $apiClient->profiles->page()->offsetGet($offset);
                    } else {
                        $profile = $apiClient->profiles->page()->offsetGet(0);
                    }
                }

                if ($settings->getProfileId() !== null) {
                    $profile = $apiClient->profiles->get($settings->getProfileId());
                }
            }
        } catch (ApiException $e) {
            //
        }

        return $profile;
    }
}