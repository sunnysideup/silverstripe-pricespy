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


}
