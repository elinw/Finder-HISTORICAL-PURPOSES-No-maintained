<?php
/**
 * @version		$Id: editor.php 483 2009-12-14 23:49:05Z eddieajau $
 * @package		JXtended.Libraries
 * @subpackage	Form
 * @copyright	Copyright (C) 2008 - 2009 JXtended, LLC. All rights reserved.
 * @license		GNU General Public License
 * @link		http://jxtended.com
 */

defined('JPATH_BASE') or die;

jimport('joomla.html.editor');
jx('jx.form.formfield');

/**
 * Form Field class for JXtended Libraries.
 *
 * @package		JXtended.Libraries
 * @subpackage	Form
 * @since		1.1
 */
class JFormFieldEditor extends JFormField
{
	/**
	 * The field type.
	 *
	 * @var		string
	 */
	public $type = 'Editor';

	/**
	 * A refenence to the editor object.
	 *
	 * @var	object.
	 */
	protected $_editor = null;

	/**
	 * Method to get the field input.
	 *
	 * @return	string		The field input.
	 */
	protected function _getInput()
	{
		$rows		= $this->_element->attributes('rows');
		$cols		= $this->_element->attributes('cols');
		$height		= ($this->_element->attributes('height')) ? $this->_element->attributes('height') : '250';
		$width		= ($this->_element->attributes('width')) ? $this->_element->attributes('width') : '100%';
		$class		= ($this->_element->attributes('class') ? 'class="'.$this->_element->attributes('class').'"' : 'class="text_area"');
		$buttons	= $this->_element->attributes('buttons');

		if ($buttons == 'true' || $buttons == 'yes' || $buttons == 1) {
			$buttons = true;
		}
		else if ($buttons == 'false' || $buttons == 'no' || $buttons == 0) {
			$buttons = false;
		}
		else {
			$buttons = explode(',', $buttons);
		}

		$editor = $this->_getEditor();

		return $editor->display($this->inputName, htmlspecialchars($this->value), $width, $height, $cols, $rows, $buttons, $this->inputId);
	}

	/**
	 * Get the editor object.
	 *
	 * @return	object
	 */
	protected function &_getEditor()
	{
		if (empty($this->_editor))
		{
			// editor attribute can be in the form of:
			// editor="desired|alternative"
			if ($editorName = trim($this->_element->attributes('editor')))
			{
				$parts	= explode('|', $editorName);
				$db		= &JFactory::getDbo();
				$query	= 'SELECT element' .
						' FROM #__plugins' .
						' WHERE element	= '.$db->Quote($parts[0]) .
						'  AND folder = '.$db->Quote('editors') .
						'  AND published = 1';
				$db->setQuery($query);
				if ($db->loadResult()) {
					$editorName	= trim($parts[0]);
				}
				else if (isset($parts[1])) {
					$editorName	= trim($parts[1]);
				}
				else {
					$editorName	= '';
				}
				$this->_element->addAttribute('editor', $editorName);
			}
			$this->_editor = JFactory::getEditor($editorName ? $editorName : null);
		}
		return $this->_editor;
	}

	/**
	 * Get the internal reference to the editor.
	 *
	 * @return	string
	 */
	public function save()
	{
		return $this->_editor->save($this->inputId);
	}
}