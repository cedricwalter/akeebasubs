<?php
/**
 * @package   AkeebaSubs
 * @copyright Copyright (c)2010-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

use Akeeba\Subscriptions\Admin\Helper\Select;

echo Select::paymentmethods(
    'paymentmethod',
    $this->input->getString('paymentmethod', ''),
    array(
        'id' 		=> 'paymentmethod',
        'level_id' 	=> $this->input->getInt('id', 0),
    )
);
