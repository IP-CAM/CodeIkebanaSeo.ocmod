<?php
class ControllerExtensionModuleCodeikebanaSeoLanguage extends Controller {
    public function index() {
        $this->load->language('common/language');

        $data['action'] = $this->url->link('common/language/language', '', $this->request->server['HTTPS']);

        $data['code'] = $this->session->data['language'];

        $this->load->model('localisation/language');

        $data['languages'] = array();

        $results = $this->model_localisation_language->getLanguages();
        
        $url_data = $this->request->get;
        unset($url_data['_route_']);
        $data['url_data'] = $url_data;

        foreach ($results as $result) {
                if ($result['status']) {
                        $data['languages'][] = array(
                                'name' => $result['name'],
                                'code' => $result['code'],
                        );
                }
        }

        $this->response->setOutput($this->load->view('extension/module/codeikebana/seo/language', $data));

        return $this->response->getOutput();
    }

    public function language() {
        if (isset($this->request->post['code'])) {
            $this->session->data['language'] = $this->request->post['code'];
            $this->config->set('config_language_id', $this->request->post['code']);
        }
        
        if (isset($this->request->post['url_data'])) {
            
            $url_data = $this->request->post['url_data'];

            if (!isset($url_data['route'])) {
                $url_data['route'] = 'common/home';
            }

            $route = $url_data['route'];
            unset($url_data['_route_']);
            unset($url_data['route']);
                
            $this->response->redirect($this->url->link($route, $url_data, $this->request->server['HTTPS']));
        } else {
            $this->response->redirect($this->url->link('common/home'));
        }
    }
}

//                $this->log->write($this->request->post);
//                $this->log->write($url_data);
//                $this->log->write($url_data);
//                $this->log->write($this->url->link($route, $url_data, $this->request->server['HTTPS']));
//        echo '<pre>';
//        var_dump(json_encode($url_data));
//        var_dump(json_decode(json_encode($url_data)));
//                $url = '';
//
//                if ($url_data) {
//                        $url = '&' . urldecode(http_build_query($url_data, '', '&'));
//                }

//		if (!isset($this->request->get['route'])) {
//			$data['redirect'] = $this->url->link('common/home');
//		} else {
//

//		}