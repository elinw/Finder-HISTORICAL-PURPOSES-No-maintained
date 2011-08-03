<?php
/**
 * @version		$Id: finder.php 981 2010-06-15 18:38:02Z robs $
 * @package		JXtended.Finder
 * @subpackage	com_finder
 * @copyright	Copyright (C) 2007 - 2010 JXtended, LLC. All rights reserved.
 * @license		GNU General Public License <http://www.gnu.org/copyleft/gpl.html>
 * @link		http://jxtended.com
 */

defined('_JEXEC') or die;

// Detect if we have full UTF-8 and unicode support.
define('JX_FINDER_UNICODE', (bool)@preg_match('/\pL/u', 'a'));

// Import the component version class.
require_once(dirname(__FILE__).'/version.php');

// Check for the JXtended Libraries.
if (!function_exists('jx'))
{
	// Import the setup helper class.
	require_once(dirname(__FILE__).'/helpers/setup.php');

	// Attempt to setup the libraries.
	if (!JXtendedSetupHelper::setupLibraries()) {
		return;
	}
}

// Check to make sure dependencies are met.
if (!FinderVersion::checkDependencies()) {
	return;
}

jx('jx.application.component.helper.controller');

// Execute the task.
$controller	= JControllerHelper::getInstance('Finder');
$controller->execute(JRequest::getCmd('task'));
$controller->redirect();
