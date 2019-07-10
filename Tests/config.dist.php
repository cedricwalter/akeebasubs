<?php
/**
 * @package    AkeebaSubs
 * @subpackage Tests
 * @copyright  Copyright (c)2010-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license    GNU General Public License version 3, or later
 */

/*
 * Configuration for unit tests
 *
 * YOU ARE SUPPOSED TO USE A DEDICATED SITE FOR THESE TESTS, SINCE WE'LL COMPLETELY OVERWRITE EXISTING
 * DATABASE DATA WITH WHAT IS NEEDED FOR THE TEST!
 */

$akeebasubsTestConfig = [
	'site_root'        => '/var/www/guineapig',
	'site_name'        => 'Akeeba Subscriptions Unit Tests',
	'site_url'         => 'http://localhost/guineapig/',
	// Paddle Vendor ID and Vendor ID authentication code, used for the Recurring validator's testing
	'vendor_id'        => '123456',
	'vendor_auth_code' => 'abcdef123',
];
