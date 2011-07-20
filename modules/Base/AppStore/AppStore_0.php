<?php

/**
 * 
 * @author pbukowski@telaxus.com
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-Base
 * @subpackage AppStore
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_AppStore extends Module {
    const refresh_info_text = 'Data is stored until close or refresh of browser\'s Epesi window or tab';

    protected $banned_columns = array('id', 'owner_id', 'path');

    public function body() {

    }

    public function admin() {
        if ($this->is_back()) {
            return $this->parent->reset();
        }
        Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());

        if (Base_EssClientCommon::get_license_key() == "") {
            // push main
            $m = $this->init_module('Base/EssClient');
            $this->display_module($m, null, 'register');
        } else {
            $this->cart_button();
            Base_ActionBarCommon::add('history', $this->t('Orders'), $this->create_callback_href(array($this, 'navigate'), array('orders_form')));
            $this->store_form();
        }
    }

    public function cart_button() {
        $cart = Base_AppStoreCommon::get_cart();
        $amount = count($cart);
        if ($amount == 0)
            $amount = $this->t('Empty');
        $label = $this->t('Cart');
        Base_ActionBarCommon::add('folder', "$label ($amount)", $this->create_callback_href(array($this, 'navigate'), array('cart_form')), $this->t(self::refresh_info_text));
    }

    public function cart_form() {
        $this->back_button();

        $items = Base_AppStoreCommon::get_cart();
        $total_price = 0;
        if (count($items) == 0) {
            print($this->t('Cart is empty!'));
            return;
        }
        $gb = $this->init_module('Utils/GenericBrowser', null, 'cartlist');
        $first = true;
        foreach ($items as $array_key => $r) {
            if ($first) {
                /* set table columns names */
                $columns = array();
                foreach ($r as $k => $v) {
                    if (in_array($k, $this->banned_columns))
                        continue;
                    $columns[] = array('name' => ucwords($k));
                }
                $gb->set_table_columns($columns);
                $first = false;
            }
            $v = array();
            foreach ($r as $k => $x) {
                if (in_array($k, $this->banned_columns))
                    continue;
                if ($k == 'price') {
                    $total_price += $x;
                    $x = $this->t('Free');
                }
                $v[] = $x;
            }
            /* @var $row Utils_GenericBrowser_Row_Object */
            $row = $gb->get_new_row();
            $row->add_data_array($v);
            $row->add_action($this->create_callback_href(array($this, 'cart_remove_item'), array($array_key)), $this->t('Remove from cart'));
        }
        $f = $this->init_module('Libs/QuickForm');
        $f->addElement('static', 'price', null, $this->t('Total price') . ': ' . $total_price);
        $f->addElement('submit', 'submit', $this->t('Buy!'));
        if ($f->validate() && $f->exportValue('submited')) {
            $modules = array();
            $module_names = array();
            foreach ($items as $r) {
                $modules[] = $r['id'];
                $module_names[$r['id']] = $r['name'];
            }
            $ret = Base_EssClientCommon::server()->order_submit($modules);
            foreach ($ret as $id => $success) {
                print("$module_names[$id] - <span style=\"color: " . ($success ? "green" : "red") . "\">" . $this->t($success ? 'Ordered' : 'Some error') . "</span><br/>");
            }
            Base_AppStoreCommon::empty_cart();
        } else {
            $this->display_module($gb);
            $f->display();
        }
    }

    public function cart_add_item($r) {
        $items = Base_AppStoreCommon::get_cart();
        $items[] = $r;
        Base_AppStoreCommon::set_cart($items);
    }

    public function cart_remove_item($array_key) {
        $items = Base_AppStoreCommon::get_cart();
        unset($items[$array_key]);
        Base_AppStoreCommon::set_cart($items);
    }

    public function orders_form() {
        $this->back_button();
        $this->download_button();

        $orders = Base_EssClientCommon::server()->orders_list();
        if (count($orders) == 0) {
            print($this->t('You don\'t have any orders'));
            return;
        }

        $gb = $this->init_module('Utils/GenericBrowser', null, 'orderslist');
        $first = true;
        foreach ($orders as $array_key => $r) {
            if ($first) {
                /* set table columns names */
                $columns = array();
                foreach ($r as $k => $v) {
                    if (in_array($k, $this->banned_columns))
                        continue;
                    $columns[] = array('name' => ucwords($k));
                }
                $gb->set_table_columns($columns);
                $first = false;
            }
            $v = array();
            foreach ($r as $k => $x) {
                if (in_array($k, $this->banned_columns))
                    continue;
                if ($k == 'price') {
                    $total_price += $x;
                    $x = $this->t('Free');
                }
                if ($k == 'paid') {
                    $x = $this->t($x ? 'Yes' : 'No');
                }
                $v[] = $x;
            }
            /* @var $row Utils_GenericBrowser_Row_Object */
            $row = $gb->get_new_row();
            $row->add_data_array($v);
            if ($r['paid'])
                $row->add_action($this->create_callback_href(array($this, 'download_queue_item'), array($r)), $this->t('Queue download'));
        }
        $this->display_module($gb);
    }

    public function download_queue_item($r) {
        $q = Base_AppStoreCommon::get_download_queue();
        $q[] = $r;
        Base_AppStoreCommon::set_download_queue($q);
    }

    public function download_button() {
        $download = $this->t('Downloads');
        $count = count(Base_AppStoreCommon::get_download_queue());
        if ($count == 0)
            $count = $this->t('Empty');
        Base_ActionBarCommon::add('clone', "$download ($count)", $this->create_callback_href(array($this, 'navigate'), array('download_form')));
    }

    public function download_form() {
        $this->back_button();
        Base_ActionBarCommon::add('delete', 'Clear list', $this->create_callback_href(array('Base_AppStoreCommon', 'empty_download_queue')));
        var_dump(Base_AppStoreCommon::get_download_queue());
    }

    public function store_form() {
        $total = Base_EssClientCommon::server()->modules_list_total_amount();
        if ($total) {
            /* @var $gb Utils_GenericBrowser */
            $gb = $this->init_module('Utils/GenericBrowser', null, 'moduleslist');
            $x = $gb->get_limit($total);
            // fetch data
            $t = Base_EssClientCommon::server()->modules_list($x['offset'], $x['numrows']);
            if ($t) {
                $first = true;
                foreach ($t as $r) {
                    if ($first) {
                        /* set table columns names */
                        $columns = array();
                        foreach ($r as $k => $v) {
                            if (in_array($k, $this->banned_columns))
                                continue;
                            $columns[] = array('name' => ucwords($k));
                        }
                        $gb->set_table_columns($columns);
                        $first = false;
                    }
                    $v = array();
                    foreach ($r as $k => $x) {
                        if (in_array($k, $this->banned_columns))
                            continue;
                        if ($k == 'price')
                            $x = $this->t('Free');
                        $v[] = $x;
                    }
                    /* @var $row Utils_GenericBrowser_Row_Object */
                    $row = $gb->get_new_row();
                    $row->add_data_array($v);
                    $row->add_action($this->create_callback_href(array($this, 'cart_add_item'), array($r)), $this->t('Add to cart'));
                }
            }
            $this->display_module($gb);
        }
    }

    public function navigate($func, $params = array()) {
        $x = ModuleManager::get_instance('/Base_Box|0');
        if (!$x)
            trigger_error('There is no base box module instance', E_USER_ERROR);
        $x->push_main($this->get_type(), $func, $params);
        return false;
    }

    public function pop_main() {
        $x = ModuleManager::get_instance('/Base_Box|0');
        if (!$x)
            trigger_error('There is no base box module instance', E_USER_ERROR);
        $x->pop_main();
    }

    public function back_button() {
        if ($this->is_back()) {
            return $this->pop_main();
        }
        Base_ActionBarCommon::add('back', 'Back', $this->create_back_href());
    }

}

?>