<?php declare(strict_types=1);
/**
 * Copyright (c) 2021 CardGate B.V.
 * See DISCLAIMER.md for disclaimer details.
 */

namespace CardGate\Shopware\Helper;

use CardGate\Shopware\CuroCardGate;
use CardGate\Shopware\PaymentMethods\Afterpay;
use CardGate\Shopware\PaymentMethods\Bancontact;
use CardGate\Shopware\PaymentMethods\Banktransfer;
use CardGate\Shopware\PaymentMethods\Billink;
use CardGate\Shopware\PaymentMethods\Bitcoin;
use CardGate\Shopware\PaymentMethods\Creditcard;
use CardGate\Shopware\PaymentMethods\Directdebit;
use CardGate\Shopware\PaymentMethods\Giftcard;
use CardGate\Shopware\PaymentMethods\Giropay;
use CardGate\Shopware\PaymentMethods\Ideal;
use CardGate\Shopware\PaymentMethods\Idealqr;
use CardGate\Shopware\PaymentMethods\Klarna;
use CardGate\Shopware\PaymentMethods\Onlineueberweisen;
use CardGate\Shopware\PaymentMethods\Paypal;
use CardGate\Shopware\PaymentMethods\Paysafecard;
use CardGate\Shopware\PaymentMethods\Paysafecash;
use CardGate\Shopware\PaymentMethods\Przelewy24;
use CardGate\Shopware\PaymentMethods\Sofortbanking;
use CardGate\Shopware\PaymentMethods\Spraypay;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class GatewayHelper
{
    /** @var EntityRepositoryInterface */
    private $orderRepository;

    /**
     * GatewayHelper constructor.
     * @param EntityRepositoryInterface $orderRepository
     */
    public function __construct(EntityRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public const GATEWAYS = [
	    Afterpay::class,
	    Bancontact::class,
	    Banktransfer::class,
	    Billink::class,
	    Bitcoin::class,
    	Creditcard::class,
	    Directdebit::class,
	    Giftcard::class,
	    Giropay::class,
        Ideal::class,
	    Idealqr::class,
	    Klarna::class,
	    Onlineueberweisen::class,
	    Paypal::class,
	    Paysafecard::class,
	    Paysafecash::class,
	    Przelewy24::class,
	    Sofortbanking::class,
	    Spraypay::class
    ];

    /**
     * @param string $orderId
     * @param Context $context
     * @return bool
     */
    public function isCardgatePaymentMethod(string $orderId, Context $context)
    {
        $order = $this->getOrderData($orderId, $context);
        $transaction = $order->getTransactions()->first();
        if (!$transaction || !$transaction->getPaymentMethod() || !$transaction->getPaymentMethod()->getPlugin()) {
            return false;
        }

        $plugin = $transaction->getPaymentMethod()->getPlugin();

        return $plugin->getBaseClass() === CuroCardGate::class;
    }

    /**
     * @param string $orderId
     * @param Context $context
     * @return mixed|null
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    private function getOrderData(string $orderId, Context $context)
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions');
        $criteria->addAssociation('transactions.paymentMethod');
        $criteria->addAssociation('transactions.paymentMethod.plugin');
        $criteria->addAssociation('salesChannel');

        return $this->orderRepository->search($criteria, $context)->get($orderId);
    }
}
