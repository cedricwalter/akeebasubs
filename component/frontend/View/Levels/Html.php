<?php
/**
 * @package   AkeebaSubs
 * @copyright Copyright (c)2010-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Subscriptions\Site\View\Levels;

defined('_JEXEC') or die;

use Akeeba\Subscriptions\Admin\Helper\Price;
use Akeeba\Subscriptions\Site\Model\Levels;

class Html extends \FOF30\View\DataView\Html
{
	/**
	 * List of subscription IDs of the current user
	 *
	 * @var  int[]
	 */
	public $subIDs = [];

	/**
	 * Should I include discounts in the displayed prices?
	 *
	 * @var  bool
	 */
	public $includeDiscount = false;

	/**
	 * Should I render prices of 0 as "FREE"?
	 *
	 * @var  bool
	 */
	public $renderAsFree = false;

	/**
	 * Exchange rate in use
	 *
	 * @var float
	 */
	public $exchangeRate = 1.00;

	/**
	 * Local currency code, e.g. EUR
	 *
	 * @var string
	 */
	public $localCurrency = '';

	/**
	 * Local currency symbol, e.g. €
	 *
	 * @var string
	 */
	public $localSymbol = '';

	/**
	 * Country used for foreign currency display
	 *
	 * @var string
	 */
	public $country = '';

	/**
	 * Should I display notices about
	 *
	 * @var bool
	 */
	public $showNotices = true;

	/**
	 * Cache of pricing information per subscription level, required to cut down on queries in the Strappy layout.
	 *
	 * @var  object[]
	 */
	protected $pricingInformationCache = [];

	public function applyViewConfiguration()
	{
		// Transfer the parameters from the helper to the View
		$params = Price::getPricingParameters();

		$this->subIDs          = Price::getSubIDs();
		$this->includeDiscount = $params->includeDiscount;
		$this->renderAsFree    = $params->renderAsFree;
		$this->country         = $params->country;
		$this->exchangeRate    = $params->exchangeRate;
		$this->localCurrency   = $params->localCurrency;
		$this->localSymbol     = $params->localSymbol;
	}

	/**
	 * Executes before rendering the page for the Browse task.
	 */
	protected function onBeforeBrowse()
	{
		$this->applyViewConfiguration();

		parent::onBeforeBrowse();
	}

	/**
	 * Returns the pricing information for a subscription level. Used by the view templates to avoid code duplication.
	 *
	 * @param   \Akeeba\Subscriptions\Site\Model\Levels  $level  The subscription level
	 *
	 * @return  object
	 */
	public function getLevelPriceInformation(Levels $level)
	{
		return Price::getLevelPriceInformation($level);
	}

	/**
	 * Format the price with the currency symbol
	 *
	 * @param   float  $rawPrice  The raw price
	 *
	 * @return  string
	 */
	public function toLocalCurrency($rawPrice)
	{
		return Price::toLocalCurrency($rawPrice);
	}
}
