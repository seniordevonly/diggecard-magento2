<?php

namespace Diggecard\Giftcard\Helper;

use Diggecard\Giftcard\Controller\Giftcard\Add;
use Diggecard\Giftcard\Model\ResourceModel\Giftcard;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Model\Stock\StockItemRepository;
use Magento\Config\Model\ResourceModel\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductSourceStatus;

class Status
{
    /** @var ResourceConnection */
    private $resourseConnection;

    /** @var ProductRepositoryInterface */
    protected $productRepository;

    /** @var ProductInterface */
    protected $product;

    /** @var StockItemRepository */
    protected $stockRepository;

    public function __construct(
        ResourceConnection $resourceConnection,
        ProductRepositoryInterface $productRepository,
        ProductInterface $product,
        StockItemRepository $stockRepository
    )
    {
        $this->resourseConnection = $resourceConnection;
        $this->productRepository = $productRepository;
        $this->product = $product;
        $this->stockRepository = $stockRepository;
    }

    public function isTableExists()
    {
        return $this->resourseConnection->getConnection()->isTableExists(Giftcard::TABLE_NAME);
    }

    public function isProductExists()
    {
        if ($this->getProduct())
            return true;

        return false;
    }

    public function isProductSalable()
    {
        $productStatus = $this->getProduct()->getStatus();
        $stockStatus = $this->stockRepository->get($this->getProduct()->getId())->getIsInStock();

        if ($productStatus == ProductSourceStatus::STATUS_ENABLED && $stockStatus)
            return true;

        return false;
    }

    private function getProduct()
    {
        return $this->productRepository->get(Add::DG_SKU, false, 0);
    }
}