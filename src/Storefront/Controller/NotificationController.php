<?php declare(strict_types=1);
/**
 * Copyright (c) 2021 CardGate B.V.
 * All rights reserved.
 * See LICENSE for license details.
 */

namespace CardGate\Shopware\Storefront\Controller;


use Exception;
use CardGate\Shopware\Helper\ApiHelper;
use CardGate\Shopware\Helper\CheckoutHelper;
use CardGate\Shopware\Helper\CgtHelper;
use CardGate\Shopware\Service\SettingsService;
use CardGate\Shopware\Service\InvoiceService;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NotificationController extends StorefrontController
{
    /** @var CheckoutHelper $checkoutHelper */
    private $checkoutHelper;
    /** @var Request $request */
    private $request;
    /** @var EntityRepository $orderRepository */
    private $orderRepository;
    /** @var ApiHelper $apiHelper */
    private $apiHelper;
    /** @var Context $context */
    private $context;
	/** @var SettingsService $settingsService */
	private $settingsService;
    /** @var InvoiceService $invoiceService */
    private $invoiceService;

    /**
     * NotificationController constructor.
     * @param EntityRepository $orderRepository
     * @param CheckoutHelper $checkoutHelper
     * @param ApiHelper $apiHelper
     * @param CgtHelper $cgtHelper
     * @param SettingsService $settingsService
     * @param InvoiceService $invoiceService
     */
    public function __construct(
        EntityRepository $orderRepository,
        CheckoutHelper $checkoutHelper,
        ApiHelper $apiHelper,
        CgtHelper $cgtHelper,
		SettingsService $settingsService,
        InvoiceService $invoiceService
    ) {
        $this->orderRepository = $orderRepository;
        $this->checkoutHelper = $checkoutHelper;
        $this->request = $cgtHelper->getGlobals();
        $this->apiHelper = $apiHelper;
        $this->settingsService = $settingsService;
        $this->invoiceService = $invoiceService;
        $this->context = Context::createDefaultContext();
    }
    #[Route(
        path: "/cardgate/notification",
        defaults: ['_routeScope' => ['storefront']],
        name: "frontend.cardgate.notification",
        options: ["seo" => false],
        methods: ["GET"]
    )]
    public function notification(): Response
     {
    	$get = [];
    	$get['transaction'] = $this->request->get('transaction');
	    $get['currency'] = $this->request->get('currency');
	    $get['amount'] = $this->request->get('amount');
	    $get['reference'] = $this->request->get('reference');
	    $get['code'] = $this->request->get('code');
	    $get['hash'] = $this->request->get('hash');
	    $get['status'] = $this->request->get('status');
	    $get['pt'] = $this->request->get('pt');
	    $testMode = $this->request->get('testmode');
	    if ($testMode == 1){
	    	$get['testmode'] = 'TEST';
	    }

        $response = new Response();

        $orderNumber = $this->request->query->get('reference');
        try {
            $order = $this->getOrderFromNumber($orderNumber);
        } catch (InconsistentCriteriaIdsException $exception) {
            return $response->setContent('Could not find Order.');
        }

        $transactionId = $order->getTransactions()->first()->getId();
        $transaction = $order->getTransactions()->first();
        $oCardGateClient = $this->apiHelper->initializeCardGateClient($order->getSalesChannelId());
        try {
	        if ( FALSE == $oCardGateClient->transactions()->verifyCallback( $get, $this->settingsService->getSetting('hashKey',$order->getSalesChannelId())) ) {
		        throw new \Exception( 'hash verification failure' );
	        }
        } catch (Exception $exception) {
            return $response->setContent('hash verification failure');
        }
        $this->checkoutHelper->transitionPaymentState($get['code'], $transactionId, $this->context);
        if (!$this->invoiceService->isInvoiced($order->getId(),$this->context)){
            $this->invoiceService->generateInvoice( $order, $this->context, $order->getSalesChannelId() );
        }
        $responseContent = $get['transaction'].'.'.$get['code'];
        return $response->setContent($responseContent);
    }

    /**
     * @param string $orderNumber
     * @return OrderEntity
     * @throws InconsistentCriteriaIdsException
     */
    public function getOrderFromNumber(string $orderNumber): OrderEntity
    {
        $orderRepo = $this->orderRepository;
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber))
            ->addAssociation('transactions');
        return $orderRepo->search($criteria, $this->context)->first();
    }
}
