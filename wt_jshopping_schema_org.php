<?php
/**
 * @package    WT JoomShopping Schema.org
 * @author     Sergey Tolkachyov info@web-tolk.ru https://web-tolk.ru
 * @copyright  Copyright (C) 2021 Sergey Tolkachyov. All rights reserved.
 * @license    GNU General Public License version 3 or later
 * @version	   1.0.1
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;


class PlgJshoppingproductsWt_jshopping_schema_org extends CMSPlugin
{

	/**
	 * Class Constructor
	 *
	 * @param   object  $subject
	 * @param   array   $config
	 */
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}

	/**
	 * Добавляем микроразметку https://schema.org/Product для карточки товара в формате ld+json
	 *
	 * @param   object  &$product  JoomShopping product
	 *
	 * @return  void
	 *
	 * @since   1.0
	 */
	public function onBeforeDisplayProductView(&$view)
	{
		$jshopConfig = $view->config;
		$product     = $view->product;
		$shop_item_id = getShopMainPageItemid();
		$link         = Route::_("index.php?option=com_jshopping&controller=product&task=view&category_id=" . $product->category_id . "&product_id=" . $product->product_id . "&Itemid=" . $shop_item_id, '', '', true);
		$product_info = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Product',
			'image'    => $jshopConfig->image_product_live_path . '/' . $product->image,
			'name'     => $product->name,
			'offers'   => array(
				'@type'         => 'Offer',
				'price'         => $product->product_price,
				'priceCurrency' => htmlspecialchars($jshopConfig->currency_code_iso),
			),
			'url'      => $link,
		);



		//		Описание товара краткое или полное
		$product_description         = $this->params->get('product_desc_is', 'short_description');
		$product_info['description'] = $product->$product_description;


		//		Рейтинг товара
		if ($this->params->get('show_product_rating', 0) == 1)
		{
			$product_info['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'bestRating'  => $jshopConfig->max_mark,
				'ratingCount' => $product->reviews_count,
				'ratingValue' => $product->average_rating,

			);
		}


		/*
		 * Показываем наличие товара, тип наличия.
		 */
		if ($this->params->get('show_product_availability') == 1)
		{
			if ((float) $product->product_quantity == 0)
			{
				$availability = 'https://schema.org/' . $this->params->get('product_zero_quantity_availability_type', 'OutOfStock');

			}
			else
			{
				$availability = 'https://schema.org/' . $this->params->get('product_non_zero_quantity_availability_type', 'InStock');
			}
			$product_info['offers']['availability'] = $availability;
		}



		$product_sku = $this->params->get('product_sku_is','product_id');
		if($product_sku != 'product_id'){
			// Проверяем, заполнены ли поля кода товара или артикула.
			// Если пусто - не пишем.
			// id товара всегда есть - пишем
			if(!empty($product->$product_sku)){
				$product_info['sku'] = $product->$product_sku;
			}
		} else{
			$product_info['sku'] = $product->$product_sku;
		}



//		Высота
//		if($this->params->get('product_extra_field_height')){
//			$height_extra_field_id = 'extra_field_'.$this->params->get('product_extra_field_height');
//			$product_info['height'] = $product->$height_extra_field_id;
//		}

		// материал
//		if($this->params->get('product_extra_field_material')){
//			$height_extra_field_id = 'extra_field_'.$this->params->get('product_extra_field_height');
//			$product_info['material'] = $product->$height_extra_field_id;
//		}
//


		if ((float) $product->weight > 0)
		{
			$product_info['weight'] = $product->weight;
		}



		$doc = Factory::getDocument();
		$doc->addScriptDeclaration(json_encode($product_info), 'application/ld+json');

	}


	/**
	 * Добавляем микроразметку Schema.org в формате ld+json в вид категории товаров.
	 * @param $view         object      JoomShooping product list view object, contains a category info, list of sub-categories, product list etc.
	 * @param $productlist  object      Product list of this category
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function onBeforeDisplayProductListView($view, &$productlist)
	{
		$jshopConfig   = JSFactory::getConfig();
		$category_description = $this->params->get('category_desc_is', 'short_description');
		$schema_org_list = array(
			'@context' => 'https://schema.org',
			'@type' => 'ItemList',
			'url' => JUri::current(),
		);

		// Категория товаров
		if (isset($view->category)){
			$schema_org_list['name'] = $view->category->name;
			if(!empty($view->category->category_image)){
				$schema_org_list['image'] = $jshopConfig->image_category_live_path.'/'.$view->category->category_image;
			}
			//Для получения описания
			$data_source = 'category';
		//Список товаров производителя.
		}elseif (isset($view->manufacturer)){
			$schema_org_list['name'] = $view->manufacturer->name;
			if(!empty($view->manufacturer->manufacturer_logo)){
				$schema_org_list['image'] = $jshopConfig->image_manufs_live_path.'/'.$view->manufacturer->manufacturer_logo;
			}
			//Для получения описания
			$data_source = 'manufacturer';
		}


		if(!empty($view->category->$category_description)){
			$schema_org_list['description'] = strip_tags($view->$data_source->$category_description);
		}

		$app = Factory::getApplication();
		$url_controller = $app->input->get('controller');

		/*
		 * Дочерние категории есть только в категориях.
		 * На странице списка товаров производителя не будем заниматься дурью
		 */
		if($url_controller != 'manufacturer'){
			// Добавляем вложенные категории
			if(count($view->categories) > 0){

				foreach ($view->categories as $category)
				{
					$category_info = array(
						'@type' => 'ListItem',
						'name' => $category->name,
						'url'      => rtrim(JUri::root(), '/') . $category->category_link,

					);
					if(!empty($category->category_image)){
						$category_info['image'] = $jshopConfig->image_product_live_path . '/' . $category->category_image;
					}
					if(!empty($category->$category_description)){
						$category_info['description'] = strip_tags($category->$category_description);
					}
					$schema_org_list['itemListElement'][] = $category_info;

				}



			}
		}

		// Добавляем товары
		if (count($productlist->products) > 0)
		{
			$product_description = $this->params->get('product_desc_is', 'short_description');
			foreach ($productlist->products as $product)
			{

				$product_info = array(
					"@type" => "ListItem",
					'item' => array(

						'@type'    => 'Product',
						'image'    => $product->image,
						'name'     => $product->name,
						'offers'   => array(
							'@type'         => 'Offer',
							'price'         => $product->product_price,
							'priceCurrency' => htmlspecialchars($jshopConfig->currency_code_iso),
						),
						'url'      => rtrim(JUri::root(), '/') . $product->product_link,
					)
				);

				//		Описание товара краткое или полное

				$product_info['item']['description'] = $product->$product_description;


				//		Рейтинг товара
				if ($this->params->get('show_product_rating', 0) == 1)
				{
					$product_info['item']['aggregateRating'] = array(
						'@type'       => 'AggregateRating',
						'bestRating'  => $jshopConfig->max_mark,
						'ratingCount' => $product->reviews_count,
						'ratingValue' => $product->average_rating,

					);
				}

				// Вес товара
				if ((float) $product->weight > 0)
				{
					$product_info['item']['weight'] = $product->weight;
				}


				/*
				 * Показываем наличие товара, тип наличия.
				 */
				if ($this->params->get('show_product_availability') == 1)
				{
					if ((float) $product->product_quantity == 0)
					{
						$availability = 'https://schema.org/' . $this->params->get('product_zero_quantity_availability_type', 'OutOfStock');

					}
					else
					{
						$availability = 'https://schema.org/' . $this->params->get('product_non_zero_quantity_availability_type', 'InStock');
					}
					$product_info['item']['offers']['availability'] = $availability;
				}

				$product_sku = $this->params->get('product_sku_is','product_id');
					if($product_sku != 'product_id'){
						// Проверяем, заполнены ли поля кода товара или артикула.
						// Если пусто - не пишем.
						// id товара всегда есть - пишем
						if(!empty($product->$product_sku)){
							$product_info['item']['sku'] = $product->$product_sku;
						}
					} else{
						$product_info['item']['sku'] = $product->$product_sku;
					}


				$schema_org_list['itemListElement'][] = $product_info;

			}

		}

		// Свойство position для элемента списка.
		for($i = 0; $i<count($schema_org_list['itemListElement']); $i++)
		{
			$schema_org_list['itemListElement'][$i]['position'] = $i+1;
		}
		//Количество элементов списка
		$schema_org_list['numberOfItems'] = count($schema_org_list['itemListElement']);

		$doc = Factory::getDocument();
		$doc->addScriptDeclaration(json_encode($schema_org_list), 'application/ld+json');
	}


	/**
	 * Триггер срабатывает на главной странице магазина JoomShopping.
	 * В объекте категории пусто, так как это root-категория. Поэтому данные
	 * берём из params
	 * @param $view     object  Jshopping category object
	 *
	 * @since 1.0.0
	 */
	public function onBeforeDisplayCategoryView($view){
		$jshopConfig   = JSFactory::getConfig();
		$category_description = $this->params->get('category_desc_is', 'short_description');
		$schema_org_list = array(
			'@context' => 'https://schema.org',
			'@type' => 'ItemList',
			'url' => JUri::current(),
		);

		//Заголовок окна браузера
		$schema_org_list['name'] = $view->params->get('page_title');

		// Из meta-description пункта меню.
		// Если оно пустое - Description из общих настроек сайта
		if(!empty($view->params->get('menu-meta_description'))){
			$schema_org_list['description'] = strip_tags($view->params->get('menu-meta_description'));
		} else{
			$schema_org_list['description'] = strip_tags($view->params->get('page_description'));
		}

		// Добавляем вложенные категории
		if(count($view->categories) > 0){

			foreach ($view->categories as $category)
			{
				$category_info = array(
					'@type' => 'ListItem',
					'name' => $category->name,
					'url'      => rtrim(JUri::root(), '/') . $category->category_link,
				);
				if(!empty($category->category_image)){
					$category_info['image'] = $jshopConfig->image_product_live_path . '/' . $category->category_image;
				}
				if(!empty($category->$category_description)){
					$category_info['description'] = strip_tags($category->$category_description);
				}
				$schema_org_list['itemListElement'][] = $category_info;

			}

			// Свойство position для элемента списка.
			for($i = 0; $i<count($schema_org_list['itemListElement']); $i++)
			{
				$schema_org_list['itemListElement'][$i]['position'] = $i+1;
			}

		}

		$doc = Factory::getDocument();
		$doc->addScriptDeclaration(json_encode($schema_org_list), 'application/ld+json');
	}

	/**
	 * Триггер срабатывает на списке производителей JoomShopping.
	 * В объекте категории пусто, так как это root-категория. Поэтому данные
	 * берём из params
	 * @param $view     object  JshoppingViewManufacturer  object
	 *
	 * @since 1.0.0
	 */
	public function onBeforeDisplayManufacturerView($view){

		$category_description = $this->params->get('category_desc_is', 'short_description');
		$schema_org_list = array(
			'@context' => 'https://schema.org',
			'@type' => 'ItemList',
			'url' => JUri::current(),
		);

		//Заголовок окна браузера
		$schema_org_list['name'] = $view->params->get('page_title');

		// Из meta-description пункта меню.
		// Если оно пустое - Description из общих настроек сайта
		if(!empty($view->params->get('menu-meta_description'))){
			$schema_org_list['description'] = strip_tags($view->params->get('menu-meta_description'));
		} else{
			$schema_org_list['description'] = strip_tags($view->params->get('page_description'));
		}

		// Добавляем вложенные категории
		if(count($view->rows) > 0){

			foreach ($view->rows as $manufacturer)
			{
				$manufacturer_info = array(
					'@type' => 'ListItem',
					'name' => $manufacturer->name,
					'url'      => rtrim(JUri::root(), '/') . $manufacturer->link,
				);
				if(!empty($manufacturer->manufacturer_logo)){
					$manufacturer_info['image'] = $view->image_manufs_live_path . '/' . $manufacturer->manufacturer_logo;
				}
				if(!empty($manufacturer->$category_description)){
					$manufacturer_info['description'] = strip_tags($manufacturer->$category_description);
				}
				$schema_org_list['itemListElement'][] = $manufacturer_info;

			}

			// Свойство position для элемента списка.
			for($i = 0; $i<count($schema_org_list['itemListElement']); $i++)
			{
				$schema_org_list['itemListElement'][$i]['position'] = $i+1;
			}

		}

		$doc = Factory::getDocument();
		$doc->addScriptDeclaration(json_encode($schema_org_list), 'application/ld+json');

	}
}
