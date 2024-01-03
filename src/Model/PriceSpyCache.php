<?php

namespace Sunnysideup\Pricespy\Model;

use SilverStripe\Control\ContentNegotiator;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SimpleXMLElement;
use Sunnysideup\Ecommerce\Api\Converters\CsvFunctionality;
use Sunnysideup\Pricespy\Providers\PriceSpyDataProvider;

/**
 * Class \Sunnysideup\Pricespy\Model\PriceSpyCache.
 *
 * @property string $Title
 * @property string $Link
 * @property int $ProductCount
 * @property bool $RunNow
 */
class PriceSpyCache extends DataObject
{
    /**
     * @var string
     */
    private static $file_name = 'products';

    private static $do_xml = true;

    private static $do_csv = false;

    private static $table_name = 'PriceSpyCache';

    private static $db = [
        'Title' => 'Varchar(255)',
        'LinkAsCSV' => 'Varchar(255)',
        'LinkAsXML' => 'Varchar(255)',
        'ProductCount' => 'Int',
        'RunNow' => 'Boolean',
    ];

    private static $default_sort = [
        'ID' => 'DESC',
    ];

    private static $dependencies = [
        'dataProviderAPI' => '%$' . PriceSpyDataProvider::class,
    ];

    //######################
    //## Names Section
    //######################

    private static $singular_name = 'Price Spy Cache';

    private static $plural_name = 'Price Spy Caches';

    private static $summary_fields = [
        'Title' => 'Type',
        'LastEdited.Ago' => 'Last updated',
        'ProductCount' => 'Products',
    ];

    public static function inst(string $className = '')
    {
        if (!$className) {
            $className = static::class;
        }

        $obj = DataObject::get_one($className, ['ClassName' => $className]);
        if (!$obj) {
            $obj = $className::create();
            $obj->write();
        }

        return $obj;
    }

    public function canEdit($member = null): bool
    {
        return false;
    }

    public function canDelete($member = null): bool
    {
        return parent::canDelete($member);
    }

    public function canCreate($member = null, $context = []): bool
    {
        return parent::canCreate($member, $context);
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->addFieldsToTab(
            'Root.Main',
            [

                ReadonlyField::create(
                    'LastEditedNice',
                    'Cached created',
                ),

                LiteralField::create(
                    'CreateNewOne',
                    '<p class="message warning">Create a new cache <a href="/createpricespyproducts">now</a>?</p>',
                ),

                LiteralField::create(
                    'ReviewCurrentOne',
                    '<p class="message good">Review current version as
                        <a href="' . $this->LinkAsCSV . '">csv</a> or
                        <a href="' . $this->LinkAsXML . '">xml</a>.
                    </p>',
                ),
            ]
        );

        return $fields;
    }
    public function getLastEditedNice()
    {
        return DBField::create_field(DBDatetime::class, $this->LastEdited)->ago();
    }

    public function getDataAsArray(?string $where = ''): array
    {
        return $this->dataProviderAPI->getDataAsArray($where);
    }

    public function WarmCache()
    {
        $data = $this->getDataAsArray();
        $this->Title = $this->Config()->singular_name . ' ran: ' . date('Y-m-d H:i');
        $this->ProductCount = count($data);
        if($this->Config()->do_xml) {
            $this->LinkAsXML = $this->getSpecificLink('xml');
            $this->WarmCacheInner($data, 'getDataAsXMLInner', 'xml');
        }
        if($this->Config()->do_csv) {
            $this->LinkAsCSV = $this->getSpecificLink('csv');
            $this->WarmCacheInner($data, 'getDataAsCSVInner', 'csv');
        }
        $this->write();

    }

    protected function getSpecificLink(string $extension)
    {
        return Controller::join_links(Director::absoluteBaseURL(), '/', $this->config()->file_name . '.' . $extension);
    }
    protected function WarmCacheInner(array $data, string $method, string $extension)
    {
        $path = $this->getFilePath($extension);
        if (file_exists($path)) {
            try {
                @unlink($path);
            } catch (\Exception $exception) {
            }
        }
        $output = $this->$method($data);
        file_put_contents($path, $output);
    }

    /**
     * returns CSV. If the cache is expired, it redoes (warms) the cache.
     * Otherwise straight from the cached file
     *
     * @param boolean|null $forceNew
     * @return string
     */
    public function getDataAs(?string $extension = 'xml', ?bool $forceNew = false): string
    {
        if (false === $forceNew) {
            $maxCacheAge = strtotime('Now') - ($this->Config()->max_age_in_minutes * 60);
            if (strtotime((string) $this->LastEdited) > $maxCacheAge) {
                $path = $this->getFilePath($extension);
                if (file_exists($path)) {
                    $timeChange = filemtime($path);
                    if ($timeChange > $maxCacheAge) {
                        return file_get_contents($path);
                    }
                }
            }
        }

        $this->WarmCache();
        return $this->getDataAs($extension, false);
    }

    public function getFileLastUpdated(string $extension = 'xml'): string
    {
        return date('Y-m-d H:i', filemtime($this->getFilePath($extension)));
    }

    protected function onAfterWrite()
    {
        parent::onAfterWrite();
        if ($this->RunNow) {
            $this->RunNow = false;
            $this->write();
            $this->WarmCache();
        }
    }

    protected function getFilePath(string $extension = 'xml'): string
    {
        return Controller::join_links(Director::baseFolder(), PUBLIC_DIR, $this->config()->file_name . '.' . $extension);
    }

    protected function getDataAsCSVInner(array $data): string
    {
        Config::modify()->set(ContentNegotiator::class, 'enabled', false);

        return CsvFunctionality::convertToCSV($data);
    }

    protected function getDataAsXMLInner(array $data): string
    {
        Config::modify()->set(ContentNegotiator::class, 'enabled', false);
        $xml = new SimpleXMLElement('<root/>');
        $data = $this->convertToAssociativeArray($data);
        array_walk_recursive($data, array($xml, 'addChild'));
        return $xml->asXML();
    }
    protected function convertToAssociativeArray(array $data): array
    {
        $headers = array_shift($data);
        $associativeArray = [];

        foreach ($data as $row) {
            $associativeArray[] = array_combine($headers, $row);
        }

        return $associativeArray;
    }

}
