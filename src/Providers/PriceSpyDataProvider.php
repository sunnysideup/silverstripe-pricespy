<?php

namespace Sunnysideup\Pricespy\Providers;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\Versioned;
use Sunnysideup\Ecommerce\Api\Converters\CsvFunctionality;
use Sunnysideup\Ecommerce\Api\ProductCollection;
use Sunnysideup\Ecommerce\Model\Config\EcommerceDBConfig;
use Sunnysideup\Ecommerce\Pages\Product;
use Sunnysideup\Pricespy\Interfaces\PriceSpyDataProviderInterface;

class PriceSpyDataProvider extends ProductCollection
{
    public function getArrayFull(?string $where = ''): array {}

    protected static function getBuyableTableNameName(?string $baseClass = Product::class): string
    {
        $obj = Injector::inst()->get($baseClass);
        $stage = self::getStage();

        return $obj->baseTable() . $stage;
    }

    /**
     * Returns a versioned record stage table suffix (i.e "" or "_Live").
     *
     * @return string
     */
    protected static function getStage(): string
    {
        $stage = '';

        if ('Live' === Versioned::get_stage()) {
            $stage = '_Live';
        }

        return $stage;
    }

}
