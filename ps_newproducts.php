<?php
/*
* 2007-2016 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2016 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;
use PrestaShop\PrestaShop\Adapter\Image\ImageRetriever;
use PrestaShop\PrestaShop\Adapter\Product\PriceFormatter;
use PrestaShop\PrestaShop\Core\Product\ProductListingPresenter;
use PrestaShop\PrestaShop\Adapter\Product\ProductColorsRetriever;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Ps_NewProducts extends Module implements WidgetInterface
{
    protected static $cache_new_products = array();

    public function __construct()
    {
        $this->name = 'ps_newproducts';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'PrestaShop';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array(
            'min' => '1.7.0.0',
            'max' => _PS_VERSION_,
        );

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans(
            'New products block',
            array(),
            'Modules.Newproducts.Admin'
        );
        $this->description = $this->trans(
            'Displays a block featuring your store\'s newest products.',
            array(),
            'Modules.Newproducts.Admin'
        );
    }

    public function install()
    {
        $success = parent::install()
            && $this->registerHook('displayLeftColumn')
            && $this->registerHook('displayRightColumn')
            && $this->registerHook('actionProductAdd')
            && $this->registerHook('actionProductUpdate')
            && $this->registerHook('actionProductDelete')
            && $this->registerHook('displayHome')
            && Configuration::updateValue('NEW_PRODUCTS_NBR', 5)
        ;

        $this->_clearCache('*');

        return $success;
    }

    public function uninstall()
    {
        $this->_clearCache('*');

        return parent::uninstall();
    }

    public function getContent()
    {
        $output = '';
        if (Tools::isSubmit('submitBlockNewProducts')) {
            $productNbr = Tools::getValue('NEW_PRODUCTS_NBR');
            if (!$productNbr || empty($productNbr)) {
                $output .= $this->displayError(
                    $this->trans(
                        'Please complete the "products to display" field.',
                        array(),
                        'Modules.Newproducts.Admin'
                    )
                );
            } elseif (0 == (int)$productNbr) {
                $output .= $this->displayError(
                    $this->trans(
                        'Invalid number.',
                        array(),
                        'Modules.Newproducts.Admin'
                    )
                );
            } else {
                Configuration::updateValue(
                    'PS_NB_DAYS_NEW_PRODUCT',
                    (int)Tools::getValue('PS_NB_DAYS_NEW_PRODUCT')
                );
                Configuration::updateValue(
                    'PS_BLOCK_NEWPRODUCTS_DISPLAY',
                    (int)Tools::getValue('PS_BLOCK_NEWPRODUCTS_DISPLAY')
                );
                Configuration::updateValue(
                    'NEW_PRODUCTS_NBR',
                    (int)($productNbr)
                );

                $this->_clearCache('*');
                $output .= $this->displayConfirmation(
                    $this->trans(
                        'Settings updated',
                        array(),
                        'Modules.Newproducts.Admin'
                    )
                );
            }
        }
        return $output.$this->renderForm();
    }

    protected function getNewProducts()
    {
        if (!empty(self::$cache_new_products)) {
            return self::$cache_new_products;
        }

        if (!Configuration::get('NEW_PRODUCTS_NBR')) {
            return;
        }

        $newProducts = false;

        if (Configuration::get('PS_NB_DAYS_NEW_PRODUCT')) {
            $newProducts = Product::getNewProducts(
                (int)$this->context->language->id,
                0,
                (int)Configuration::get('NEW_PRODUCTS_NBR')
            );
        }

        $assembler = new ProductAssembler($this->context);

        $presenterFactory = new ProductPresenterFactory($this->context);
        $presentationSettings = $presenterFactory->getPresentationSettings();
        $presenter = new ProductListingPresenter(
            new ImageRetriever(
                $this->context->link
            ),
            $this->context->link,
            new PriceFormatter(),
            new ProductColorsRetriever(),
            $this->context->getTranslator()
        );

        $products_for_template = array();

        if (is_array($newProducts)) {
            foreach ($newProducts as $rawProduct) {
                $products_for_template[] = $presenter->present(
                    $presentationSettings,
                    $assembler->assembleProduct($rawProduct),
                    $this->context->language
                );
            }
        }

        return self::$cache_new_products = $products_for_template;
    }

    public function hookActionProductAdd($params)
    {
        $this->_clearCache('*');
    }

    public function hookActionProductUpdate($params)
    {
        $this->_clearCache('*');
    }

    public function hookActionProductDelete($params)
    {
        $this->_clearCache('*');
    }

    public function _clearCache($template, $cache_id = null, $compile_id = null)
    {
        self::$cache_new_products = array();

        parent::_clearCache(
            'module:ps_newproducts/views/templates/hook/ps_newproducts.tpl'
        );
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans(
                        'Settings',
                        array(),
                        'Modules.Newproducts.Admin'
                    ),
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->trans(
                            'Products to display',
                            array(),
                            'Modules.Newproducts.Admin'
                        ),
                        'name' => 'NEW_PRODUCTS_NBR',
                        'class' => 'fixed-width-xs',
                        'desc' => $this->trans(
                            'Define the number of products to be displayed in' .
                            ' this block.',
                            array(),
                            'Modules.Newproducts.Admin'
                        ),
                    ),
                    array(
                        'type'  => 'text',
                        'label' => $this->trans(
                            'Number of days for which the product is' .
                            ' considered \'new\'',
                            array(),
                            'Modules.Newproducts.Admin'
                        ),
                        'name'  => 'PS_NB_DAYS_NEW_PRODUCT',
                        'class' => 'fixed-width-xs',
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->trans(
                            'Always display this block',
                            array(),
                            'Modules.Newproducts.Admin'
                        ),
                        'name' => 'PS_BLOCK_NEWPRODUCTS_DISPLAY',
                        'desc' => $this->trans(
                            'Show the block even if no new products are' .
                            ' available.',
                            array(),
                            'Modules.Newproducts.Admin'
                        ),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans(
                                    'Enabled',
                                    array(),
                                    'Modules.Newproducts.Admin'
                                ),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans(
                                    'Disabled',
                                    array(),
                                    'Modules.Newproducts.Admin'
                                ),
                            ),
                        ),
                    )
                ),
                'submit' => array(
                    'title' => $this->trans(
                        'Save',
                        array(),
                        'Modules.Newproducts.Admin'
                    ),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table =  $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang =
            Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ?
            Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') :
            0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitBlockNewProducts';
        $helper->currentIndex = $this->context->link->getAdminLink(
                'AdminModules',
                false
            ) .
            '&configure=' . $this->name .
            '&tab_module=' . $this->tab .
            '&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        return array(
            'PS_NB_DAYS_NEW_PRODUCT' => Tools::getValue(
                'PS_NB_DAYS_NEW_PRODUCT',
                Configuration::get('PS_NB_DAYS_NEW_PRODUCT')
            ),
            'PS_BLOCK_NEWPRODUCTS_DISPLAY' => Tools::getValue(
                'PS_BLOCK_NEWPRODUCTS_DISPLAY',
                Configuration::get('PS_BLOCK_NEWPRODUCTS_DISPLAY')
            ),
            'NEW_PRODUCTS_NBR' => Tools::getValue(
                'NEW_PRODUCTS_NBR',
                Configuration::get('NEW_PRODUCTS_NBR')
            ),
        );
    }

    public function renderWidget($hookName, array $configuration)
    {
        if (empty($this->getNewProducts())
            && !Configuration::get('PS_BLOCK_NEWPRODUCTS_DISPLAY')) {
            return;
        }

        $cacheId = $this->getCacheId('ps_newproducts');
        $isCached = $this->isCached(
            'module:ps_newproducts/views/templates/hook/ps_newproducts.tpl',
            $cacheId
        );
        if (!$isCached) {
            $this->smarty->assign(
                $this->getWidgetVariables(
                    $hookName,
                    $configuration
                )
            );
        }
        return $this->fetch(
            'module:ps_newproducts/views/templates/hook/ps_newproducts.tpl',
            $cacheId
        );
    }

    public function getWidgetVariables($hookName, array $configuration)
    {
        return array(
            'products' => $this->getNewProducts(),
        );
    }
}
