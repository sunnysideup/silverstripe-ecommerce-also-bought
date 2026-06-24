<?php

namespace Sunnysideup\EcommerceAlsoBought\Model;

use SilverStripe\ORM\ManyManyList;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\GridFieldArchiveAction;
use Sunnysideup\Ecommerce\Config\EcommerceConfig;
use Sunnysideup\Ecommerce\Forms\Gridfield\Configs\GridFieldConfigForProducts;
use Sunnysideup\Ecommerce\Pages\Product;

/**
 * Class \Sunnysideup\EcommerceAlsoBought\Model\EcommerceAlsoBoughtDOD
 *
 * @property Product|EcommerceAlsoBoughtDOD $owner
 * @method ManyManyList|Product[] EcommerceAlsoBoughtProducts()
 * @method ManyManyList|Product[] BoughtFor()
 */
class EcommerceAlsoBoughtDOD extends Extension
{

    private static $minimum_strength = 20;

    private static $many_many = [
        'EcommerceAlsoBoughtProducts' => Product::class,
    ];

    private static $many_many_extraFields = [
        'EcommerceAlsoBoughtProducts' => [
            'Strength' => 'Float',
            'AutomaticallyAdded' => 'Boolean',
            'PercentageAddedAfterwards' => 'Percentage',
        ],
    ];

    private static $belongs_many_many = [
        'BoughtFor' => Product::class,
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $owner = $this->getOwner();
        $config = EcommerceConfig::inst();
        if ($config->ShowFullDetailsForProducts && $this->getOwner() instanceof Product) {
            $fields->addFieldsToTab(
                'Root.Recommend',
                [
                    GridField::create(
                        'EcommerceAlsoBoughtProducts',
                        'Also Bought Products',
                        $owner->EcommerceAlsoBoughtProducts(),
                        GridFieldConfigForProducts::create()
                            ->removeComponentsByType(GridFieldArchiveAction::class)
                            ->removeComponentsByType((GridFieldAddExistingAutocompleter::class))
                    ),
                ]
            );
        }
    }

    /**
     * only returns the products that are for sale
     * if only those need to be showing.
     *
     * @return DataList
     */
    public function EcommerceAlsoBoughtProductsForSale()
    {
        $owner = $this->getOwner();
        $minStrength = $owner->config()->get('minimum_strength');
        if (!is_numeric($minStrength)) {
            $minStrength = self::$minimum_strength; // default value
        }

        $list = $owner->getManyManyComponents('EcommerceAlsoBoughtProducts')
            ->where(['Product_EcommerceAlsoBoughtProducts.Strength > ' . $minStrength])
            ->orderBy('Product_EcommerceAlsoBoughtProducts.Strength DESC');

        return $this->addAllowPurchaseFilter($list);
    }

    /**
     * only returns the products that are for sale
     * if only those need to be showing.
     *
     * @return DataList
     */
    public function BoughtForForSale()
    {
        $owner = $this->getOwner();
        $list = $owner->BoughtFor();

        return $this->addAllowPurchaseFilter($list);
    }

    protected function addAllowPurchaseFilter(DataList $list)
    {
        $owner = $this->getOwner();
        if (EcommerceConfig::inst()->OnlyShowProductsThatCanBePurchased) {
            $list = $list->filter(['AllowPurchase' => 1]);
        }

        $list = $list->orderBy('Product_EcommerceAlsoBoughtProducts.Strength DESC');

        return $list;
    }

    public function onRequireDefaultRecords()
    {
        DB::require_index(
            'Product_EcommerceAlsoBoughtProducts',
            'AutomaticallyAdded',
            [
                'type' => 'index',
                'columns' => ['AutomaticallyAdded'],
            ],
        );
        DB::require_index(
            'Product_EcommerceAlsoBoughtProducts', // Table name
            'Strength',  // Field name
            [
                'type' => 'index',
                'columns' => ['Strength'],
            ],
        );
        DB::require_index(
            'Product_EcommerceAlsoBoughtProducts', // Table name
            'UserAddedAfterwards',  // Field name
            [
                'type' => 'index',
                'columns' => ['PercentageAddedAfterwards'],
            ],
        );
    }

    public function EcommerceAlsoBoughtProducts()
    {
        $owner = $this->getOwner();
        return $owner->getManyManyComponents('EcommerceAlsoBoughtProducts')
            ->orderBy('Product_EcommerceAlsoBoughtProducts.Strength DESC');
    }
}
