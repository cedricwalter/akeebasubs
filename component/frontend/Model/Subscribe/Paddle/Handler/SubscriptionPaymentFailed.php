<?php
/**
 * @package   AkeebaSubs
 * @copyright Copyright (c)2010-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Subscriptions\Site\Model\Subscribe\Paddle\Handler;


use Akeeba\Subscriptions\Admin\Helper\Message;
use Akeeba\Subscriptions\Admin\Helper\UserLogin;
use Akeeba\Subscriptions\Site\Model\Subscribe\HandlerTraits\StackCallback;
use Akeeba\Subscriptions\Site\Model\Subscribe\SubscriptionCallbackHandlerInterface;
use Akeeba\Subscriptions\Site\Model\Subscriptions;
use FOF30\Container\Container;
use FOF30\Date\Date;
use FOF30\View\Exception\AccessForbidden;
use Joomla\CMS\HTML\HTMLHelper;

/**
 * Handle a subscription's recurring payment failure
 *
 * @see         https://paddle.com/docs/subscriptions-event-reference/#subscription_payment_failed
 *
 * @since       7.0.0
 */
class SubscriptionPaymentFailed implements SubscriptionCallbackHandlerInterface
{
	use StackCallback;

	/**
	 * The component's container
	 *
	 * @var   Container
	 * @since 7.0.0
	 */
	private $container;

	/**
	 * Constructor
	 *
	 * @param   Container  $container  The component container
	 *
	 * @since  7.0.0
	 */
	public function __construct(Container $container)
	{
		$this->container = $container;
	}
	/**
	 * Handle a webhook callback from the payment service provider about a specific subscription
	 *
	 * @param   Subscriptions  $subscription  The subscription the webhook refers to
	 * @param   array          $requestData   The request data minus component, option, view, task
	 *
	 * @return  string|null  Text to include in the callback response page
	 *
	 * @throws  \RuntimeException  In case an error occurs. The exception code will be used as the HTTP status.
	 *
	 * @since  7.0.0
	 *
	 * @throws \Exception
	 */
	public function handleCallback(Subscriptions $subscription, array $requestData): ?string
	{
		if ($requestData['status'] != 'past_due')
		{
			throw new \RuntimeException('Invalid or no subscription status', 403);
		}

		// Stack the callback data to the subscription
		$updates = $this->getStackCallbackUpdate($subscription, $requestData);

		// Store the subscription update and cancel URLs
		$updates['update_url'] = $requestData['update_url'];
		$updates['cancel_url'] = $requestData['cancel_url'];

		// Do not send emails about automatically recurring subscriptions
		$updates['contact_flag'] = 3;

		$jDate      = new Date($requestData['next_retry_date']);
		$plusOneDay = new \DateInterval('P1D');
		$jDate->add($plusOneDay);

		// Yup, that's a STRING, not a boolean. We get a STRING from Paddle.
		$hardFailure = $requestData['hard_failure'] === 'true';
		$setPending  = $this->container->params->get('on_past_due_pending', 1);

		if ($hardFailure)
		{
			$updates['publish_down'] = gmdate('Y-m-d H:i:s');
			$updates['state'] = 'X';
			$updates['cancellation_reason'] = 'past_due';
		}
		elseif($setPending)
		{
			$updates['state'] = 'P';
			$updates['cancellation_reason'] = 'past_due';
			$subscription->_noemail(true);
		}
		else
		{
			$updates['publish_down'] = $jDate->format('Y-m-d H:i:s');
			$subscription->_noemail(true);
		}

		$subscription->save($updates);
		$subscription->_noemail(false);

		return null;
	}
}