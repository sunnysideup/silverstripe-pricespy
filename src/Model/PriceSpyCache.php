<?php

namespace Sunnysideup\Pricespy\Model;

use DOMDocument;
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
use Sunnysideup\Pricespy\Providers\PriceSpyDataProvider;

/**
 * Class \Sunnysideup\Pricespy\Model\PriceSpyCache.
 *
 * @property string $Title
 * @property string $Link
 * @property int $ProductCount
 */
class PriceSpyCache extends DataObject
{
    /**
     * @var string
     */
    private static $file_name = 'products.xml';

    private static $do_xml = true;


    private static $table_name = 'PriceSpyCache';

    private static $db = [
        'Title' => 'Varchar(255)',
        'Link' => 'Varchar(255)',
        'ProductCount' => 'Int',
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
        if (!$obj || !$obj->exists()) {
            $obj = $className::create();
            $obj->write();
        }

        return $obj;
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
                    '<p class="message warning">Create a new cache by writing this one.</p>',
                ),

                LiteralField::create(
                    'ReviewCurrentOne',
                    '<p class="message good">Review current version as
                        <a href="' . $this->Link . '">xml file</a> or
                    </p>',
                ),
            ]
        );

        return $fields;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->WarmCache();
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
            $this->Link = $this->getSpecificLink();
            $this->saveToFile($data, 'getDataAsXMLInner');
        } else {
            file_put_contents($this->getFilePath(), ' Not enabled');
        }
        $this->write();
        return  file_get_contents($this->getFilePath()),
    }

    protected function getSpecificLink()
    {
        return Controller::join_links(Director::absoluteBaseURL(), '/', $this->config()->file_name);
    }
    protected function saveToFile(array $data, string $method)
    {
        $path = $this->getFilePath();
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
     * returns xml. If the cache is expired, it redoes (warms) the cache.
     * Otherwise straight from the cached file
     *
     * @param boolean|null $forceNew
     * @return string
     */
    public function getDataAs(?bool $forceNew = false): string
    {
        if (false === $forceNew) {
            $maxCacheAge = strtotime('NOW') - ($this->Config()->max_age_in_minutes * 60);
            if (strtotime((string) $this->LastEdited) > $maxCacheAge) {
                $path = $this->getFilePath();
                if (file_exists($path)) {
                    $timeChange = filemtime($path);
                    if ($timeChange > $maxCacheAge) {
                        return file_get_contents($path);
                    }
                }
            }
        }

        return $this->WarmCache();
    }

    public function getFileLastUpdated(): string
    {
        return date('Y-m-d H:i', filemtime($this->getFilePath()));
    }


    protected function getFilePath(): string
    {
        return Controller::join_links(Director::baseFolder(), PUBLIC_DIR, $this->config()->file_name);
    }


    protected function getDataAsXMLInner(array $data): string
    {


        $data = $this->convertToAssociativeArray($data);
        Config::modify()->set(ContentNegotiator::class, 'enabled', false);

        $xmlString =
            '<?xml version="1.0" encoding="UTF-8"?>
                <rss xmlns:pj="https://schema.prisjakt.nu/ns/1.0" xmlns:g="http://base.google.com/ns/1.0" version="3.0">
                <channel>
                    <title>Prisjakt Minimal Example Feed</title>
                    <description>This is an example feed with the minimal values required</description>
                    <link>https://schema.prisjakt.nu</link>
                </channel>
            </rss>
            ';
        $xml = simplexml_load_string($xmlString);

        // Adding item under channel
        $channel = $xml->channel;
        foreach ($data as $entry) {
            $item = $channel->addChild('item');
            $this->addArrayToXml($entry, $item);
        }
        return $this->formatXml($xml->asXML());
    }

    protected function addArrayToXml($item, SimpleXMLElement $xml)
    {
        foreach ($item as $key => $value) {
            // Add child with namespace
            if (is_array($value)) {
                $subnode = $xml->addChild($key, null, 'http://base.google.com/ns/1.0');
                $this->addArrayToXml($value, $subnode);
            } else {
                $xml->addChild($key, htmlspecialchars($value), 'http://base.google.com/ns/1.0');
            }
        }
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

    private function formatXml(string $xmlContent): string
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xmlContent);

        return $dom->saveXML();
    }
}
