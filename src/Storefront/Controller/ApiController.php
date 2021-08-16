<?php


namespace CardGate\Shopware\Storefront\Controller;

use CardGate\Shopware\Helper\ApiHelper;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @RouteScope(scopes={"api"})
 */
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
