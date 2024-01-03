<?php

namespace Sunnysideup\Pricespy\Control;

use SilverStripe\Control\ContentNegotiator;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Middleware\HTTPCacheControlMiddleware;
use SilverStripe\Core\Config\Config;
use Sunnysideup\Pricespy\Model\PriceSpyCache ;

/**
 * provides data for the PriceSpy App.
 *
 */
class PriceSpy extends Controller
{
    protected $filename = 'products';

    public function index($request)
    {
        Config::modify()->set(ContentNegotiator::class, 'enabled', false);
        $this->getResponse()->addHeader('Content-Type', 'text/xml; charset="utf-8"');
        $this->getResponse()->addHeader('Content-Disposition', 'attachment; filename=' . $this->filename . '.xml');
        $this->getResponse()->addHeader('Pragma', 'no-cache');
        HTTPCacheControlMiddleware::singleton()
            ->disableCache()
        ;
        return $this->getDataAsString();
    }

    public function MyOutput()
    {
        return $this->getDataAsString();
    }

    protected function getDataAsString(): string
    {
        $flush = $this->getRequest()->getVar('flush') ? true : false;
        return PriceSpyCache::inst(PriceSpyCache::class)->getDataAs('xml', $flush);
    }
}
