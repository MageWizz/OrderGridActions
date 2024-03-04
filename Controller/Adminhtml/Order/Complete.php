<?php

namespace MageWizz\OrderGridActions\Controller\Adminhtml\Order;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Backend\App\Action\Context;
use Magento\Sales\Controller\Adminhtml\Order\AbstractMassAction;
use Magento\Ui\Component\MassAction\Filter;
use MageWizz\OrderGridActions\Service\Order;

class Complete extends AbstractMassAction
{
    private AbstractCollection $orderCollection;

    public function __construct(
        Context $context,
        Filter $filter,
        protected Order $orderService
    )
    {
        parent::__construct($context, $filter);
    }

    protected function massAction(AbstractCollection $collection)
    {
        $this->orderCollection = $collection;
        $this->orderService->completeOrders($this->orderCollection);
    }
}
