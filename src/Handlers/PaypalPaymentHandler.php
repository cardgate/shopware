<?php declare(strict_types=1);
/**
 * Copyright (c) 2021 CardGate B.V.
 * All rights reserved.
 * See LICENSE for license details.
 */

namespace CardGate\Shopware\Handlers;

use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PaypalPaymentHandler extends AsyncPaymentHandler {
	private $paymentMethod = 'paypal';
	/**
	 * @param AsyncPaymentTransactionStruct $transaction
	 * @param RequestDataBag $dataBag
	 * @param SalesChannelContext $salesChannelContext
	 * @param string|null $paymentMethod
	 *
	 * @return RedirectResponse
	 * @throws \Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException
	 */
	public function pay(
		AsyncPaymentTransactionStruct $transaction,
		RequestDataBag $dataBag,
		SalesChannelContext $salesChannelContext,
		string $paymentMethod = null
	): RedirectResponse {
		return parent::pay( $transaction, $dataBag, $salesChannelContext, $this->paymentMethod);
	}
}

