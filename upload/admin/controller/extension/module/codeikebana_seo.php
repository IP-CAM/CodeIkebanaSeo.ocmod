<?php
class ControllerExtensionModuleCodeikebanaSeo extends Controller {

    public function index() {
        $this->response->redirect($this->request->server['HTTP_REFERER']);
    }

    public function install() {
        $this->load->model('setting/event');
        $this->model_setting_event->addEvent('codeikebana_seo', 'catalog/view/common/language/after', 'extension/module/codeikebana/seo/language');
        $this->model_setting_event->addEvent('codeikebana_seo', 'catalog/controller/common/language/language/before', 'extension/module/codeikebana/seo/language/language', 1, 10);
        
        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting('module_codeikebana_seo',  ['module_codeikebana_seo_status' => "1"]);
    }
    
    public function uninstall() {
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('codeikebana_seo');
        
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('module_codeikebana_seo');
    }
}