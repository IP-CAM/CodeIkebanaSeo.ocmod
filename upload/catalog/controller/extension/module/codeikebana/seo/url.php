<?php
class ControllerExtensionModuleCodeikebanaSeoUrl extends Controller {

        private $_languages = [];
    
	public function index() {
            // Add rewrite to url class
            if ($this->config->get('config_seo_url')) {
                $this->url->addRewrite($this);
            }
            
            // no seo urls (might be old ones inc empty domain/home)
            $isOldUrl = $this->redirectToSeo();
            
            $routeArray = $this->getRouteAsArrayOrFalse();
            
            // Language detection
            $language = $this->getLanguageByUrlCode($routeArray ? $routeArray[0] : $routeArray);
            
//            echo '<pre>';
//            var_dump($this->request);
//            var_dump($routeArray);
//            var_dump($isOldUrl);
//            var_dump($language);
//            echo '</pre>';
            
            if ($language) {
                // remove language part
                // set language if session language is different!
                if ($language['language_id'] !== $this->config->get('config_language_id')) {
                    $this->setLanguage($language);
                }
                unset($routeArray[0]);
            } 
            
            $redirect = false;
            // Decode URL
            if ($routeArray) {
                foreach ($routeArray as $part) {
                    $query = $this->getQueryByKeyword($part);
                    if ($query) {
                        // if query word is in language that does not match language?
                        // 
                        // admin can add only one keyword or can add all of them
                        // 
                        // so if language is set we just need to check if keywords are in selected language
                        // 
                        // we will base everything on first keyword
                        // 
                        // or there is only one keyword in 
                        
                        // if there is no language set we could determine what that language should be by keywords so
                        
                        if ($language && ($language['language_id'] == $query['language_id'])) {
                            // proceed nothing to do - further checks are not needed
                        } elseif ($language && ($language['language_id'] !== $query['language_id'])) {
                            // lang is present but query do not mach find if there is maching one if so use it if not
                            $foundQueries = $this->findQueryForLanguage($query, $language['language_id']);
                            if ($foundQueries['found']) {
                                // we have found query with language we should redirect to
//                                var_dump('found');
//                                var_dump($foundQueries['found']);
                                $query = $foundQueries['found'];
                                $redirect = true;
                                
                            } elseif ($foundQueries['default'] 
                                    && $foundQueries['default']['keyword'] !== $query['keyword']
                                    && $foundQueries['default']['language_id'] !== $language['language_id']) {
//                                var_dump('default');
                                // query does not match language so we will use default query if current does not match
//                                var_dump($foundQueries['default']);
                                $query = $foundQueries['default'];
                                $redirect = true;
                                
                            }
                            
                            // use default for that language
                            
                        } elseif (!$language) {
                            // find language for query and redirect if exists
//                            var_dump('no language');
                            $language = $this->getLanguageById($query['language_id']);
                            if ($language) {
                                $this->setLanguage($language);
                                $redirect = true;
                            } else {
                                // we have found query but language does not exists for that query
                                // not found
                                $this->request->get['route'] = 'error/not_found';
                                
                                break;
                            }
                        }

                        $this->processQuery($query['query']);
                    } else {
                        $this->request->get['route'] = 'error/not_found';

                        break;
                    }
                }

                $this->setRoute();
            }
            
            if ($redirect) {
                $this->redirect();
            }
	}
        
	public function rewrite($link) {
            
            $url_info = parse_url(str_replace('&amp;', '&', $link));
            $url = '';
            $data = [];
            
            parse_str($url_info['query'], $data);
//            echo '<pre>';
//            var_dump($url_info);
//            var_dump($data);
//            echo '</pre>';
            $langCode = array_key_exists('lang', $data) ? $data['lang'] : $this->getCurrentLanguageUrlCode() ;
            foreach ($data as $key => $value) {
                if (isset($data['route'])) {
                    if (($data['route'] == 'product/product' && $key == 'product_id') || (($data['route'] == 'product/manufacturer/info' || $data['route'] == 'product/product') && $key == 'manufacturer_id') || ($data['route'] == 'information/information' && $key == 'information_id')) {
                        $keyword = $this->getSeoKeyword($key, $value, null, $langCode);
                        if ($keyword) {
                            $url .= '/' . $keyword;
                            
                            unset($data[$key]);
                        }
                    } elseif ($key == 'route') {
                        $keyword = $this->getSeoKeyword('route', $value, null, $langCode);
//                        $this->log->write($value);
                        if ($keyword) {
                            $url .= '/' . $keyword;
                            
                            unset($data[$key]);
                        }
                    } elseif ($key == 'path') {
                        $categories = explode('_', $value);
                        foreach ($categories as $category) {
                            $keyword = $this->getSeoKeyword('category_id', $category, null, $langCode);
                            if ($keyword) {
                                $url .= '/' . $keyword;
                            } else {
                                $url = '';

                                break;
                            }
                        }
                        
                        unset($data[$key]);
                    }
                }
            }
            
            if ($url || (isset($data['route']) && $data['route'] == 'common/home')) {
                unset($data['route']);

                $query = '';

                if ($data) {
                    foreach ($data as $key => $value) {
                        $query .= '&' . rawurlencode((string)$key) . '=' . rawurlencode((is_array($value) ? http_build_query($value) : (string)$value));
                    }

                    if ($query) {
                        $query = '?' . str_replace('&', '&amp;', trim($query, '&'));
                    }
                }

                return $url_info['scheme'] . '://' . $url_info['host'] . (isset($url_info['port']) ? ':' . $url_info['port'] : '') . str_replace('/index.php', '', $url_info['path']) . '/' . $this->getCurrentLanguageUrlCode() . $url . $query;
            } else {
                return $link;
            }
	}
        
        private function getRouteAsArrayOrFalse() {
            // Decode URL
            if (isset($this->request->get['_route_'])) {
                $parts = explode('/', $this->request->get['_route_']);
                // remove any empty arrays from trailing
                if (utf8_strlen(end($parts)) == 0) {
                        array_pop($parts);
                }
                
                return $parts;
            }
            
            return false;
        }

        private function isUrlSeo($url) {
            return !strpos($url, 'index.php?route');
        }
        
        private function redirectToSeo() {
            $url_data = $this->request->get;
            if ($this->request->server['REQUEST_URI'] == '/') {
                $url_data['route'] = 'common/home';
            }
            
            if (!isset($url_data['route'])) {
                return false;
            }
            
            $route = $url_data['route'];
            unset($url_data['_route_']);
            unset($url_data['route']);
            
            $url = '';
            if ($url_data) {
                $url = '&' . urldecode(http_build_query($url_data, '', '&'));
            }
            
            $seoUrl = $this->url->link($route, $url, $this->request->server['HTTPS']);
            if ($this->isUrlSeo($seoUrl)) {
                $this->response->redirect($seoUrl); //, 301)
//                var_dump($seoUrl);
            }
            
            return false;
        }
        
        private function redirect($code = 302) {
            $url_data = $this->request->get;
            $route = $url_data['route'];
            unset($url_data['_route_']);
            unset($url_data['route']);
            
            $url = '';
            if ($url_data) {
                $url = '&' . urldecode(http_build_query($url_data, '', '&'));
            }
            
            $this->response->redirect($this->url->link($route, $url, $this->request->server['HTTPS']), $code); //
        }
        
        private function findQueryForLanguage($query, $language_id) {
            $result['default'] = false;
            $result['found'] = false;
            if ($language_id && array_key_exists('query', $query)) {
                $keyValue = explode('=', $query['query']);
                if (count($keyValue) == 2) {
                    $queries = $this->getSeoKeywords($keyValue[0], $keyValue[1]);
                } else {
                    $queries = $this->getSeoKeywords('route', $query['query']);
                }
   
                if (!$queries) {
                    return $result;
                }
                    
                foreach ($queries as $q ) {
                    if ($q['language_id'] == $language_id) {
                        $result['found'] = $q;
                    }
                    if ($q['language_id'] == 1) {
                        $result['default'] = $q;
                    }
                }

                return $result;
            }
            
            return $result;
        }
        
        private function getQueryByKeyword($keyword) {
            $sql = "SELECT * FROM " . DB_PREFIX . "seo_url";
            $sql .= " WHERE keyword = '" . $this->db->escape($keyword) . "'";
            $sql .= " AND store_id = '" . (int)$this->config->get('config_store_id') . "'";
            $query = $this->db->query($sql);
            // we do return language in case it does not corespond to a detected language
            return $query->num_rows ? $query->row : false;
        }
        
        private function processQuery($query) {
            $url = explode('=', $query);

            if ($url[0] == 'product_id') {
                $this->request->get['product_id'] = $url[1];
            }

            if ($url[0] == 'category_id') {
                if (!isset($this->request->get['path'])) {
                    $this->request->get['path'] = $url[1];
                } else {
                    $this->request->get['path'] .= '_' . $url[1];
                }
            }

            if ($url[0] == 'manufacturer_id') {
                $this->request->get['manufacturer_id'] = $url[1];
            }

            if ($url[0] == 'information_id') {
                $this->request->get['information_id'] = $url[1];
            }

            if ($query && $url[0] != 'information_id' && $url[0] != 'manufacturer_id' && $url[0] != 'category_id' && $url[0] != 'product_id') {
                $this->request->get['route'] = $query;
            }
        }
        
        private function detectRoute() {
            if (isset($this->request->get['product_id'])) {
                return 'product/product';
            } elseif (isset($this->request->get['path'])) {
                return 'product/category';
            } elseif (isset($this->request->get['manufacturer_id'])) {
                return 'product/manufacturer/info';
            } elseif (isset($this->request->get['information_id'])) {
                return 'information/information';
            }
        }
        
        private function setRoute() {
            if (!isset($this->request->get['route'])) {
                $this->request->get['route'] = $this->detectRoute();
            }
        }
        
        private function getSeoKeyword($key = null, $value = null, $store = null, $language = null) {
            //category_id=60
            $keywords = $this->getSeoKeywords($key, $value, $store);
            if (!$keywords) {
                return false;
            }
            
            $languages = $this->getLanguages();
            $language = ($language === null || !array_key_exists($language, $languages)) ? $this->config->get('config_language') : $language;
            $language_id = array_key_exists($language, $languages) ? $languages[$language]['language_id'] : 1 ;
            
            $found_keyword = false;
            // check for current/selected language
            foreach ($keywords as $keyword) {
               if ($keyword['language_id'] == $language_id) {
                   $found_keyword = $keyword['keyword'];
               }
            }
            // return first language/keyword usually english one
            if (!$found_keyword) {
                $found_keyword = array_key_exists(0, $keywords) ? $keywords[0]['keyword'] : false;    
            }
            
            return $found_keyword;
        }
        
        private function getSeoKeywords($key = null, $value = null, $store = null) {
            
            if (empty($key) || empty($value)) {
                // default non seo url
                return false;
            }
            
            $store = $store === null ? $this->config->get('config_store_id') : $store;
            
            $sql = "SELECT * FROM " . DB_PREFIX . "seo_url WHERE `query` = ";
            if ($key == 'route') {
                $sql .= "'". $this->db->escape($value) . "'";
            } else {
                $sql .= "'". $this->db->escape($key . '=' . (int)$value) . "'";
            }
            $sql .= " AND store_id = '" . (int)$store . "'";
//            $sql .= " AND language_id = '" . (int)$this->config->get('config_language_id') . "'";
            $query = $this->db->query($sql);
            
            return $query->num_rows ? $query->rows : false;
        }
        
	private function getLanguages() {
            
            if (empty($this->_languages)) {
                $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "language` WHERE status = '1'"); 
                foreach ($query->rows as $result) {
                    $this->_languages[$result['code']] = $result;
                }
            }

            return $this->_languages;
	}
        
        private function setLanguage($language) {
            
            if (!isset($this->session->data['language']) || $this->session->data['language'] != $language['code']) {
                    $this->session->data['language'] = $language['code'];
            }

            if (!isset($this->request->cookie['language']) || $this->request->cookie['language'] != $language['code']) {
                    setcookie('language', $language['code'], time() + 60 * 60 * 24 * 30, '/', $this->request->server['HTTP_HOST']);
            }

            // Overwrite the default language object
            $newLanguage = new Language($language['code']);
            $newLanguage->load($language['code']);

            $this->registry->set('language', $newLanguage);

            // Set the config language_id
//            $this->config->set('config_language_id', $languages[$code]['language_id']);
            $this->config->set('config_language_id', $language['language_id']);
            
        }
        
	private function getCurrentLanguageUrlCode() {
            $languages = $this->getLanguages();
//            $this->config->get('config_language_id')
            $currentLanguage = $this->session->data['language'];
            
            foreach ($languages as $code => $language) {
                if ($code == $currentLanguage) {
                    return $this->convertToUrlCode($code);
                }
            }
            // default
            return 'en';
	}
        
        private function convertToUrlCode($language_code = 'en-gb') {
            // only two chars
            $urlCode = substr($language_code, 0, 2);
            return isset($urlCode) ? $urlCode : 'en';
        }
        
        private function getLanguageByUrlCode($url_code = null) {
            
            if ($url_code === null) {
                return false;
            }
            
            $languages = $this->getLanguages();
            foreach ($languages as $code => $language) {
                if (strpos($code, $url_code) !== false) {
                    return $language;
                }
            }
            
            return false;
        }
        
        private function getLanguageIdByUrlCode($url_code = null) {
            
            if ($url_code === null) {
                return false;
            }
            $language = $this->getLanguageByUrlCode($url_code);

            return $language && array_key_exists('language_id', $language) ? $language['language_id'] : false;
        }
        
        private function getLanguageById($language_id = null) {
            
            if (!$language_id) {
                return false;
            }
            
            $languages = $this->getLanguages();
            foreach ($languages as $code => $language) {
                if ($language['language_id'] == $language_id) {
                    return $language;
                }
            }
            // default
            return false;
        }
}


//	public function index() {
//                var_dump('codeikebana seo');
//		// Add rewrite to url class
//		if ($this->config->get('config_seo_url')) {
//			$this->url->addRewrite($this);
//		}
//                
//                
//                
//                
//		// Decode URL
//		if (isset($this->request->get['_route_'])) {
//			$parts = explode('/', $this->request->get['_route_']);
//
//			// remove any empty arrays from trailing
//			if (utf8_strlen(end($parts)) == 0) {
//				array_pop($parts);
//			}
//
//			foreach ($parts as $part) {
//				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE keyword = '" . $this->db->escape($part) . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "'");
//
//				if ($query->num_rows) {
//					$url = explode('=', $query->row['query']);
//
//					if ($url[0] == 'product_id') {
//						$this->request->get['product_id'] = $url[1];
//					}
//
//					if ($url[0] == 'category_id') {
//						if (!isset($this->request->get['path'])) {
//							$this->request->get['path'] = $url[1];
//						} else {
//							$this->request->get['path'] .= '_' . $url[1];
//						}
//					}
//
//					if ($url[0] == 'manufacturer_id') {
//						$this->request->get['manufacturer_id'] = $url[1];
//					}
//
//					if ($url[0] == 'information_id') {
//						$this->request->get['information_id'] = $url[1];
//					}
//
//					if ($query->row['query'] && $url[0] != 'information_id' && $url[0] != 'manufacturer_id' && $url[0] != 'category_id' && $url[0] != 'product_id') {
//						$this->request->get['route'] = $query->row['query'];
//					}
//				} else {
//					$this->request->get['route'] = 'error/not_found';
//
//					break;
//				}
//			}
//
//			if (!isset($this->request->get['route'])) {
//				if (isset($this->request->get['product_id'])) {
//					$this->request->get['route'] = 'product/product';
//				} elseif (isset($this->request->get['path'])) {
//					$this->request->get['route'] = 'product/category';
//				} elseif (isset($this->request->get['manufacturer_id'])) {
//					$this->request->get['route'] = 'product/manufacturer/info';
//				} elseif (isset($this->request->get['information_id'])) {
//					$this->request->get['route'] = 'information/information';
//				}
//			}
//		}
//	}
//
//	public function rewrite($link) {
//		$url_info = parse_url(str_replace('&amp;', '&', $link));
//
//		$url = '';
//
//		$data = array();
//
//		parse_str($url_info['query'], $data);
//
//		foreach ($data as $key => $value) {
//			if (isset($data['route'])) {
//				if (($data['route'] == 'product/product' && $key == 'product_id') || (($data['route'] == 'product/manufacturer/info' || $data['route'] == 'product/product') && $key == 'manufacturer_id') || ($data['route'] == 'information/information' && $key == 'information_id')) {
//					$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE `query` = '" . $this->db->escape($key . '=' . (int)$value) . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");
//
//					if ($query->num_rows && $query->row['keyword']) {
//						$url .= '/' . $query->row['keyword'];
//
//						unset($data[$key]);
//					}
//				} elseif ($key == 'path') {
//					$categories = explode('_', $value);
//
//					foreach ($categories as $category) {
//						$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE `query` = 'category_id=" . (int)$category . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");
//
//						if ($query->num_rows && $query->row['keyword']) {
//							$url .= '/' . $query->row['keyword'];
//						} else {
//							$url = '';
//
//							break;
//						}
//					}
//
//					unset($data[$key]);
//				}
//			}
//		}
//
//		if ($url) {
//			unset($data['route']);
//
//			$query = '';
//
//			if ($data) {
//				foreach ($data as $key => $value) {
//					$query .= '&' . rawurlencode((string)$key) . '=' . rawurlencode((is_array($value) ? http_build_query($value) : (string)$value));
//				}
//
//				if ($query) {
//					$query = '?' . str_replace('&', '&amp;', trim($query, '&'));
//				}
//			}
//
//			return $url_info['scheme'] . '://' . $url_info['host'] . (isset($url_info['port']) ? ':' . $url_info['port'] : '') . str_replace('/index.php', '', $url_info['path']) . $url . $query;
//		} else {
//			return $link;
//		}
//	}


//            $oldUrl = ($this->config->get('site_ssl') ? $this->config->get('site_ssl') : $this->config->get('site_url') ). 'index.php?route=' . $route . $url;
//        private function detectLanguage() {
//            //home page no language we have to add default language code and redirect
//            if (!isset($this->request->get['_route_'])) {
//                $this->response->redirect($this->url->link('common/home'));
//            } else {
//                $languages = $this->getLanguages();
//                $parts = $this->getRouteAsArrayOrFalse();
//                
//                if ($parts && isset($parts[0]) && $this->isLanguageUrlCode($parts[0])) {
//                    $this->setLanguage($parts[0]);
//                } else {
//                    $url_data = $this->request->get;
//                    unset($url_data['_route_']);
//                    $route = $url_data['route'];
//                    unset($url_data['route']);
//                    $url = '';
//                    if ($url_data) {
//                            $url = '&' . urldecode(http_build_query($url_data, '', '&'));
//                    }
////                    $this->response->redirect($this->url->link($route, $url, $this->request->server['HTTPS']));
//                }
//            }
//        }
        
//        private function setLanguage($language) {
            
//            if ($this->request->get['_route_'] == 'common/language/language') {
//                
//            } else {
//                $this->session->data['language'] = $language == 'en' ? 'en-gb' : $language;
//                if ()
//                
//                
//            }
            
            
//		if (!isset($this->request->get['route'])) {
//			$data['redirect'] = $this->url->link('common/home');
//		} else {
//			$url_data = $this->request->get;
//
//			unset($url_data['_route_']);
//
//			$route = $url_data['route'];
//
//			unset($url_data['route']);
//
//			$url = '';
//
//			if ($url_data) {
//				$url = '&' . urldecode(http_build_query($url_data, '', '&'));
//			}
//
//			$data['redirect'] = $this->url->link($route, $url, $this->request->server['HTTPS']);
//		}
            
            
            
//        }
//	public function index() {
//		// Add rewrite to url class
//		if ($this->config->get('config_seo_url')) {
//			$this->url->addRewrite($this);
//		}
//
//		// Decode URL
//		if (isset($this->request->get['_route_'])) {
//			$parts = explode('/', $this->request->get['_route_']);
//
//			// remove any empty arrays from trailing
//			if (utf8_strlen(end($parts)) == 0) {
//				array_pop($parts);
//			}
//
//			foreach ($parts as $part) {
//				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE keyword = '" . $this->db->escape($part) . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "'");
//
//				if ($query->num_rows) {
//					$url = explode('=', $query->row['query']);
//
//					if ($url[0] == 'product_id') {
//						$this->request->get['product_id'] = $url[1];
//					}
//
//					if ($url[0] == 'category_id') {
//						if (!isset($this->request->get['path'])) {
//							$this->request->get['path'] = $url[1];
//						} else {
//							$this->request->get['path'] .= '_' . $url[1];
//						}
//					}
//
//					if ($url[0] == 'manufacturer_id') {
//						$this->request->get['manufacturer_id'] = $url[1];
//					}
//
//					if ($url[0] == 'information_id') {
//						$this->request->get['information_id'] = $url[1];
//					}
//
//					if ($query->row['query'] && $url[0] != 'information_id' && $url[0] != 'manufacturer_id' && $url[0] != 'category_id' && $url[0] != 'product_id') {
//						$this->request->get['route'] = $query->row['query'];
//					}
//				} else {
//					$this->request->get['route'] = 'error/not_found';
//
//					break;
//				}
//			}
//
//			if (!isset($this->request->get['route'])) {
//				if (isset($this->request->get['product_id'])) {
//					$this->request->get['route'] = 'product/product';
//				} elseif (isset($this->request->get['path'])) {
//					$this->request->get['route'] = 'product/category';
//				} elseif (isset($this->request->get['manufacturer_id'])) {
//					$this->request->get['route'] = 'product/manufacturer/info';
//				} elseif (isset($this->request->get['information_id'])) {
//					$this->request->get['route'] = 'information/information';
//				}
//			}
//		}
//	}
//
//	public function rewrite($link) {
//		$url_info = parse_url(str_replace('&amp;', '&', $link));
//
//                $this->log->write($link);
//		$url = '';
//
//		$data = array();
//
//		parse_str($url_info['query'], $data);
//
//		foreach ($data as $key => $value) {
//                        $this->log->write($key);
//			if (isset($data['route'])) {
//				if (($data['route'] == 'product/product' && $key == 'product_id') || (($data['route'] == 'product/manufacturer/info' || $data['route'] == 'product/product') && $key == 'manufacturer_id') || ($data['route'] == 'information/information' && $key == 'information_id')) {
//					$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE `query` = '" . $this->db->escape($key . '=' . (int)$value) . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");
//
//					if ($query->num_rows && $query->row['keyword']) {
//						$url .= '/' . $query->row['keyword'];
//
//						unset($data[$key]);
//					}
//				} elseif ($key == 'path') {
//					$categories = explode('_', $value);
//
//					foreach ($categories as $category) {
//						$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE `query` = 'category_id=" . (int)$category . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");
//
//						if ($query->num_rows && $query->row['keyword']) {
//							$url .= '/' . $query->row['keyword'];
//						} else {
//							$url = '';
//
//							break;
//						}
//					}
//
//					unset($data[$key]);
//				}
//			}
//		}
//
//		if ($url) {
//			unset($data['route']);
//
//			$query = '';
//
//			if ($data) {
//				foreach ($data as $key => $value) {
//					$query .= '&' . rawurlencode((string)$key) . '=' . rawurlencode((is_array($value) ? http_build_query($value) : (string)$value));
//				}
//
//				if ($query) {
//					$query = '?' . str_replace('&', '&amp;', trim($query, '&'));
//				}
//			}
//
//			return $url_info['scheme'] . '://' . $url_info['host'] . (isset($url_info['port']) ? ':' . $url_info['port'] : '') . str_replace('/index.php', '', $url_info['path']) . $url . $query;
//		} else {
//			return $link;
//		}
//	}

                    
                    
//                    if ($query->num_rows) {

//                    } else {
//                            $this->request->get['route'] = 'error/not_found';
//
//                            break;
//                    }
//                    
//                    
//                    if ($query->num_rows) {
//                        $url = explode('=', $query->row['query']);
//
//                        if ($url[0] == 'product_id') {
//                            $this->request->get['product_id'] = $url[1];
//                        }
//
//                        if ($url[0] == 'category_id') {
//                                if (!isset($this->request->get['path'])) {
//                                        $this->request->get['path'] = $url[1];
//                                } else {
//                                        $this->request->get['path'] .= '_' . $url[1];
//                                }
//                        }
//
//                        if ($url[0] == 'manufacturer_id') {
//                                $this->request->get['manufacturer_id'] = $url[1];
//                        }
//
//                        if ($url[0] == 'information_id') {
//                                $this->request->get['information_id'] = $url[1];
//                        }
//
//                        if ($query->row['query'] && $url[0] != 'information_id' && $url[0] != 'manufacturer_id' && $url[0] != 'category_id' && $url[0] != 'product_id') {
//                                $this->request->get['route'] = $query->row['query'];
//                        }
//                    } else {
//                            $this->request->get['route'] = 'error/not_found';
//
//                            break;
//                    }
//                $this->log->write($this->getSeoKeyword('category_id', 60,0,'pl'));
//                $this->log->write($this->getSeoKeyword('product_id', 50,0,'pl'));
//                $this->log->write($this->config->get('config_language'));