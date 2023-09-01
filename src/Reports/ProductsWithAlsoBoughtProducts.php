<?php

namespace Sunnysideup\Ecommerce\Reports;

use SilverStripe\Reports\Report;
use Sunnysideup\Ecommerce\Pages\Product;

/**
 * Selects all products without an image.
 *
 * @author: Nicolaas [at] Sunny Side Up .co.nz
 * @package: ecommerce
 * @sub-package: reports
 */
class ProductsWithBoughtProducts extends Report
{
    use EcommerceProductReportTrait;

    protected $dataClass = Product::class;

    /**
     * @return int - for sorting reports
     */
    public function sort()
    {
        return 7001;
    }

    /**
     * @return string
     */
    public function title()
    {
        return 'E-commerce: Products: products with bought products';
    }

    /**
     * @param mixed $list
     */
    protected function updateEcommerceList($list)
    {
        return $list
            ->where('Product_EcommerceAlsoBoughtProducts.ID IS NOT NULL')
            ->sort('Title', 'ASC')
            ->leftJoin(
                'Product_EcommerceAlsoBoughtProducts',
                '"Product"."ID" = Product_EcommerceAlsoBoughtProducts.ProductID'
            )
        ;
    }
}
