<?php declare(strict_types=1);
/**
 * Copyright (c) 2021 CardGate B.V.
 * All rights reserved.
 * See LICENSE for license details.
 */

namespace CardGate\Shopware\Handlers;

use Exception;
use CardGate\Shopware\Helper\ApiHelper;
use CardGate\Shopware\Helper\CheckoutHelper;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use CardGate\Shopware\Helper\CgtHelper;

class AsyncPaymentHandler implements AsynchronousPaymentHandlerInterface
{
	/**
	 * @var OrderTransactionStateHandler
	 */
	private $orderTransactionStateHandler;
    /** @var ApiHelper $apiHelper */
    public $apiHelper;
    /** @var CheckoutHelper $checkoutHelper */
    public $checkoutHelper;
    /** @var CgtHelper $cgtHelper */
    public $cgtHelper;
    /**
     * @var EntityRepository
     */
    private $customerRepository;
    /**
     * CardGate constructor.
     * @param ApiHelper $apiHelper
     * @param CheckoutHelper $checkoutHelper
     * @param CgtHelper $cgtHelper
     */
    public function __construct(
	    OrderTransactionStateHandler $orderTransactionStateHandler,
        ApiHelper $apiHelper,
        CheckoutHelper $checkoutHelper,
        CgtHelper $cgtHelper,
	    EntityRepository $customerRepository
    ) {
	    $this->orderTransactionStateHandler = $orderTransactionStateHandler;
        $this->apiHelper = $apiHelper;
        $this->checkoutHelper = $checkoutHelper;
        $this->cgtHelper = $cgtHelper;
        $this->customerRepository = $customerRepository;
    }

    /**
     * @param AsyncPaymentTransactionStruct $transaction
     * @param RequestDataBag $dataBag
     * @param SalesChannelContext $salesChannelContext
     * @param string|null $paymentMethod
     * @return RedirectResponse
     * @throws PaymentException
     */
    public function pay(
        AsyncPaymentTransactionStruct $paymentTransaction,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext,
        string $paymentMethod = null
    ): RedirectResponse {
	    $transactionId = $paymentTransaction->getOrderTransaction()->getId();
	    $customer = $salesChannelContext->getCustomer();
	    if ($customer === null) {
            throw PaymentException::asyncProcessInterrupted($transactionId,'Customer not logged in');
	    }
	    $paymentOptions = $this->checkoutHelper->getPaymentOptions($paymentTransaction);
	    $this->saveFinalizeUrl($salesChannelContext,$paymentOptions['finalize_url']);

	    $this->orderTransactionStateHandler->process($transactionId, $salesChannelContext->getContext());
        $oCardGate = $this->apiHelper->initializeCardGateClient($salesChannelContext->getSalesChannel()->getId());
        $aMetaData = $this->checkoutHelper->getPluginMetadata($salesChannelContext->getContext());
	    $oCardGate->version()->setPlatformName( $aMetaData['platform_name'] );
	    $oCardGate->version()->setPlatformVersion( $aMetaData['platform_version'] );
	    $oCardGate->version()->setPluginName($aMetaData['plugin_name'] );
	    $oCardGate->version()->setPluginVersion( $aMetaData['plugin_version']);

	    $order = $paymentTransaction->getOrder();
        $request = $this->cgtHelper->getGlobals();

	    $currency = $order->getCurrency()->getIsoCode();

	    if ($currency === null) {
		    $currency = $salesChannelContext->getCurrency()->getIsoCode();
	    }
	    $grandTotal = (int)round($order->getAmountTotal()*100);
	    try {
		    $transaction = $oCardGate->transactions()->create(
		    	$this->apiHelper->getSiteId(),
			    $grandTotal,
			    $currency
		    );

			$transaction->setPaymentMethod($paymentMethod);
			$transaction->setCallbackUrl($paymentOptions['notification_url']);
			$transaction->setRedirectUrl($paymentOptions['return_url']);
			$transaction->setReference($order->getOrderNumber());
			$transaction->setDescription($order->getOrderNumber());

		    if ( $paymentMethod == 'ideal' && $this->apiHelper->getShowIssuers() ){
		    	$issuer = $dataBag->get('issuer');
		    	$transaction->setIssuer($issuer);
			   $this->saveLastIssuer($salesChannelContext,$issuer);
		    }

			//Add consumer data to the transaction.
		    $billingAddress = $this->checkoutHelper->getBillingData($request, $customer);
		    $shippingAddress = $this->checkoutHelper->getShippingData($customer);
		    $consumer = $transaction->getConsumer();
		    $consumer->setEmail($billingAddress['email']);
		    if (!(is_null($billingAddress['telephone']) || empty($billingAddress['telephone']))) {
			    $consumer->setPhone( $billingAddress['$telephone']);
		    }
		    $this->_convertAddress($billingAddress, $consumer, 'address');
		    $this->_convertAddress($shippingAddress, $consumer, 'shippingAddress');

		    $calculatedGrandTotal = 0;
		    $calculatedVatTotal = 0;
		    $cart = $transaction->getCart();

		    $shoppingCart = $this->checkoutHelper->getShoppingCart($order);

		    foreach ($shoppingCart['products'] as $product){
				$cartItem = $cart->addItem(
					\cardgate\api\Item::TYPE_PRODUCT,
					$product['sku'],
					$product['name'],
					$product['qty'],
					$product['unit_price']
				);
				$cartItem->setVat($product['vat']);
				$cartItem->setVatIncluded($product['vat_included']);
				$cartItem->setVatAmount($product['vat_amount']);

			    $calculatedGrandTotal += $product['unit_price'] * $product['qty'];
			    $calculatedVatTotal += $product['vat_amount'] * $product['qty'];
		    }

		    foreach ($shoppingCart['discounts'] as $discount){
			    $cartItem = $cart->addItem(
				    \cardgate\api\Item::TYPE_DISCOUNT,
				    $discount['sku'],
				    $discount['name'],
				    $discount['qty'],
				    $discount['unit_price']
			    );
			    $cartItem->setVat($discount['vat']);
			    $cartItem->setVatIncluded($discount['vat_included']);
			    $cartItem->setVatAmount($discount['vat_amount']);

			    $calculatedGrandTotal += $discount['unit_price'] * $discount['qty'];
			    $calculatedVatTotal += $discount['vat_amount'] * $discount['qty'];
		    }

		    $shippingItem = $this->checkoutHelper->getShippingItem($order);
		    if ($shippingItem['unit_price'] > 0){
			    $cartItem = $cart->addItem(
				    \cardgate\api\Item::TYPE_SHIPPING,
				    $shippingItem['sku'],
				    $shippingItem['name'],
				    $shippingItem['qty'],
				    $shippingItem['unit_price']
			    );
			    $cartItem->setVat($shippingItem['vat']);
			    $cartItem->setVatIncluded($shippingItem['vat_included']);
			    $cartItem->setVatAmount($shippingItem['vat_amount']);

			    $calculatedGrandTotal += $shippingItem['unit_price'] * $shippingItem['qty'] ;
			    $calculatedVatTotal += $shippingItem['vat_amount'] * $shippingItem['qty'];

		    }
			$orderVat = $order->getAmountTotal() - $order->getAmountNet();

		    // Failsafe; correct VAT if needed.
		    $vatTotalCorrection = round(($orderVat * 100 - $calculatedVatTotal),0);
		    if ( abs($vatTotalCorrection) >= 1 ){
			    $vatCorrection = $orderVat - $calculatedVatTotal/100;
			    $cartItem = $cart->addItem(
				    \cardgate\api\Item::TYPE_VAT_CORRECTION,
				    'cg-vatcorrection',
				    'VAT Correction',
				    1,
				    $vatTotalCorrection
			    );
			    $cartItem->setVat( 100 );
			    $cartItem->setVatIncluded( TRUE );
			    $cartItem->setVatAmount( round( $vatCorrection * 100, 0 ) );

			    $calculatedGrandTotal += $vatCorrection;
		    }
		    // Failsafe; correct grandtotal if needed.
		    $grandTotalCorrection = round( ( $order->getAmountNet() * 100 - $calculatedGrandTotal ), 0 );
		    if ( abs( $grandTotalCorrection ) > 0 ) {
			    $cartItem = $cart->addItem(
				    \cardgate\api\Item::TYPE_CORRECTION,
				    'cg-correction',
				    'Correction',
				    1,
				    $grandTotalCorrection
			    );
			    $cartItem->setVat( 0 );
			    $cartItem->setVatIncluded( TRUE );
			    $cartItem->setVatAmount( 0 );
		    }
		    // Register the transaction and finish up.
		    $transaction->register();

		    $actionUrl = $transaction->getActionUrl();
		    if ( NULL !== $actionUrl ) {
			    // Redirect the consumer to the CardGate payment gateway.
			    return new RedirectResponse($actionUrl);
		    } else {
			    // Payment methods without user interaction are not yet supported.
			    throw new \Exception( 'unsupported payment action' );
		    }

	    } catch (Exception $exception) {
		    throw PaymentException::asyncProcessInterrupted(
		    $order->getTransactions()->first()->getId(),
			    $exception->getMessage()
		    );
	    }
    }

    /**
     * @param AsyncPaymentTransactionStruct $transaction
     * @param Request $request
     * @param SalesChannelContext $salesChannelContext
     * @throws PaymentException
     */
    public function finalize(
        AsyncPaymentTransactionStruct $transaction,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): void {
        $orderTransactionId = $transaction->getOrderTransaction()->getId();
        $orderId = $transaction->getOrder()->getOrderNumber();

        try {
            $transactionId = $request->query->get('transactionid');

            if ($orderId !== (string)$transactionId) {
                throw new Exception('Order number does not match order number known at CardGate');
            }
        } catch (Exception $exception) {
            throw PaymentException::invalidOrder($orderId);
        }

        if ($request->query->getBoolean('cancel')) {
            throw PaymentException::customerCanceled($orderTransactionId, 'Canceled at payment page');
        }
    }

	/**
	 * Converts a shopware address array to a cardgate consumer address.
	 * @return array
	 */
	private static function _convertAddress( $aAddress, \cardgate\api\Consumer &$oConsumer_, $sMethod_ ) {
		$oConsumer_->$sMethod_()->setFirstName( $aAddress['first_name'] );
		$oConsumer_->$sMethod_()->setLastName( $aAddress['last_name'] );
		$oConsumer_->$sMethod_()->setAddress($aAddress['address'] );
		$oConsumer_->$sMethod_()->setCity( $aAddress['city'] );
		if ( !is_null($aAddress['state'] ) || !empty($aAddress['state']) ) {
			$oConsumer_->$sMethod_()->setState( $aAddress['state']);
		}
		$oConsumer_->$sMethod_()->setZipCode( $aAddress['zip_code'] );
		$oConsumer_->$sMethod_()->setCountry( $aAddress['country'] );
	}

	/** Saves Finalize Url
	 * @return void
	 */
	private function saveFinalizeUrl($salesChannelContext,$finalizeUrl){
		$customer = $salesChannelContext->getCustomer();
		$this->customerRepository->upsert(
			[
				[
					'id'           => $customer->getId(),
					'customFields' => [ 'finalize_url' => $finalizeUrl ]
				]
			], $salesChannelContext->getContext()
		);
	}

	/** Saves last iDEAL issuer
	 * @return void
	*/
	private function saveLastIssuer($salesChannelContext,$issuer){
		$customer = $salesChannelContext->getCustomer();
		$this->customerRepository->upsert(
			[
				[
					'id'           => $customer->getId(),
					'customFields' => [ 'last_used_issuer' => $issuer ]
				]
			], $salesChannelContext->getContext()
		);

	}
}
