<?php
/**
 * @package		JXtended.Finder
 * @subpackage	com_finder
 * @copyright	Copyright (C) 2007 - 2010 JXtended, LLC. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 * @link		http://jxtended.com
 */

defined('JPATH_BASE') or die;

jimport('joomla.html.html');
jimport('joomla.form.formfield');
jimport('joomla.form.helper');
JFormHelper::loadFieldClass('list');

// Load the base adapter.
require_once JPATH_ADMINISTRATOR.'/components/com_finder/helpers/indexer/adapter.php';

/**
 * Renders a list of directories.
 *
 * @package		JXtended.Finder
 * @subpackage	com_finder
 */
class JFormFieldDirectories extends JFormFieldList
{
	/**
	 * The form field type.
	 *
	 * @var		string
	 * @since	1.7
	 */
	protected $type = 'Directories';

	/**
	 * Method to get the field options.
	 *
	 * @return	array	The field option objects.
	 * @since	1.7
	 */
	public function getOptions()
	{
		$values		= array();
		$options	= array();
		$exclude	= array(
						JPATH_ADMINISTRATOR,
						JPATH_INSTALLATION,
						JPATH_LIBRARIES,
						JPATH_PLUGINS,
						JPATH_SITE.DS.'cache',
						JPATH_SITE.DS.'components',
						JPATH_SITE.DS.'includes',
						JPATH_SITE.DS.'language',
						JPATH_SITE.DS.'modules',
						JPATH_SITE.DS.'templates',
						JPATH_XMLRPC,
						JFactory::getApplication()->getCfg('log_path'),
						JFactory::getApplication()->getCfg('tmp_path'),
					);

		// Get the base directories.
		$dirs = JFolder::folders(JPATH_SITE, '.', false, true);

		// Iterate through the base directories and find the subdirectories.
		foreach ($dirs as $dir)
		{
			// Check if the directory should be excluded.
			if (in_array($dir, $exclude)) {
				continue;
			}

			// Get the child directories.
			$return = JFolder::folders($dir, '.', true, true);

			// Merge the directories.
			if (is_array($return)) {
				$values[]	= $dir;
				$values		= array_merge($values, $return);
			}
		}

		// Convert the values to options.
		for ($i = 0, $c = count($values); $i < $c; $i++) {
			$options[] = JHtml::_('select.option', str_replace(JPATH_SITE.DS, '', $values[$i]), str_replace(JPATH_SITE.DS, '', $values[$i]));
		}

		// Add a null option.
		array_unshift($options, JHTML::_('select.option', '', '- '.JText::_('FINDER_NONE').' -'));

		// Handle default values of value1|value2|value3
		if (is_string($value) && strpos($value, '|') && preg_match('#(?<!\\\)\|#', $value))
		{
			// Explode the value if it is serialized as an array of value1|value2|value3
			$value	= preg_split('/(?<!\\\)\|/', $value);
			$value	= str_replace('\|', '|', $value);
			$value	= str_replace('\n', "\n", $value);
		}

		return $options;
	}
}