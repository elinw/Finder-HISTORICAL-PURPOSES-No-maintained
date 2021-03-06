<?php
/**
 * @version		$Id: file.php 462 2009-09-23 18:49:29Z louis $
 * @package		JXtended.Libraries
 * @subpackage	Form
 * @copyright	Copyright (C) 2008 - 2009 JXtended, LLC. All rights reserved.
 * @license		GNU General Public License
 * @link		http://jxtended.com
 */

defined('JPATH_BASE') or die;

jx('jx.form.formfield');

/**
 * Form Field class for JXtended Libraries.
 *
 * @package		JXtended.Libraries
 * @subpackage	Form
 * @since		1.1
 */
class JFormFieldFile extends JFormField
{
	/**
	 * The field type.
	 *
	 * @var		string
	 */
	public $type = 'File';

	/**
	 * Method to get the field input.
	 *
	 * @return	string		The field input.
	 */
	protected function _getInput()
	{
		$size		= $this->_element->attributes('size') ? ' size="'.$this->_element->attributes('size').'"' : '';
		$class		= $this->_element->attributes('class') ? 'class="'.$this->_element->attributes('class').'"' : 'class="text_area"';
		$onchange	= $this->_element->attributes('onchange') ? ' onchange="'.$this->_element->attributes('onchange').'"' : '';

		return '<input type="file" name="'.$this->inputName.'" id="'.$this->inputId.'" value="" '.$class.$size.$onchange.' />';
	}
}