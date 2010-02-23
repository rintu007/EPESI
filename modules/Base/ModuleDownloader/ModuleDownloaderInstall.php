<?php
/**
 *
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @copyright Copyright &copy; 2010, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage ModuleDownloader
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ModuleDownloaderInstall extends ModuleInstall {
	public function install() {
        if($this->create_data_dir())
            return true;
        return false;
	}
	
	public function uninstall() {
        $this->remove_data_dir();
		return true;
	}
	public function requires($v) {
		return array(
		array('name'=>'Libs/QuickForm','version'=>0));
	}
}

?>
