<?php

namespace Sunnysideup\EcommerceAlsoBought\Model;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataList;
use Sunnysideup\Ecommerce\Config\EcommerceConfig;
use Sunnysideup\Ecommerce\Forms\Gridfield\Configs\GridFieldConfigForProducts;
use Sunnysideup\Ecommerce\Pages\Product;

/**
 * Class \Sunnysideup\EcommerceAlsoBought\Model\EcommerceAlsoBoughtDOD
 *
 * @property \Sunnysideup\Ecommerce\Pages\Product|\Sunnysideup\EcommerceAlsoBought\Model\EcommerceAlsoBoughtDOD $owner
 * @method \SilverStripe\ORM\ManyManyList|\Sunnysideup\Ecommerce\Pages\Product[] EcommerceAlsoBoughtProducts()
 * @method \SilverStripe\ORM\ManyManyList|\Sunnysideup\Ecommerce\Pages\Product[] BoughtFor()
 */
class EcommerceAlsoBoughtDOD extends DataExtension
{
    private static $many_many = [
        'EcommerceAlsoBoughtProducts' => Product::class,
    ];

    private static $belongs_many_many = [
        'BoughtFor' => Product::class,
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $owner = $this->getOwner();
        if ($this->owner instanceof Product) {
            $fields->addFieldsToTab(
                'Root.Recommend',
                [
                    GridField::create(
                        'EcommerceAlsoBoughtProducts',
                        'Also Bought Products',
                        $owner->EcommerceAlsoBoughtProducts(),
                        GridFieldConfigForProducts::create()
                    ),
                    GridField::create(
                        'BoughtFor',
                        'Bought For',
                        $owner->BoughtFor(),
                        GridFieldConfigForProducts::create()
                    ),
                ]
            );
        }
    }

    /**
     * only returns the products that are for sale
     * if only those need to be showing.
     *
     * @return \SilverStripe\ORM\DataList
     */
    public function EcommerceAlsoBoughtProductsForSale()
    {
        $owner = $this->getOwner();
        $list = $owner->EcommerceAlsoBoughtProducts();

        return $this->addAllowPurchaseFilter($list);
    }

    /**
     * only returns the products that are for sale
     * if only those need to be showing.
     *
     * @return \SilverStripe\ORM\DataList
     */
    public function BoughtForForSale()
    {
        $list = $owner->BoughtFor();

        return $this->addAllowPurchaseFilter($list);
    }

    protected function addAllowPurchaseFilter(DataList $list)
    {
        $owner = $this->getOwner();
        if (EcommerceConfig::inst()->OnlyShowProductsThatCanBePurchased) {
            $list = $list->filter(['AllowPurchase' => 1]);
        }

        return $list;
    }
}
