import './extension/sw-customer';
import './extension/sw-order';
import './extension/sw-plugin';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('trxps-payments', {
    type: 'plugin',
    name: 'TrxpsPayments',
    title: 'trxps-payments.general.mainMenuItemGeneral',
    description: 'trxps-payments.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#333',
    icon: 'default-action-settings',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    }
});
