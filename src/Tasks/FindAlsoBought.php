<?php

namespace Sunnysideup\EcommerceAlsoBought\Tasks;

use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Member;
use Sunnysideup\Ecommerce\Config\EcommerceConfig;
use Sunnysideup\Ecommerce\Interfaces\BuyableModel;
use Sunnysideup\Ecommerce\Model\Address\BillingAddress;
use Sunnysideup\Ecommerce\Model\Address\ShippingAddress;
use Sunnysideup\Ecommerce\Model\Order;
use Sunnysideup\Ecommerce\Model\OrderAttribute;
use Sunnysideup\Ecommerce\Model\OrderItem;
use Sunnysideup\Ecommerce\Model\Process\OrderEmailRecord;
use Sunnysideup\Ecommerce\Model\Process\OrderStatusLog;
use Sunnysideup\Ecommerce\Model\Process\OrderStep;
use Sunnysideup\Ecommerce\Pages\Product;

/**
 * @description: cleans up old (abandonned) carts...
 *
 * @author: Nicolaas [at] Sunny Side Up .co.nz
 * @package: ecommerce
 * @sub-package: tasks
 */
class FindAlsoBought extends BuildTask
{
    protected $verbose = false;
    protected $verboseMainDetailsOnly = true;
    protected $dryRun = true;

    protected $title = 'Find also bought products';

    protected $description = 'Uses the order history to find products that are often bought together.';

    private static $segment = 'findalsobought';


    /**
     * this is the decay rate for the popularity. The higher the number, the faster the decay.
     * 0.003 is a good number for a product that is popular for 3 months.
     * 0.001 is a good number for a product that is popular for 1 year.
     * 0.0001 is a good number for a product that is popular for 10 years.
     * @var float
     */
    private static float $decay_rate = 0.0005;

    /**
     * run in verbose mode.
     */
    public static function run_on_demand()
    {
        $obj = new self();
        $obj->verbose = true;
        $obj->run(null);
    }

    /**
     * runs the task without output.
     */
    public function runSilently()
    {
        $this->verbose = false;

        $this->run(null);
    }

    public function run($request)
    {
        if ($this->verbose) {
            $this->verboseMainDetailsOnly = true;
        }
        $products = Product::get()
            ->innerJoin('OrderItem', 'OrderItem.BuyableID = Product.ID')
            ->columnUnique('ID');
        if ($this->verbose) {
            DB::alteration_message('Sold Products Found ' . count($products), 'created');
        }
        $report = [];
        if (! $this->dryRun) {
            DB::query('DELETE FROM Product_EcommerceAlsoBoughtProducts WHERE AutomaticallyAdded = 1 ');
        }
        foreach ($products as $productID) {
            $product = $this->getCachedProducts($productID);
            $links = $this->findAlsoBought($product);
            foreach ($links as $name => $productArray) {
                if ($this->verbose) {
                    DB::alteration_message('DOING ' . $product->Title, 'created');
                }
                foreach ($productArray as $productId => $strength) {
                    if (! $this->dryRun) {
                        $product->$name()->add(
                            $productId,
                            [
                                'AutomaticallyAdded' => 1,
                                'Strength' => $strength,
                            ]
                        );
                    }
                    $otherProductTitle = DB::query('SELECT Title FROM SiteTree WHERE ID = ' . $productId)->value();
                    $otherProductInternalItemID = DB::query('SELECT InternalItemID FROM Product WHERE ID = ' . $productId)->value();
                    if ($name === 'EcommerceAlsoBoughtProducts') {
                        $mainProduct = $product->Title . ' (' . $product->InternalItemID . ')';
                        $accessory = $otherProductTitle . ' (' . $otherProductInternalItemID . ')';
                    } else {
                        $mainProduct = $otherProductTitle . ' (' . $otherProductInternalItemID . ')';
                        $accessory = $product->Title . ' ' . $product->InternalItemID . ')';
                    }
                    DB::alteration_message('... Adding ' . $accessory . ') as accessory to ' . $mainProduct, 'created');
                    if (! isset($report[$mainProduct])) {
                        $report[$mainProduct] = [];
                    }
                    $report[$mainProduct][] = $accessory;
                }
            }
        }
        if ($this->verboseMainDetailsOnly) {
            foreach ($report as $mainProduct => $accessories) {
                DB::alteration_message($mainProduct);
                $accessories = array_unique($accessories);
                foreach ($accessories as $accessory) {
                    DB::alteration_message('...' . $accessory);
                }
            }
        }
    }

    protected function findAlsoBought($product): array
    {
        $lambda = Config::inst()->get(FindAlsoBought::class, 'decay_rate') * -1;
        $links = [];
        $orderIds = $this->getOrderIds($product);
        if (!empty($orderIds)) {
            foreach ($orderIds as $orderId) {
                $orderItems = OrderItem::get()->filter(['OrderID' => $orderId]);
                $myOrderItem = $orderItems->filter(['BuyableID' => $product->ID])->first();
                if (! $myOrderItem || ! $myOrderItem->exists()) {
                    user_error('FindAlsoBought::findAlsoBought: No order item found for product ID ' . $product->ID, E_USER_WARNING);
                }
                $orderItems = $orderItems->exclude('BuyableID', $product->ID);
                $orderTs = strtotime($myOrderItem->Created);
                foreach ($orderItems as $orderItem) {
                    $otherProduct = $this->getCachedProducts($orderItem->BuyableID);
                    if ($otherProduct && $otherProduct->exists()) {
                        // added after main item ...
                        // or price is less than the price of the main item (by 1.5 times)
                        if ($orderItem->ID > $myOrderItem->ID || ($otherProduct->Price * 1.5)  < ($product->Price)) {
                            $name = 'EcommerceAlsoBoughtProducts';
                        } else {
                            $name = 'BoughtFor';
                        }
                        if (! isset($links[$name])) {
                            $links[$name] = [];
                        }
                        if (! isset($links[$name][$otherProduct->ID])) {
                            $links[$name][$otherProduct->ID] = 0;
                        }
                        $daysAgo = (time() - $orderTs) / 86400;
                        $links[$name][$otherProduct->ID] += exp($lambda * $daysAgo);
                    }
                }
            }
        }
        return $links;
    }

    /**
     * returns an array of order IDs for the given product.
     */
    protected function getOrderIds(BuyableModel $product): array
    {
        return $product->SalesOrderItems()->columnUnique('OrderID');
    }

    protected $cachedProducts = [];

    protected function getCachedProducts(int $id)
    {
        if (empty($this->cachedProducts[$id])) {
            $this->cachedProducts[$id] = Product::get()->byID($id);
        }
        return $this->cachedProducts[$id];
    }
}
