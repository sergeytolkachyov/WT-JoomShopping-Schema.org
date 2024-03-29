<?php
/**
 * @package    WT JoomShopping Schema.org
 * @author     Sergey Tolkachyov info@web-tolk.ru https://web-tolk.ru
 * @copyright  Copyright (C) 2021 Sergey Tolkachyov. All rights reserved.
 * @license    GNU General Public License version 3 or later
 * @version	   1.0.1
 */

defined('_JEXEC') or die;

use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Language\Text;
use \Joomla\CMS\Factory;
FormHelper::loadFieldClass('spacer');

class JFormFieldPlugininfo extends JFormFieldSpacer
{

	protected $type = 'plugininfo';

	/**
	 * Method to get the field input markup for a spacer.
	 * The spacer does not have accept input.
	 *
	 * @return  string  The field input markup.
	 *
	 * @since   1.7.0
	 */
	protected function getInput()
	{
		return ' ';
	}

	/**
	 * @return  string  The field label markup.
	 *
	 * @since   1.7.0
	 */
	protected function getLabel()
	{
		$doc = Factory::getApplication()->getDocument();
		$doc->addStyleDeclaration("
			.wt-b24-plugin-info{
				box-shadow: 0 .5rem 1rem rgba(0,0,0,.15); 
				padding:1rem; 
				margin-bottom: 2rem;
				display:flex;
				
			}
			.plugin-info-img{
			    margin-right:auto;
			    max-width: 100%;
			}
			.plugin-info-img svg:hover * {
				cursor:pointer;
			}
		");

		$wt_plugin_info = simplexml_load_file(JPATH_SITE."/plugins/jshoppingproducts/wt_jshopping_schema_org/wt_jshopping_schema_org.xml");

		?>
		<div class="wt-b24-plugin-info">
            <div class="plugin-info-img">
				<a href="https://web-tolk.ru" target="_blank">
							<svg width="200" height="50" xmlns="http://www.w3.org/2000/svg">
								 <g>
								  <title>Go to https://web-tolk.ru</title>
								  <text font-weight="bold" xml:space="preserve" text-anchor="start" font-family="Helvetica, Arial, sans-serif" font-size="32" id="svg_3" y="36.085949" x="8.152073" stroke-opacity="null" stroke-width="0" stroke="#000" fill="#0fa2e6">Web</text>
								  <text font-weight="bold" xml:space="preserve" text-anchor="start" font-family="Helvetica, Arial, sans-serif" font-size="32" id="svg_4" y="36.081862" x="74.239105" stroke-opacity="null" stroke-width="0" stroke="#000" fill="#384148">Tolk</text>
								 </g>
							</svg>
				</a>
            </div>
			<div style="padding: 0px 15px;">
				<span class="badge badge-success bg-success">v.<?php echo $wt_plugin_info->version; ?></span>
				<?php echo Text::_("PLG_WT_JSHOPPING_SCHEMA_ORG_DESC"); ?>
			</div>
		</div>
<?php

	}

	/**
	 * Method to get the field title.
	 *
	 * @return  string  The field title.
	 *
	 * @since   1.7.0
	 */
	protected function getTitle()
	{
		return $this->getLabel();
	}

}
?>