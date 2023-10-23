<?php
namespace Opencart\Catalog\Model\Extension\Opencartfrenetv4\Shipping;

class Frenet extends \Opencart\System\Engine\Model {

	private $servicos 	= array();
	private $url = '';
	private $quote_data = array();
	
	private $cep_destino;
	private $cep_origem;
	private $pais_destino;

	private $mensagem_erro = array();

    protected function get_coupon() {
        if (!isset($this->session->data['coupon'])) {
            return null;
        }

        return $this->session->data['coupon'];
    }

	// função responsável pelo retorno à loja dos valores finais dos valores dos fretes
    /**
     * @param $address
     * @return array
     */
    public function getQuote($address) {        
        $method_data = array();
        
        try {
            $this->load->language('extension/opencartfrenetv4/shipping/frenet');
    
            $produtos = $this->cart->getProducts();
    
            // obtém só a parte numérica do CEP
            $this->cep_origem = preg_replace ("/[^0-9]/", '', $this->config->get('shipping_frenet_postcode'));
            $this->cep_destino = preg_replace ("/[^0-9]/", '', $address['postcode']);
    
            $this->pais_destino='BR';
            $this->load->model('localisation/country');
            $country_info = $this->model_localisation_country->getCountry($address['country_id']);
            if ($country_info) {
                $this->pais_destino = $country_info['iso_code_2'];
            }
    
            // product array
            $shippingItemArray = array();
            $count = 0;
    
            foreach ($produtos as $prod) {
                $qty = $prod['quantity'];
                $shippingItem = new \stdClass();
    
                $shippingItem->Weight = $this->getPesoEmKg($prod['weight_class_id'], $prod['weight']) / $qty;
                $shippingItem->Length = $this->getDimensaoEmCm($prod['length_class_id'], $prod['length']);
                $shippingItem->Height = $this->getDimensaoEmCm($prod['length_class_id'], $prod['height']);
                $shippingItem->Width = $this->getDimensaoEmCm($prod['length_class_id'], $prod['width']);
                $shippingItem->Diameter = 0;
                $shippingItem->SKU = '';
                $shippingItem->Category = '';
                $shippingItem->isFragile=false;
    
             //   $this->log->write( 'shippingItem: ' . print_r($shippingItem, true));
             
                $shippingItem->Quantity = $qty;
    
                $shippingItemArray[$count] = $shippingItem;
                $count++;
            }
    
            $coupon = $this->get_coupon();
            $token = $this->config->get('shipping_frenet_contrato_token');
            
            $objRequest = new \stdClass();
            $objRequest->Coupom = $coupon;
            $objRequest->PlatformName = "Opencartv4";
            $objRequest->PlatformVersion = VERSION;
            $objRequest->SellerCEP = $this->cep_origem;
            $objRequest->RecipientCEP = $this->cep_destino;
            $objRequest->ShipmentInvoiceValue = $this->cart->getSubTotal();
            $objRequest->ShippingItemArray = $shippingItemArray;
            $objRequest->RecipientCountry = $this->pais_destino;
            $jsonRequest = json_encode($objRequest, JSON_PRETTY_PRINT);
    
            $this->setApiUrl();
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonRequest);
    
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Accept: application/json",
                "Content-Type: application/json",
                "token: $token"
              ));
    
            $response_json = curl_exec($ch);
            curl_close($ch);
    
            $response = json_decode($response_json);
    
            $values = array();
    
            if ( isset( $response ) && isset($response->ShippingSevicesArray) ) {
                        $shippingServices = $response->ShippingSevicesArray;
                        $count = count(is_array($shippingServices) ? $shippingServices : []);
                        if($count == 1) {
                            $servicosArray[0] = $shippingServices;
                        } else {
                            $servicosArray = $shippingServices;
                        }
    
                $frenet_tax_class_id = $this->config->get('frenet_tax_class_id');
                $config_tax = $this->config->get('config_tax');
                $config_currency = $this->session->data['currency'];
    
                if (empty($frenet_tax_class_id)) { $frenet_tax_class_id = "1"; }
                        
                foreach($servicosArray as $servicos){
                    if (!isset($servicos->ServiceCode) || $servicos->ServiceCode . '' == '' || !isset($servicos->ShippingPrice)) {
                        continue;
                    }
    
                    if(!isset($servicos->ShippingPrice))
                        continue;
    
                    if (isset($servicos->DeliveryTime))
                        $deliveryTime=$servicos->DeliveryTime;
    
                    if ( $deliveryTime > 0 && $this->config->get('shipping_frenet_msg_prazo') ) {
                        $label = sprintf($this->config->get('shipping_frenet_msg_prazo'), $servicos->ServiceDescription, (int)$deliveryTime);
                    }
                    else{
                        $label = $servicos->ServiceDescription;
                    }
    
    
                    $cost  = floatval(str_replace(",", ".", (string) $servicos->ShippingPrice));
                    if (version_compare(VERSION, '2.2') < 0) {
                        $text = $this->currency->format($this->tax->calculate($cost, $frenet_tax_class_id, $config_tax));
                    } else {
                        $text = $this->currency->format($this->tax->calculate($cost, $frenet_tax_class_id, $config_tax), $config_currency);
                    }
    
                    $this->quote_data[$servicos->ServiceCode] = array(
                        'code'         => 'frenet.' . $servicos->ServiceCode,
                        'name'        => $label,
                        'cost'         => $cost,
                        'tax_class_id' => $frenet_tax_class_id,
                        'text'         => $text
                    );
    
                }
            }
    
            // ajustes finais
            if ($this->quote_data) {
    
                $method_data = array(
                    'code'       => 'frenet',
                    'name'      => $this->language->get('text_title'),
                    'quote'      => $this->quote_data,
                    'sort_order' => $this->config->get('shipping_frenet_sort_order'),
                    'error'      => false
                );
            }
            else if(!empty($this->mensagem_erro)){
                $method_data = array(
                    'code'       => 'frenet',
                    'name'      => $this->language->get('text_title'),
                    'quote'      => $this->quote_data,
                    'sort_order' => $this->config->get('shipping_frenet_sort_order'),
                    'error'      => implode('<br />', $this->mensagem_erro)
                );
            }            
        } catch (\Throwable $th) {
            $this->mensagem_erro = $th->getMessage() . ' in ' . $th->getFile() . ' on line ' . $th->getLine();
            if ($this->config->get('config_error_log')) {
                $this->log->write($this->mensagem_erro);
            }
    
            $method_data = array(
                'code'       => 'frenet',
                'name'      => $this->language->get('text_title'),
                'quote'      => array(),
                'sort_order' => $this->config->get('shipping_frenet_sort_order'),
                'error'      => $this->mensagem_erro
            );
        }

		return $method_data;
	}


	// prepara a url de chamada ao site dos frenet
	private function setApiUrl(){
		
		$url = "http://api.frenet.com.br/shipping/quote";

		$this->url = $url;
	}

	// retorna a dimensão em centímetros
	private function getDimensaoEmCm($unidade_id, $dimensao){
		
		if(is_numeric($dimensao)){
			$length_class_product_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "length_class mc LEFT JOIN " . DB_PREFIX . "length_class_description mcd ON (mc.length_class_id = mcd.length_class_id) WHERE mcd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND mc.length_class_id =  '" . (int)$unidade_id . "'");
			
			if(isset($length_class_product_query->row['unit'])){
				if($length_class_product_query->row['unit'] == 'mm'){
					return $dimensao / 10;
				}		
			}
		}
		return $dimensao;
	}
	
	// retorna o peso em quilogramas
	private function getPesoEmKg($unidade_id, $peso){
		
		if(is_numeric($peso)) {
			$weight_class_product_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "weight_class wc LEFT JOIN " . DB_PREFIX . "weight_class_description wcd ON (wc.weight_class_id = wcd.weight_class_id) WHERE wcd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND wc.weight_class_id =  '" . (int)$unidade_id . "'");
			
			if(isset($weight_class_product_query->row['unit'])){
				if($weight_class_product_query->row['unit'] == 'g'){
					return ($peso / 1000);
				}		
			}
		}
		return $peso;
	}	
	

}
?>
