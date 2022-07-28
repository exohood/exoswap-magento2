<?php

namespace Exoswap\Payment\Model;

use Exoswap\Payment\Gateway\Exoswap\Exoswap;
use Magento\Directory\Model\CountryFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Response\Http;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;

class Payment extends AbstractMethod
{
    const CODE = 'Exoswap_payment';

    protected $_code = 'Exoswap_payment';

    protected $_isInitializeNeeded = true;

    protected $urlBuilder;
    protected $storeManager;
    protected $orderManagement;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param Data $paymentData
     * @param ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     * @param UrlInterface $urlBuilder
     * @param StoreManagerInterface $storeManager
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     * @internal param ModuleListInterface $moduleList
     * @internal param TimezoneInterface $localeDate
     * @internal param CountryFactory $countryFactory
     * @internal param Http $response
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        UrlInterface $urlBuilder,
        StoreManagerInterface $storeManager,
        OrderManagementInterface $orderManagement,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );

        $this->urlBuilder = $urlBuilder;
        $this->storeManager = $storeManager;
        $this->orderManagement = $orderManagement;

        Exoswap::config(
            [
                'public_token' => $this->getConfigData('token_public'),
                'secret_token' => $this->getConfigData('token_private'),
                'engine' => 'magento'
            ]
        );
    }

		/**
		 * @param Order $order
		 * @return array
		 * @throws LocalizedException
		 * @throws \Exception
		 */
    public function getExoswapRequest(Order $order)
    {
        $token = substr(md5(rand()), 0, 32);

        $payment = $order->getPayment();
        $payment->setAdditionalInformation('Exoswap_order_token', $token);
        $payment->save();

        $items = [];
        foreach ($order->getAllItems() as $item) {
            $items[] = [
                'count' => intval($item->getQtyOrdered()),
                'name' => $item->getName(),
                'subtotal' => floatval($item->getRowTotalInclTax()),
                'currency' => $order->getOrderCurrencyCode(),
            ];
        }

        $shipping = [];

        if (floatval($order->getShippingAmount()) > 0) {
            $shipping = [
                "amount" => $order->getShippingAmount(),
                "description" => $order->getShippingDescription(),
            ];
        }

        $discount = [];

        if (floatval($order->getDiscountAmount())) {
            $discount = [
                "amount" => $order->getDiscountAmount(),
                "description" => $order->getDiscountDescription(),
            ];
        }

        $created_at = new \DateTime($order->getCreatedAt());

        $data = [

            'amount' => floatval($order->getGrandTotal()),
            'currency' => $order->getOrderCurrencyCode(),

            'shop_order_id' => $order->getIncrementId(),
            'shop_created_at' => $created_at->format('Y-m-d\TH:i:s'),
            'shop_domain' => $this->urlBuilder->getBaseUrl(),

            'customer_email' => $order->getCustomerEmail(),

            'callback_token' => $token,
            'callback_url' => ($this->urlBuilder->getUrl('Exoswap/payment/callback')),

            'properties' => [
                'shop_name' => $this->storeManager->getWebsite()->getName(),
                'shop_url' => $this->urlBuilder->getBaseUrl(),
                'items' => $items,
                'shipping' => $shipping,
                'discount' => $discount,
                'return_url' => $this->urlBuilder->getBaseUrl(),
                'cancel_url' => $this->urlBuilder->getUrl('Exoswap/payment/cancelOrder'),
            ]
        ];

        return $data;
    }
}
