<?php

namespace Sunnysideup\EcommerceAlsoBought\Tasks;

use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\ArrayInput;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Command\Command;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use Sunnysideup\Ecommerce\Interfaces\BuyableModel;
use Sunnysideup\Ecommerce\Model\OrderItem;
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
    protected ?PolyOutput $output = null;

    protected $verbose = true;

    protected string $title = 'Find also bought products';

    private static $description = 'Uses the order history to find products that are often bought together.';

    protected static $commandName = 'findalsobought';

    private static $minimum_strength = 3;


    /**
     * this is the decay rate for the popularity. The higher the number, the faster the decay.
     * 0.003 is a good number for a product that is popular for 3 months.
     * 0.001 is a good number for a product that is popular for 1 year.
     * 0.0001 is a good number for a product that is popular for 10 years.
     * @var float
     */
    private static float $decay_rate = 0.0005;

    public function run($request)
    {
        $this->runOnDemand();
    }

    /**
     * run in verbose mode.
     */
    public function runOnDemand()
    {
        $this->verbose = true;

        $definition = new InputDefinition($this->getOptions());
        $input = new ArrayInput([], $definition);
        $this->output = PolyOutput::create(PolyOutput::FORMAT_ANSI);
        $this->execute($input, $this->output);
    }

    /**
     * runs the task without output.
     */
    public function runSilently()
    {
        $this->verbose = false;
        $definition = new InputDefinition($this->getOptions());
        $input = new ArrayInput([], $definition);
        $this->output = PolyOutput::create(PolyOutput::FORMAT_ANSI);

        $this->execute($input, $this->output);
    }


    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $this->output = $output;
        $products = Product::get()
            ->innerJoin('OrderItem', 'OrderItem.BuyableID = Product.ID')
            ->columnUnique('ID');
        if ($this->verbose) {
            $this->output->writeln('Sold Products Found ' . count($products));
        }

        DB::query('DELETE FROM Product_EcommerceAlsoBoughtProducts WHERE AutomaticallyAdded = 1 ');
        $minStrength = $this->Config()->get('minimum_strength');
        foreach ($products as $productID) {
            $product = $this->getCachedProducts($productID);
            $links = $this->findAlsoBought($product);
            $rel = $product->EcommerceAlsoBoughtProducts();
            foreach ($links as $otherProductID => $extraFields) {
                if ($extraFields['Strength'] > $minStrength) {
                    $rel->add(
                        $otherProductID,
                        $extraFields
                    );
                }
            }
        }

        if ($this->verbose) {
            $this->output->writeln('Finished');
        }

        return Command::SUCCESS;
    }

    protected function findAlsoBought(BuyableModel $product): array
    {
        $lambda = Config::inst()->get(FindAlsoBought::class, 'decay_rate') * -1;
        $links = [];
        $orderIds = $this->getOrderIds($product);
        if ($orderIds !== []) {
            foreach ($orderIds as $orderId) {
                $orderItems = OrderItem::get()->filter(['OrderID' => $orderId]);
                $myOrderItem = $orderItems->filter(['BuyableID' => $product->ID])->first();
                if (! $myOrderItem || ! $myOrderItem->exists()) {
                    user_error('FindAlsoBought::findAlsoBought: No order item found for product ID ' . $product->ID, E_USER_WARNING);
                }

                $orderItems = $orderItems->exclude(['BuyableID' => $product->ID]);
                $orderTs = strtotime($myOrderItem->Created);
                $countBefore = 0;
                $countAfter = 0;
                foreach ($orderItems as $orderItem) {
                    $otherProduct = $this->getCachedProducts($orderItem->BuyableID);
                    if ($otherProduct && $otherProduct->exists()) {
                        // added after main item ...
                        // or price is less than the price of the main item (by 1.5 times)
                        if (! isset($links[$otherProduct->ID])) {
                            $links[$otherProduct->ID] = [
                                'Strength' => 0,
                                'AutomaticallyAdded' => 1,
                                'PercentageAddedAfterwards' => 0,
                                'Count' => 0,
                            ];
                        }

                        if ($orderItem->ID > $myOrderItem->ID) {
                            $countAfter++;
                        } else {
                            $countBefore++;
                        }

                        $daysAgo = (time() - $orderTs) / 86400;
                        $links[$otherProduct->ID]['Strength'] += exp($lambda * $daysAgo);
                        $links[$otherProduct->ID]['PercentageAddedAfterwards'] += $countAfter / ($countBefore + $countAfter);
                        $links[$otherProduct->ID]['Count']++;
                    } else {
                        $this->output->writeln('Could not find ' . $orderItem->BuyableID);
                    }
                }
            }
        }

        if ($this->verbose) {
            $vv = [];
            $vv[] = $product->Title . ' (' . $product->InternalItemID . ')';
        }

        foreach (array_keys($links) as $id) {
            $links[$id]['PercentageAddedAfterwards'] = round(
                $links[$id]['PercentageAddedAfterwards'] / $links[$id]['Count'],
                4
            );
            $links[$id]['Strength'] = round($links[$id]['Strength'], 4);
            unset($links[$id]['Count']);
            if ($this->verbose) {
                $otherProduct =  $this->getCachedProducts($id);
                $vv[] = '... ' . $otherProduct->Title . ' (' . $otherProduct->InternalItemID . ') ' .
                    '... Strenth: ' . $links[$id]['Strength'] .
                    '... % Added After: ' . $links[$id]['PercentageAddedAfterwards'];
            }
        }

        if ($this->verbose) {
            foreach ($vv as $s) {
                $this->output->writeln($s);
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
