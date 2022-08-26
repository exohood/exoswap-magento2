<?php

namespace Exoswap\Payment\Controller\Payment;

use Exoswap\Payment\Model\Payment as ExoswapPayment;
use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Sales\Model\Order;

class Callback extends Action
{
    /**
     * @var Order
     */
    protected $order;
    /**
     * @var mixed|null
     */
    protected $_shop_order_id;
    protected $_callback_token;
    protected $_message;
    protected $_paid;

    /**
     * @param Context $context
     * @param Order $order
     * @param Magento\Payment\Model\Method\AbstractMethod|ExoswapPayment $ExoswapPayment
     * @throws NotFoundException
     * @internal param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        Order $order,
        ExoswapPayment $ExoswapPayment
    ) {
        parent::__construct($context);

        $this->_shop_order_id = !empty($_REQUEST) && !empty($_REQUEST['shop_order_id']) ? $_REQUEST['shop_order_id'] : null;
        $this->_callback_token = !empty($_REQUEST) && !empty($_REQUEST['callback_token']) ? $_REQUEST['callback_token'] : null;
        $this->_message = !empty($_REQUEST) && !empty($_REQUEST['message']) ? $_REQUEST['message'] : null;
        $this->_paid = !empty($_REQUEST) && !empty($_REQUEST['paid']) ? ($_REQUEST['paid'] === true || $_REQUEST['paid'] === 'true') : false;

        $this->order = $order;
        $this->ExoswapPayment = $ExoswapPayment;
        $this->execute();
    }

    /**
     * @return ResponseInterface|ResultInterface|void
     */
    public function execute()
    {
        $return = json_encode(
            [
                'success' => true
            ]
        );

        try {
            if (!$this->_shop_order_id || !$this->_callback_token) {
                throw new Exception('404', 0);
            }

            $order = $this->order->loadByIncrementId($this->_shop_order_id);

            if (!$order->getId()) {
                throw new Exception('Order #' . $this->_shop_order_id . ' does not exists', 1);
            }

            $payment = $order->getPayment();
            $token = $payment->getAdditionalInformation('Exoswap_order_token');

            if (!$token || $this->_callback_token !== $token) {
                throw new Exception('Callback token does not match', 1);
            }

            if (!$this->_message) {
                throw new Exception('There are no message or status', 2);
            }

            if ($this->_paid) {
                $order->setState(Order::STATE_PROCESSING);
                $order->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING));
            }
            $order->addCommentToStatusHistory('[Exoswap] : ' . $this->_message);
            $order->save();
        } catch (Exception $e) {
            $return = json_encode(
                [
                    'success' => false,
                    'error' => [
                        'code' => $e->getCode(),
                        'message' => $e->getMessage()
                    ]
                ]
            );
        }

        echo $return;

        exit;
    }
}
