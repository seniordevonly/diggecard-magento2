<?php
/**
 * @author DiggEcard Team
 * @copyright Copyright (c) 2019 DiggEcard (https://diggecard.com)
 */

namespace Diggecard\Giftcard\Observer\Api\AfterOrderSave\Post\Order;

use Diggecard\Giftcard\Api\Data\GiftcardInterface;
use Diggecard\Giftcard\Helper\Hash;
use Diggecard\Giftcard\Helper\Log;
use Magento\Framework\Event\ObserverInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Diggecard\Giftcard\Model\Product\Type\Giftcard as GiftcardType;
use Diggecard\Giftcard\Api\OrderApiRepositoryInterface;
use Magento\Framework\Event\Observer;
use Diggecard\Giftcard\Api\GiftcardRepositoryInterface;
use Diggecard\Giftcard\Model\GiftcardFactory;
use Diggecard\Giftcard\Helper\Data as Json;
use Magento\Customer\Model\Session;

/**
 * Class Complete
 *
 * @package Diggecard\Giftcard\Observer\Api
 */
class Save implements ObserverInterface
{
    const ALLOWED_PAYMENT = [
        'klarna_kp',
    ];

    const ALLOWED_ORDER_STATE = [
        'new',
        'processing',
    ];

    /**
     * @var OrderApiRepositoryInterface
     */
    protected $orderApiRepository;

    /**
     * @var GiftcardRepositoryInterface
     */
    protected $giftcardRepository;

    /**
     * @var GiftcardFactory
     */
    protected $giftcardFactory;

    /**
     * @var Json
     */
    protected $json;
    /**
     * @var Hash
     */
    protected $hash;
    /**
     * @var Log
     */
    private $logger;
    /**
     * @var Session
     */
    private $customerSession;

    /**
     * Complete constructor.
     * @param OrderApiRepositoryInterface $orderApiRepository
     * @param GiftcardRepositoryInterface $giftcardRepository
     * @param GiftcardFactory $giftcardFactory
     * @param Log $logger
     * @param Json $json
     * @param Hash $hash
     * @param Session $customerSession
     */
    public function __construct(
        OrderApiRepositoryInterface $orderApiRepository,
        GiftcardRepositoryInterface $giftcardRepository,
        GiftcardFactory $giftcardFactory,
        Log $logger,
        Json $json,
        Hash $hash,
        Session $customerSession
    )
    {
        $this->orderApiRepository = $orderApiRepository;
        $this->giftcardRepository = $giftcardRepository;
        $this->giftcardFactory = $giftcardFactory;
        $this->json = $json;
        $this->logger = $logger;
        $this->hash = $hash;
        $this->customerSession = $customerSession;
    }

    /**
     * @param Observer $observer
     * @return OrderInterface
     * @throws LocalizedException
     */
    public function execute(Observer $observer)
    {
        /** @var OrderInterface */
        $order = $observer->getEvent()->getOrder();
        $orderPayment = $order->getPayment()->getMethodInstance()->getCode();
        if (!in_array($orderPayment, self::ALLOWED_PAYMENT)) {
            return $order;
        }
        $orderState = $order->getState();
        $errors = [];
        $this->logger->saveLog(__('complete_observer'));
        if ($this->customerSession->getDelegatedNewCustomerData()) {
            $this->logger->saveLog(__('Customer creation'));

            return $order;
        }

        $itemCollection = $order->getAllItems();
        foreach ($itemCollection as $item) {
            /** @var ProductInterface $item */
            $itemType = $item->getProductType();
            if ($itemType == GiftcardType::TYPE_CODE && in_array($orderState, self::ALLOWED_ORDER_STATE)) {
                $orderHash = $item->getProductOptions()["dg_giftcard_hash"];
                $billingAdress = $order->getBillingAddress();
                $data = [
                    "orderHash" => $orderHash,
                    "firstName" => $billingAdress->getFirstname(),
                    "lastName" => $billingAdress->getLastname(),
                    "email" => $billingAdress->getEmail(),
                    "externalOrderId" => $this->hash->generateHash($order)
                        . '_' . $order->getStoreId()
                        . '_' . $order->getIncrementId()
                ];
                $this->logger->saveLog(__('Request DATA:'));
                $this->logger->saveLog($data);
                $response = $this->orderApiRepository->postCompleteOrder($data);
                $this->logger->saveLog(__('Response DATA:'));
                $this->logger->saveLog($response);
                if (isset($response['errorMessage']) || isset($response['validationErrors'])) {
                    $errors[] = $item->getPrice();
                    continue;
                }
                if (array_key_exists('orderHash', $response) && array_key_exists('giftCards', $response)) {
                    /** @var GiftcardInterface $giftcard */
                    foreach ($response['giftCards'] as $giftcardData) {
                        $this->logger->saveLog('before_add_giftcard');
                        $this->addGiftcard($giftcardData);
                        $this->logger->saveLog('giftcard_added');
                    }
                }
            }
        }

        if (!empty($errors)) {
            $message = implode(', ', $errors);
            throw new LocalizedException(
                __(
                    "Cannot create giftcard(s) with value(s): %1",
                    $message
                )
            );
        }

        return $order;
    }

    /**
     * @param $giftcardData
     */
    private function addGiftcard($giftcardData)
    {
        $giftcard = $this->giftcardFactory->create();
        $keys = ['qrCode', 'valueRemains', 'createdTime', 'validUntilTime'];
        $cardData = [];
        foreach ($giftcardData as $key => $value) {
            if (in_array($key, $keys)) {
                switch ($key) {
                    case 'qrCode':
                        $giftcard->setQrCode($giftcardData['qrCode']);
                        break;
                    case 'valueRemains':
                        $giftcard->setValueRemains($giftcardData['valueRemains']);
                        $giftcard->setBaseValueRemains($giftcardData['valueRemains']);
                        break;
                    case 'createdTime':
                        $date = date("Y-m-d H:i:s", $giftcardData['createdTime']);
                        $giftcard->setCreatedAt($date);
                        $giftcard->setUpdatedAt($date);
                        break;
                    case 'validUntilTime':
                        $date = date("Y-m-d H:i:s", $giftcardData['validUntilTime']);
                        $giftcard->setValidUntil($date);
                        break;
                    default:
                        $cardData[$key] = $value;
                }
            } else {
                $cardData[$key] = $value;
            }
        }

        $jsonCardData = $this->json->serialize($cardData);
        $giftcard->setCardData($jsonCardData);
        try {
            $this->giftcardRepository->save($giftcard);
        } catch (CouldNotSaveException $e) {
            $this->logger->saveLog(__($e->getMessage()), Log::TYPE_EXCEPTION);
        } catch (LocalizedException $e) {
            $this->logger->saveLog(__($e->getMessage()), Log::TYPE_EXCEPTION);
        }
    }
}