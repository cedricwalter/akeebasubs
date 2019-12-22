<?php
/**
 * @package   AkeebaSubs
 * @copyright Copyright (c)2010-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

use Akeeba\Subscriptions\Admin\Helper\Email;
use Akeeba\Subscriptions\Admin\Model\Subscriptions;

require_once __DIR__ . '/../subscriptionemails/subscriptionemails.php';

class plgAkeebasubsAdminemails extends plgAkeebasubsSubscriptionemails
{
	protected $emails = [];

	/**
	 * Public constructor. Overridden to load the language strings.
	 */
	public function __construct(&$subject, $config = [])
	{
		if (!is_object($config['params']))
		{
			$config['params'] = new JRegistry($config['params']);
		}

		parent::__construct($subject, $config);

		$emailsString = trim($this->params->get('emails', ''));

		if (empty($emailsString))
		{
			$this->emails = [];
		}
		else
		{
			$this->emails = explode(',', $emailsString);
		}
	}

	/**
	 * Called whenever a subscription is modified. Namely, when its enabled status,
	 * payment status or valid from/to dates are changed.
	 *
	 * @param   Subscriptions  $row   The subscriptions row
	 * @param   array          $info  The row modification information
	 *
	 * @return  void
	 */
	public function onAKSubscriptionChange(Subscriptions $row, array $info)
	{
		// No point running if there are no emails defined, right?
		if (empty($this->emails))
		{
			return;
		}

		parent::onAKSubscriptionChange($row, $info);
	}

	/**
	 * Notifies the component of the supported email keys by this plugin.
	 *
	 * @return  array
	 *
	 * @since 3.0
	 */
	public function onAKGetEmailKeys()
	{
		$this->loadLanguage();

		return [
			'section' => $this->_name,
			'title'   => JText::_('PLG_AKEEBASUBS_ADMINEMAILS_EMAILSECTION'),
			'keys'    => [
				'paid'                 => JText::_('PLG_AKEEBASUBS_ADMINEMAILS_EMAIL_PAID'),
				'new_active'           => JText::_('PLG_AKEEBASUBS_ADMINEMAILS_EMAIL_NEW_ACTIVE'),
				'new_renewal'          => JText::_('PLG_AKEEBASUBS_ADMINEMAILS_EMAIL_NEW_RENEWAL'),
				'new_pending'          => JText::_('PLG_AKEEBASUBS_ADMINEMAILS_EMAIL_NEW_PENDING'),
				'cancelled_new'        => JText::_('PLG_AKEEBASUBS_ADMINEMAILS_EMAIL_CANCELLED_NEW'),
				'cancelled_existing'   => JText::_('PLG_AKEEBASUBS_ADMINEMAILS_EMAIL_CANCELLED_EXISTING'),
				'expired'              => JText::_('PLG_AKEEBASUBS_ADMINEMAILS_EMAIL_EXPIRED'),
				'published'            => JText::_('PLG_AKEEBASUBS_ADMINEMAILS_EMAIL_PUBLISHED'),
				'generic'              => JText::_('PLG_AKEEBASUBS_ADMINEMAILS_EMAIL_GENERIC'),
				'problem_transactions' => JText::_('PLG_AKEEBASUBS_ADMINEMAILS_EMAIL_PROBLEM_TRANSACTIONS'),
			],
		];
	}

	/**
	 * Sends out the email to the administrator(s)
	 *
	 * @param   Subscriptions  $row   The subscription row object
	 * @param   string         $type  The type of the email to send (generic, new, ...)
	 * @param   array          $info  Subscription modification information (used in children classes)
	 *
	 * @return bool
	 */
	protected function sendEmail($row, $type = '', array $info = [])
	{
		// Get a preloaded mailer
		$key    = 'plg_akeebasubs_' . $this->_name . '_' . $type;
		$mailer = Email::getPreloadedMailer($row, $key);

		if (is_null($mailer))
		{
			return false;
		}

		$mailer->addRecipient($this->emails);
		$result = $mailer->Send();
		$mailer = null;

		return $result;
	}
}
