<?php declare(strict_types=1);
/**
 * Copyright (c) 2021 CardGate B.V.
 * All rights reserved.
 * See LICENSE for license details.
 */

namespace CardGate\Shopware\Helper;

use CardGate\Shopware\Service\SettingsService;
use \cardgate\api\Client;

class ApiHelper
{

    /** @var SettingsService $settingsService */
    private $settingsService;

	/**
	 * @var \cardgate\api\Client
	 */
	private $_oClient;

	/**
	 * @var int
	 */
	private $_iSiteId;

	/**
	 * ApiHelper constructor.
	 *
	 * @param SettingsService $settingsService
	 * @param CgtClient $cgtClient
	 * @param string $shopwareVersion
	 */
    public function __construct(SettingsService $settingsService, string $shopwareVersion)
    {
        $this->settingsService = $settingsService;
	    $this->shopwareVersion = $shopwareVersion;
    }

	/**
	 * @param string|null $salesChannelId
	 *
	 * @return Client
	 * @throws \cardgate\api\Exception
	 */
    public function initializeCardGateClient(?string $salesChannelId): Client
    {
    	$iMerchantId = (int)$this->settingsService->getSetting('merchantId', $salesChannelId);
    	$sApiKey = $this->settingsService->getSetting('apiKey',$salesChannelId);
    	$bTestMode = ($this->settingsService->getSetting('environment',$salesChannelId) == 'live' ? false : true);
    	$oCardGate = new Client($iMerchantId,$sApiKey,$bTestMode);
	    $oCardGate->setIp( $this->_determineIp());
	    $oCardGate->setLanguage( 'nl' );
	    $this->_oClient = $oCardGate;
    	$this->_iSiteId = $this->settingsService->getSetting('siteId',$salesChannelId);
    	return $this->_oClient;
    }

	/**
	 * @return int
	 */
    public function getSiteId(): int{
    	return $this->_iSiteId;
    }

    /**
	 * Get the ip address of the client.
	 * @return string
	 */
	private static function _determineIp() {
		$sIp = '0.0.0.0';
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$sIp = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$sIp = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) && $_SERVER['REMOTE_ADDR'] !='::1' ) {
			$sIp = $_SERVER['REMOTE_ADDR'];
		}
		foreach( preg_split( "/\s?[,;\|]\s?/i", $sIp ) as $sIp ) {
			if ( filter_var( $sIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return $sIp;
			}
		}
		return $sIp;
	}
}
