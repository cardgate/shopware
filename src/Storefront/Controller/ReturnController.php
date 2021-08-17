<?php declare(strict_types=1);
/**
 * Copyright (c) 2021 CardGate B.V.
 * All rights reserved.
 * See LICENSE for license details.
 */
namespace CardGate\Shopware\Storefront\Controller;

use CardGate\Shopware\Helper\CgtHelper;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;

class ReturnController extends StorefrontController {
	/** @var EntityRepositoryInterface */
	private $orderRepository;
	/** @var EntityRepositoryInterface */
	private $customerRepository;
	/** @var Request $request */
	private $request;
	/** @var Context $context */
	private $context;
/**
    * @param EntityRepository $transactionRepository
	*@param EntityRepositoryInterface $customerRepository
    *@param CgtHelper $cgtHelper
 *
 **/
	public function __construct(
		EntityRepositoryInterface $orderRepository,
		EntityRepositoryInterface $customerRepository,
		CgtHelper $cgtHelper
	) {
		$this->orderRepository = $orderRepository;
		$this->customerRepository = $customerRepository;
		$this->request = $cgtHelper->getGlobals();
		$this->context = Context::createDefaultContext();
	}

	/**
	 * @RouteScope(scopes={"storefront"})
	 * @Route("/cardgate/return",
	 *      name="frontend.cardgate.return",
	 *      options={"seo"="false"},
	 *      methods={"GET"}
	 *     )
	 * @return Response
	 */
	public function return(): Response{
		$status = $this->request->get('status');
		$reference = $this->request->get('reference');
		$finalizeUrl = $this->getFinalizeUrl($reference,$status );
		return $this->redirect($finalizeUrl);
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

	/**
	 * @param $reference
	 * @param $status
	 *
	 * @return string
	 */
	private function getFinalizeUrl($reference, $status): string
	{
		$order = $this->getOrderFromNumber($reference);
		$customerNumber = $order->getOrderCustomer()->getCustomerNumber();
		$contextSource = new SaleschannelApiSource($order->getSalesChannelId());
		$context = new Context($contextSource);
		$criteria = new Criteria();
		$criteria->addFilter(new EqualsFilter('customerNumber', $customerNumber));
		$customer = $this->customerRepository->search($criteria, $context);
		$elements = $customer->getEntities()->getElements();
		$elements = reset($elements);
		$status= ($status == 'failure' ? 'true': 'false');
		$params = '&transactionid='.$reference.'&cancel='.$status;
		$finalizeUrl = $elements->getCustomFields()['finalize_url'].$params;
		return $finalizeUrl;
	}
}
