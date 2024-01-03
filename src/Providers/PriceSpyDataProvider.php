<?php

namespace Sunnysideup\Pricespy\Providers;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\Versioned;
use Sunnysideup\Ecommerce\Api\Converters\CsvFunctionality;
use Sunnysideup\Ecommerce\Model\Config\EcommerceDBConfig;
use Sunnysideup\Ecommerce\Pages\Product;
use Sunnysideup\Pricespy\Interfaces\PriceSpyDataProviderInterface;

class PriceSpyDataProvider implements PriceSpyDataProviderInterface
{
    public function getDataAsArray(?string $where = ''): array
    {
        $defaultImageLink = EcommerceDBConfig::current_ecommerce_db_config()->DefaultProductImage()->Filename;
        $baseURL = Director::absoluteBaseURL();
        $assetUrl = Controller::join_links($baseURL, 'assets');
        $array = [];
        $array[] = [
            'id', //1
            'title', //2
            'price', //3
            'link', //4
            'availability', //5
            'condition', //6
            'image_link', //6
        ];
        /** @todo rewrite to re-useable DB::Select() with prepared statements */
        $sql = '
            SELECT
                "SiteTree_Live"."ID" ProductID,
                "SiteTree_Live"."Title" ProductTitle,
                "Product_Live"."InternalItemID",
                "Product_Live"."Price",
                "File"."FileFilename",
                "ParentSiteTree"."Title" as ParentTitle
            FROM
                "SiteTree_Live"
            INNER JOIN
                "SiteTree_Live" AS ParentSiteTree ON "ParentSiteTree"."ID" = "SiteTree_Live"."ParentID"
            INNER JOIN
                "Product_Live" ON "SiteTree_Live"."ID" = "Product_Live"."ID"
            INNER JOIN
                "Product_ProductGroups" ON "Product_Live"."ID" = "Product_ProductGroups"."ProductID"
            INNER JOIN
                "ProductGroup_Live" ON "Product_ProductGroups"."ProductGroupID" = "ProductGroup_Live"."ID"

            LEFT JOIN
                "File" ON "Product_Live"."ImageID" = "File"."ID"
            WHERE
                ' . $where . '
                "Product_Live"."AllowPurchase" = 1
            ORDER BY
                "SiteTree_Live"."ID" DESC
            LIMIT 10
                ;
        ';

        $data = DB::query($sql);

        foreach ($data as $page) {
            $internalItemID = CsvFunctionality::removeBadCharacters($page['InternalItemID']);
            $productTitle = CsvFunctionality::removeBadCharacters($page['ProductTitle']);
            $price = $page['Price'];
            $link = Controller::join_links($baseURL, CsvFunctionality::removeBadCharacters($page['InternalItemID'])) . '?utm_source=PriceSpy';
            $stock = 'in_stock';
            $condition = 'new';
            $imageLink = Controller::join_links($assetUrl, ($page['FileFilename'] ?: $defaultImageLink));
            // $shippingCost = 10;
            // $category = CsvFunctionality::removeBadCharacters($page['ParentTitle']);
            // $brand = 'NO BRAND';
            // $sku = 'TBC';
            // $ean = 'TBC';
            // $shipping = $shippingCost;

            $array[] = [
                $internalItemID, //1. Your-item-number
                $productTitle, //2. Product-name
                $price, //3. price-including-gst
                $link, //4. link
                $stock, //5. stock status
                $condition, //6. condition
                $imageLink, //6. condition
            ];
        }
        return $array;
    }

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
