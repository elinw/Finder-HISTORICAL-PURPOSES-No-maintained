<?php
/**
 * @version		$Id: parser.php 922 2010-03-11 20:17:33Z robs $
 * @package		JXtended.Finder
 * @subpackage	com_finder
 * @copyright	Copyright (C) 2007 - 2010 JXtended, LLC. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 * @link		http://jxtended.com
 */

defined('_JEXEC') or die;

/**
 * Parser base class for the Finder indexer package.
 *
 * @package		JXtended.Finder
 * @subpackage	com_finder
 */
abstract class FinderIndexerParser
{
	/**
	 * Method to get a parser, creating it if necessary.
	 *
	 * @param	string		The type of parser to load.
	 * @return	object		A FinderIndexerParser.
	 * @throws	JException on invalid parser.
	 */
	public static function getInstance($format)
	{
		static $instances;

		// Only create one parser for each format.
		if (isset($instances[$format])) {
			return $instances[$format];
		}

		// Create an array of instances if necessary.
		if (!is_array($instances)) {
			$instances = array();
		}

		// Setup the adapter for the parser.
		$format	= JFilterInput::clean($format, 'cmd');
		$path	= dirname(__FILE__).DS.'parser'.DS.$format.'.php';
		$class	= 'FinderIndexerParser'.ucfirst($format);

		// Check if a parser exists for the format.
		if (file_exists($path)) {
			// Instantiate the parser.
			require_once $path;
			$instances[$format] = new $class;
		}
		else {
			// Throw invalid format exception.
			throw new Exception(JText::sprintf('FINDER_INDEXER_INVALID_PARSER', $format));
		}

		return $instances[$format];
	}

	/**
	 * Method to parse input and extract the plain text. Because this method is
	 * called from both inside and outside the indexer, it needs to be able to
	 * batch out its parsing functionality to deal with the inefficiencies of
	 * regular expressions. We will parse recursively in 2KB chunks.
	 *
	 * @param	string		The input to parse.
	 * @return	string		The plain text input.
	 */
	public function parse($input)
	{
		$return	= null;

		// Parse the input in batches if bigger than 2KB.
		if (strlen($input) > 2048)
		{
			$start	= 0;
			$end	= strlen($input);
			$chunk	= 2048;

			while ($start < $end)
			{
				// Setup the string.
				$string	= substr($input, $start, $chunk);

				// Find the last space character if we aren't at the end.
				$ls = (($start + $chunk) < $end ? strrpos($string, ' ') : false);

				// Truncate to the last space character.
				if ($ls !== false) {
					$string = substr($string, 0, $ls);
				}

				// Adjust the start position for the next iteration.
				$start += ($ls !== false ? ($ls+1 - $chunk) + $chunk : $chunk);

				// Parse the chunk.
				$return .= $this->process($string);
			}
		}
		// The input is less than 2KB so we can parse it efficiently.
		else
		{
			// Parse the chunk.
			$return .= $this->process($input);
		}

		return $return;
	}

	/**
	 * Method to process input and extract the plain text.
	 *
	 * @param	string		The input to process.
	 * @return	string		The plain text input.
	 */
	abstract protected function process($input);
}