<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_finder
 *
 * @copyright   Copyright (C) 2005 - 2011 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.view');

/**
 * Search feed view class for the Finder package.
 *
 * @package     Joomla.Site
 * @subpackage  com_finder
 * @since       2.5
 */
class FinderViewSearch extends JView
{
	/**
	 * Method to display the view.
	 *
	 * @param   string  $tpl  A template file to load.
	 *
	 * @return  mixed  JError object on failure, void on success.
	 *
	 * @since   2.5
	 */
	public function display($tpl = null)
	{
		// Adjust the list limit to the feed limit.
		JRequest::setVar('limit', JFactory::getApplication()->getCfg('feed_limit'));

		// Get view data.
		$state		= $this->get('State');
		$params		= $state->get('params');
		$query		= $this->get('Query');
		$results	= $this->get('Results');

		// Push out the query data.
		JHtml::addIncludePath(JPATH_COMPONENT.'/helpers/html');
		$suggested = JHtml::_('query.suggested', $query);
		$explained = JHtml::_('query.explained', $query);

		// Set the document title.
		$this->document->setTitle($params->get('page_title'));

		// Configure the document description.
		if (!empty($explained))
		{
			$this->document->setDescription(html_entity_decode(strip_tags($explained), ENT_QUOTES, 'UTF-8'));
		}

		// Set the document link.
		$this->document->link = JRoute::_($query->toURI());

		// Convert the results to feed entries.
		foreach ($results as $result)
		{
			// Convert the result to a feed entry.
			$item = new JFeedItem();
			$item->title 		= $result->title;
			$item->link 		= JRoute::_($result->route);
			$item->description 	= $result->description;
			$item->date			= intval($result->start_date) ? JHtml::date($result->start_date, '%A %d %B %Y') : $result->indexdate;

			// Get the taxonomy data.
			$taxonomy = $result->getTaxonomy();

			// Add the category to the feed if available.
			if (isset($taxonomy['Category']))
			{
				$node = array_pop($taxonomy['Category']);
				$item->category = $node->title;
			}

			// loads item info into rss array
			$this->document->addItem($item);
		}
	}
}
