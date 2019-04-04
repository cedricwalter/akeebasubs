<?php
/**
 * @package   AkeebaSubs
 * @copyright Copyright (c)2010-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Subscriptions\Site\Model;

use Akeeba\Subscriptions\Admin\PluginAbstracts\AkpaymentBase;
use Akeeba\Subscriptions\Site\Model\Subscribe\StateData;
use Akeeba\Subscriptions\Site\Model\Subscribe\Validation;
use Akeeba\Subscriptions\Site\Model\Subscribe\ValidatorFactory;
use FOF30\Container\Container;
use FOF30\Model\Model;
use FOF30\Utils\Ip;
use JBrowser;
use JFactory;
use JLoader;
use JLog;
use JUser;

defined('_JEXEC') or die;

/**
 * This model handles validation and subscription creation
 *
 * @method $this slug() slug(string $v)
 * @method $this id() id(int $v)
 * @method $this paymentmethod() paymentmethod(string $v)
 * @method $this username() username(string $v)
 * @method $this password() password(string $v)
 * @method $this password2() password2(string $v)
 * @method $this name() name(string $v)
 * @method $this email() email(string $v)
 * @method $this email2() email2(string $v)
 * @method $this country() country(string $v)
 * @method $this coupon() coupon(string $v)
 */
class Subscribe extends Model
{
	/**
	 * Raw HTML source of the payment form, as returned by the payment plugin
	 *
	 * @var string
	 */
	private $paymentForm = '';

	/**
	 * @var  ValidatorFactory  The validator object factory
	 */
	protected $validatorFactory = null;

	/**
	 * Public constructor. Initialises the internal objects used for validation and subscription creation.
	 *
	 * @param   Container  $container
	 * @param   array      $config
	 */
	public function __construct(Container $container, array $config = array())
	{
		parent::__construct($container, $config);

		$forceReload = $this->getContainer()->platform->getSessionVar('firstrun', true, 'com_akeebasubs');

		$this->validatorFactory = new ValidatorFactory($this->container, $this->getStateVariables($forceReload), $this->container->platform->getUser());
	}

	/**
	 * Gets the state variables from the form submission or validation request
	 *
	 * @param    bool  $force  Should I force-reload the data?
	 *
	 * @return   StateData
	 */
	public function &getStateVariables($force = false)
	{
		static $stateVars = null;

		if (is_null($stateVars) || $force)
		{
			$stateVars = new StateData($this);
		}

		return $stateVars;
	}

	/**
	 * Gets a validator object by type. If you request the same object type again the same object will be returned.
	 *
	 * @param   string  $type  The validator type
	 *
	 * @return  Validation\Base
	 *
	 * @throws  \InvalidArgumentException  If the validator type is not found
	 */
	public function getValidator($type)
	{
		return $this->validatorFactory->getValidator($type);
	}

	/**
	 * Performs a validation
	 */
	public function getValidation($force = false)
	{
		$response = new \stdClass();

		$state = $this->getStateVariables($force);

		if ($force)
		{
			$this->validatorFactory->setStateData($state);
		}

		switch ($state->opt)
		{
			case 'username':
				$response->validation = (object)[
					'username' => $this->getValidator('username')->execute(),
					'password' => $this->getValidator('password')->execute()
				];
				break;

			default:
				$response->validation = (object)$this->getValidator('PersonalInformation')->execute();
				$response->validation->username = $this->getValidator('username')->execute();
				$response->validation->password = $this->getValidator('password')->execute();
				$response->validation->paymentmethod = $this->getValidator('PaymentMethod')->execute();
				$response->price = (object)$this->getValidator('Price')->execute();

				break;
		}

		return $response;
	}

	/**
	 * Checks that the current state passes the validation
	 *
	 * @return bool
	 */
	public function isValid()
	{
		// Step #1. Check the validity of the user supplied information
		// ----------------------------------------------------------------------
		$validation = $this->getValidation();
		$state = $this->getStateVariables();

		// Iterate the core validation rules
		$isValid = true;

		foreach ($validation->validation as $key => $validData)
		{
			// Skip over debug data dump
			if ($key == 'rawDataForDebug')
			{
				continue;
			}

			// A wrong coupon code is not a fatal error, unless we require a coupon code
			if ($key == 'coupon')
			{
				continue;
			}

			$isValid = $isValid && $validData;

			if (!$isValid)
			{
				if ($key == 'username')
				{
					$user = $this->container->platform->getUser();

					// Not a logged in user? Empty username is of course an error!
					if ($user->guest)
					{
						break;
					}

					// Username for the validation state matches current user = valid username
					if (!empty($state->username) && ($user->username == $state->username))
					{
						$isValid = true;

						continue;
					}

					// Username in the state empty, but user is logged in. Accept this as-is.
					if (empty($state->username))
					{
						$isValid = true;

						continue;
					}
				}

				break;
			}
		}

		return $isValid;
	}

	/**
	 * Updates the user info based on the state data
	 *
	 * @param   bool    $allowNewUser  When true, we can create a new user. False, only update an existing user's data.
	 * @param   Levels  $level         The subscription level object
	 *
	 * @return  bool  True on success
	 */
	public function updateUserInfo($allowNewUser = true, $level = null)
	{
		$state = $this->getStateVariables();
		$user = $this->container->platform->getUser();
		$user = $this->getState('user', $user);

		if (($user->id == 0) && !$allowNewUser)
		{
			// New user creation is not allowed. Sorry.
			return false;
		}

		if ($user->id == 0)
		{
			// Check for an existing, blocked, unactivated user with the same
			// username or email address.
			/** @var JoomlaUsers $joomlaUsers */
			$joomlaUsers = $this->container->factory->model('JoomlaUsers')->tmpInstance();

			/** @var JoomlaUsers $user1 */
			$user1 = $joomlaUsers->getClone()->reset(true, true)
				->clearState()
				->username($state->username)
				->block(1)
				->firstOrNew();

			/** @var JoomlaUsers $user2 */
			$user2 = $joomlaUsers->getClone()->reset(true, true)
				->clearState()
				->email($state->email)
				->block(1)
				->firstOrNew();

			$id1 = $user1->id;
			$id2 = $user2->id;

			// Do we have a match?
			if ($id1 || $id2)
			{
				if ($id1 == $id2)
				{
					// Username and email match with the blocked user; reuse that
					// user, please.
					$user = $this->container->platform->getUser($user1->id);
				}
				elseif ($id1 && $id2)
				{
					// We have both the same username and same email, but in two
					// different users. In order to avoid confusion we will remove
					// user 2 and change user 1's email into the email address provided

					// Remove the last subscription for $user2 (it will be an unpaid one)
					/** @var Subscriptions $subscriptionsModel */
					$subscriptionsModel = $this->container->factory->model('Subscriptions')->tmpInstance();

					$substodelete = $subscriptionsModel
						->user_id($id2)
						->get(true);

					if ($substodelete->count())
					{
						/** @var Subscriptions $subtodelete */
						foreach ($substodelete as $subtodelete)
						{
							$substodelete->delete($subtodelete->akeebasubs_subscription_id);
						}
					}

					// Remove $user2 and set $user to $user1 so that it gets updated
					$jUser2 = $this->container->platform->getUser($user2->id);
					$error = '';

					try
					{
						$jUser2->delete();
					}
					catch (\Exception $e)
					{
						$error = $e->getMessage();
					}

					// If deleting through JUser failed, try a direct deletion (may leave junk behind, e.g. in user-usergroup map table)
					if ($jUser2->getErrors() || $error)
					{
						$user2->delete($id2);
					}

					$user = $this->container->platform->getUser($user1->id);
					$user->email = $state->email;
					$user->save(true);
				}
				elseif (!$id1 && $id2)
				{
					// We have a user with the same email, but the wrong username.
					// Use this user (the username is updated later on)
					$user = $this->container->platform->getUser($user2->id);
				}
				elseif ($id1 && !$id2)
				{
					// We have a user with the same username, but the wrong email.
					// Use this user (the email is updated later on)
					$user = $this->container->platform->getUser($user1->id);
				}
			}
		}

		if (is_null($user->id) || ($user->id == 0))
		{
			// CREATE A NEW USER
			$params = array(
				'name'      => $state->name,
				'username'  => $state->username,
				'email'     => $state->email,
				'password'  => $state->password,
				'password2' => $state->password2
			);

			// We have to use JUser directly instead of JFactory getUser
			$user = new JUser(0);

			JLoader::import('joomla.application.component.helper');
			$usersConfig = \JComponentHelper::getParams('com_users');
			$newUsertype = $usersConfig->get('new_usertype');

			// get the New User Group from com_users' settings
			if (empty($newUsertype))
			{
				$newUsertype = 2;
			}

			$params['groups'] = array($newUsertype);

			$params['sendEmail'] = 0;

			// Set the user's default language to whatever the site's current language is
			$params['params'] = array(
				'language' => $this->container->platform->getConfig()->get('language')
			);

			// We always block the user, so that only a successful payment or
			// clicking on the email link activates his account. This is to
			// prevent spam registrations when the subscription form is abused.
			JLoader::import('joomla.user.helper');
			JLoader::import('cms.application.helper');
			$params['block'] = 1;

			$randomString = \JUserHelper::genRandomPassword();
			$hash = \JApplicationHelper::getHash($randomString);
			$params['activation'] = $hash;

			$user->bind($params);
			$userIsSaved = $user->save();
		}
		else
		{
			// UPDATE EXISTING USER

			// Update existing user's details
			/** @var JoomlaUsers $userRecord */
			$userRecord = $this->container->factory->model('JoomlaUsers')->tmpInstance();
			$userRecord->find($user->id);

			$updates = array(
				'name'  => $state->name,
				'email' => $state->email
			);

			if (!empty($state->password) && ($state->password == $state->password2))
			{
				JLoader::import('joomla.user.helper');
				$salt = \JUserHelper::genRandomPassword(32);
				$pass = \JUserHelper::getCryptedPassword($state->password, $salt);
				$updates['password'] = $pass . ':' . $salt;
			}

			if (!empty($state->username))
			{
				$updates['username'] = $state->username;
			}

			$userIsSaved = $userRecord->save($updates);
		}

		// Send activation email for free subscriptions if confirmfree is enabled
		if ($user->block && ($level->price < 0.01))
		{
			$confirmfree = $this->container->params->get('confirmfree', 0);
			if ($confirmfree)
			{
				// Send the activation email
				if (!isset($params))
				{
					$params = array();
				}

				$this->sendActivationEmail($user, $params);
			}
		}

		if (!$userIsSaved)
		{
			$this->setState('user', null);

			return false;
		}

		$this->setState('user', $user);

		return true;
	}

	/**
	 * Processes the form data and creates a new subscription
	 */
	public function createNewSubscription()
	{
		// Fetch state and validation variables
		$this->setState('opt', '');
		$state = $this->getStateVariables();
		$validation = $this->getValidation();

		// Mark this subscription attempt in the session
		$this->container->platform->setSessionVar('apply_validation.' . $state->id, 1, 'com_akeebasubs');

		// Step #1.a. Check that the form is valid
		// ----------------------------------------------------------------------
		$isValid = $this->isValid();

		if (!$isValid)
		{
			$this->logSubscriptionCreationFailure('Validation failure');

			return false;
		}

		// Step #1.b. Check that the subscription level is allowed
		// ----------------------------------------------------------------------

		// Is this actually an allowed subscription level?
		/** @var Levels $levelsModel */
		$levelsModel = $this->container->factory->model('Levels')->tmpInstance();

		$allowedLevels = $levelsModel
			->only_once(1)
			->enabled(1)
			->get(true);

		$allowed = false;

		if ($allowedLevels->count())
		{
			/** @var Levels $l */
			foreach ($allowedLevels as $l)
			{
				if ($l->akeebasubs_level_id == $state->id)
				{
					$allowed = true;
					break;
				}
			}
		}

		if (!$allowed)
		{
			$this->logSubscriptionCreationFailure('Subscription level has the Only Once flag but the user has already a subscription in it.');

			return false;
		}

		// Fetch the level's object, used later on
		$level = $levelsModel->getClone()->find($state->id);

		// Step #2. Check that the payment plugin exists or return false
		// ----------------------------------------------------------------------
		/** @var PaymentMethods $paymentMethodsModel */
		$paymentMethodsModel = $this->container->factory->model('PaymentMethods')->tmpInstance();
		$plugins = $paymentMethodsModel->getPaymentPlugins();

		$found = false;

		if (!empty($plugins))
		{
			foreach ($plugins as $plugin)
			{
				if ($plugin->name == $state->paymentmethod)
				{
					$found = true;
					break;
				}
			}
		}

		if (!$found)
		{
			$this->logSubscriptionCreationFailure(sprintf('Cannot find payment method ‘%s’', htmlentities($state->paymentmethod)));

			return false;
		}

		// Reset the session flag, so that future registrations will not merge data stored in the database
		$this->container->platform->setSessionVar('firstrun', false, 'com_akeebasubs');

		// Step #2.b. Apply block rules
		// ----------------------------------------------------------------------
		/** @var BlockRules $blockRulesModel */
		$blockRulesModel = $this->container->factory->model('BlockRules')->tmpInstance();

		if ($blockRulesModel->isBlocked($state))
		{
			throw new \Exception(\JText::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
		}

		// Step #3. Create or update a user record
		// ----------------------------------------------------------------------
		$user = $this->container->platform->getUser();
		$this->setState('user', $user);
		$userIsSaved = $this->updateUserInfo(true, $level);

		if (!$userIsSaved)
		{
			$this->logSubscriptionCreationFailure(sprintf('Cannot update user information for user ID %d', $user->id));

			return false;
		}

		$user = $this->getState('user', $user);

		// Store the user's ID in the session
		$this->container->platform->setSessionVar('subscribes.user_id', $user->id, 'com_akeebasubs');

		// Step #4. Check for existing subscription records and calculate the subscription expiration date
		// ----------------------------------------------------------------------
		// @todo Refactor the entire step #4 into a validator class
		// Get subscriptions on the same level.
		/** @var Subscriptions $subscriptionsModel */
		$subscriptionsModel = $this->container->factory->model('Subscriptions')->tmpInstance();

		$subscriptions = $subscriptionsModel
			->user_id($user->id)
			->level($state->id)
			->paystate('C')
			->get(true);

		$now = time();
		$mNow = $this->container->platform->getDate()->toSql();
		$noContact = array();

		if (!$subscriptions->count())
		{
			$startDate = $now;
		}
		else
		{
			$startDate = $now;

			/** @var Subscriptions $row */
			foreach ($subscriptions as $row)
			{
				// Only take into account paid-for subscriptions. Note: you can't use $row->state, it returns the model state!
				if ($row->getFieldValue('state', null) != 'C')
				{
					continue;
				}

				// Calculate the expiration date
				$expiryDate = $this->container->platform->getDate($row->publish_down)->toUnix();

				// If the subscription expiration date is earlier than today, ignore it
				if ($expiryDate < $now)
				{
					continue;
				}

				// If the previous subscription's expiration date is later than the current start date,
				// update the start date to be one second after that.
				if ($expiryDate > $startDate)
				{
					$startDate = $expiryDate + 1;
				}

				/**
				 * Also mark the old subscription as "communicated". We don't want to spam our users with subscription
				 * renewal notices or expiration notification after they have effectively renewed!
				 *
				 * Note that we don't update the rows here! It would be premature. If the user abandons the payment we
				 * want to remind them of the expiring subscriptions. That's why the information for no-contact
				 * subscriptions is passed to the payment plugin which stores it as custom parameters to the
				 * subscription record. It will then apply the no-contact information when the payment is finalized, by
				 * the AkpaymentBase::fixSubscriptionDates() method.
				 */
				$noContact[] = $row->akeebasubs_subscription_id;
			}
		}

		$nullDate = $this->container->db->getNullDate();

		/** @var Levels $level */
		$level = $this->container->factory->model('Levels')->tmpInstance();
		$level->find($state->id);

		if ($level->forever)
		{
			$jStartDate = $this->container->platform->getDate();
			$endDate = '2038-01-01 00:00:00';
		}
		elseif (!is_null($level->fixed_date) && ($level->fixed_date != $nullDate))
		{
			$jStartDate = $this->container->platform->getDate();
			$endDate = $level->fixed_date;
		}
		else
		{
			$jStartDate = $this->container->platform->getDate($startDate);

			// Subscription duration (length) modifiers, via plugins
			$duration_modifier = 0;

			$this->container->platform->importPlugin('akeebasubs');
			$jResponse = $this->container->platform->runPlugins('onValidateSubscriptionLength', array($state));

			if (is_array($jResponse) && !empty($jResponse))
			{
				foreach ($jResponse as $pluginResponse)
				{
					if (empty($pluginResponse))
					{
						continue;
					}

					$duration_modifier += $pluginResponse;
				}
			}

			// Calculate the effective duration
			$duration = (int)$level->duration + $duration_modifier;

			if ($duration <= 0)
			{
				$duration = 0;
			}

			$duration = $duration * 3600 * 24;
			$endDate = $startDate + $duration;
		}

		$mStartDate = $jStartDate->toSql();
		$mEndDate = $this->container->platform->getDate($endDate)->toSql();

		// Step #5. Create a new subscription record
		// ----------------------------------------------------------------------
		// @todo Get the start ($mStartDate) and end ($mEndDate) dates and the $noContact array from the validator plugin which replaces step 4

		// Store the price validation's "oldsub" and "expiration" keys in
		// the subscriptions subcustom array
		$subcustom = [];

		if (empty($subcustom))
		{
			$subcustom = array();
		}
		elseif (is_object($subcustom))
		{
			$subcustom = (array)$subcustom;
		}

		$priceValidation = $this->getValidator('Price')->execute();

		$subcustom['fixdates'] = array(
			'oldsub'     => $priceValidation['oldsub'],
			'allsubs'    => $priceValidation['allsubs'],
			'expiration' => $priceValidation['expiration'],
			'nocontact'  => $noContact,
		);

		// Get the IP address
		$ip = Ip::getIp();

		// Get the country from the IP address if the Akeeba GeoIP Provider Plugin is installed and activated
		$ip_country = '(Unknown)';

		if (class_exists('AkeebaGeoipProvider'))
		{
			$geoip = new \AkeebaGeoipProvider();
			$ip_country = $geoip->getCountryName($ip);

			if (empty($ip_country))
			{
				$ip_country = '(Unknown)';
			}
		}

		// Get the User Agent string
		JLoader::import('joomla.environment.browser');
		$browser = new JBrowser();
		$ua      = $browser->getAgentString();
		$mobile  = $browser->isMobile();

		// Setup the new subscription
		$data = array(
			'akeebasubs_subscription_id' => null,
			'user_id'                    => $user->id,
			'akeebasubs_level_id'        => $state->id,
			'publish_up'                 => $mStartDate,
			'publish_down'               => $mEndDate,
			'notes'                      => '',
			'enabled'                    => ($validation->price->gross < 0.01) ? 1 : 0,
			'processor'                  => ($validation->price->gross < 0.01) ? 'none' : $state->paymentmethod,
			'processor_key'              => ($validation->price->gross < 0.01) ? $this->_uuid(true) : '',
			'state'                      => ($validation->price->gross < 0.01) ? 'C' : 'N',
			'net_amount'                 => $validation->price->net - $validation->price->discount,
			'tax_amount'                 => $validation->price->tax,
			'gross_amount'               => $validation->price->gross,
			'recurring_amount'           => $validation->price->recurring,
			'tax_percent'                => $validation->price->taxrate,
			'created_on'                 => $mNow,
			'params'                     => $subcustom,
			'ip'                         => $ip,
			'ip_country'                 => $ip_country,
			'akeebasubs_coupon_id'       => $validation->price->couponid,
			'akeebasubs_upgrade_id'      => $validation->price->upgradeid,
			'contact_flag'               => 0,
			'prediscount_amount'         => $validation->price->net,
			'discount_amount'            => $validation->price->discount,
			'first_contact'              => '0000-00-00 00:00:00',
			'second_contact'             => '0000-00-00 00:00:00',
			'ua'                         => $ua,
			'mobile'                     => $mobile ? 1 : 0,
			// Flags
			'_dontCheckPaymentID'        => true,
		);

		/** @var Subscriptions $subscription */
		$subscription = $this->container->factory->model('Subscriptions')->tmpInstance();
		$this->_item = $subscription->reset(true, true)->save($data);

		// Step #7. Hit the coupon code, if a coupon is indeed used
		// ----------------------------------------------------------------------
		if ($validation->price->couponid)
		{
			/** @var Coupons $couponsModel */
			$couponsModel = $this->container->factory->model('Coupons')->tmpInstance();
			$couponsModel->find($validation->price->couponid);
			$couponsModel->hits++;
			$couponsModel->save();
		}

		// Step #8. Clear the session
		// ----------------------------------------------------------------------
		$this->container->platform->setSessionVar('apply_validation.' . $state->id, null, 'com_akeebasubs');

		// Step #9. Call the specific plugin's onAKPaymentNew() method and get the redirection URL,
		//          or redirect immediately on auto-activated subscriptions
		// ----------------------------------------------------------------------
		if ($subscription->gross_amount != 0)
		{
			// Non-zero charges; use the plugins
			$jResponse = $this->container->platform->runPlugins('onAKPaymentNew', array(
				$state->paymentmethod,
				$user,
				$level,
				$subscription
			));

			if (empty($jResponse))
			{
				$this->logSubscriptionCreationFailure(sprintf('Calling onAKPaymentNew for ‘%s’ resulted in an empty response', htmlentities($state->paymentmethod)));

				return false;
			}

			foreach ($jResponse as $response)
			{
				if ($response === false)
				{
					continue;
				}

				$this->paymentForm = $response;
			}
		}
		else
		{
			// Zero charges. First apply subscription replacement
			$updates = array();
			AkpaymentBase::fixSubscriptionDates($subscription, $updates);

			if (!empty($updates))
			{
				$subscription->save($updates);
				$this->_item = $subscription;
			}

			// and then just redirect
			$this->container->platform->redirect(str_replace('&amp;', '&', \JRoute::_('index.php?option=com_akeebasubs&layout=default&view=message&slug=' . $level->slug . '&layout=order&subid=' . $subscription->akeebasubs_subscription_id)));

			$this->removeSubscriptionCreationFailureLog($level);

			return false;
		}

		// Return true
		// ----------------------------------------------------------------------
		$this->removeSubscriptionCreationFailureLog($level);

		return true;
	}

	public function runCancelRecurring()
	{
		$state = $this->getStateVariables();

		$data = $this->input->getData();

		// Some plugins result in an empty Itemid being added to the request
		// data, screwing up the payment callback validation in some cases (e.g.
		// PayPal).
		if (array_key_exists('Itemid', $data))
		{
			if (empty($data['Itemid']))
			{
				unset($data['Itemid']);
			}
		}

		/** @var PaymentMethods $paymentMethodsModel */
		$paymentMethodsModel = $this->container->factory->model('PaymentMethods')->tmpInstance();
		$paymentMethodsModel->getPaymentPlugins();

		$app = JFactory::getApplication();
		$jResponse = $app->triggerEvent('onAKPaymentCancelRecurring', array(
			$state->paymentmethod,
			$data
		));

		if (empty($jResponse))
		{
			return false;
		}

		$status = false;

		foreach ($jResponse as $response)
		{
			$status = $status || $response;
		}

		return $status;
	}

	/**
	 * Runs a payment callback
	 */
	public function runCallback()
	{
		// Debug log
		JLog::addLogger(['text_file' => "akeebasubs_payment.php"], JLog::ALL, ['akeebasubs.payment']);

		$data = $this->input->getData();

		// Some plugins result in an empty Itemid being added to the request
		// data, screwing up the payment callback validation in some cases (e.g.
		// PayPal).
		if (array_key_exists('Itemid', $data))
		{
			if (empty($data['Itemid']))
			{
				unset($data['Itemid']);
			}
		}

		/** @var PaymentMethods $paymentMethodsModel */
		$paymentMethodsModel = $this->container->factory->model('PaymentMethods')->tmpInstance();
		$paymentMethodsModel->getPaymentPlugins();

		$paymentMethod = $this->input->getCmd('paymentmethod', 'none');

		JLog::add("Got payment callback for “{$paymentMethod}”.", JLog::INFO, 'akeebasubs.payment');

		$jResponse     = $this->container->platform->runPlugins('onAKPaymentCallback', [
			$paymentMethod,
			$data,
		]);

		if (empty($jResponse))
		{
			JLog::add("No response from plugins: FAILED.", JLog::ERROR, 'akeebasubs.payment');

			return false;
		}

		JLog::add(sprintf("Raw response array: %s", json_encode($jResponse)), JLog::DEBUG, 'akeebasubs.payment');

		$status = false;

		foreach ($jResponse as $response)
		{
			$status = $status || $response;
		}

		JLog::add(sprintf("Final decision: %s", $status ? 'OK' : 'FAILED'), JLog::DEBUG, 'akeebasubs.payment');

		return $status;
	}

	/**
	 * Get the form set by the active payment plugin
	 */
	public function getPaymentForm()
	{
		return $this->paymentForm;
	}

	/**
	 * Returns the state data.
	 *
	 * @return  StateData
	 */
	public function getData()
	{
		return $this->getStateVariables();
	}

	/**
	 * Generates a Universally Unique IDentifier, version 4.
	 *
	 * This function generates a truly random UUID.
	 *
	 * @param   boolean  $hex  If TRUE return the uuid in hex format, otherwise as a string
	 *
	 * @return  string A UUID, made up of 36 characters or 16 hex digits.
	 *
	 * @see     http://tools.ietf.org/html/rfc4122#section-4.4
	 * @see     http://en.wikipedia.org/wiki/UUID
	 */
	protected function _uuid($hex = false)
	{
		$pr_bits = false;

		$fp = @fopen('/dev/urandom', 'rb');

		if ($fp !== false)
		{
			$pr_bits .= @fread($fp, 16);
			@fclose($fp);
		}
		else
		{
			// If /dev/urandom isn't available (eg: in non-unix systems), use mt_rand().
			$pr_bits = "";

			for ($cnt = 0; $cnt < 16; $cnt++)
			{
				$pr_bits .= chr(mt_rand(0, 255));
			}
		}

		$time_low = bin2hex(substr($pr_bits, 0, 4));
		$time_mid = bin2hex(substr($pr_bits, 4, 2));
		$time_hi_and_version = bin2hex(substr($pr_bits, 6, 2));
		$clock_seq_hi_and_reserved = bin2hex(substr($pr_bits, 8, 2));
		$node = bin2hex(substr($pr_bits, 10, 6));

		/**
		 * Set the four most significant bits (bits 12 through 15) of the
		 * time_hi_and_version field to the 4-bit version number from
		 * Section 4.1.3.
		 *
		 * @see http://tools.ietf.org/html/rfc4122#section-4.1.3
		 */
		$time_hi_and_version = hexdec($time_hi_and_version);
		$time_hi_and_version = $time_hi_and_version >> 4;
		$time_hi_and_version = $time_hi_and_version | 0x4000;

		/**
		 * Set the two most significant bits (bits 6 and 7) of the
		 * clock_seq_hi_and_reserved to zero and one, respectively.
		 */
		$clock_seq_hi_and_reserved = hexdec($clock_seq_hi_and_reserved);
		$clock_seq_hi_and_reserved = $clock_seq_hi_and_reserved >> 2;
		$clock_seq_hi_and_reserved = $clock_seq_hi_and_reserved | 0x8000;

		//Either return as hex or as string
		$format = $hex ? '%08s%04s%04x%04x%012s' : '%08s-%04s-%04x-%04x-%012s';

		return sprintf($format, $time_low, $time_mid, $time_hi_and_version, $clock_seq_hi_and_reserved, $node);
	}

	/**
	 * Send an activation email to the user
	 *
	 * @param   JUser  $user
	 * @param   array   $indata
	 *
	 * @return  bool
	 *
	 * @throws  \Exception
	 */
	private function sendActivationEmail($user, array $indata = [])
	{
		$app    = JFactory::getApplication();
		$config = $this->container->platform->getConfig();
		$db     = $this->container->db;
		$params = \JComponentHelper::getParams('com_users');

		$data = array_merge((array) $user->getProperties(), $indata);

		$useractivation = $params->get('useractivation');
		$sendpassword   = $params->get('sendpassword', 1);

		// Check if the user needs to activate their account.
		if (($useractivation == 1) || ($useractivation == 2))
		{
			$user->activation    = \JApplicationHelper::getHash(\JUserHelper::genRandomPassword());
			$user->block         = 1;
			$user->lastvisitDate = JFactory::getDbo()->getNullDate();
		}
		else
		{
			$user->block = 0;
		}

		// Load the users plugin group.
		\JPluginHelper::importPlugin('user');

		// Store the data.
		if (!$user->save())
		{
			return false;
		}

		// Compile the notification mail values.
		$data                   = $user->getProperties();
		$data['password_clear'] = $indata['password2'];
		$data['fromname']       = $config->get('fromname');
		$data['mailfrom']       = $config->get('mailfrom');
		$data['sitename']       = $config->get('sitename');
		$data['siteurl']        = $this->getContainer()->params->get('siteurl') ?? \JUri::root();

		// Load com_users translation files
		$jlang = JFactory::getLanguage();
		$jlang->load('com_users', JPATH_SITE, 'en-GB', true); // Load English (British)
		$jlang->load('com_users', JPATH_SITE, $jlang->getDefault(), true); // Load the site's default language
		$jlang->load('com_users', JPATH_SITE, null, true); // Load the currently selected language

		// Handle account activation/confirmation emails.
		if ($useractivation == 2)
		{
			$uri              = \JURI::getInstance();
			$base             = $uri->toString(['scheme', 'user', 'pass', 'host', 'port']);
			$data['activate'] = $base . \JRoute::_('index.php?option=com_users&task=registration.activate&token=' . $data['activation'], false);

			$emailSubject = \JText::sprintf(
				'COM_USERS_EMAIL_ACCOUNT_DETAILS',
				$data['name'],
				$data['sitename']
			);

			if ($sendpassword)
			{
				$emailBody = \JText::sprintf(
					'COM_USERS_EMAIL_REGISTERED_WITH_ADMIN_ACTIVATION_BODY',
					$data['name'],
					$data['sitename'],
					$data['activate'],
					$data['siteurl'],
					$data['username'],
					$data['password_clear']
				);
			}
			else
			{
				$emailBody = \JText::sprintf(
					'COM_USERS_EMAIL_REGISTERED_WITH_ADMIN_ACTIVATION_BODY_NOPW',
					$data['name'],
					$data['sitename'],
					$data['activate'],
					$data['siteurl'],
					$data['username']
				);
			}
		}
		elseif ($useractivation == 1)
		{
			// Set the link to activate the user account.
			$uri              = \JUri::getInstance();
			$base             = $uri->toString(['scheme', 'user', 'pass', 'host', 'port']);
			$data['activate'] = $base . \JRoute::_('index.php?option=com_users&task=registration.activate&token=' . $data['activation'], false);

			$emailSubject = \JText::sprintf(
				'COM_USERS_EMAIL_ACCOUNT_DETAILS',
				$data['name'],
				$data['sitename']
			);

			if ($sendpassword)
			{
				$emailBody = \JText::sprintf(
					'COM_USERS_EMAIL_REGISTERED_WITH_ACTIVATION_BODY',
					$data['name'],
					$data['sitename'],
					$data['activate'],
					$data['siteurl'],
					$data['username'],
					$data['password_clear']
				);
			}
			else
			{
				$emailBody = \JText::sprintf(
					'COM_USERS_EMAIL_REGISTERED_WITH_ACTIVATION_BODY_NOPW',
					$data['name'],
					$data['sitename'],
					$data['activate'],
					$data['siteurl'],
					$data['username']
				);
			}
		}
		else
		{

			$emailSubject = \JText::sprintf(
				'COM_USERS_EMAIL_ACCOUNT_DETAILS',
				$data['name'],
				$data['sitename']
			);

			if ($sendpassword)
			{
				$emailBody = \JText::sprintf(
					'COM_USERS_EMAIL_REGISTERED_BODY',
					$data['name'],
					$data['sitename'],
					$data['siteurl'],
					$data['username'],
					$data['password_clear']
				);
			}
			else
			{
				$emailBody = \JText::sprintf(
					'COM_USERS_EMAIL_REGISTERED_BODY_NOPW',
					$data['name'],
					$data['sitename'],
					$data['siteurl']
				);
			}
		}

		// Send the registration email.
		try
		{
			$return = JFactory::getMailer()->sendMail($data['mailfrom'], $data['fromname'], $data['email'], $emailSubject, $emailBody);
		}
		catch (\Exception $e)
		{
			// Joomla! 3.5 is written by incompetent bonobos
			$return = false;
		}

		//Send Notification mail to administrators
		if (($params->get('useractivation') < 2) && ($params->get('mail_to_admin') == 1))
		{
			$emailSubject = \JText::sprintf(
				'COM_USERS_EMAIL_ACCOUNT_DETAILS',
				$data['name'],
				$data['sitename']
			);

			$emailBodyAdmin = \JText::sprintf(
				'COM_USERS_EMAIL_REGISTERED_NOTIFICATION_TO_ADMIN_BODY',
				$data['name'],
				$data['username'],
				$data['siteurl']
			);

			// get all admin users
			$query = $db->getQuery(true);
			$query->select($db->quoteName(['name', 'email', 'sendEmail', 'id']))
				->from($db->quoteName('#__users'))
				->where($db->quoteName('sendEmail') . ' = ' . 1);

			$db->setQuery($query);

			try
			{
				$rows = $db->loadObjectList();
			}
			catch (\RuntimeException $e)
			{
				return false;
			}

			// Send mail to all superadministrators id
			foreach ($rows as $row)
			{
				try
				{
					$return = JFactory::getMailer()->sendMail($data['mailfrom'], $data['fromname'], $row->email, $emailSubject, $emailBodyAdmin);
				}
				catch (\Exception $e)
				{
					// Joomla! 3.5 is written by incompetent bonobos
					$return = false;
				}
			}
		}

		return $return;
	}

	/**
	 * Logs the failure of creating a subscription. The information is logged in a plain text file inside your site's
	 * logs folder, under its akeebasubs_failed subdirectory. These files are generated every time the user submits the
	 * subscription form with invalid data and are removed if the subscription is created successfully (even if it is
	 * NOT paid / activated). For this reason we recommend NOT taking these files into account if they are newer than
	 * 30': the user may still be collecting / reviewing information to fix the validation errors.
	 *
	 * @param   string  $reason  The failure reason
	 *
	 * @return  void
	 *
	 * @since   5.2.6
	 */
	private function logSubscriptionCreationFailure($reason = 'Validation failure')
	{
		/** @var Levels $levelsModel */
		$levelsModel       = $this->container->factory->model('Levels')->tmpInstance();
		$validation        = $this->getValidation();
		$state             = $this->getStateVariables();
		$level             = $levelsModel->getClone()->find($state->id);
		$logFilepath       = $this->getLogFilename($level);
		$application       = JFactory::getApplication();
		$sessionName       = $application->getSession()->getName();
		$sessionId         = $application->getSession()->getId();
		$subscriptionLevel = $level->getId();
		$user              = JFactory::getUser();
		$txtValidation     = print_r($validation, true);
		$txtState          = print_r($state, true);
		$txtGET            = print_r($application->input->get->getArray(), true);
		$txtPOST           = print_r($application->input->post->getArray(), true);
		$txtREQUEST        = print_r($application->input->request->getArray(), true);
		$txtCOOKIE         = print_r($application->input->cookie->getArray(), true);
		$browser           = new JBrowser();
		$ua                = $browser->getAgentString();
		$ip                = Ip::getIp();
		$modelState        = $this->getState();
		$txtModelState     = print_r($modelState, true);

		$text = <<< TEXT
<?php die(); ?>
================================================================================
FAILED SUBSCRIPTION CREATION REPORT
================================================================================

Identity
--------------------------------------------------------------------------------
Failure reason     : $reason
Session Name       : $sessionId
Session ID         : $sessionId
IP Address         : $ip
User Agent         : $ua
Subscription Level : $subscriptionLevel [{$level->title}] 
Logged In Username : $user->username
Logged In Email    : $user->email
Requested Username : $state->username
Requested Email    : $state->email

Validation Results
--------------------------------------------------------------------------------
$txtValidation

User State Information
--------------------------------------------------------------------------------
$txtState

Model State
--------------------------------------------------------------------------------
$txtModelState

\$_GET
--------------------------------------------------------------------------------
$txtGET

\$_POST
--------------------------------------------------------------------------------
$txtPOST

\$_COOKIE
--------------------------------------------------------------------------------
$txtCOOKIE

\$_REQUEST
--------------------------------------------------------------------------------
$txtREQUEST

================================================================================
Important note:
  This file is generated every time the user presses on Subscribe Now and the
  subscription form is invalid. If the user resubmits the form during the same
  session with valid information this file will be deleted. As a result you
  should not take into account this file if it's less than 30' old.

TEXT;

		\JFile::write($logFilepath, $text);
	}

	/**
	 * Removes the file created by logSubscriptionCreationFailure.
	 *
	 * This happens in two cases:
	 *
	 * - There is no charge for the subscription. This is called right before redirecting the user to the success page.
	 * - The new subscription record has been created and we're handing over execution to the payment flow.
	 *
	 * @param   Levels  $level  The subscription level the user is subscribing to
	 *
	 * @since   5.2.6
	 */
	private function removeSubscriptionCreationFailureLog(Levels $level)
	{
		$logFilename = $this->getLogFilename($level);

		if (\JFile::exists($logFilename))
		{
			\JFile::delete($logFilename);
		}
	}

	/**
	 * Gets the absolute path to the subscription failure log file.
	 *
	 * @param   Levels $level The level the user is subscribing to
	 *
	 * @return  string
	 *
	 * @since   5.2.6
	 *
	 * @see     logSubscriptionCreationFailure()
	 *
	 * @throws \Exception
	 */
	public function getLogFilename(Levels $level = null)
	{
		if (empty($level))
		{
			/** @var Levels $levelsModel */
			$levelsModel = $this->container->factory->model('Levels')->tmpInstance();
			$state       = $this->getStateVariables();
			$level       = $levelsModel->getClone()->find($state->id);
		}

		$application       = JFactory::getApplication();
		$sessionId         = $application->getSession()->getId();
		$userId            = JFactory::getUser()->id ?: 'guest';
		$subscriptionLevel = $level->getId();
		$logPath           = $application->get('log_path') . '/akeebasubs_failed';
		$logFilepath       = $logPath . '/' . $sessionId . '_' . $userId . '_' . $subscriptionLevel . '.php';

		if (!\JFolder::exists($logPath))
		{
			\JFolder::create($logPath);
		}

		return $logFilepath;
	}
}
