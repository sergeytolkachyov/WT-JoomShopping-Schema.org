<?php
defined('_JEXEC') or die;

use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;

FormHelper::loadFieldClass('list');

class JFormFieldJshoppingextrafields extends JFormFieldList
{

	protected $type = 'jshoppingextrafields';

	protected function getOptions()
	{
		if (file_exists(JPATH_SITE . "/components/com_jshopping/jshopping.php"))
		{
			$lang         = Factory::getLanguage();
			$current_lang = $lang->getTag();
			$db           = JFactory::getDBO();
			$query        = $db->getQuery(true);
			$query->select($db->quoteName('name_' . $current_lang));
			$query->select($db->quoteName('id'))
				->from($db->quoteName('#__jshopping_products_extra_fields'));
			$db->setQuery($query);
			$extra_fields = $db->loadAssocList();
			$name         = 'name_' . $current_lang;
			$options      = array();
			if (!empty($extra_fields))
			{
				foreach ($extra_fields as $extra_field)
				{
					$options[] = HTMLHelper::_('select.option', $extra_field["id"], $extra_field[$name]);
				}
			}

			return $options;
		}

	}
}

?>