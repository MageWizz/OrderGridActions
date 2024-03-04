<?php
declare(strict_types=1);

namespace MageWizz\OrderGridActions\Service;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Convert\OrderFactory;
use Magento\Sales\Model\Convert\Order as ConvertOrder;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Sales\Model\Order\ShipmentFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use MageWizz\Base\Logger\Logger;

class Order
{
    public const LOG_PREFIX = "OrderGridActions";

    /**
     * @param CollectionFactory $orderCollectionFactory
     * @param Logger $logger
     * @param InvoiceService $invoiceService
     * @param Transaction $transaction
     * @param InvoiceSender $invoiceSender
     * @param OrderFactory $orderConvertFactory
     */
    public function __construct(
        protected CollectionFactory $orderCollectionFactory,
        public Logger $logger,
        protected InvoiceService $invoiceService,
        protected Transaction $transaction,
        protected InvoiceSender $invoiceSender,
        protected OrderFactory $orderConvertFactory,
        protected ShipmentFactory $shipmentFactory,
        protected ConvertOrder $convertOrder
    )
    {
    }

    public function completeOrdersByIds(array $orderIds): void
    {
        $orderCollection = $this->orderCollectionFactory->create();
        $orderCollection->addFieldToFilter('entity_id', ['IN' => $orderIds]);

        foreach($orderCollection as $order) {
            $this->completeOrder($order);
        }
    }

    /**
     * @param MagentoOrder $order
     * @return void
     */
    public function completeOrder(MagentoOrder $order): void
    {
        if ($order->canInvoice()) {
            $this->createInvoiceForOrder($order);
        }
        if ($order->canShip()) {
            $this->createShipmentForOrder($order);
        }
    }

    /**
     * @param MagentoOrder $order
     * @return void
     */
    private function createInvoiceForOrder(MagentoOrder $order): void
    {
        try {
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->register();
            $invoice->save();
            $transactionSave =
                $this->transaction
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder());
            $transactionSave->save();
        } catch (Exception $e) {
            $this->logger->error("Failed to create invoice", [
                'order_id' => $order->getId(),
                'error' => $e->getMessage(),
            ]);
            return;
        }
    }

    /**
     * @param MagentoOrder $order
     * @return void
     */
    private function createShipmentForOrder(MagentoOrder $order): void
    {
        try {
            $orderShipment = $this->convertOrder->toShipment($order);
            foreach ($order->getAllItems() as $orderItem) {
                // Check virtual item and item Quantity
                if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                    continue;
                }
                $qty = $orderItem->getQtyToShip();
                $shipmentItem = $this->convertOrder->itemToShipmentItem($orderItem)->setQty($qty);
                $orderShipment->addItem($shipmentItem);
            }

            $orderShipment->register();
            $orderShipment->getOrder()->setIsInProcess(true);
            $orderShipment->save();
            $orderShipment->getOrder()->save();
        } catch (Exception $e) {
            $this->logger->error("Failed to save shipment", [
                'order_id' => $order->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param AbstractCollection $orderCollection
     * @return void
     */
    public function completeOrders(\Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection $orderCollection): void
    {
        foreach ($orderCollection as $order) {
            $this->completeOrder($order);
        }
    }
}
