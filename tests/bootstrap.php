<?php
/**
 * PHPUnit bootstrap file
 *
 * @package AcfMultisiteSync
 */

// First, let's require composer's autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Set up Brain Monkey
require_once dirname( __DIR__ ) . '/vendor/antecedent/patchwork/Patchwork.php';
require_once dirname( __DIR__ ) . '/vendor/brain/monkey/inc/patchwork-loader.php';

// Load our plugin files.
require_once dirname( __DIR__ ) . '/includes/class-acf-sync.php';
