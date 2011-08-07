<?php
/**
 * @version		$Id: joomla_contacts.php 981 2010-06-15 18:38:02Z robs $
 * @package		JXtended.Finder
 * @subpackage	plgFinderJoomla_Contacts
 * @copyright	Copyright (C) 2007 - 2010 JXtended, LLC. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 * @link		http://jxtended.com
 */

defined('JPATH_BASE') or die;

// Load the base adapter.
require_once JPATH_ADMINISTRATOR.'/components/com_finder/helpers/indexer/adapter.php';

/**
 * Finder adapter for Joomla Contacts.
 *
 * @package		JXtended.Finder
 * @subpackage	plgFinderJoomla_Contacts
 */
class plgFinderJoomla_Contacts extends FinderIndexerAdapter
{
	/**
	 * @var		string		The plugin identifier.
	 */
	protected $_context = 'Joomla_Contacts';

	/**
	 * @var		string		The sublayout to use when rendering the results.
	 */
	protected $_layout = 'contact';

	/**
	 * @var		string		The type of content that the adapter indexes.
	 */
	protected $_type_title = 'Contact';

	/**
	 * Constructor
	 *
	 * @param	object	$subject	The object to observe
	 * @param	array	$config		An array that holds the plugin configuration
	 *
	 * @return	void
	 * @since	1.8
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
	 * @param	integer		The id of the item.
	 * @return	boolean		True on success.
	 * @throws	Exception on database error.
	 */
	public function onBeforeSaveJoomlaContact($id)
	{
		// Queue the item to be reindexed.
		FinderIndexerQueue::add($this->_context, $id, JFactory::getDate()->toMySQL());

		return true;
	}

	/**
	 * Method to update the link information for items that have been changed
	 * from outside the edit screen. This is fired when the item is published,
	 * unpublished, archived, or unarchived from the list view.
	 *
	 * @param	array		An array of item ids.
	 * @param	string		The property that is being changed.
	 * @param	integer		The new value of that property.
	 * @return	boolean		True on success.
	 * @throws	Exception on database error.
	 */
	public function onChangeJoomlaContact($ids, $property, $value)
	{
		// Check if we are changing the contact state.
		if ($property === 'published')
		{
			// The contact published state is tied to the category published
			// state so we need to look up all published states before we
			// change anything.
			foreach ($ids as $id)
			{
				$sql = clone($this->_getStateQuery());
				$sql->where('a.id = '.(int)$id);

				// Get the published states.
				$this->_db->setQuery($sql);
				$item = $this->_db->loadObject();

				// Translate the state.
				$temp = $this->_translateState($value, $item->cat_state);

				// Update the item.
				$this->_change($id, 'state', $temp);
			}
		}
		// Check if we are changing the contact access level.
		elseif ($property === 'access')
		{
			// The contact access state is tied to the category access state so
			// we need to look up all access states before we change anything.
			foreach ($ids as $id)
			{
				$sql = clone($this->_getStateQuery());
				$sql->where('a.id = '.(int)$id);

				// Get the published states.
				$this->_db->setQuery($sql);
				$item = $this->_db->loadObject();

				// Translate the state.
				$temp = max($value, $item->cat_access);

				// Update the item.
				$this->_change($id, 'access', $temp);
			}
		}

		return true;
	}

	/**
	 * Method to update the item link information when the item category is
	 * changed. This is fired when the item category is published, unpublished,
	 * or an access level is changed.
	 *
	 * @param	array		An array of item ids.
	 * @param	string		The property that is being changed.
	 * @param	integer		The new value of that property.
	 * @return	boolean		True on success.
	 * @throws	Exception on database error.
	 */
	public function onChangeJoomlaCategory($ids, $property, $value)
	{
		// Check if we are changing the category state.
		if ($property === 'published')
		{
			// The contact published state is tied to the category published
			// state so we need to look up all published states before we
			// change anything.
			foreach ($ids as $id)
			{
				$sql = clone($this->_getStateQuery());
				$sql->where('c.id = '.(int)$id);

				// Get the published states.
				$this->_db->setQuery($sql);
				$items = $this->_db->loadObjectList();

				// Adjust the state for each item within the category.
				foreach ($items as $item)
				{
					// Translate the state.
					$temp = $this->_translateState($item->state, $value);

					// Update the item.
					$this->_change($item->id, 'state', $temp);
				}
			}
		}
		// Check if we are changing the category access level.
		elseif ($property === 'access')
		{
			// The contact access state is tied to the category access state so
			// we need to look up all access states before we change anything.
			foreach ($ids as $id)
			{
				$sql = clone($this->_getStateQuery());
				$sql->where('c.id = '.(int)$id);

				// Get the published states.
				$this->_db->setQuery($sql);
				$items = $this->_db->loadObjectList();

				// Adjust the state for each item within the category.
				foreach ($items as $item)
				{
					// Translate the state.
					$temp = max($item->access, $value);

					// Update the item.
					$this->_change($item->id, 'access', $temp);
				}
			}
		}

		return true;
	}

	/**
	 * Method to remove the link information for items that have been deleted.
	 *
	 * @param	array		An array of item ids.
	 * @return	boolean		True on success.
	 * @throws	Exception on database error.
	 */
	public function onDeleteJoomlaContact($ids)
	{
		// Remove the items.
		return $this->_remove($ids);
	}

	/**
	 * Method to index an item. The item must be a FinderIndexerResult object.
	 *
	 * @param	object		The item to index as an FinderIndexerResult object.
	 * @throws	Exception on database error.
	 */
	protected function _index(FinderIndexerResult $item)
	{
		// Initialize the item parameters.
		$item->params = new JParameter($item->params);

		// Let's do a little trick to get the Itemid.
		$tmp = array('option' => 'com_contact', 'view' => 'contact', 'id' => $item->slug, 'catid' => $item->catslug);
		ContactBuildRoute($tmp);
		$Itemid = !empty($tmp['Itemid']) ? '&Itemid='.$tmp['Itemid'] : null;

		// Build the necessary route and path information.
		$item->url		= $this->_getURL($item->id);
		$item->route	= $this->_getURL($item->slug).'&catid='.$item->catslug.$Itemid;
		$item->path		= FinderIndexerHelper::getContentPath($item->route);

		// Get the menu title if it exists.
		$title = $this->_getItemMenuTitle($item->url);

		// Adjust the title if necessary.
		if (!empty($title) && $this->params->get('use_menu_title', true)) {
			$item->title = $title;
		}

		/*
		 * Add the meta-data processing instructions based on the contact
		 * configuration parameters.
		 */
		// Handle the contact position.
		if ($item->params->get('show_position', true)) {
			$item->addInstruction(FinderIndexer::META_CONTEXT, 'position');
		}

		// Handle the contact street address.
		if ($item->params->get('show_street_address', true)) {
			$item->addInstruction(FinderIndexer::META_CONTEXT, 'address');
		}

		// Handle the contact city.
		if ($item->params->get('show_suburb', true)) {
			$item->addInstruction(FinderIndexer::META_CONTEXT, 'city');
		}

		// Handle the contact region.
		if ($item->params->get('show_state', true)) {
			$item->addInstruction(FinderIndexer::META_CONTEXT, 'region');
		}

		// Handle the contact country.
		if ($item->params->get('show_country', true)) {
			$item->addInstruction(FinderIndexer::META_CONTEXT, 'country');
		}

		// Handle the contact zip code.
		if ($item->params->get('show_postcode', true)) {
			$item->addInstruction(FinderIndexer::META_CONTEXT, 'zip');
		}

		// Handle the contact telephone number.
		if ($item->params->get('show_telephone', true)) {
			$item->addInstruction(FinderIndexer::META_CONTEXT, 'telephone');
		}

		// Handle the contact fax number.
		if ($item->params->get('show_fax', true)) {
			$item->addInstruction(FinderIndexer::META_CONTEXT, 'fax');
		}

		// Handle the contact e-mail address.
		if ($item->params->get('show_email', true)) {
			$item->addInstruction(FinderIndexer::META_CONTEXT, 'email');
		}

		// Handle the contact mobile number.
		if ($item->params->get('show_mobile', true)) {
			$item->addInstruction(FinderIndexer::META_CONTEXT, 'mobile');
		}

		// Handle the contact webpage.
		if ($item->params->get('show_webpage', true)) {
			$item->addInstruction(FinderIndexer::META_CONTEXT, 'webpage');
		}

		// Handle the contact user name.
		$item->addInstruction(FinderIndexer::META_CONTEXT, 'user');

		// Set the language.
		$item->language	= FinderIndexerHelper::getDefaultLanguage();

		// Add the type taxonomy data.
		$item->addTaxonomy('Type', 'Contact');

		// Add the category taxonomy data.
		if (!empty($item->category)) {
			$item->addTaxonomy('Category', $item->category, $item->cat_state, $item->cat_access);
		}

		// Add the region taxonomy data.
		if (!empty($item->region) && $this->params->get('tax_add_region', true)) {
			$item->addTaxonomy('Region', $item->region);
		}

		// Add the country taxonomy data.
		if (!empty($item->country) && $this->params->get('tax_add_country', true)) {
			$item->addTaxonomy('Country', $item->country);
		}

		// Get content extras.
		FinderIndexerHelper::getContentExtras($item);

		// Index the item.
		FinderIndexer::index($item);
	}

	/**
	 * Method to setup the indexer to be run.
	 *
	 * @return	boolean		True on success.
	 */
	protected function _setup()
	{
		// Load dependent classes.
		require_once JPATH_SITE.'/components/com_contact/router.php';

		// This is a hack to get around the lack of a route helper.
		FinderIndexerHelper::getContentPath('index.php?option=com_contact');

		return true;
	}

	/**
	 * Method to get the SQL query used to retrieve the list of content items.
	 *
	 * @param	mixed		A JDatabaseQuery object or null.
	 * @return	object		A JDatabaseQuery object.
	 */
	protected function _getListQuery($sql = null)
	{
		// Check if we can use the supplied SQL query.
		$sql = is_a($sql, 'JDatabaseQuery') ? $sql : $this->_db->getQuery(true);
		$sql->select('a.id, a.name AS title, a.alias, con_position AS position, a.address');
		$sql->select('a.suburb AS city, a.state AS region, a.country, a.postcode AS zip');
		$sql->select('a.telephone, a.fax, a.misc AS summary, a.email_to AS email, a.mobile');
		$sql->select('a.webpage, a.access, a.published AS state, a.ordering, a.params, a.catid');
		$sql->select('c.title AS category, c.published AS cat_state, c.access AS cat_access');
		$sql->select('CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(":", a.id, a.alias) ELSE a.id END as slug');
		$sql->select('CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(":", c.id, c.alias) ELSE c.id END as catslug');
		$sql->select('u.name AS user');
		$sql->from('#__contact_details AS a');
		$sql->join('LEFT', '#__categories AS c ON c.id = a.catid');
		$sql->join('LEFT', '#__users AS u ON u.id = a.user_id');

		return $sql;
	}

	/**
	 * Method to get the query clause for getting items to update by time.
	 *
	 * @param	string		The modified timestamp.
	 * @return	object		A JDatabaseQuery object.
	 */
	protected function _getUpdateQueryByTime($time)
	{
		/*
		 * The #__contact_details table does not have a modified date column
		 * so we need to use a different method for find new items. Our best
		 * bet is to order by the primary key putting the new items first.
		 */
		$sql = $this->_db->getQuery(true);
		$sql->order('a.id DESC');

		return $sql;
	}

	/**
	 * Method to get the URL for the item. The URL is how we look up the link
	 * in the Finder index.
	 *
	 * @param	string		The id of the item.
	 * @return	string		The URL of the item.
	 */
	protected function _getURL($id)
	{
		return 'index.php?option=com_contact&view=contact&id='.$id;
	}

	/**
	 * Method to translate the native content states into states that the
	 * indexer can use.
	 *
	 * @param	integer		The article state.
	 * @param	mixed		The category state, if known.
	 * @return	integer		The translated indexer state.
	 */
	private function _translateState($contact, $category)
	{
		// If category is present, factor in its states as well.
		if ($category !== null) {
			if ($category == 0) {
				$contact = 0;
			}
		}

		// Translate the state.
		switch ($contact)
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
	 * an article and category.
	 *
	 * @return	object		A JDatabaseQuery object.
	 */
	private function _getStateQuery()
	{
		$sql = $this->_db->getQuery(true);
		$sql->select('a.id');
		$sql->select('a.published AS state, c.published AS cat_state');
		$sql->select('a.access, c.access AS cat_access');
		$sql->from('#__contact_details AS a');
		$sql->join('LEFT', '#__categories AS c ON c.id = a.catid');

		return $sql;
	}
}