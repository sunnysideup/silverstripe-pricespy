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
            'Product-name', //1
            'Your-item-number', //2
            'category', //3
            'price-including-gst', //4
            'Product-URL', //5
            'manufacturer', //6
            'manufacturer-SKU', //7
            'EAN-13', //8
            'shipping', //9
            'image-URL', //10
            'stock status', //11
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
                ;
        ';

        $data = DB::query($sql);


        foreach ($data as $page) {
            $productTitle = CsvFunctionality::removeBadCharacters($page['ProductTitle']);
            $internalItemID = CsvFunctionality::removeBadCharacters($page['InternalItemID']);
            $category = CsvFunctionality::removeBadCharacters($page['ParentTitle']);
            $shippingCost = 10;
            $price = $page['Price'] + $shippingCost;
            $link = Controller::join_links($baseURL, CsvFunctionality::removeBadCharacters($page['InternalItemID'])) . '?utm_source=PriceSpy';
            $brand = CsvFunctionality::removeBadCharacters($page['BrandTitle']);
            $page['BrandTitle'] = 'TBC';
            $page['SKU'] = 'TBC';
            $page['EAN'] = 'TBC';
            $shipping = $shippingCost;
            $imageLink = Controller::join_links($assetUrl, ($page['FileFilename'] ?: $defaultImageLink));
            $stock = 1;

            $array[] = [
                $productTitle, //1. Product-name
                $internalItemID, //2. Your-item-number
                $category, //3. category
                $price, //4. price-including-gst
                $link, //5. Product-URL
                $brand, //6. manufacturer
                $page['SKU'], //7. manufacturer-SKU
                $page['EAN'], //8 ean
                $shipping, //9. shipping
                $imageLink, //10. image-URL
                $stock, //11. stock status
            ];
        }

        return $array;
    }

    // protected static function getBuyableTableNameName(?string $baseClass = Product::class): string
    // {
    //     $obj = Injector::inst()->get($baseClass);
    //     $stage = self::getStage();

    //     return $obj->baseTable() . $stage;
    // }

    // /**
    //  * Returns a versioned record stage table suffix (i.e "" or "_Live").
    //  *
    //  * @return string
    //  */
    // protected static function getStage(): string
    // {
    //     $stage = '';

    //     if ('Live' === Versioned::get_stage()) {
    //         $stage = '_Live';
    //     }

    //     return $stage;
    // }

}
