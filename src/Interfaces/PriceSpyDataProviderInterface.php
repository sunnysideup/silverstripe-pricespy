<?php

namespace Sunnysideup\Pricespy\Interfaces;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\Versioned;
use Sunnysideup\Ecommerce\Api\Converters\CsvFunctionality;
use Sunnysideup\Ecommerce\Model\Config\EcommerceDBConfig;
use Sunnysideup\Ecommerce\Pages\Product;

interface PriceSpyDataProviderInterface
{
    //######################
    //## Names Section
    //######################

    public function getDataAsArray(?string $where = ''): array;
}
