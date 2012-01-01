<?php
/**
 *  @package AkeebaSubs
 *  @copyright Copyright (c)2010-2012 Nicholas K. Dionysopoulos
 *  @license GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') or die();

FOFTemplateUtils::addCSS('media://com_akeebasubs/css/backend.css?'.AKEEBASUBS_VERSIONHASH);
FOFTemplateUtils::addJS('media://com_akeebasubs/js/backend.js?'.AKEEBASUBS_VERSIONHASH);
FOFTemplateUtils::addJS('media://com_akeebasubs/js/akeebajq.js?'.AKEEBASUBS_VERSIONHASH);

JHTML::_('behavior.calendar');
JHTML::_('behavior.tooltip');

$this->loadHelper('cparams');
$this->loadHelper('select');
$this->loadHelper('format');

?>
<form action="index.php" method="post" name="adminForm">
<input type="hidden" name="option" value="com_akeebasubs" />
<input type="hidden" name="view" value="taxrules" />
<input type="hidden" id="task" name="task" value="browse" />
<input type="hidden" name="hidemainmenu" id="hidemainmenu" value="0" />
<input type="hidden" name="boxchecked" id="boxchecked" value="0" />
<input type="hidden" name="filter_order" id="filter_order" value="<?php echo $this->lists->order ?>" />
<input type="hidden" name="filter_order_Dir" id="filter_order_Dir" value="<?php echo $this->lists->order_Dir ?>" />
<input type="hidden" name="<?php echo JUtility::getToken();?>" value="1" />

<table class="adminlist">
	<thead>
		<tr>
			<th width="10px"><?php echo  JText::_('Num'); ?></th>
			<th width="16px"></th>
			<th>
				<?php echo  JHTML::_('grid.sort', 'COM_AKEEBASUBS_TAXRULES_COUNTRY', 'country', $this->lists->order_Dir, $this->lists->order); ?>
			</th>
			<th>
				<?php echo  JHTML::_('grid.sort', 'COM_AKEEBASUBS_TAXRULES_STATE', 'state', $this->lists->order_Dir, $this->lists->order); ?>
			</th>
			<th>
				<?php echo  JHTML::_('grid.sort', 'COM_AKEEBASUBS_TAXRULES_CITY', 'city', $this->lists->order_Dir, $this->lists->order); ?>
			</th>
			<th width="30px">
				<?php echo  JHTML::_('grid.sort', 'COM_AKEEBASUBS_TAXRULES_VIES', 'vies', $this->lists->order_Dir, $this->lists->order); ?>
			</th>
			<th width="60px">
				<?php echo  JHTML::_('grid.sort', 'COM_AKEEBASUBS_TAXRULES_TAXRATE', 'taxrate', $this->lists->order_Dir, $this->lists->order); ?>
			</th>
			<th width="50px">
				<?php echo JHTML::_('grid.sort', 'Ordering', 'ordering', $this->lists->order_Dir, $this->lists->order); ?>
				<?php echo JHTML::_('grid.order', $this->items); ?>
			</th>
			<th width="100px">
				<?php if(version_compare(JVERSION,'1.6.0','ge')):?>
				<?php echo JHTML::_('grid.sort', 'JPUBLISHED', 'enabled', $this->lists->order_Dir, $this->lists->order); ?>
				<?php else: ?>
				<?php echo JHTML::_('grid.sort', 'PUBLISHED', 'enabled', $this->lists->order_Dir, $this->lists->order); ?>
				<?php endif; ?>
			</th>			
		</tr>
		<tr>
			<td></td>
			<td>
				<input type="checkbox" name="toggle" value="" onclick="checkAll(<?php echo count( $this->items ) + 1; ?>);" />
			</td>
			<td>
				<?php echo AkeebasubsHelperSelect::countries($this->getModel()->getState('country',''), 'country', array('onchange'=>'this.form.submit();')); ?>
			</td>
			<td>
				<?php echo AkeebasubsHelperSelect::states($this->getModel()->getState('state',''), 'state', array('onchange'=>'this.form.submit();')); ?>
			</td>
			<td>
				<input type="text" name="search" id="search"
					value="<?php echo $this->escape($this->getModel()->getState('search',''));?>"
					class="text_area" onchange="document.adminForm.submit();" />
				<button onclick="this.form.submit();">
					<?php echo version_compare(JVERSION, '1.6.0', 'ge') ? JText::_('JSEARCH_FILTER') : JText::_('Go'); ?>
				</button>
				<button onclick="document.adminForm.search.value='';this.form.submit();">
					<?php echo version_compare(JVERSION, '1.6.0', 'ge') ? JText::_('JSEARCH_RESET') : JText::_('Reset'); ?>
				</button>
			</td>
			<td>
				<?php echo AkeebasubsHelperSelect::published($this->getModel()->getState('vies',''), 'vies', array('onchange'=>'this.form.submit();')) ?>
			</td>
			<td></td>
			<td></td>
			<td>
				<?php echo AkeebasubsHelperSelect::published($this->getModel()->getState('enabled',''), 'enabled', array('onchange'=>'this.form.submit();')) ?>
			</td>
		</tr>
		
	</thead>
	<tfoot>
		<tr>
			<td colspan="20">
				<?php if($this->pagination->total > 0) echo $this->pagination->getListFooter() ?>	
			</td>
		</tr>
	</tfoot>
	<tbody>
		<?php if($count = count($this->items)): ?>
		<?php $m = 1; $i = -1; ?>
		<?php foreach($this->items as $taxrule):?>
		<?php
			$i++; $m = 1-$m;
			$checkedOut = ($taxrule->locked_by != 0);
			$ordering = $this->lists->order == 'ordering';
			$taxrule->published = $taxrule->enabled;
		?>
		<tr class="row<?php echo $m?>">
			<td align="center">
				<?php echo $taxrule->akeebasubs_taxrule_id; ?>
			</td>
			<td align="center">
				<?php echo JHTML::_('grid.id', $i, $taxrule->akeebasubs_taxrule_id, $checkedOut); ?>
			</td>
			<td>
				<a href="index.php?option=com_akeebasubs&view=taxrule&id=<?php echo $taxrule->akeebasubs_taxrule_id; ?>">
					<?php echo AkeebasubsHelperSelect::formatCountry($taxrule->country) ?>
					<?php echo $taxrule->country ? ' ('.$this->escape($taxrule->country).')' : ''?>
				</a>
			</td>
			<td>
				<a href="index.php?option=com_akeebasubs&view=taxrule&id=<?php echo $taxrule->akeebasubs_taxrule_id; ?>">
					<?php echo AkeebasubsHelperSelect::formatState($taxrule->state) ?>
					<?php echo $taxrule->state ? ' ('.$this->escape($taxrule->state).')' : ''?>
				</a>
			</td>
			<td>
				<a href="index.php?option=com_akeebasubs&view=taxrule&id=<?php echo $taxrule->akeebasubs_taxrule_id; ?>">
					<?php echo $taxrule->city ? $this->escape($taxrule->city) : '&mdash;'?>
				</a>
			</td>
			<td>
				<?php if(version_compare(JVERSION, '1.6.0', 'ge')): ?>
				<?php echo $taxrule->vies ? JText::_('jyes') : JText::_('jno')?>
				<?php else: ?>
				<?php echo $taxrule->vies ? JText::_('yes') : JText::_('no')?>
				<?php endif; ?>
			</td>
			<td>
				<a href="index.php?option=com_akeebasubs&view=taxrule&id=<?php echo $taxrule->akeebasubs_taxrule_id; ?>">
					<?php echo sprintf('%02.2f', (int)$taxrule->taxrate)?> %
				</a>
			</td>
			<td class="order" align="center">
				<span><?php echo $this->pagination->orderUpIcon( $i, true, 'orderup', 'Move Up', $ordering ); ?></span>
				<span><?php echo $this->pagination->orderDownIcon( $i, $count, true, 'orderdown', 'Move Down', $ordering ); ?></span>
				<?php $disabled = $ordering ?  '' : 'disabled="disabled"'; ?>
				<input type="text" name="order[]" size="5" value="<?php echo $taxrule->ordering;?>" <?php echo $disabled ?> class="text_area" style="text-align: center" />
			</td>
			<td align="center">
				<?php echo JHTML::_('grid.published', $taxrule, $i); ?>
			</td>
		</tr>			
		<?php endforeach; ?>
		<?php else: ?>
		<tr>
			<td colspan="20">
				<?php echo  JText::_('COM_AKEEBASUBS_COMMON_NORECORDS') ?>
			</td>
		</tr>
		<?php endif; ?>
	</tbody>
</table>
</form>