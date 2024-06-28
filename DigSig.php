<?php

/**
 * DigSig
 *
 * @author  James K
 * @license MIT
 * @link    https://github.com/tenthpres/digsig
 * @package DigSig
 */

/*
Plugin Name:        DigSig
Plugin URI:         https://github.com/tenthpres/digsig
GitHub Plugin URI:  https://github.com/tenthpres/digsig
Description:        A WordPress Plugin for Digital Signage
Version:            1.0.1
Author:             James K
Author URI:         https://github.com/jkrrv
License:            MIT
Requires at least:  5.5
Tested up to:       5.9.3
Requires PHP:       7.4
*/

// die if called directly.
if ( ! defined('WPINC')) {
    die;
}

/*** Load everything **/
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    /** @noinspection PhpIncludeInspection
     * @noinspection RedundantSuppression
     */
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    require_once __DIR__ . "/src/DigSig/DigSig.php";
    require_once __DIR__ . "/src/DigSig/DigSig_Settings.php";
}

use tp\DigSig\DigSig;

/*** Load (set action hooks, etc.) ***/
DigSig::load(__FILE__);
