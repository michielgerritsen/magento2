<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Sales\Model\Order\Email\Sender;

use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Container\ShipmentIdentity;
use Magento\Sales\Model\Order\Email\Container\Template;
use Magento\Sales\Model\Order\Email\NotifySender;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Resource\Order\Shipment as ShipmentResource;
use Magento\Sales\Model\Order\Address\Renderer;
use Magento\Framework\Event\ManagerInterface;

/**
 * Class ShipmentSender
 */
class ShipmentSender extends NotifySender
{
    /**
     * @var PaymentHelper
     */
    protected $paymentHelper;

    /**
     * @var ShipmentResource
     */
    protected $shipmentResource;

    /**
     * @var Renderer
     */
    protected $addressRenderer;

    /**
     * Application Event Dispatcher
     *
     * @var ManagerInterface
     */
    protected $eventManager;

    /**
     * @param Template $templateContainer
     * @param ShipmentIdentity $identityContainer
     * @param Order\Email\SenderBuilderFactory $senderBuilderFactory
     * @param PaymentHelper $paymentHelper
     * @param ShipmentResource $shipmentResource
     * @param Renderer $addressRenderer
     * @param ManagerInterface $eventManager
     */
    public function __construct(
        Template $templateContainer,
        ShipmentIdentity $identityContainer,
        \Magento\Sales\Model\Order\Email\SenderBuilderFactory $senderBuilderFactory,
        PaymentHelper $paymentHelper,
        ShipmentResource $shipmentResource,
        Renderer $addressRenderer,
        ManagerInterface $eventManager
    ) {
        parent::__construct($templateContainer, $identityContainer, $senderBuilderFactory);
        $this->paymentHelper = $paymentHelper;
        $this->shipmentResource = $shipmentResource;
        $this->addressRenderer = $addressRenderer;
        $this->eventManager = $eventManager;
    }

    /**
     * Send email to customer
     *
     * @param Shipment $shipment
     * @param bool $notify
     * @param string $comment
     * @return bool
     */
    public function send(Shipment $shipment, $notify = true, $comment = '')
    {
        $order = $shipment->getOrder();
        if ($order->getShippingAddress()) {
            $formattedShippingAddress = $this->addressRenderer->format($order->getShippingAddress(), 'html');
        } else {
            $formattedShippingAddress = '';
        }
        $formattedBillingAddress = $this->addressRenderer->format($order->getBillingAddress(), 'html');

        $transport = new \Magento\Framework\Object(
            ['template_vars' =>
                 [
                     'order'                    => $order,
                     'shipment'                 => $shipment,
                     'comment'                  => $comment,
                     'billing'                  => $order->getBillingAddress(),
                     'payment_html'             => $this->getPaymentHtml($order),
                     'store'                    => $order->getStore(),
                     'formattedShippingAddress' => $formattedShippingAddress,
                     'formattedBillingAddress'  => $formattedBillingAddress,
                 ]
            ]
        );

        $this->eventManager->dispatch(
            'email_shipment_set_template_vars_before', array('sender' => $this, 'transport' => $transport)
        );

        $this->templateContainer->setTemplateVars($transport->getTemplateVars());

        $result = $this->checkAndSend($order, $notify);
        if ($result) {
            $shipment->setEmailSent(true);
            $this->shipmentResource->saveAttribute($shipment, 'email_sent');
        }
        return $result;
    }

    /**
     * Get payment info block as html
     *
     * @param Order $order
     * @return string
     */
    protected function getPaymentHtml(Order $order)
    {
        return $this->paymentHelper->getInfoBlockHtml(
            $order->getPayment(),
            $this->identityContainer->getStore()->getStoreId()
        );
    }
}
