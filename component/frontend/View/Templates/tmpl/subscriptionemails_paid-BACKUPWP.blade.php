<?php
/**
 * Akeeba Subscriptions – Pending subscription got paid (BACKUPWP)
 *
 * @package    akeeba/internal
 * @subpackage email_template
 * @copyright  Copyright (c) 2017-2019 Akeeba Ltd
 * @license    Proprietary
 */
?>
@extends('any:com_akeebasubs/Templates/subscriptionemails_new_active-BACKUPWP')
@section('subject')
    Your [LEVEL] subscription at [SITENAME] is now paid
@stop
@section('topic')
    The payment for your [LEVEL] subscription on our site has just been cleared.
@stop