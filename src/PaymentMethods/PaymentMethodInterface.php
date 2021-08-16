<?php declare(strict_types=1);
/**
 * Copyright (c) 2021 CardGate B.V.
 * See DISCLAIMER.md for disclaimer details.
 */

namespace CardGate\Shopware\PaymentMethods;

interface PaymentMethodInterface
{
    /**
     * Return name of the payment method.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Return the description of the payment method.
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Return the payment handler of a plugin.
     *
     * @return string
     */
    public function getPaymentHandler(): string;

    /**
     * Give the location of a twig file to load with the payment method.
     *
     * @return string|null
     */
    public function getTemplate(): ?string;

    /**
     * Give the location of the payment logo.
     *
     * @return string
     */
    public function getMedia(): string;

    /**
     * Get the different translations of a payment method
     *
     * @return array
     */
    public function getTranslations(): array;

    /**
     * Get the type that is currently being used.
     *
     * @return string
     */
    public function getType(): string;
}
