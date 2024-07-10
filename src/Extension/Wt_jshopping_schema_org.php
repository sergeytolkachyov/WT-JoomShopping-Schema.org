<?php
/**
 * @package       WT JoomShopping Schema.org
 * @author        Sergey Tolkachyov info@web-tolk.ru https://web-tolk.ru
 * @copyright     Copyright (C) 2022 Sergey Tolkachyov. All rights reserved.
 * @license       GNU General Public License version 3 or later
 * @version       2.0.0
 */

namespace Joomla\Plugin\Jshoppingproducts\Wt_jshopping_schema_org\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

class Wt_jshopping_schema_org extends CMSPlugin implements SubscriberInterface
{

    protected $autoloadLanguage = true;

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   4.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onBeforeDisplayProductView' => 'onBeforeDisplayProductView',
            'onBeforeDisplayProductListView' => 'onBeforeDisplayProductListView',
            'onBeforeDisplayCategoryView' => 'onBeforeDisplayCategoryView',
            'onBeforeDisplayManufacturerView' => 'onBeforeDisplayManufacturerView',
        ];
    }

    /**
     * Добавляем микроразметку https://schema.org/Product для карточки товара в формате ld+json
     *
     * @param \Joomla\Event\Event $event
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function onBeforeDisplayProductView(Event $event): void
    {
        /**
         * @var object $view JoomShopping Product view object
         */
        [$view] = $event->getArguments();
        $jshopConfig = $view->config;
        $product = $view->product;

        $link = "index.php?option=com_jshopping&controller=product&task=view&category_id=" . $view->category_id . "&product_id=" . $product->product_id;
        $Itemid = \JSHelper::getDefaultItemid($link);
        $link = Route::_("index.php?option=com_jshopping&controller=product&task=view&category_id=" . $view->category_id . "&product_id=" . $product->product_id . "&Itemid=" . $Itemid, '', '', true);

        $product_info = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'image' => $jshopConfig->image_product_live_path . '/' . $product->image,
            'name' => $product->name,
            'offers' => [
                '@type' => 'Offer',
                'price' => $product->product_price,
                'priceCurrency' => htmlspecialchars($jshopConfig->currency_code_iso),
            ],
            'url' => $link,
        ];


        //		Описание товара краткое или полное
        $product_description = $this->params->get('product_desc_is', 'short_description');
        $product_info['description'] = $product->$product_description;


        //		Рейтинг товара
        if ($this->params->get('show_product_rating', 0) == 1) {
            $product_info['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'bestRating' => $jshopConfig->max_mark,
                'ratingCount' => $product->reviews_count,
                'ratingValue' => $product->average_rating,

            ];
        }


        /*
         * Показываем наличие товара, тип наличия.
         */
        if ($this->params->get('show_product_availability') == 1) {
            if ((float)$product->product_quantity == 0) {
                $availability = 'https://schema.org/' . $this->params->get('product_zero_quantity_availability_type', 'OutOfStock');

            } else {
                $availability = 'https://schema.org/' . $this->params->get('product_non_zero_quantity_availability_type', 'InStock');
            }
            $product_info['offers']['availability'] = $availability;
        }

        $product_sku = $this->params->get('product_sku_is', 'product_id');
        if ($product_sku != 'product_id') {
            // Проверяем, заполнены ли поля кода товара или артикула.
            // Если пусто - не пишем.
            // id товара всегда есть - пишем
            if (!empty($product->$product_sku)) {
                $product_info['sku'] = $product->$product_sku;
            }
        } else {
            $product_info['sku'] = $product->$product_sku;
        }

        if ($product->product_weight && (float)$product->product_weight > 0) {

            $product_info['weight'] = $product->product_weight;
        }

        $doc = $this->getApplication()->getDocument();
        $doc->addScriptDeclaration(json_encode($product_info), 'application/ld+json');

    }


    /**
     * Добавляем микроразметку Schema.org в формате ld+json в вид категории товаров.
     *
     * @param \Joomla\Event\Event $event
     *
     * @return void
     * @since 1.0.0
     */
    public function onBeforeDisplayProductListView(Event $event): void
    {
        /**
         * @param $view         object      JoomShooping product list view object, contains a category info, list of sub-categories, product list etc.
         * @param $productlist  object      Product list of this category
         */
        [$view, $productlist] = $event->getArguments();

        $jshopConfig = \JSFactory::getConfig();

        $category_description = $this->params->get('category_desc_is', 'short_description');
        $schema_org_list = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'url' => Uri::current(),
        ];

        // Категория товаров
        if (isset($view->category)) {
            $schema_org_list['name'] = $view->category->name;
            if (!empty($view->category->category_image)) {
                $schema_org_list['image'] = $jshopConfig->image_category_live_path . '/' . $view->category->category_image;
            }
            //Для получения описания
            $data_source = 'category';
            //Список товаров производителя.
        } elseif (isset($view->manufacturer)) {
            $schema_org_list['name'] = $view->manufacturer->name;
            if (!empty($view->manufacturer->manufacturer_logo)) {
                $schema_org_list['image'] = $jshopConfig->image_manufs_live_path . '/' . $view->manufacturer->manufacturer_logo;
            }
            //Для получения описания
            $data_source = 'manufacturer';
        }


        if (!empty($view->category->$category_description)) {
            $schema_org_list['description'] = strip_tags($view->$data_source->$category_description);
        }

        $app = $this->getApplication();
        $url_controller = $app->getInput()->getWord('controller');

        /**
         * Дочерние категории есть только в категориях.
         * На странице списка товаров производителя не будем заниматься дурью
         */
        if ($url_controller != 'manufacturer') {
            // Добавляем вложенные категории
            if (isset($view->categories) && count($view->categories) > 0) {

                foreach ($view->categories as $category) {
                    $category_info = [
                        '@type' => 'ListItem',
                        'name' => $category->name,
                        'url' => rtrim(Uri::root(), '/') . $category->category_link,
                    ];
                    if (!empty($category->category_image)) {
                        $category_info['image'] = $jshopConfig->image_product_live_path . '/' . $category->category_image;
                    }
                    if (!empty($category->$category_description)) {
                        $category_info['description'] = strip_tags($category->$category_description);
                    }
                    $schema_org_list['itemListElement'][] = $category_info;
                }
            }
        }

        // Добавляем товары
        if (isset($productlist->products) && count($productlist->products) > 0) {
            $product_description = $this->params->get('product_desc_is', 'short_description');
            foreach ($productlist->products as $product) {

                $product_info = [
                    "@type" => "ListItem",
                    'item' => [
                        '@type' => 'Product',
                        'image' => $product->image,
                        'name' => $product->name,
                        'offers' => [
                            '@type' => 'Offer',
                            'price' => $product->product_price,
                            'priceCurrency' => htmlspecialchars($jshopConfig->currency_code_iso),
                        ],
                        'url' => rtrim(Uri::root(), '/') . $product->product_link,
                    ]
                ];

                //		Описание товара краткое или полное

                $product_info['item']['description'] = $product->$product_description;


                //		Рейтинг товара
                if ($this->params->get('show_product_rating', 0) == 1) {
                    $product_info['item']['aggregateRating'] = [
                        '@type' => 'AggregateRating',
                        'bestRating' => $jshopConfig->max_mark,
                        'ratingCount' => $product->reviews_count,
                        'ratingValue' => $product->average_rating,

                    ];
                }

                // Вес товара
                if ($product->product_weight && (float)$product->product_weight > 0) {
                    $product_info['item']['weight'] = $product->product_weight;
                }

                /*
                 * Показываем наличие товара, тип наличия.
                 */
                if ($this->params->get('show_product_availability') == 1) {
                    if ((float)$product->product_quantity == 0) {
                        $availability = 'https://schema.org/' . $this->params->get('product_zero_quantity_availability_type', 'OutOfStock');

                    } else {
                        $availability = 'https://schema.org/' . $this->params->get('product_non_zero_quantity_availability_type', 'InStock');
                    }
                    $product_info['item']['offers']['availability'] = $availability;
                }

                $product_sku = $this->params->get('product_sku_is', 'product_id');
                if ($product_sku != 'product_id') {
                    // Проверяем, заполнены ли поля кода товара или артикула.
                    // Если пусто - не пишем.
                    // id товара всегда есть - пишем
                    if (!empty($product->$product_sku)) {
                        $product_info['item']['sku'] = $product->$product_sku;
                    }
                } else {
                    $product_info['item']['sku'] = $product->$product_sku;
                }

                $schema_org_list['itemListElement'][] = $product_info;

            }


            // Свойство position для элемента списка.
            for ($i = 0; $i < count($schema_org_list['itemListElement']); $i++) {
                $schema_org_list['itemListElement'][$i]['position'] = $i + 1;
            }
            //Количество элементов списка
            $schema_org_list['numberOfItems'] = count($schema_org_list['itemListElement']);
        }

        $doc = $this->getApplication()->getDocument();
        $doc->addScriptDeclaration(json_encode($schema_org_list), 'application/ld+json');
    }


    /**
     * Триггер срабатывает на главной странице магазина JoomShopping.
     * В объекте категории пусто, так как это root-категория. Поэтому данные
     * берём из params
     *
     * @param \Joomla\Event\Event $event
     *
     * @since 1.0.0
     */
    public function onBeforeDisplayCategoryView(Event $event): void
    {
        /**
         * @param object $view JoomShooping product list view object, contains a category info, list of sub-categories, product list etc.
         */
        [$view] = $event->getArguments();
        $jshopConfig = \JSFactory::getConfig();
        $category_description = $this->params->get('category_desc_is', 'short_description');
        $schema_org_list = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'url' => Uri::current(),
        ];

        //Заголовок окна браузера
        $schema_org_list['name'] = $view->params->get('page_title');

        // Из meta-description пункта меню.
        // Если оно пустое - Description из общих настроек сайта
        if (!empty($view->params->get('menu-meta_description'))) {
            $schema_org_list['description'] = strip_tags($view->params->get('menu-meta_description'));
        } else {
            $schema_org_list['description'] = strip_tags($view->params->get('page_description'));
        }

        // Добавляем вложенные категории
        if (isset($view->categories) && count($view->categories) > 0) {

            foreach ($view->categories as $category) {
                $category_info = array(
                    '@type' => 'ListItem',
                    'name' => $category->name,
                    'url' => rtrim(Uri::root(), '/') . $category->category_link,
                );
                if (!empty($category->category_image)) {
                    $category_info['image'] = $jshopConfig->image_product_live_path . '/' . $category->category_image;
                }
                if (!empty($category->$category_description)) {
                    $category_info['description'] = strip_tags($category->$category_description);
                }
                $schema_org_list['itemListElement'][] = $category_info;

            }

            // Свойство position для элемента списка.
            for ($i = 0; $i < count($schema_org_list['itemListElement']); $i++) {
                $schema_org_list['itemListElement'][$i]['position'] = $i + 1;
            }

        }

        $doc = $this->getApplication()->getDocument();
        $doc->addScriptDeclaration(json_encode($schema_org_list), 'application/ld+json');
    }

    /**
     * Триггер срабатывает на списке производителей JoomShopping.
     * В объекте категории пусто, так как это root-категория. Поэтому данные
     * берём из params
     *
     * @param \Joomla\Event\Event $event
     *
     * @since 1.0.0
     */
    public function onBeforeDisplayManufacturerView(Event $event)
    {

        /**
         * @param object $view JshoppingViewManufacturer
         */
        [$view] = $event->getArguments();

        $category_description = $this->params->get('category_desc_is', 'short_description');
        $schema_org_list = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'url' => Uri::current(),
        ];

        //Заголовок окна браузера
        $schema_org_list['name'] = $view->params->get('page_title');

        // Из meta-description пункта меню.
        // Если оно пустое - Description из общих настроек сайта
        if (!empty($view->params->get('menu-meta_description'))) {
            $schema_org_list['description'] = strip_tags($view->params->get('menu-meta_description'));
        } else {
            $schema_org_list['description'] = strip_tags($view->params->get('page_description'));
        }

        // Добавляем вложенные категории
        if (isset($view->rows) && count($view->rows) > 0) {

            foreach ($view->rows as $manufacturer) {
                $manufacturer_info = array(
                    '@type' => 'ListItem',
                    'name' => $manufacturer->name,
                    'url' => rtrim(Uri::root(), '/') . $manufacturer->link,
                );
                if (!empty($manufacturer->manufacturer_logo)) {
                    $manufacturer_info['image'] = $view->image_manufs_live_path . '/' . $manufacturer->manufacturer_logo;
                }
                if (!empty($manufacturer->$category_description)) {
                    $manufacturer_info['description'] = strip_tags($manufacturer->$category_description);
                }
                $schema_org_list['itemListElement'][] = $manufacturer_info;

            }

            // Свойство position для элемента списка.
            for ($i = 0; $i < count($schema_org_list['itemListElement']); $i++) {
                $schema_org_list['itemListElement'][$i]['position'] = $i + 1;
            }

        }

        $doc = $this->getApplication()->getDocument();
        $doc->addScriptDeclaration(json_encode($schema_org_list), 'application/ld+json');
    }
}
