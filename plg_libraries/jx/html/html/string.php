<?php
/**
 * @version		$Id: string.php 266 2009-01-10 01:01:32Z louis $
 * @package		JXtended.Libraries
 * @subpackage	HTML
 * @copyright	Copyright (C) 2008 - 2009 JXtended, LLC. All rights reserved.
 * @license		GNU General Public License <http://www.gnu.org/copyleft/gpl.html>
 * @link		http://jxtended.com
 */

defined('JPATH_BASE') or die;

/**
 * HTML helper class for rendering manipulated strings.
 *
 * @package 	JXtended.Libraries
 * @subpackage	HTML
 * @static
 */
class JHtmlString
{
	/**
	 * Truncates text blocks over the specified character limit. The
	 * behavior will not truncate an individual word, it will find the first
	 * space that is within the limit and truncate at that point. This
	 * method is UTF-8 safe.
	 *
	 * @static
	 * @param	string	$text		The text to truncate.
	 * @param	int		$length		The maximum length of the text.
	 * @return	string	The truncated text.
	 */
	function truncate($text, $length = 0)
	{
		// Truncate the item text if it is too long.
		if ($length > 0 && JString::strlen($text) > $length)
		{
			// Find the first space within the allowed length.
			$tmp = JString::substr($text, 0, $length);
			$tmp = JString::substr($tmp, 0, JString::strrpos($tmp, ' '));

			// If we don't have 3 characters of room, go to the second space within the limit.
			if (JString::strlen($tmp) >= $length - 3) {
				$tmp = JString::substr($tmp, 0, JString::strrpos($tmp, ' '));
			}

			$text = $tmp.'...';
		}

		return $text;
	}

	/**
	 * Abridges text strings over the specified character limit. The
	 * behavior will insert an ellipsis into the text replacing a section
	 * of variable size to ensure the string does not exceed the defined
	 * maximum length. This method is UTF-8 safe.
	 *
	 *	eg. Transform "Really long title" to "Really...title"
	 *
	 * @static
	 * @param	string	$text		The text to abridge.
	 * @param	int		$length		The maximum length of the text.
	 * @param	int		$intro		The maximum length of the intro text.
	 * @return	string	The abridged text.
	 */
	function abridge($text, $length = 50, $intro = 30)
	{
		// Abridge the item text if it is too long.
		if (JString::strlen($text) > $length) {
			// Determine the remaining text length.
			$remainder = $length - ($intro + 3);

			// Extract the beginning and ending text sections.
			$beg = JString::substr($text, 0, $intro);
			$end = JString::substr($text, JString::strlen($text)-$remainder);

			// Build the resulting string.
			$text = $beg.'...'.$end;
		}

		return $text;
	}
}