<?php

namespace MageWizz\OrderGridActions\Console\Command;

use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use MageWizz\Base\Logger\Logger;
use MageWizz\OrderGridActions\Service\Order;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CompleteOrder extends Command
{
    public function __construct(
        protected CollectionFactory $orderCollectionFactory,
        protected Order $orderService,
        protected Logger $logger,
        string $name = null
    )
    {
        parent::__construct($name);
    }

    /**
     * Initialization of the command.
     */
    protected function configure()
    {
        $this->setName('magewizz:order:complete');
        $this->setDescription('This CLI command can be used to set an order to the "complete" state, meaning an invoice and a shipment will be created if missing.');
        $this->addArgument(
            "ids",
            InputArgument::REQUIRED,
            "ID's of orders to complete"
        );
        parent::configure();
    }

    /**
     * CLI command description.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info(Order::LOG_PREFIX . 'Start running command to put orders to complete');
        $ids = $input->getArgument('ids');
        $arrIds = explode(',', $ids);

        $orderCollection = $this->orderCollectionFactory
            ->create();
        $orderCollection->addFieldToFilter('entity_id', ['IN' => $arrIds]);

        foreach ($orderCollection as $order) {
            $this->logger->info(Order::LOG_PREFIX . 'Completing order ' . $order->getId());
            $this->orderService->completeOrder($order);
        }
        return 1;
    }
}
