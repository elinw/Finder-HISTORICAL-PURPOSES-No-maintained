<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Finder.Joomla_categories
 *
 * @copyright   Copyright (C) 2005 - 2011 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_BASE') or die;

// Load the base adapter.
require_once JPATH_ADMINISTRATOR.'/components/com_finder/helpers/indexer/adapter.php';

/**
 * Finder adapter for Joomla Categories.
 *
 * @package     Joomla.Plugin
 * @subpackage  Finder.Joomla_categories
 * @since       2.5
 */
class plgFinderJoomla_Categories extends FinderIndexerAdapter
{
	/**
	 * The plugin identifier.
	 *
	 * @var    string
	 * @since  2.5
	 */
	protected $context = 'Joomla_Categories';

	/**
	 * The sublayout to use when rendering the results.
	 *
	 * @var    string
	 * @since  2.5
	 */
	protected $layout = 'category';

	/**
	 * The type of content that the adapter indexes.
	 *
	 * @var    string
	 * @since  2.5
	 */
	protected $type_title = 'Category';

	/**
	 * Constructor
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An array that holds the plugin configuration
	 *
	 * @return  plgFinderJoomla_Categories
	 *
	 * @since   2.5
	 */
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}

	/**
	 * Method to reindex the link information for an item that has been saved.
	 * This event is fired before the data is actually saved so we are going
	 * to queue the item to be indexed later.
	 *
	 * @param	string   $context  The context of the content passed to the plugin.
	 * @param	JTable   &$row     A JTable object
	 * @param	boolean  $isNew    If the content is just about to be created
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   2.5
	 * @throws  Exception on database error.
	 */
	public function onContentBeforeSave($context, &$row, $isNew)
	{
		// We only want to handle categories here
		if ($context != 'com_categories.category')
		{
			return;
		}

		// Queue the item to be reindexed.
		FinderIndexerQueue::add($context, $row->id, JFactory::getDate()->toMySQL());

		return true;
	}

	/**
	 * Method to update the item link information when the item category is
	 * changed. This is fired when the item category is published, unpublished,
	 * or an access level is changed.
	 *
	 * @param   array    $ids       An array of item ids.
	 * @param   string   $property  The property that is being changed.
	 * @param   integer  $value     The new value of that property.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   2.5
	 * @throws  Exception on database error.
	 */
	public function onChangeJoomlaCategory($ids, $property, $value)
	{
		// Check if we are changing the category state.
		if ($property === 'published')
		{
			// The article published state is tied to the category
			// published state so we need to look up all published states
			// before we change anything.
			foreach ($ids as $id)
			{
				$sql = clone($this->_getStateQuery());
				$sql->where('c.id = '.(int)$id);

				// Get the published states.
				$this->db->setQuery($sql);
				$items = $this->db->loadObjectList();

				// Adjust the state for each item within the category.
				foreach ($items as $item)
				{
					// Translate the state.
					$temp = $this->_translateState($value);

					// Update the item.
					$this->change($item->id, 'state', $temp);
				}
			}
		}
		// Check if we are changing the category access level.
		else if ($property === 'access')
		{
			// The article access state is tied to the category
			// access state so we need to look up all access states
			// before we change anything.
			foreach ($ids as $id)
			{
				$sql = clone($this->_getStateQuery());
				$sql->where('c.id = '.(int)$id);

				// Get the published states.
				$this->db->setQuery($sql);
				$items = $this->db->loadObjectList();

				// Adjust the state for each item within the category.
				foreach ($items as $item)
				{
					// Translate the state.
					$temp = max($value);

					// Update the item.
					$this->change($item->id, 'access', $temp);
				}
			}
		}

		return true;
	}

	/**
	 * Method to remove the link information for items that have been deleted.
	 *
	 * @param   string  $context  The context of the action being performed.
	 * @param   JTable  $table    A JTable object containing the record to be deleted
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   2.5
	 * @throws  Exception on database error.
	 */
	public function onContentAfterDelete($context, $table)
	{
		if ($context == 'com_categories.category')
		{
			$id = $table->id;
		}
		else if ($context == 'com_finder.index')
		{
			$id = $table->link_id;
		}
		else
		{
			return;
		}
		// Remove the items.
		return $this->remove($id);
	}

	/**
	 * Method to index an item. The item must be a FinderIndexerResult object.
	 *
	 * @param   FinderIndexerResult  $item  The item to index as an FinderIndexerResult object.
	 *
	 * @return  void
	 *
	 * @since   2.5
	 * @throws  Exception on database error.
	 */
	protected function index(FinderIndexerResult $item)
	{
		// Initialize the item parameters.
		$registry = new JRegistry;
		$registry->loadString($item->params);
		$item->params	= $registry;

		// Trigger the onPrepareContent event.
		$item->summary	= FinderIndexerHelper::prepareContent($item->summary, $item->params);

		// Build the necessary route and path information.
		$item->url		= $this->getURL($item->id);
		$item->route	= ContentHelperRoute::getCategoryRoute($item->slug, $item->catid);
		$item->path		= FinderIndexerHelper::getContentPath($item->route);

		// Get the menu title if it exists.
		$title = $this->getItemMenuTitle($item->url);

		// Adjust the title if necessary.
		if (!empty($title) && $this->params->get('use_menu_title', true))
		{
			$item->title = $title;
		}

		// Translate the state. Categories should only be published if the section is published.
		$item->state = $this->_translateState($item->state);

		// Set the language.
		$item->language	= $item->params->get('language', FinderIndexerHelper::getDefaultLanguage());

		// Add the type taxonomy data.
		$item->addTaxonomy('Type', 'Category');

		// Get content extras.
		FinderIndexerHelper::getContentExtras($item);

		// Index the item.
		FinderIndexer::index($item);
	}

	/**
	 * Method to setup the indexer to be run.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   2.5
	 */
	protected function setup()
	{
		// Load dependent classes.
		include_once JPATH_SITE.'/components/com_content/helpers/route.php';

		return true;
	}

	/**
	 * Method to get the SQL query used to retrieve the list of content items.
	 *
	 * @param   mixed  $sql  A JDatabaseQuery object or null.
	 *
	 * @return  object  A JDatabaseQuery object.
	 *
	 * @since   2.5
	 */
	protected function getListQuery($sql = null)
	{
		$db = JFactory::getDbo();
		// Check if we can use the supplied SQL query.
		$sql = is_a($sql, 'JDatabaseQuery') ? $sql : $db->getQuery(true);
		$sql->select('a.id, a.title, a.alias, a.description AS summary');
		$sql->select('a.created_time AS start_date, a.published AS state, a.access, a.params');
		$sql->select('CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(":", a.id, a.alias) ELSE a.id END as slug');
		$sql->from('#__categories AS a');
		$sql->where($db->quoteName('a.id').' > 1');

		return $sql;
	}

	/**
	 * Method to get the URL for the item. The URL is how we look up the link
	 * in the Finder index.
	 *
	 * @param   mixed  $id  The id of the item.
	 *
	 * @return  string  The URL of the item.
	 *
	 * @since   2.5
	 */
	protected function getURL($id)
	{
		return 'index.php?option=com_content&view=category&id='.$id;
	}

	/**
	 * Method to translate the native content states into states that the
	 * indexer can use.
	 *
	 * @param   integer  $category  The category state.
	 *
	 * @return  integer  The translated indexer state.
	 *
	 * @since   2.5
	 */
	private function _translateState($category)
	{
		// Translate the state.
		switch ($category)
		{
			// Unpublished.
			case 0:
				return 0;

			// Published.
			default:
			case 1:
				return 1;
		}
	}

	/**
	 * Method to get a SQL query to load the published and access states for
	 * a category and section.
	 *
	 * @return  object  A JDatabaseQuery object.
	 *
	 * @since   2.5
	 */
	private function _getStateQuery()
	{
		$sql = $this->db->getQuery(true);
		$sql->select($db->quoteName('c.id'));
		$sql->select($db->quoteName('c.published').' AS cat_state');
		$sql->select($db->quoteName('c.access').' AS cat_access');
		$sql->from($db->quoteName('#__categories').' AS c');

		return $sql;
	}
}
