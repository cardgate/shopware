<?php declare(strict_types=1);
/**
 * Copyright (c) 2021 CardGate B.V.
 * See DISCLAIMER.md for disclaimer details.
 */

namespace CardGate\Shopware\Events;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class SalesChannelContextSwitchEvent implements EventSubscriberInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    public $customerRepository;
    public $paymentMethodRepository;

    /**
     * SalesChannelContextSwitchEvent constructor.
     * @param EntityRepositoryInterface $customerRepository
     * @param EntityRepositoryInterface $paymentMethodRepository
     */
    public function __construct(
        EntityRepositoryInterface $customerRepository,
        EntityRepositoryInterface $paymentMethodRepository
    ) {
        $this->customerRepository = $customerRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    /**
     * @return array|string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            \Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent::class =>
                'salesChannelContextSwitchedEvent'
        ];
    }

    /**
     * @param \Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent $event
     */
    public function salesChannelContextSwitchedEvent(
        \Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent $event
    ): void {
        $databag = $event->getRequestDataBag();
        $paymentMethodId = $databag->get('paymentMethodId');
        $customer = $event->getSalesChannelContext()->getCustomer();

	    $issuer = $databag->get('issuer');
	    if ($issuer) {
		    $this->customerRepository->upsert(
			    [[
				    'id' => $customer->getId(),
				    'customFields' => ['last_used_issuer' => $issuer]
			    ]],
			    $event->getContext()
		    );
	    }

        if ($customer === null || $paymentMethodId === null) {
            return;
        }

        if ($customer->getGuest()) {
	        return;
        }
    }
}
