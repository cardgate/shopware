<?php declare(strict_types=1);
/**
 * Copyright (c) 2021 CardGate B.V.
 * All rights reserved.
 * See LICENSE for license details.
 */

namespace CardGate\Shopware\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class SettingsService
{
    /**
     * @var SystemConfigService
     */
    public $systemConfigService;

    /**
     * SettingsService constructor.
     * @param SystemConfigService $systemConfigService
     */
    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * @param string $setting
     * @param string|null $salesChannelId
     * @return mixed|null
     */
    public function getSetting(string $setting, ?string $salesChannelId = null)
    {
        return $this->systemConfigService->get('CuroCardGate.config.' . $setting, $salesChannelId);
    }
}
