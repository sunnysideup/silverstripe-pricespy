<?php

namespace Sunnysideup\Pricespy\Control;

use Sunnysideup\EcommerceGoogleShoppingFeed\Controllers\GoogleShoppingFeedController;
use Sunnysideup\Pricespy\Providers\PriceSpyDataProvider;

/**
 * provides data for the PriceSpy App.
 *
 */
class PriceSpy extends GoogleShoppingFeedController
{
    /**
     * @var array
     */
    private static $allowed_actions = [
        'index',
    ];


    private static $dependencies = [
        'dataProviderAPI' => '%$' . PriceSpyDataProvider::class,
    ];

    protected function getFileName(): string
    {
        return 'pricespyproducts.xml';
    }

    protected function getTitle(): string
    {
        return 'PriceSpy Feed  ('.$this->getProductCount().')';
    }

    protected function getSchema(): string
    {
        return '<rss xmlns:pj="https://schema.prisjakt.nu/ns/1.0" xmlns:g="http://base.google.com/ns/1.0" version="3.0">';
    }

}
