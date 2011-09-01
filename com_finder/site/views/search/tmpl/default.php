<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_finder
 *
 * @copyright   Copyright (C) 2005 - 2011 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

JHtml::_('behavior.framework');
JHtml::addIncludePath(JPATH_COMPONENT.'/helpers/html');
JHtml::stylesheet('components/com_finder/media/css/finder.css', false, false, false);

// Check if we need to show the page title.
if ($this->params->get('show_page_title', 1)):
?>
	<h1><?php echo $this->escape($this->params->get('page_title')); ?></h1>
<?php
endif;

// Display the search form if enabled.
if ($this->params->get('show_search_form', 1)):
?>
	<div id="search-form">
		<?php echo $this->loadTemplate('form'); ?>
	</div>
<?php
endif;

// Load the search results layout if we are performing a search.
if ($this->query->search === true):
?>
	<div id="search-results">
		<?php echo $this->loadTemplate('results'); ?>
	</div>
<?php
endif;