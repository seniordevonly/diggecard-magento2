<?php
/**
 * @author DiggEcard Team
 * @copyright Copyright (c) 2019 DiggEcard (https://diggecard.com)
 */

namespace Diggecard\Giftcard\Model\Giftcard;

use Diggecard\Giftcard\Api\GiftcardRepositoryInterface;
use Diggecard\Giftcard\Api\Data\GiftcardInterface;
use Diggecard\Giftcard\Helper\Log;
use Diggecard\Giftcard\Model\GiftcardFactory;
use Exception;
use Magento\Framework\Exception\NoSuchEntityException;
use Diggecard\Giftcard\Api\GiftcardApiRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Message\ManagerInterface;

/**
 * Class Manager
 *
 * @package Diggecard\Giftcard\Model\Giftcard
 */
class Manager
{
    /**
     * @var GiftcardFactory
     */
    protected $giftcardFactory;

    /**
     * @var GiftcardRepositoryInterface
     */
    public $giftcardRepository;

    /**
     * @var Log
     */
    protected $logger;

    /**
     * @var GiftcardApiRepositoryInterface
     */
    public $giftcardApiRepository;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var ManagerInterface
     */
    protected $_messageManager;

    /**
     * Manager constructor.
     *
     * @param ManagerInterface $messageManager
     * @param GiftcardFactory $giftcardFactory
     * @param GiftcardRepositoryInterface $giftcardRepository
     * @param GiftcardApiRepositoryInterface $giftcardApiRepository
     * @param StoreManagerInterface $storeManager
     * @param Log $logger
     */
    public function __construct(
        ManagerInterface $messageManager,
        GiftcardFactory $giftcardFactory,
        GiftcardRepositoryInterface $giftcardRepository,
        GiftcardApiRepositoryInterface $giftcardApiRepository,
        StoreManagerInterface $storeManager,
        Log $logger
    )
    {
        $this->_messageManager = $messageManager;
        $this->giftcardFactory = $giftcardFactory;
        $this->giftcardRepository = $giftcardRepository;
        $this->logger = $logger;
        $this->giftcardApiRepository = $giftcardApiRepository;
        $this->_storeManager = $storeManager;
    }

    /**
     * @param $qrCode
     * @param bool $api
     * @return bool|GiftcardInterface
     */
    public function validateGiftcard($qrCode, $api = false)
    {
        try {
            /** @var GiftcardInterface $remoteGiftcard */
            $remoteGiftcard = $this->giftcardApiRepository->getGiftCardByQrCode($qrCode);
            $this->logger->saveLog(__('Validate GiftCard:'));
            $this->logger->saveLog($remoteGiftcard);
            $currentCurrencyCode = $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
            $baseCurrencyCode = $this->_storeManager->getStore()->getBaseCurrency()->getCode();
            if (is_array($remoteGiftcard) && array_key_exists('id', $remoteGiftcard) && !empty($remoteGiftcard['id'])) {
                try {
                    $localGiftcard = $this->giftcardRepository->getByQrCode($qrCode);
                    if (($baseCurrencyCode == $currentCurrencyCode) && ($remoteGiftcard['currencyCode'] == $currentCurrencyCode)) {
                        if ($localGiftcard->getEntityId()) {
                            $localGiftcard->setValueRemains($remoteGiftcard['valueRemains']);
                            return $this->giftcardRepository->save($localGiftcard);
                        }
                    } else {
                        $message = __('Cannot convert currency!');
                        $this->_messageManager->addErrorMessage($message);
                    }
                } catch (NoSuchEntityException $exception) {
                    $this->logger->saveLog(__('Creating new GC'));
                    $newGiftCard = $this->giftcardFactory->create();
                    $newGiftCard->setQrCode($remoteGiftcard['qrCode']);
                    $newGiftCard->setValueRemains($remoteGiftcard['valueRemains']);
                    $date = date("Y-m-d H:i:s", $remoteGiftcard['createdTime']);
                    $newGiftCard->setCreatedAt($date);
                    return $this->giftcardRepository->save($newGiftCard);
                };
            }
        } catch (Exception $e) {
            $this->logger->saveLog(
                __FILE__ . __(' There is no card with QrCode:') . ' "' . $qrCode . '" ' . $e->getMessage()
                , Log::TYPE_EXCEPTION);
            return false;
        }
        return false;
    }

}