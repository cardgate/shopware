<?php declare(strict_types=1);
/**
 * Copyright (c) 2021 CardGate B.V.
 * All rights reserved.
 * See LICENSE for license details.
 */
namespace CardGate\Shopware\Installers;

use CardGate\Shopware\Handlers\GenericPaymentHandler;
use CardGate\Shopware\Helper\GatewayHelper;
use CardGate\Shopware\CuroCardGate;
use CardGate\Shopware\PaymentMethods\CardGate;
use CardGate\Shopware\PaymentMethods\PaymentMethodInterface;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Context;

class PaymentMethodsInstaller implements InstallerInterface
{
    public const IS_CARDGATE = 'is_cardgate';
    public const TEMPLATE = 'template';

    /** @var PluginIdProvider */
    public $pluginIdProvider;
    /** @var EntityRepository */
    public $paymentMethodRepository;
    /** @var EntityRepository */
    public $mediaRepository;

    /**
     * PaymentMethodsInstaller constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->pluginIdProvider = $container->get(PluginIdProvider::class);
        $this->paymentMethodRepository = $container->get('payment_method.repository');
        $this->mediaRepository = $container->get('media.repository');
    }

    /**
     * @param InstallContext $context
     */
    public function install(InstallContext $context): void
    {
        $this->updateCardGatePaymentMethod($context->getContext());

        foreach (GatewayHelper::GATEWAYS as $gateway) {
            $this->addPaymentMethod(new $gateway(), $context->getContext(), false);
        }
    }

    /**
     * @param UpdateContext $context
     */
    public function update(UpdateContext $context): void
    {
        $this->updateCardGatePaymentMethod($context->getContext());

        foreach (GatewayHelper::GATEWAYS as $gateway) {
            $this->addPaymentMethod(new $gateway(), $context->getContext(), $context->getPlugin()->isActive());
        }
    }

    /**
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context): void
    {
        foreach (GatewayHelper::GATEWAYS as $gateway) {
            $this->setPaymentMethodActive(false, new $gateway(), $context->getContext());
        }
    }

    /**
     * @param ActivateContext $context
     */
    public function activate(ActivateContext $context): void
    {
        foreach (GatewayHelper::GATEWAYS as $gateway) {
            $this->setPaymentMethodActive(true, new $gateway(), $context->getContext());
        }
    }

    /**
     * @param DeactivateContext $context
     */
    public function deactivate(DeactivateContext $context): void
    {
        foreach (GatewayHelper::GATEWAYS as $gateway) {
            $this->setPaymentMethodActive(false, new $gateway(), $context->getContext());
        }
    }

    /**
     * @param PaymentMethodInterface $paymentMethod
     * @param Context $context
     * @param bool $isActive
     */
    public function addPaymentMethod(PaymentMethodInterface $paymentMethod, Context $context, bool $isActive): void
    {
        $paymentMethodId = $this->getPaymentMethodId($paymentMethod, $context);

        $pluginId = $this->pluginIdProvider->getPluginIdByBaseClass(CuroCardGate::class, $context);

        $mediaId = $this->getMediaId($paymentMethod, $context);

        if ($paymentMethodId !== null && $paymentMethod->getPaymentHandler() === GenericPaymentHandler::class) {
            return;
        }

        $paymentData = [
            'id' => $paymentMethodId,
            'handlerIdentifier' => $paymentMethod->getPaymentHandler(),
            'name' => $paymentMethod->getName(),
            'description' => $paymentMethod->getDescription(),
            'pluginId' => $pluginId,
            'mediaId' => $mediaId,
            'afterOrderEnabled' => true,
            'translations' => $paymentMethod->getTranslations(),
            'customFields' => [
                self::IS_CARDGATE => true,
                self::TEMPLATE => $paymentMethod->getTemplate()
            ]
        ];


        if ($isActive && $paymentMethodId === null) {
            $paymentData['active'] = true;
        }

        $this->paymentMethodRepository->upsert([$paymentData], $context);
    }

    /**
     * @param PaymentMethodInterface $paymentMethod
     * @param Context $context
     * @return string|null
     */
    public function getPaymentMethodId(PaymentMethodInterface $paymentMethod, Context $context): ?string
    {
        $paymentCriteria = (new Criteria())->addFilter(
            new EqualsFilter(
                'handlerIdentifier',
                $paymentMethod->getPaymentHandler()
            )
        );

        $paymentIds = $this->paymentMethodRepository->searchIds(
            $paymentCriteria,
            $context
        );

        if ($paymentIds->getTotal() === 0) {
            return null;
        }

        return $paymentIds->getIds()[0];
    }

    /**
     * @param bool $active
     * @param PaymentMethodInterface $paymentMethod
     * @param Context $context
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    public function setPaymentMethodActive(bool $active, PaymentMethodInterface $paymentMethod, Context $context): void
    {
        $paymentMethodId = $this->getPaymentMethodId($paymentMethod, $context);

        if (!$paymentMethodId) {
            return;
        }

        $paymentData = [
            'id' => $paymentMethodId,
            'active' => $active,
        ];

        $this->paymentMethodRepository->upsert([$paymentData], $context);
    }

    /**
     * @param PaymentMethodInterface $paymentMethod
     * @param Context $context
     * @return string|null
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    private function getMediaId(PaymentMethodInterface $paymentMethod, Context $context): ?string
    {
        $criteria = (new Criteria())->addFilter(
            new EqualsFilter(
                'fileName',
                $this->getMediaName($paymentMethod)
            )
        );

        /** @var MediaEntity $media */
        $media = $this->mediaRepository->search($criteria, $context)->first();

        if (!$media) {
            return null;
        }

        return $media->getId();
    }

    /**
     * @param Context $context
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    private function updateCardGatePaymentMethod(Context $context): void
    {
        $paymentCriteria = (new Criteria())->addFilter(
            new EqualsFilter(
                'handlerIdentifier',
                CardGate::class
            )
        );

        $paymentIds = $this->paymentMethodRepository->searchIds(
            $paymentCriteria,
            $context
        );

        if ($paymentIds->getTotal() === 0) {
            return;
        }

        $paymentData = [
            'id' => $paymentIds->getIds()[0],
            'handlerIdentifier' => (new CardGate())->getPaymentHandler(),
        ];

        $this->paymentMethodRepository->upsert([$paymentData], $context);
    }

    /**
     * @param PaymentMethodInterface $paymentMethod
     * @return string
     */
    private function getMediaName(PaymentMethodInterface $paymentMethod): string
    {
        return 'cgt_' . $paymentMethod->getName();
    }
}
