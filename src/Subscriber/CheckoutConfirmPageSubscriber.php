<?php

namespace Etbag\TrxpsPayments\Subscriber;

use Exception;
use Etbag\TrxpsPayments\Service\CustomerService;
use Etbag\TrxpsPayments\Service\CustomFieldService;
use Etbag\TrxpsPayments\Service\SettingsService;
use Etbag\TrxpsPayments\Setting\TrxpsSettingStruct;
use Etbag\TrxpsPayments\Api\Exceptions\ApiException;
use Etbag\TrxpsPayments\Api\TrxpsApiClient;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;




class CheckoutConfirmPageSubscriber implements EventSubscriberInterface
{
    /** @var TrxpsApiClient */
    private $apiClient;

    /** @var SettingsService */
    private $settingsService;

    /** @var EntityRepositoryInterface */
    private $languageRepositoryInterface;

    /** @var EntityRepositoryInterface */
    private $localeRepositoryInterface;


    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     * * The method name to call (priority defaults to 0)
     * * An array composed of the method name to call and the priority
     * * An array of arrays composed of the method names to call and respective
     *   priorities, or 0 if unset
     *
     * For instance:
     *
     * * array('eventName' => 'methodName')
     * * array('eventName' => array('methodName', $priority))
     * * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'addDataToPage',
        ];
    }

    /**
     * Creates a new instance of the checkout confirm page subscriber.
     *
     * @param TrxpsApiClient $apiClient
     * @param SettingsService $settingsService
     */
    public function __construct(
        TrxpsApiClient $apiClient,
        SettingsService $settingsService,
        EntityRepositoryInterface $languageRepositoryInterface,
        EntityRepositoryInterface $localeRepositoryInterface

    )
    {
        $this->apiClient = $apiClient;
        $this->settingsService = $settingsService;
        $this->languageRepositoryInterface = $languageRepositoryInterface;
        $this->localeRepositoryInterface = $localeRepositoryInterface;
    }

    /**
     * @param PageLoadedEvent|CheckoutConfirmPageLoadedEvent $args
     */
    public function addDataToPage($args): void
    {
        $this->addTrxpsComponentsVariableToPage($args);
    }

    /**
     * Adds the components variable to the storefront.
     *
     * @param PageLoadedEvent|CheckoutConfirmPageLoadedEvent $args
     */
    public function addTrxpsComponentsVariableToPage($args)
    {
        // /** @var TrxpsSettingStruct $settings */
        // $settings = $this->settingsService->getSettings(
        //     $args->getSalesChannelContext()->getSalesChannel()->getId(),
        //     $args->getContext()
        // );

        // $args->getPage()->assign([
        //     'enable_credit_card_components' => $settings->getEnableCreditCardComponents(),
        // ]);
    }
}
