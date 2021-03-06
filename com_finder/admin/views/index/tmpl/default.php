<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_finder
 *
 * @copyright   Copyright (C) 2005 - 2011 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

JHtml::_('behavior.tooltip');

$listOrder	= $this->escape($this->state->get('list.ordering'));
$listDirn	= $this->escape($this->state->get('list.direction'));

$lang = &JFactory::getLanguage();
JText::script('COM_FINDER_INDEX_CONFIRM_PURGE_PROMPT');
JText::script('COM_FINDER_INDEX_CONFIRM_DELETE_PROMPT');
?>

<script type="text/javascript">
Joomla.submitbutton = function(pressbutton) {
	if (pressbutton == 'index.purge') {
		if (confirm(Joomla.JText._('COM_FINDER_INDEX_CONFIRM_PURGE_PROMPT'))) {
			Joomla.submitform(pressbutton);
		} else {
			return false;
		}
	}
	if (pressbutton == 'index.delete') {
		if (confirm(Joomla.JText._('COM_FINDER_INDEX_CONFIRM_DELETE_PROMPT'))) {
			Joomla.submitform(pressbutton);
		} else {
			return false;
		}
	}

	Joomla.submitform(pressbutton);
}
</script>

<form action="<?php echo JRoute::_('index.php?option=com_finder&view=index');?>" method="post" name="adminForm" id="adminForm">
	<fieldset id="filter-bar">
		<div class="filter-search fltlft">
			<label class="filter-search-lbl" for="filter_search"><?php echo JText::sprintf('COM_FINDER_SEARCH_LABEL', JText::_('COM_FINDER_ITEMS')); ?></label>
			<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->escape($this->state->get('filter.search')); ?>" title="<?php echo JText::_('COM_FINDER_FILTER_SEARCH_DESCRIPTION'); ?>" />
			<button type="submit" class="btn"><?php echo JText::_('JSEARCH_FILTER_SUBMIT'); ?></button>
			<button type="button" onclick="document.id('filter_search').value='';this.form.submit();"><?php echo JText::_('JSEARCH_FILTER_CLEAR'); ?></button>
		</div>
		<div class="filter-select fltrt">
			<select name="filter_type" class="inputbox" onchange="this.form.submit()">
				<option value=""><?php echo JText::_('COM_FINDER_INDEX_TYPE_FILTER');?></option>
				<?php echo JHtml::_('select.options', JHtml::_('finder.typeslist'), 'value', 'text', $this->state->get('filter.type'), true);?>
			</select>
			<select name="filter_state" class="inputbox" onchange="this.form.submit()">
				<option value=""><?php echo JText::_('COM_FINDER_INDEX_FILTER_BY_STATE');?></option>
				<?php echo JHtml::_('select.options', JHtml::_('finder.statelist'), 'value', 'text', $this->state->get('filter.state'), true);?>
			</select>
		</div>
	</fieldset>
	<div class="clr"> </div>

	<table class="adminlist" style="clear: both;">
		<thead>
			<tr>
				<th width="1%">
					<input type="checkbox" name="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
				</th>
				<th>
					<?php echo JHtml::_('grid.sort', 'JGLOBAL_TITLE', 'l.title', $listDirn, $listOrder); ?>
				</th>
				<th width="5%">
					<?php echo JHtml::_('grid.sort', 'JSTATUS', 'l.published', $listDirn, $listOrder); ?>
				</th>
				<th width="5%">
					<?php echo JHtml::_('grid.sort', 'COM_FINDER_INDEX_HEADING_INDEX_TYPE', 'l.type_id', $listDirn, $listOrder); ?>
				</th>
				<th width="20%">
					<?php echo JHtml::_('grid.sort', 'COM_FINDER_INDEX_HEADING_LINK_URL', 'l.url', $listDirn, $listOrder); ?>
				</th>
				<th width="10%">
					<?php echo JHtml::_('grid.sort', 'COM_FINDER_INDEX_HEADING_INDEX_DATE', 'l.indexdate', $listDirn, $listOrder); ?>
				</th>
			</tr>
		</thead>
		<tbody>
			<?php if (count($this->items) == 0): ?>
			<tr class="row0">
				<td align="center" colspan="7">
					<?php
					if ($this->total == 0) {
						echo JText::_('COM_FINDER_INDEX_NO_DATA').'  '.JText::_('COM_FINDER_INDEX_TIP');
					} else {
						echo JText::_('COM_FINDER_INDEX_NO_CONTENT');
					}
					?>
				</td>
			</tr>
			<?php endif; ?>

			<?php $canChange	= JFactory::getUser()->authorise('core.manage',	'com_finder'); ?>
			<?php foreach ($this->items as $i => $item): ?>

			<tr class="row<?php echo $i % 2; ?>">
				<td class="center">
					<?php echo JHtml::_('grid.id', $i, $item->link_id); ?>
				</td>
				<td>
					<?php if (intval($item->publish_start_date) OR intval($item->publish_end_date) OR intval($item->start_date) OR intval($item->end_date)) : ?>
					<img src="<?php echo JURI::root();?>/media/com_finder/images/calendar.png" style="border:1;float:right" class="hasTip" title="<?php echo JText::sprintf('COM_FINDER_INDEX_DATE_INFO', $item->publish_start_date, $item->publish_end_date, $item->start_date, $item->end_date);?>" />
					<?php endif; ?>
					<?php echo $this->escape($item->title); ?>
				</td>
				<td class="center nowrap">
					<?php echo JHtml::_('jgrid.published', $item->published, $i, 'index.', $canChange, 'cb'); ?>
				</td>
				<td class="center nowrap">
					<?php
					$key = $lang->hasKey('COM_FINDER_TYPE_S_'.strtoupper(str_replace(' ', '_', $item->t_title))) ? 'COM_FINDER_TYPE_S_'.strtoupper(str_replace(' ', '_', $item->t_title)) : $item->t_title;
					echo JText::_($key); ?>
				</td>
				<td class="nowrap">
					<?php
					if (strlen($item->url) > 80) {
						echo substr($item->url, 0, 70).'...';
					} else {
						echo $item->url;
					}
					?>
				</td>
				<td class="center nowrap">
					<?php echo JHtml::_('date', $item->indexdate, JText::_('DATE_FORMAT_LC4')); ?>
				</td>
			</tr>

			<?php endforeach; ?>
		</tbody>
		<tfoot>
			<tr>
				<td colspan="7" class="nowrap">
					<?php echo $this->pagination->getListFooter(); ?>
				</td>
			</tr>
		</tfoot>
	</table>

	<input type="hidden" name="task" value="display" />
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="filter_order" value="<?php echo $listOrder ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn ?>" />
	<?php echo JHtml::_('form.token'); ?>
</form>
