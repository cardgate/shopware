<?php
/**
 * Copyright (c) 2021 CardGate B.V.
 * All rights reserved.
 * See LICENSE for license details.
 */

namespace CardGate\Shopware\Storefront\Controller;

use CardGate\Shopware\Helper\ApiHelper;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ApiController extends AbstractController
{
	/** @var ApiHelper */
	private $apiHelper;

	/**
	 * ApiController constructor.
	 * @param ApiHelper $apiHelper
	 */
	public function __construct(ApiHelper $apiHelper)
	{
		$this->apiHelper = $apiHelper;
	}
}
