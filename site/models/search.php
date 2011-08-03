<?php
/**
 * @version		$Id: search.php 1068 2010-10-05 15:55:57Z robs $
 * @package		JXtended.Finder
 * @subpackage	com_finder
 * @copyright	Copyright (C) 2007 - 2010 JXtended, LLC. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 * @link		http://jxtended.com
 */

defined('_JEXEC') or die;

// Register dependent classes.
define('FINDER_PATH_INDEXER', JPATH_ADMINISTRATOR.DS.'components'.DS.'com_finder'.DS.'helpers'.DS.'indexer');
JLoader::register('FinderIndexerHelper', FINDER_PATH_INDEXER.DS.'helper.php');
JLoader::register('FinderIndexerQuery', FINDER_PATH_INDEXER.DS.'query.php');
JLoader::register('FinderIndexerResult', FINDER_PATH_INDEXER.DS.'result.php');

jx('jx.application.component.modellist');

/**
 * Search model class for the Finder package.
 *
 * @package		JXtended.Finder
 * @subpackage	com_finder
 */
class FinderModelSearch extends JModelList
{
	/**
	 * Context string for the model type.  This is used to handle uniqueness
	 * when dealing with the _getStoreId() method and caching data structures.
	 *
	 * @var		string
	 */
	protected $_context = 'com_finder.search';

	/**
	 * The query object is an instance of FinderIndexerQuery which contains and
	 * models the entire search query including the text input; static and
	 * dynamic taxonomy filters; date filters; etc.
	 *
	 * @var		object		A FinderIndexerQuery object.
	 */
	protected $_query;

	/**
	 * @var		array		An array of all excluded terms ids.
	 */
	protected $_excludedTerms = array();

	/**
	 * @var		array		An array of all included terms ids.
	 */
	protected $_includedTerms = array();

	/**
	 * @var		array		An array of all required terms ids.
	 */
	protected $_requiredTerms = array();

	/**
	 * Method to get the results of the query.
	 *
	 * @return	array		An array of FinderIndexerResult objects.
	 * @throws	Exception on database error.
	 */
	public function getResults()
	{
		// Check if the search query is valid.
		if (empty($this->_query->search)) {
			return null;
		}

		// Check if we should return results.
		if (empty($this->_includedTerms) && (empty($this->_query->filters) || !$this->_query->empty)) {
			return null;
		}

		// Get the store id.
		$store = $this->_getStoreId('getResults');

		// Use the cached data if possible.
		if ($this->_retrieve($store)) {
			return $this->_retrieve($store);
		}

		// Get the row data.
		$items = $this->_getResultsData();

		// Check the data.
		if (empty($items)) {
			return null;
		}

		// Create the query to get the search results.
		$sql = new JDatabaseQuery();
		$sql->select('link_id, object');
		$sql->from('#__jxfinder_links');
		$sql->where('link_id IN ('.implode(',', array_keys($items)).')');

		// Load the results from the database.
		$this->_db->setQuery($sql);
		$rows = $this->_db->loadObjectList('link_id');

		// Check for a database error.
		if ($this->_db->getErrorNum()) {
			throw new Exception($this->_db->getErrorMsg(), 500);
		}

		// Set up our results container.
		$results = $items;

		// Convert the rows to result objects.
		foreach ($rows as $rk => $row)
		{
			// Build the result object.
			$result				= unserialize($row->object);
			$result->weight		= $results[$rk];
			$result->link_id	= $rk;

			// Add the result back to the stack.
			$results[$rk] = $result;
		}

		// Switch to a non-associative array.
		$results = array_values($results);

		// Push the results into cache.
		$this->_store($store, $results);

		// Return the results.
		return $this->_retrieve($store);
	}

	/**
	 * Method to get the total number of results.
	 *
	 * @return	integer		The total number of results.
	 * @throws	Exception on database error.
	 */
	public function getTotal()
	{
		// Check if the search query is valid.
		if (empty($this->_query->search)) {
			return null;
		}

		// Check if we should return results.
		if (empty($this->_includedTerms) && (empty($this->_query->filters) || !$this->_query->empty)) {
			return null;
		}

		// Get the store id.
		$store = $this->_getStoreId('getTotal');

		// Use the cached data if possible.
		if ($this->_retrieve($store)) {
			return $this->_retrieve($store);
		}

		// Get the results total.
		$total = $this->_getResultsTotal();

		// Push the total into cache.
		$this->_store($store, $total);

		// Return the total.
		return $this->_retrieve($store);
	}

	/**
	 * Method to get the query object.
	 *
	 * @return	object		A FinderIndexerQuery object.
	 */
	public function getQuery()
	{
		// Get the state in case it isn't loaded.
		$state = $this->getState();

		// Return the query object.
		return $this->_query;
	}

	/**
	 * Method to get the base database query for the search query.
	 *
	 * @return	object		A JDatabaseQuery object.
	 */
	protected function _getListQuery()
	{
		// Get the store id.
		$store = $this->_getStoreId('_getListQuery');

		// Use the cached data if possible.
		if ($this->_retrieve($store, false)) {
			return clone($this->_retrieve($store, false));
		}

		// Create the base query.
		$sql = new JDatabaseQuery();
		$sql->select('l.link_id');
		$sql->from('#__jxfinder_links AS l');
		$sql->where('l.access <= '.(int)$this->getState('user.aid'));
		$sql->where('l.state = 1');
		$sql->where('l.published = 1');

		// Get the null date and the current date, minus seconds.
		$nullDate	= $this->_db->quote($this->_db->getNullDate());
		$nowDate	= $this->_db->quote(substr_replace(JFactory::getDate()->toMySQL(), '00', -2));

		// Add the publish up and publish down filters.
		$sql->where('(l.publish_start_date = '.$nullDate.' OR l.publish_start_date <= '.$nowDate.')');
		$sql->where('(l.publish_end_date = '.$nullDate.' OR l.publish_end_date >= '.$nowDate.')');

		/*
		 * Add the taxonomy filters to the query. We have to join the taxonomy
		 * map table for each group so that we can use AND clauses across
		 * groups. Within each group there can be an array of values that will
		 * use OR clauses.
		 */
		if (!empty($this->_query->filters))
		{
			// Convert the associative array to a numerically indexed array.
			$groups = array_values($this->_query->filters);

			// Iterate through each taxonomy group and add the join and where.
			for ($i = 0, $c = count($groups); $i < $c; $i++)
			{
				// We use the offset because each join needs a unique alias.
				$sql->join('INNER', '#__jxfinder_taxonomy_map AS t'.$i.' ON t'.$i.'.link_id = l.link_id');
				$sql->where('t'.$i.'.node_id IN ('.implode(',', $groups[$i]).')');
			}
		}

		/*
		 * Add the start date filter to the query.
		 */
		if (!empty($this->_query->date1))
		{
			// Escape the date.
			$date1 = $this->_db->quote($this->_query->date1);

			// Add the appropriate WHERE condition.
			if ($this->_query->when1 == 'before') {
				$sql->where('l.start_date <= '.$date1);
			} else if ($this->_query->when1 == 'after') {
				$sql->where('l.start_date >= '.$date1);
			} else {
				$sql->where('l.start_date = '.$date1);
			}
		}

		/*
		 * Add the end date filter to the query.
		 */
		if (!empty($this->_query->date2))
		{
			// Escape the date.
			$date2 = $this->_db->quote($this->_query->date2);

			// Add the appropriate WHERE condition.
			if ($this->_query->when2 == 'before') {
				$sql->where('l.start_date <= '.$date2);
			} else if ($this->_query->when2 == 'after') {
				$sql->where('l.start_date >= '.$date2);
			} else {
				$sql->where('l.start_date = '.$date2);
			}
		}

		// Push the data into cache.
		$this->_store($store, $sql, false);

		// Return a copy of the query object.
		return clone($this->_retrieve($store, false));
	}

	/**
	 * Method to get the total number of results for the search query.
	 *
	 * @return	integer		The results total.
	 * @throws	Exception on database error.
	 */
	protected function _getResultsTotal()
	{
		// Get the store id.
		$store = $this->_getStoreId('_getResultsTotal', false);

		// Use the cached data if possible.
		if ($this->_retrieve($store)) {
			return $this->_retrieve($store);
		}

		// Get the base query and add the ordering information.
		$base = $this->_getListQuery();
		$base->select('0 AS ordering');

		// Get the maximum number of results.
		$limit = (int)$this->getState('match.limit');

		/*
		 * If there are no optional or required search terms in the query,
		 * we can get the result total in one relatively simple database query.
		 */
		if (empty($this->_includedTerms))
		{
			// Adjust the query to join on the appropriate mapping table.
			$sql = clone($base);
			$sql->clear('select')->select('COUNT(l.link_id)');

			// Get the total from the database.
			$this->_db->setQuery($sql);
			$total = $this->_db->loadResult();

			// Check for a database error.
			if ($this->_db->getErrorNum()) {
				throw new Exception($this->_db->getErrorMsg(), 500);
			}

			// Push the total into cache.
			$this->_store($store, min($total, $limit));

			// Return the total.
			return $this->_retrieve($store);
		}

		/*
		 * If there are optional or required search terms in the query, the
		 * process of getting the result total is more complicated.
		 */
		$start		= 0;
		$total		= 0;
		$more		= false;
		$items		= array();
		$sorted		= array();
		$maps		= array();
		$excluded	= $this->_getExcludedLinkIds();

		/*
		 * Iterate through the included search terms and group them by mapping
		 * table suffix. This ensures that we never have to do more than 16
		 * queries to get a batch. This may seem like a lot but it is rarely
		 * anywhere near 16 because of the improved mapping algorithm.
		 */
		foreach ($this->_includedTerms as $token => $ids)
		{
			// Get the mapping table suffix.
			$suffix = JString::substr(md5(JString::substr($token, 0, 1)), 0, 1);

			// Initialize the mapping group.
			if (!array_key_exists($suffix, $maps)) {
				$maps[$suffix] = array();
			}
			// Add the terms to the mapping group.
			$maps[$suffix] = array_merge($maps[$suffix], $ids);
		}

		/*
		 * When the query contains search terms we need to find and process the
		 * result total iteratively using a do-while loop.
		 */
		do {
			// Create a container for the fetched results.
			$results 	= array();
			$more		= false;

			/*
			 * Iterate through the mapping groups and load the total from each
			 * mapping table.
			 */
			foreach ($maps as $suffix => $ids)
			{
				// Create a storage key for this set.
				$setId = $this->_getStoreId('_getResultsTotal:'.serialize(array_values($ids)).':'.$start.':'.$limit);

				// Use the cached data if possible.
				if ($this->_retrieve($setId)) {
					$temp = $this->_retrieve($setId);
				}
				// Load the data from the database.
				else
				{
					// Adjust the query to join on the appropriate mapping table.
					$sql = clone($base);
					$sql->join('INNER', '#__jxfinder_links_terms'.$suffix.' AS m ON m.link_id = l.link_id');
					$sql->where('m.term_id IN ('.implode(',', $ids).')');

					// Load the results from the database.
					$this->_db->setQuery($sql, $start, $limit);
					$temp = $this->_db->loadObjectList();

					// Check for a database error.
					if ($this->_db->getErrorNum()) {
						throw new Exception($this->_db->getErrorMsg(), 500);
					}

					// Set the more flag to true if any of the sets equal the limit.
					$more = (count($temp) === $limit) ? true : false;

					// We loaded the data unkeyed but we need it to be keyed for later.
					$junk = $temp;
					$temp = array();

					// Convert to an associative array.
					for ($i = 0, $c = count($junk); $i < $c; $i++) {
						$temp[$junk[$i]->link_id] = $junk[$i];
					}

					// Store this set in cache.
					$this->_store($setId, $temp);
				}

				// Merge the results.
				$results = array_merge($results, $temp);
			}

			// Check if there are any excluded terms to deal with.
			if (count($excluded))
			{
				// Remove any results that match excluded terms.
				for ($i = 0, $c = count($results); $i < $c; $i++) {
					if (in_array($results[$i]->link_id, $excluded)) unset($results[$i]);
				}

				// Reset the array keys.
				$results = array_values($results);
			}

			// Iterate through the set to extract the unique items.
			for ($i = 0, $c = count($results); $i < $c; $i++)
			{
				if (!isset($sorted[$results[$i]->link_id])) {
					$sorted[$results[$i]->link_id] = $results[$i]->ordering;
				}
			}

			/*
			 * If the query contains just optional search terms and we have
			 * enough items for the page, we can stop here.
			 */
			if (empty($this->_requiredTerms))
			{
				// If we need more items and they're available, make another pass.
				if ($more && count($sorted) < $limit)
				{
					// Increment the batch starting point and continue.
					$start += $limit;
					continue;
				}

				// Push the total into cache.
				$this->_store($store, min(count($sorted), $limit));

				// Return the total.
				return $this->_retrieve($store);
			}

			/*
			 * The query contains required search terms so we have to iterate
			 * over the items and remove any items that do not match all of the
			 * required search terms. This is one of the most expensive steps
			 * because a required token could theoretically eliminate all of
			 * current terms which means we would have to loop through all of
			 * the possibilities.
			 */
			foreach ($this->_requiredTerms as $token => $required)
			{
				// Create a storage key for this set.
				$setId = $this->_getStoreId('_getResultsTotal:required:'.serialize(array_values($required)).':'.$start.':'.$limit);

				// Use the cached data if possible.
				if ($this->_retrieve($setId)) {
					$reqTemp = $this->_retrieve($setId);
				}
				// Check if the token was matched.
				elseif (empty($required)) {
					return null;
				}
				// Load the data from the database.
				else
				{
					// Setup containers in case we have to make multiple passes.
					$reqMore	= false;
					$reqStart	= 0;
					$reqTemp	= array();

					do {
						// Get the map table suffix.
						$suffix = JString::substr(md5(JString::substr($token, 0, 1)), 0, 1);

						// Adjust the query to join on the appropriate mapping table.
						$sql = clone($base);
						$sql->join('INNER', '#__jxfinder_links_terms'.$suffix.' AS m ON m.link_id = l.link_id');
						$sql->where('m.term_id IN ('.implode(',', $required).')');

						// Load the results from the database.
						$this->_db->setQuery($sql, $reqStart, $limit);
						$temp = $this->_db->loadObjectList('link_id');

						// Check for a database error.
						if ($this->_db->getErrorNum()) {
							throw new Exception($this->_db->getErrorMsg(), 500);
						}

						// Set the required token more flag to true if the set equal the limit.
						$reqMore = (count($temp) === $limit) ? true : false;

						// Merge the matching set for this token.
						$reqTemp = $reqTemp + $temp;

						// Increment the term offset.
						$reqStart += $limit;

					} while ($reqMore == true);

					// Store this set in cache.
					$this->_store($setId, $reqTemp);
				}

				// Remove any items that do not match the required term.
				$sorted = array_intersect_key($sorted, $reqTemp);
			}

			// If we need more items and they're available, make another pass.
			if ($more && count($sorted) < $limit)
			{
				// Increment the batch starting point.
				$start += $limit;

				// Merge the found items.
				$items = $items + $sorted;

				continue;
			}
			// Otherwise, end the loop.
			{
				// Merge the found items.
				$items = $items + $sorted;

				$more = false;
			}

		// End do-while loop.
		} while ($more === true);

		// Set the total.
		$total = count($items);
		$total = min($total, $limit);

		// Push the total into cache.
		$this->_store($store, $total);

		// Return the total.
		return $this->_retrieve($store);
	}

	/**
	 * Method to get the results for the search query.
	 *
	 * @return	array		An array of result data objects.
	 * @throws	Exception on database error.
	 */
	protected function _getResultsData()
	{
		// Get the store id.
		$store = $this->_getStoreId('_getResultsData', false);

		// Use the cached data if possible.
		if ($this->_retrieve($store)) {
			return $this->_retrieve($store);
		}

		// Get the result ordering and direction.
		$ordering	= $this->getState('list.ordering', 'l.start_date');
		$direction	= $this->getState('list.direction', 'DESC');

		// Get the base query and add the ordering information.
		$base = $this->_getListQuery();
		$base->select($this->_db->getEscaped($ordering).' AS ordering');
		$base->order($this->_db->getEscaped($ordering).' '.$this->_db->getEscaped($direction));

		/*
		 * If there are no optional or required search terms in the query, we
		 * can get the results in one relatively simple database query.
		 */
		if (empty($this->_includedTerms))
		{
			// Get the results from the database.
			$this->_db->setQuery($base, $this->getState('list.start'), $this->getState('list.limit'));
			$return = $this->_db->loadObjectList('link_id');

			// Check for a database error.
			if ($this->_db->getErrorNum()) {
				throw new Exception($this->_db->getErrorMsg(), 500);
			}

			// Get a new store id because this data is page specific.
			$store = $this->_getStoreId('_getResultsData', true);

			// Push the results into cache.
			$this->_store($store, $return);

			// Return the results.
			return $this->_retrieve($store);
		}

		/*
		 * If there are optional or required search terms in the query, the
		 * process of getting the results is more complicated.
		 */
		$start		= 0;
		$limit		= (int)$this->getState('match.limit');
		$more		= false;
		$items		= array();
		$sorted		= array();
		$maps		= array();
		$excluded	= $this->_getExcludedLinkIds();

		/*
		 * Iterate through the included search terms and group them by mapping
		 * table suffix. This ensures that we never have to do more than 16
		 * queries to get a batch. This may seem like a lot but it is rarely
		 * anywhere near 16 because of the improved mapping algorithm.
		 */
		foreach ($this->_includedTerms as $token => $ids)
		{
			// Get the mapping table suffix.
			$suffix = JString::substr(md5(JString::substr($token, 0, 1)), 0, 1);

			// Initialize the mapping group.
			if (!array_key_exists($suffix, $maps)) {
				$maps[$suffix] = array();
			}
			// Add the terms to the mapping group.
			$maps[$suffix] = array_merge($maps[$suffix], $ids);
		}

		/*
		 * When the query contains search terms we need to find and process the
		 * results iteratively using a do-while loop.
		 */
		do {
			// Create a container for the fetched results.
			$results 	= array();
			$more		= false;

			/*
			 * Iterate through the mapping groups and load the results from each
			 * mapping table.
			 */
			foreach ($maps as $suffix => $ids)
			{
				// Create a storage key for this set.
				$setId = $this->_getStoreId('_getResultsData:'.serialize(array_values($ids)).':'.$start.':'.$limit);

				// Use the cached data if possible.
				if ($this->_retrieve($setId)) {
					$temp = $this->_retrieve($setId);
				}
				// Load the data from the database.
				else
				{
					// Adjust the query to join on the appropriate mapping table.
					$sql = clone($base);
					$sql->join('INNER', '#__jxfinder_links_terms'.$suffix.' AS m ON m.link_id = l.link_id');
					$sql->where('m.term_id IN ('.implode(',', $ids).')');

					// Load the results from the database.
					$this->_db->setQuery($sql, $start, $limit);
					$temp = $this->_db->loadObjectList('link_id');

					// Check for a database error.
					if ($this->_db->getErrorNum()) {
						throw new Exception($this->_db->getErrorMsg(), 500);
					}

					// Store this set in cache.
					$this->_store($setId, $temp);

					// The data is keyed by link_id to ease caching, we don't need it till later.
					$temp = array_values($temp);
				}

				// Set the more flag to true if any of the sets equal the limit.
				$more = (count($temp) === $limit) ? true : false;

				// Merge the results.
				$results = array_merge($results, $temp);
			}

			// Check if there are any excluded terms to deal with.
			if (count($excluded))
			{
				// Remove any results that match excluded terms.
				for ($i = 0, $c = count($results); $i < $c; $i++) {
					if (in_array($results[$i]->link_id, $excluded)) unset($results[$i]);
				}

				// Reset the array keys.
				$results = array_values($results);
			}

			/*
			 * If we are ordering by relevance we have to add up the relevance
			 * scores that are contained in the ordering field.
			 */
			if ($ordering === 'm.weight')
			{
				// Iterate through the set to extract the unique items.
				for ($i = 0, $c = count($results); $i < $c; $i++)
				{
					// Add the total weights for all included search terms.
					if (isset($sorted[$results[$i]->link_id])) {
						$sorted[$results[$i]->link_id] += (float)$results[$i]->ordering;
					} else {
						$sorted[$results[$i]->link_id] = (float)$results[$i]->ordering;
					}
				}
			}
			/*
			 * If we are ordering by start date we have to add convert the
			 * dates to unix timestamps.
			 */
			elseif ($ordering === 'l.start_date')
			{
				// Iterate through the set to extract the unique items.
				for ($i = 0, $c = count($results); $i < $c; $i++)
				{
					if (!isset($sorted[$results[$i]->link_id])) {
						$sorted[$results[$i]->link_id] = strtotime($results[$i]->ordering);
					}
				}
			}
			/*
			 * If we are not ordering by relevance or date, we just have to add
			 * the unique items to the set.
			 */
			else
			{
				// Iterate through the set to extract the unique items.
				for ($i = 0, $c = count($results); $i < $c; $i++)
				{
					if (!isset($sorted[$results[$i]->link_id])) {
						$sorted[$results[$i]->link_id] = $results[$i]->ordering;
					}
				}
			}

			/*
			 * Sort the results.
			 */
			if ($direction === 'ASC') {
				natcasesort($items);
			} else {
				natcasesort($items);
				$items = array_reverse($items, true);
			}

			/*
			 * If the query contains just optional search terms and we have
			 * enough items for the page, we can stop here.
			 */
			if (empty($this->_requiredTerms))
			{
				// If we need more items and they're available, make another pass.
				if ($more && count($sorted) < ($this->getState('list.start') + $this->getState('list.limit')))
				{
					// Increment the batch starting point and continue.
					$start += $limit;
					continue;
				}

				// Push the results into cache.
				$this->_store($store, $sorted);

				// Return the requested set.
				return array_slice($this->_retrieve($store), $this->getState('list.start'), $this->getState('list.limit'), true);
			}

			/*
			 * The query contains required search terms so we have to iterate
			 * over the items and remove any items that do not match all of the
			 * required search terms. This is one of the most expensive steps
			 * because a required token could theoretically eliminate all of
			 * current terms which means we would have to loop through all of
			 * the possibilities.
			 */
			foreach ($this->_requiredTerms as $token => $required)
			{
				// Create a storage key for this set.
				$setId = $this->_getStoreId('_getResultsData:required:'.serialize(array_values($required)).':'.$start.':'.$limit);

				// Use the cached data if possible.
				if ($this->_retrieve($setId)) {
					$reqTemp = $this->_retrieve($setId);
				}
				// Check if the token was matched.
				elseif (empty($required)) {
					return null;
				}
				// Load the data from the database.
				else
				{
					// Setup containers in case we have to make multiple passes.
					$reqMore	= false;
					$reqStart	= 0;
					$reqTemp	= array();

					do {
						// Get the map table suffix.
						$suffix = JString::substr(md5(JString::substr($token, 0, 1)), 0, 1);

						// Adjust the query to join on the appropriate mapping table.
						$sql = clone($base);
						$sql->join('INNER', '#__jxfinder_links_terms'.$suffix.' AS m ON m.link_id = l.link_id');
						$sql->where('m.term_id IN ('.implode(',', $required).')');

						// Load the results from the database.
						$this->_db->setQuery($sql, $reqStart, $limit);
						$temp = $this->_db->loadObjectList('link_id');

						// Check for a database error.
						if ($this->_db->getErrorNum()) {
							throw new Exception($this->_db->getErrorMsg(), 500);
						}

						// Set the required token more flag to true if the set equal the limit.
						$reqMore = (count($temp) === $limit) ? true : false;

						// Merge the matching set for this token.
						$reqTemp = $reqTemp + $temp;

						// Increment the term offset.
						$reqStart += $limit;

					} while ($reqMore == true);

					// Store this set in cache.
					$this->_store($setId, $reqTemp);
				}

				// Remove any items that do not match the required term.
				$sorted = array_intersect_key($sorted, $reqTemp);
			}

			// If we need more items and they're available, make another pass.
			if ($more && count($sorted) < ($this->getState('list.start') + $this->getState('list.limit')))
			{
				// Increment the batch starting point.
				$start += $limit;

				// Merge the found items.
				$items = array_merge($items, $sorted);

				continue;
			}
			// Otherwise, end the loop.
			{
				// Set the found items.
				$items = $sorted;

				$more = false;
			}

		// End do-while loop.
		} while ($more === true);

		// Push the results into cache.
		$this->_store($store, $items);

		// Return the requested set.
		return array_slice($this->_retrieve($store), $this->getState('list.start'), $this->getState('list.limit'), true);
	}

	/**
	 * Method to get an array of link ids that match excluded terms.
	 *
	 * @return	array		An array of links ids.
	 * @throws	Exception on database error.
	 */
	protected function _getExcludedLinkIds()
	{
		// Check if the search query has excluded terms.
		if (empty($this->_excludedTerms)) {
			return array();
		}

		// Get the store id.
		$store = $this->_getStoreId('_getExcludedLinkIds', false);

		// Use the cached data if possible.
		if ($this->_retrieve($store)) {
			return $this->_retrieve($store);
		}

		// Initialize containers.
		$links	= array();
		$maps	= array();

		/*
		 * Iterate through the excluded search terms and group them by mapping
		 * table suffix. This ensures that we never have to do more than 16
		 * queries to get a batch. This may seem like a lot but it is rarely
		 * anywhere near 16 because of the improved mapping algorithm.
		 */
		foreach ($this->_excludedTerms as $token => $id)
		{
			// Get the mapping table suffix.
			$suffix = JString::substr(md5(JString::substr($token, 0, 1)), 0, 1);

			// Initialize the mapping group.
			if (!array_key_exists($suffix, $maps)) {
				$maps[$suffix] = array();
			}

			// Add the terms to the mapping group.
			$maps[$suffix][] = (int)$id;
		}

		/*
		 * Iterate through the mapping groups and load the excluded links ids
		 * from each mapping table.
		 */
		foreach ($maps as $suffix => $ids)
		{
			// Create the query to get the links ids.
			$sql = new JDatabaseQuery();
			$sql->select('link_id');
			$sql->from('#__jxfinder_links_terms'.$suffix);
			$sql->where('term_id IN ('.implode(',', $ids).')');
			$sql->group('link_id');

			// Load the link ids from the database.
			$this->_db->setQuery($sql);
			$temp = $this->_db->loadResultArray();

			// Check for a database error.
			if ($this->_db->getErrorNum()) {
				throw new Exception($this->_db->getErrorMsg(), 500);
			}

			// Merge the link ids.
			$links = array_merge($links, $temp);
		}

		// Sanitize the link ids.
		$links = array_unique($links);
		JArrayHelper::toInteger($links);

		// Push the link ids into cache.
		$this->_store($store, $links);

		return $links;
	}

	/**
	 * Method to get a subquery for filtering link ids mapped to specific
	 * terms ids.
	 *
	 * @param	array		An array of search term ids.
	 * @return	object		A JDatabaseQuery object.
	 */
	protected function _getTermsQuery($terms)
	{
		// Create the SQL query to get the matching link ids.
		$sql = new JDatabaseQuery();
		$sql->select('SQL_NO_CACHE link_id');
		$sql->from('#__jxfinder_links_terms');
		$sql->where('term_id IN ('.implode(',', $terms).')');

		return $sql;
	}

	/**
	 * Method to get a store id based on model the configuration state.
	 *
	 * This is necessary because the model is used by the component and
	 * different modules that might need different sets of data or different
	 * ordering requirements.
	 *
	 * @param	string	An identifier string to generate the store id.
	 * @return	string	A store id.
	 */
	protected function _getStoreId($id = '', $page = true)
	{
		// Get the query object.
		$query = $this->getQuery();

		// Add the search query state.
		$id .= ':'.$query->input;
		$id .= ':'.$query->language;
		$id .= ':'.$query->filter;
		$id .= ':'.serialize($query->filters);
		$id .= ':'.$query->date1;
		$id .= ':'.$query->date2;
		$id .= ':'.$query->when1;
		$id .= ':'.$query->when2;

		if ($page)
		{
			// Add the list state for page specific data.
			$id	.= ':'.$this->getState('list.start');
			$id	.= ':'.$this->getState('list.limit');
			$id	.= ':'.$this->getState('list.ordering');
			$id	.= ':'.$this->getState('list.direction');
		}

		// Add the user access state.
		$id .= ':'.$this->getState('user.aid');

		return parent::_getStoreId($id);
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * This method should only be called once per instantiation and is designed
	 * to be called on the first call to the getState() method unless the model
	 * configuration flag to ignore the request is set.
	 *
	 * @return	void
	 */
	protected function _populateState()
	{
		// Get the configuration options.
		$app	= JFactory::getApplication();
		$params	= $app->getParams('com_finder');
		$user	= JFactory::getUser();

		// Setup the stemmer.
		if ($params->get('stem', 1) && $params->get('stemmer', 'porter_en')) {
			FinderIndexerHelper::$stemmer = FinderIndexerStemmer::getInstance($params->get('stemmer', 'porter_en'));
		}

		// Initialize variables.
		$request = JRequest::get('request');
		$options = array();

		// Get the query string.
		$options['input'] = isset($request['q']) ? $request['q'] : $params->get('q');
		$options['input'] = JFilterInput::clean($options['input'], 'string');

		// Get the empty query setting.
		$options['empty'] = $params->get('allow_empty_query', 0);

		// Get the query language.
		$options['language'] = isset($request['l']) ? $request['l'] : $params->get('l');
		$options['language'] = JFilterInput::clean($options['language'], 'cmd');

		// Get the static taxonomy filters.
		$options['filter'] = isset($request['f']) ? $request['f'] : $params->get('f');
		$options['filter'] = JFilterInput::clean($options['filter'], 'int');

		// Get the dynamic taxonomy filters.
		$options['filters'] = isset($request['t']) ? $request['t'] : array();
		$options['filters'] = JFilterInput::clean($options['filters'], 'array');
		JArrayHelper::toInteger($options['filters']);

		// Get the start date and start date modifier filters.
		$options['date1'] = isset($request['d1']) ? $request['d1'] : $params->get('d1');
		$options['date1'] = JFilterInput::clean($options['date1'], 'string');
		$options['when1'] = isset($request['w1']) ? $request['w1'] : $params->get('w1');
		$options['when1'] = JFilterInput::clean($options['when1'], 'string');

		// Get the end date and end date modifier filters.
		$options['date2'] = isset($request['d2']) ? $request['d2'] : $params->get('d2');
		$options['date2'] = JFilterInput::clean($options['date2'], 'string');
		$options['when2'] = isset($request['w2']) ? $request['w2'] : $params->get('w2');
		$options['when2'] = JFilterInput::clean($options['when2'], 'string');

		// Load the query object.
		$this->_query = new FinderIndexerQuery($options);

		// Load the query token data.
		$this->_excludedTerms = $this->_query->getExcludedTermIds();
		$this->_includedTerms = $this->_query->getIncludedTermIds();
		$this->_requiredTerms = $this->_query->getRequiredTermIds();

		// Load the list state.
		$this->setState('list.start', JRequest::getInt('limitstart', 0));
		$this->setState('list.limit', JRequest::getInt('limit', $app->getCfg('list_limit', 20)));

		// Load the list ordering.
		$order = $params->get('search_order', 'relevance_dsc');
		switch ($order)
		{
			case ($order == 'relevance_asc' && !empty($this->_includedTerms)):
				$this->setState('list.ordering', 'm.weight');
				$this->setState('list.direction', 'ASC');
				break;

			case ($order == 'relevance_dsc' && !empty($this->_includedTerms)):
				$this->setState('list.ordering', 'm.weight');
				$this->setState('list.direction', 'DESC');
				break;

			case 'date_asc':
				$this->setState('list.ordering', 'l.start_date');
				$this->setState('list.direction', 'ASC');
				break;

			default:
			case 'date_dsc':
				$this->setState('list.ordering', 'l.start_date');
				$this->setState('list.direction', 'DESC');
				break;

			case 'price_asc':
				$this->setState('list.ordering', 'l.list_price');
				$this->setState('list.direction', 'ASC');
				break;

			case 'price_dsc':
				$this->setState('list.ordering', 'l.list_price');
				$this->setState('list.direction', 'DESC');
				break;
		}

		// Set the match limit.
		$this->setState('match.limit', 1000);

		// Load the parameters.
		$this->setState('params', $params);

		// Load the user state.
		$this->setState('user.id', (int)$user->get('id'));
		$this->setState('user.aid',	(int)$user->get('aid'));
	}
}