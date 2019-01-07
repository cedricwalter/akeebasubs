<?php
/**
 * @package   AkeebaSubs
 * @copyright Copyright (c)2010-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

use Akeeba\Subscriptions\Admin\Model\Subscriptions;
use FOF30\Container\Container;

class plgAkeebasubsNeedslogout extends JPlugin
{
	/**
	 * Called whenever a subscription is modified. Namely, when its enabled status,
	 * payment status or valid from/to dates are changed.
	 *
	 * @param   Subscriptions $row  The subscriptions row
	 * @param   array         $info The row modification information
	 *
	 * @return  void
	 */
	public function onAKSubscriptionChange(Subscriptions $row, array $info)
	{
		if ($info['status'] != 'modified')
		{
			return;
		}

		$modified = (array)$info['modified'];

		if (!isset($modified['enabled']))
		{
			return;
		}

		$user = $row->user;

		// This happens if the Joomla! user record was removed manually
		if (!is_object($user))
		{
			return;
		}

		$updates = ['needs_logout' => 1];
		$user->save($updates);
	}
}
