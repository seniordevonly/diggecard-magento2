<?php
/**
 * @author DiggEcard Team
 * @copyright Copyright (c) 2019 DiggEcard (https://diggecard.com)
 */

namespace Diggecard\Giftcard\Service;

use Diggecard\Giftcard\Model\Product\Type\Giftcard as GiftcardType;
use Magento\Eav\Setup\EavSetup;
use Magento\Framework\App\State;
use Magento\Catalog\Model\Product as ProductModel;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductAttributeStatus;
use Magento\Catalog\Model\Product\Visibility as ProductVisibility;
use Magento\Framework\App\Area;
use Exception;
use Magento\Framework\Exception\StateException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Catalog\Model\Product;

/**
 * Class GiftcardSampleData
 *
 * @package Diggecard\Giftcard\Service
 */
class GiftcardSampleData
{
    /** @var ProductFactory */
    protected $productFactory;

    /** @var State */
    protected $state;

    /** @var ProductModel */
    protected $productModel;

    /** @var ProductRepositoryInterface */
    protected $productRepository;

    /** @var ProductRepositoryInterface */
    protected $storeManager;

    /** @var string */
    protected $giftcardType = GiftcardType::TYPE_CODE;

    /**
     * @var ImportImageService
     */
    protected $imageImporter;

    /** @var EavSetupFactory */
    protected $eavSetupFactory;

    /**
     * Giftcard SampleData constructor.
     * @param ProductFactory $productFactory
     * @param State $state
     * @param ProductModel $productModel
     * @param ProductRepositoryInterface $productRepository
     * @param StoreManagerInterface $storeManager
     * @param ImportImageService $imageImporter
     */
    public function __construct(
        ProductFactory $productFactory,
        State $state,
        ProductModel $productModel,
        ProductRepositoryInterface $productRepository,
        StoreManagerInterface $storeManager,
        ImportImageService $imageImporter,
        EavSetupFactory $eavSetupFactory
    )
    {
        $this->productFactory = $productFactory;
        $this->state = $state;
        $this->productModel = $productModel;
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
        $this->imageImporter = $imageImporter;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * @return array|bool
     */
    public function addGiftcardProduct()
    {
        try {
            $this->state->setAreaCode(Area::AREA_ADMINHTML);
            $this->createSimpleGiftcard(0, 10);
        } catch (Exception $exception) {
            return false;
        }
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function addAssociateAttributes(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

        $fieldList = [
            'tax_class_id',
        ];

        foreach ($fieldList as $field) {
            $applyTo = explode(
                ',',
                $eavSetup->getAttribute(Product::ENTITY, $field, 'apply_to')
            );
            if (!in_array($this->giftcardType, $applyTo)) {
                $applyTo[] = $this->giftcardType;
                $eavSetup->updateAttribute(
                    Product::ENTITY,
                    $field,
                    'apply_to',
                    implode(',', $applyTo)
                );
            }
        }
    }

    /**
     * @param int $price
     * @param int $qty
     * @param int $weight
     * @param null $imagePath
     * @return mixed
     * @throws Exception
     */
    public function createSimpleGiftcard($price = 0, $qty = 0, $weight = 0, $imagePath = null)
    {
        $attributeSetId = $this->productModel->getDefaultAttributeSetId();
        $type = $weight == 0 ? 'Virtual' : 'Physical';
        $skuTypeCode = strtolower($type[0]);
        $simpleProduct = $this->productFactory->create();
        $simpleProduct->setStoreId(Store::DEFAULT_STORE_ID);
        $simpleProduct->setWebsiteIds([$this->storeManager->getDefaultStoreView()->getWebsiteId()]);
        $simpleProduct->setTypeId($this->giftcardType);
        $sku = "dg-general-giftcard" . ($price == 0 ? '' : '-' . $price);
        $simpleProduct->addData(array(
            'sku' => $sku,
            'name' => "Diggecard Giftcard {$type}",
            'price' => $price,
            'attribute_set_id' => $attributeSetId,
            'status' => ProductAttributeStatus::STATUS_ENABLED,
            'visibility' => ProductVisibility::VISIBILITY_BOTH,
            'weight' => $weight,
            'product_has_weight' => $weight == 0 ? 0 : 1,
            'tax_class_id' => 0,
            'description' => __('Diggecard Giftcard Sample Product'),
            'short_description' => __('Diggecard Giftcard Sample Product'),
            'stock_data' => array(
                'use_config_manage_stock' => 0,
                'manage_stock' => 1,
                'is_in_stock' => $qty != 0 ? 1 : 0,
                'qty' => $qty
            )
        ));

        $product = $this->productRepository->save($simpleProduct);
        if ($imagePath) {
            $imageTypes = array('image', 'small_image', 'thumbnail');
            $this->imageImporter->execute($product, $imagePath, true, $imageTypes);
        }

        return $this->productRepository->save($simpleProduct);
    }
}
