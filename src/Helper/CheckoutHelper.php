<?php declare(strict_types=1);
/**
 * Copyright (c) 2021 CardGate B.V.
 * All rights reserved.
 * See LICENSE for license details.
 */

namespace CardGate\Shopware\Helper;

use CardGate\Shopware\Service\SettingsService;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\StateMachine\Exception\StateMachineInvalidEntityIdException;
use Shopware\Core\System\StateMachine\Exception\StateMachineInvalidStateFieldException;
use Shopware\Core\System\StateMachine\Exception\StateMachineNotFoundException;
use Shopware\Core\System\StateMachine\Exception\StateMachineStateNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CheckoutHelper
{
    /** @var UrlGeneratorInterface $router */
    private $router;
    /** @var OrderTransactionStateHandler $orderTransactionStateHandler*/
    private $orderTransactionStateHandler;
    /** @var EntityRepository $transactionRepository */
    private $transactionRepository;
    /** @var EntityRepository $stateMachineRepository */
    private $stateMachineRepository;
    /** @var SettingsService */
    private $settingsService;

    /**
     * @var string
     */
    private $shopwareVersion;

    /**
     * @var PluginService
     */
    private $pluginService;

    /**
     * CheckoutHelper constructor.
     * @param UrlGeneratorInterface $router
     * @param OrderTransactionStateHandler $orderTransactionStateHandler
     * @param SettingsService $settingsService
     * @param EntityRepository $transactionRepository
     * @param EntityRepository $stateMachineRepository
     * @param string $shopwareVersion
     * @param PluginService $pluginService
     */
    public function __construct(
        UrlGeneratorInterface $router,
        OrderTransactionStateHandler $orderTransactionStateHandler,
        SettingsService $settingsService,
        EntityRepository $transactionRepository,
        EntityRepository $stateMachineRepository,
        string $shopwareVersion,
        PluginService $pluginService
    ) {
        $this->router = $router;
        $this->settingsService = $settingsService;
        $this->transactionRepository = $transactionRepository;
        $this->orderTransactionStateHandler = $orderTransactionStateHandler;
        $this->stateMachineRepository = $stateMachineRepository;
        $this->shopwareVersion = $shopwareVersion;
        $this->pluginService = $pluginService;
    }

    /**
     * @param string $address1
     * @param string $address2
     * @return string
     */
    public function parseAddress(string $address1, string $address2 = ''): string
    {
        $address1 = trim($address1);
        $address2 = trim($address2);
        $fullAddress = trim("{$address1} {$address2}");
        $fullAddress = preg_replace('/[[:blank:]]+/', ' ', $fullAddress);
        $matches = [];
        $pattern = '/(.+?)\s?([\d]+[\S]*)(\s?[A-z]*?)$/';
        preg_match($pattern, $fullAddress, $matches);
        $street = $matches[1] ?? '';
        $apartment = $matches[2] ?? '';
        $extension = $matches[3] ?? '';
        $street = trim($street);
        $apartment = trim($apartment . $extension);

        return trim("{$street} {$apartment}");
    }

    /**
     * @param Request $request
     * @param CustomerEntity $customer
     * @return array
     */
    public function getBillingData(Request $request, CustomerEntity $customer): array
    {

        return [
            'locale' => $this->getTranslatedLocale($request->getLocale()),
            'ip_address' => $request->getClientIp(),
            'first_name' => $customer->getDefaultBillingAddress()->getFirstName(),
            'last_name' => $customer->getDefaultBillingAddress()->getLastName(),
            'address' => $this->parseAddress($customer->getDefaultBillingAddress()->getStreet()),
            'zip_code' => $customer->getDefaultBillingAddress()->getZipcode(),
            'state' => $customer->getDefaultBillingAddress()->getCountryState(),
            'city' => $customer->getDefaultBillingAddress()->getCity(),
            'country' => $this->getCountryIso($customer->getDefaultBillingAddress()),
            'phone' => $customer->getDefaultBillingAddress()->getPhoneNumber(),
            'email' => $customer->getEmail(),
            'referrer' => $request->server->get('HTTP_REFERER'),
            'user_agent' => $request->headers->get('User-Agent'),
            'reference' => $customer->getGuest() ? null : $customer->getId()
        ];
    }

    /**
     * @param CustomerEntity $customer
     * @return array
     */
    public function getShippingData(CustomerEntity $customer): array
    {
        return [
            'first_name' => $customer->getDefaultShippingAddress()->getFirstName(),
            'last_name' => $customer->getDefaultShippingAddress()->getLastName(),
            'address' => $this->parseAddress($customer->getDefaultShippingAddress()->getStreet()),
            'zip_code' => $customer->getDefaultShippingAddress()->getZipcode(),
            'state' => $customer->getDefaultShippingAddress()->getCountryState(),
            'city' => $customer->getDefaultShippingAddress()->getCity(),
            'country' => $this->getCountryIso($customer->getDefaultShippingAddress()),
            'phone' => $customer->getDefaultShippingAddress()->getPhoneNumber(),
            'email' => $customer->getEmail()
        ];
    }

    /**
     * @param AsyncPaymentTransactionStruct $transaction
     * @return array
     */
    public function getPaymentOptions(AsyncPaymentTransactionStruct $transaction): array
    {
        return [
            'notification_url' => $this->router->generate(
                'frontend.cardgate.notification',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'return_url' => $this->router->generate(
	            'frontend.cardgate.return',
	            [],
	            UrlGeneratorInterface::ABSOLUTE_URL
            ),
	        'finalize_url' => $transaction->getReturnUrl(),
             'cancel_url' => sprintf('%s&cancel=1', $transaction->getReturnUrl()),
            'close_window' => false
        ];
    }

    /**
     * @param OrderEntity $order
     * @return array
     */
    public function getShoppingCart(OrderEntity $order): array
    {
        $shoppingCart = [];
        $hasNetPrices = $order->getPrice()->hasNetPrices();

        /** @var OrderLineItemEntity $item */
        foreach ($order->getNestedLineItems() as $item) {
	        // Support SwagCustomizedProducts
	        if ( $item->getType() === 'customized-products' ) {
		        foreach ( $item->getChildren() as $customItem ) {
			        $shoppingCart[] = $this->getShoppingCartItem( $customItem, $hasNetPrices );
		        }
		        continue;
	        }

	        $thisItem = $this->getShoppingCartItem( $item, $hasNetPrices );

	        switch ( $thisItem['type'] ) {
		        case 'product':
			        $shoppingCart['products'][] = $thisItem;
			        break;
		        case 'promotion':
			        $shoppingCart['discounts'][] = $thisItem;
			        break;
		        default:
			        $shoppingCart['item'][] = $thisItem;
	        }
        }

        return $shoppingCart;
    }

	/**
	 * @param OrderEntity $order
	 * @return array
	 */
	public function getShippingItem(OrderEntity $order): array
	{
		$vat = $this->getTaxRate($order->getShippingCosts());
		$hasNetPrices = $order->getPrice()->hasNetPrices();
		$unitPrice = $this->getUnitPriceExclTax($order->getShippingCosts(), $hasNetPrices);

		return [
			'sku' => 'Shipping',
			'name' => 'shipping',
			'qty' => $order->getShippingCosts()->getQuantity(),
			'unit_price' => round($unitPrice *100),
			'vat' => $vat,
			'vat_included' => false,
			'vat_amount' => (int)round($unitPrice * $vat,0)
		];
	}


    /**
     * @param OrderEntity $order
     * @return array
     */
    public function getCheckoutOptions(OrderEntity $order): array
    {
        $checkoutOptions['tax_tables']['default'] = [
            'shipping_taxed' => true,
            'rate' => ''
        ];

        // Create array with unique tax rates from order_items
        foreach ($order->getLineItems() as $item) {
            $taxRates[] = $this->getTaxRate($item->getPrice());
        }
        // Add shippingTax to array with unique tax rates
        $taxRates[] = $this->getTaxRate($order->getShippingCosts());

        $uniqueTaxRates = array_unique($taxRates);

        // Add unique tax rates to CheckoutOptions
        foreach ($uniqueTaxRates as $taxRate) {
            $checkoutOptions['tax_tables']['alternate'][] = [
                'name' => (string) $taxRate,
                'standalone' => true,
                'rules' => [
                    [
                        'rate' => $taxRate / 100
                    ]
                ]
            ];
        }

        return $checkoutOptions;
    }


    /**
     * @param $locale
     * @return string
     */
    public function getTranslatedLocale(?string $locale): string
    {
        switch ($locale) {
            case 'nl':
                $translatedLocale = 'nl_NL';
                break;
            case 'de':
                $translatedLocale = 'de_DE';
                break;
            default:
                $translatedLocale = 'en_GB';
                break;
        }
        return $translatedLocale;
    }

    /**
     * @param CustomerAddressEntity $customerAddress
     * @return string|null
     */
    private function getCountryIso(CustomerAddressEntity $customerAddress): ?string
    {
        $country = $customerAddress->getCountry();
        if (!$country) {
            return null;
        }
        return $country->getIso();
    }

    /**
     * @param OrderLineItemEntity $item
     * @return mixed
     */
    private function getMerchantItemId(OrderLineItemEntity $item)
    {
        if ($item->getType() === 'promotion') {
            return $item->getPayload()['discountId'];
        }
        return $item->getPayload()['productNumber'];
    }

    /**
     * @param CalculatedPrice $calculatedPrice
     * @return float
     */
    public function getTaxRate(CalculatedPrice $calculatedPrice) : float
    {
        $rates = [];

        // Handle TAX_STATE_FREE
        if ($calculatedPrice->getCalculatedTaxes()->count() === 0) {
            return 0;
        }

        foreach ($calculatedPrice->getCalculatedTaxes() as $tax) {
            $rates[] = $tax->getTaxRate();
        }
        // return highest taxRate
        return (float) max($rates);
    }

    /**
     * @param CalculatedPrice $calculatedPrice
     * @param bool $hasNetPrices
     * @return float
     */
    public function getUnitPriceExclTax(CalculatedPrice $calculatedPrice, bool $hasNetPrices) : float
    {
        $unitPrice = $calculatedPrice->getUnitPrice();

        // Do not calculate excl TAX when price is already excl TAX
        if ($hasNetPrices) {
            return $unitPrice;
        }

        $taxRate = $this->getTaxRate($calculatedPrice);
        if ($unitPrice && $taxRate) {
            $unitPrice /= (1 + ($taxRate / 100));
        }
        return (float) $unitPrice;
    }

    /**
     * @param string $status
     * @param string $orderTransactionId
     * @param Context $context
     * @throws IllegalTransitionException
     * @throws InconsistentCriteriaIdsException
     * @throws StateMachineInvalidEntityIdException
     * @throws StateMachineInvalidStateFieldException
     * @throws StateMachineNotFoundException
     * @throws StateMachineStateNotFoundException
     */
    public function transitionPaymentState(string $code, string $orderTransactionId, Context $context): void
    {
        $transitionAction = $this->getTransitionAction($code);

        if ($transitionAction === null) {
            return;
        }

        if ($this->isSameStateId($transitionAction, $orderTransactionId, $context)) {
            return;
        }

        if (!$this->canChangeState($transitionAction, $orderTransactionId, $context)){
        	return;
        }

        try {
            $functionName = $this->convertToFunctionName($transitionAction);
            $this->orderTransactionStateHandler->$functionName($orderTransactionId, $context);
        } catch (IllegalTransitionException $exception) {
            if ($transitionAction !== StateMachineTransitionActions::ACTION_PAID) {
                return;
            }

            $this->orderTransactionStateHandler->reopen($orderTransactionId, $context);
            $this->transitionPaymentState($code, $orderTransactionId, $context);
        }
    }

    /**
     * @param string $status
     * @return string|null
     */
    public function getTransitionAction(string $code): ?string
    {
    	if ($code < 100){
    		// pending
		    return StateMachineTransitionActions::ACTION_REOPEN;
	    } elseif ($code < 200){
    		// auth phase
		    return StateMachineTransitionActions::ACTION_REOPEN;
	    } elseif ($code < 300){
    		// success
		    return StateMachineTransitionActions::ACTION_PAID;
	    } elseif ($code < 400){
    		//cancel
		    if ($code == 309){
			    return StateMachineTransitionActions::ACTION_CANCEL;
		    }
    		// error
		    return StateMachineTransitionActions::ACTION_FAIL;
	    } elseif ($code < 500){
    		// refund
		    return StateMachineTransitionActions::ACTION_REFUND;
	    } elseif ($code < 600 ) {
		    // subscription expired
	    } elseif ($code < 700){
		    // notification from bank
	    } elseif ($code < 800){
    		// waiting for capture
	    } elseif ($code < 900){
    		// subscription on hold
	    }

        return null;
    }

    /**
     * @param string $transactionId
     * @param Context $context
     * @return OrderTransactionEntity
     * @throws InconsistentCriteriaIdsException
     */
    public function getTransaction(string $transactionId, Context $context): OrderTransactionEntity
    {
        $criteria = new Criteria([$transactionId]);
        /** @var OrderTransactionEntity $transaction */
        return $this->transactionRepository->search($criteria, $context)
            ->get($transactionId);
    }

    /**
     * @param string $actionName
     * @param string $orderTransactionId
     * @param Context $context
     * @return bool
     * @throws InconsistentCriteriaIdsException
     */
    public function isSameStateId(string $actionName, string $orderTransactionId, Context $context): bool
    {
        $transaction = $this->getTransaction($orderTransactionId, $context);
        $currentStateId = $transaction->getStateId();


        $actionStatusTransition = $this->getTransitionFromActionName($actionName, $context);
        $actionStatusTransitionId = $actionStatusTransition->getId();

        return $currentStateId === $actionStatusTransitionId;
    }

	/**
	 * @param string $actionName
	 * @param string $orderTransactionId
	 * @param Context $context
	 * @return bool
	 * @throws InconsistentCriteriaIdsException
	 */
	public function canChangeState(string $actionName, string $orderTransactionId, Context $context): bool
	{
		$transaction = $this->getTransaction($orderTransactionId, $context);
		$currentStateId = $transaction->getStateId();

		$paidActionId = $this->getTransitionFromActionName(StateMachineTransitionActions::ACTION_PAID, $context)->getId();
		$refundActionId = $this->getTransitionFromActionName(StateMachineTransitionActions::ACTION_REFUND, $context)->getId();
		$actionStatusTransitionId = $this->getTransitionFromActionName($actionName, $context)->getId();

		if ($currentStateId == $paidActionId && $actionStatusTransitionId == $refundActionId){
			return true;
		} elseif ( $currentStateId !== $paidActionId){
			return true;
		}
		return false;
	}

    /**
     * @param string $actionName
     * @param Context $context
     * @return StateMachineStateEntity
     * @throws InconsistentCriteriaIdsException
     */
    public function getTransitionFromActionName(string $actionName, Context $context): StateMachineStateEntity
    {
        $stateName = $this->getOrderTransactionStatesNameFromAction($actionName);
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $stateName));
        return $this->stateMachineRepository->search($criteria, $context)->first();
    }

    /**
     * @param string $actionName
     * @return string
     */
    public function getOrderTransactionStatesNameFromAction(string $actionName): string
    {
        switch ($actionName) {
            case StateMachineTransitionActions::ACTION_PAID:
                return OrderTransactionStates::STATE_PAID;
                break;
            case StateMachineTransitionActions::ACTION_CANCEL:
                return OrderTransactionStates::STATE_CANCELLED;
                break;
        }
        return OrderTransactionStates::STATE_OPEN;
    }

    /**
     * Convert from snake_case to CamelCase.
     *
     * @param string $string
     * @return string
     */
    private function convertToFunctionName(string $string): string
    {
        $string = str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
        return lcfirst($string);
    }

    /**
     * @param Context $context
     * @return array
     * @throws \Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException
     */
    public function getPluginMetadata(Context $context): array
    {
        return [
            'platform_name' => 'Shopware',
            'platform_version' => $this->shopwareVersion,
            'plugin_name' => 'cardgate/shopware6',
            'plugin_version' => $this->pluginService->getPluginByName('CuroCardGate', $context)->getVersion(),
        ];
    }

    /**
     * @param OrderLineItemEntity $item
     * @param $hasNetPrices
     * @return array
     */
    public function getShoppingCartItem(OrderLineItemEntity $item, $hasNetPrices): array
    {
    	$unitPrice = (int)round($this->getUnitPriceExclTax($item->getPrice(), $hasNetPrices) * 100,0);
    	$vat = $this->getTaxRate($item->getPrice());
        return [
        	'type' => $item->getType(),
        	'sku' => $this->getMerchantItemId($item),
            'name' => $item->getLabel(),
	        'qty' => $item->getQuantity(),
            'unit_price' => $unitPrice,
	        'vat' => $vat,
            'vat_included' => false,
	        'vat_amount' => round($unitPrice * $vat/100,0),
        ];
    }
}
