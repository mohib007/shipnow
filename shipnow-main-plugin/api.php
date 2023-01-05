<?php
namespace shipnow_shipping;

defined('ABSPATH') or exit;

/**
 * Interfaz con API de Shipnow.
 */
class api {
	/** @var float tax Coeficiente a aplicar sobre los importes obtenidos.  */
	const tax=1.21;
	const apiUrl='https://api.shipnow.com.ar/';
	const storesUrl='https://stores.shipnow.com.ar/stores/';
	const version=1;
	
	protected $token=null;
	protected $lastCode=null;
	protected $lastError=null;
	protected $lastErrorCode=null;

	/**
	 * Establece el token para esta instancia.
	 * @param string $token Token.
	 * @return shipnow_shipping\api
	 */
	public function setToken($token) {
		$this->token=$token;
		return $this;
	}

	/**
	 * Valida el token actual.
	 * @return bool|null
	 */
	public function validateToken() {
		$response=$this->getStores('current');
		if($this->lastError!==null) return null;
		if($response&&$response->store_type_code=='woocommerce'&&$response->active) return true;
		return false;
	}

	/**
	 * Devuelve los tipos de envío disponibles.
	 * @return array
	 */
	public function getShippingTypes() {
		return [
			'pap_economic'=>(object)[
				'name'=>'pap_economic',
				'title'=>__('Door to door','shipnow-shipping'),
				'description'=>__('Home delivery','shipnow-shipping'),
				'default'=>true
			],
			'pas'=>(object)[
				'name'=>'pas',
				'title'=>__('Door to post office','shipnow-shipping'),
				'description'=>__('Pick up at post office','shipnow-shipping'),
				'default'=>true
			],
			'pap_express'=>(object)[
				'name'=>'pap_express',
				'title'=>__('Door to door express','shipnow-shipping'),
				'description'=>__('Express home delivery','shipnow-shipping'),
				'default'=>false
			]
		];
	}

	/**
	 * Devuelve el tipo de envío correspondiente a un objeto con formato anterior (`ship_pap`, `ship_pas`).
	 * @return array
	 */
	protected function translateLegacyShippingType($obj) {
		if(!$obj||!isset($obj->shipping_service)||!isset($obj->shipping_service->type)) return null;

		if($obj->shipping_service->type=='ship_pas') return 'pas';

		if($obj->shipping_service->type=='ship_pap') {
			if(isset($obj->shipping_service)&&isset($obj->shipping_service->category)&&$obj->shipping_service->category=='economic') return 'pap_economic';
			return 'pap_express';
		}
		
		return null;
	}

	/**
	 * Devuelve los parámetros de un tipo de envío.
	 * @param string $name Nombre.
	 * @return array|null
	 */
	public function getShippingType($name) {
		return array_keys($this->getShippingTypes())[$name];
	}

	/**
	 * Devuelve la cotización del envío.
	 * @param string $zip Código postal.
	 * @param array $type Nombre del tipo de envío.
	 * @param float $weight Peso en Kg.
	 * @param float $volume Volumen en cm3.
	 * @return object|null
	 */
	public function getShippingEstimate($zip,$type,$weight,$volume) {
		$estimates=$this->getShippingEstimates($zip,[$type],$weight,$volume);
		if(!count($estimates)) return null;
		return $estimates[0];
	}

	/**
	 * Devuelve la cotización del envío.
	 * @param string $zip Código postal.
	 * @param array $types Nombres de los tipos de envío a consultar.
	 * @param float $weight Peso en kg.
	 * @param float $volume Volumen en cm3.
	 * @return array
	 */
	public function getShippingEstimates($zip,$types,$weight,$volume) {
		$existingTypes=$this->getShippingTypes();
		$validTypes=[];
		if(is_array($types))
			foreach($types as $name)
				if(array_key_exists($name,$existingTypes))
					$validTypes[]=$existingTypes[$name]->name;

		if(!count($validTypes)) return [];

		$res=$this->postStores('woocommerce/shipping_options',[
			'weight'=>round($weight*1000),
			'volume'=>round($volume),
			'to_zip_code'=>$zip,
			'shipping_services'=>$validTypes
		]);

		if(!$res||!count($res)) return [];

		$results=[];
		foreach($res as $option) {
			$shipTo=null;
			if($option->ship_to_type=='PostOffice')
				$shipTo=__($option->ship_to->address->address_line.' - '.$option->ship_to->address->city);

			$cost=$option->price*self::tax;
			$minDate=strtotime('today',strtotime($option->minimum_delivery));
			$maxDate=strtotime('today',strtotime($option->maximum_delivery));

			$type=$this->translateLegacyShippingType($option);
			if(!$type) continue;

			$code=$option->shipping_contract->id.'_'.$option->shipping_service->id;
			if($option->ship_to) $code.='_'.$option->ship_to->id;
			
			$results[]=(object)[
				'code'=>$code,
				'option'=>$option,
				'zip'=>$zip,
				'type'=>$existingTypes[$type],
				'typeName'=>$type,
				'to'=>$shipTo,
				'carrier'=>__($option->shipping_service->carrier->name),
				'carrierCode'=>$option->shipping_service->carrier->code,
				'cost'=>$cost,
				'days_min'=>ceil(($minDate-time())/86400),
				'days_max'=>ceil(($maxDate-time())/86400)
			];
		}

		return $results;
	}

	/**
	 * Realiza una solicitud GET a `api.shipnow.com.ar`.
	 * @param string $uri URI.
	 * @param array|object|null $data Parámetros.
	 * @return mixed
	 */
	public function getApi($uri,$data=null) {
		return $this->get(self::apiUrl,$uri,$data);
	}

	/**
	 * Realiza una solicitud POST a `api.shipnow.com.ar`.
	 * @param string $uri URI.
	 * @param array|object|null $data Parámetros.
	 * @return mixed
	 */
	public function postApi($uri,$data=null) {
		return $this->post(self::apiUrl,$uri,$data);
	}

	/**
	 * Realiza una solicitud PUT a `api.shipnow.com.ar`.
	 * @param string $uri URI.
	 * @param array|object|null $data Parámetros.
	 * @return mixed
	 */
	public function putApi($uri,$data=null) {
		return $this->put(self::apiUrl,$uri,$data);
	}

	/**
	 * Realiza una solicitud GET a `stores.shipnow.com.ar`.
	 * @param string $uri URI.
	 * @param array|object|null $data Parámetros.
	 * @return mixed
	 */
	public function getStores($uri,$data=null) {
		return $this->get(self::storesUrl,$uri,$data);
	}

	/**
	 * Realiza una solicitud POST a `stores.shipnow.com.ar`.
	 * @param string $uri URI.
	 * @param array|object|null $data Parámetros.
	 * @return mixed
	 */
	public function postStores($uri,$data=null) {
		return $this->post(self::storesUrl,$uri,$data);
	}

	/**
	 * Realiza una solicitud PUT a `stores.shipnow.com.ar`.
	 * @param string $uri URI.
	 * @param array|object|null $data Parámetros.
	 * @return mixed
	 */
	public function putStores($uri,$data=null) {
		return $this->put(self::storesUrl,$uri,$data);
	}

	/**
	 * Realiza una solicitud GET.
	 * @param string $base URL base (`api::url` or `api::storesUrl`).
	 * @param string $uri URI.
	 * @param array|object|null $data Parámetros.
	 * @return mixed
	 */
	public function get($base,$uri,$data=null) {
		return $this->request('get',$base,$uri,$data);
	}

	/**
	 * Realiza una solicitud POST.
	 * @param string $base URL base (`api::url` or `api::storesUrl`).
	 * @param string $uri URI.
	 * @param array|object|null $data Parámetros.
	 * @return mixed
	 */
	public function post($base,$uri,$data=null) {
		return $this->request('post',$base,$uri,$data);
	}

	/**
	 * Realiza una solicitud PUT.
	 * @param string $base URL base (`api::url` or `api::storesUrl`).
	 * @param string $uri URI.
	 * @param array|object|null $data Parámetros.
	 * @return mixed
	 */
	public function put($base,$uri,$data=null) {
		return $this->request('put',$base,$uri,$data);
	}

	/**
	 * Realiza una solicitud.
	 * @param string $method Método (`'get'`, `'post'` o `'put'`).
	 * @param string $base URL base (`api::url` or `api::storesUrl`).
	 * @param string $uri URI.
	 * @param array|object|null $data Parámetros.
	 * @return mixed
	 */
	protected function request($method,$base,$uri,$data=null) {
		$this->lastCode=null;
		$this->lastError=null;
		$this->lastErrorCode=null;

		$isPost=$method=='post';
		$isPut=$method=='put';
		$isGet=!$isPost&&!$isPost;

		if(!$isPost&&!$isPut&&$data) $uri.='?'.http_build_query($data);

		$auth='Token token='.$this->token;
		if($base==self::storesUrl) $auto='Bearer '.$this->token;

		$opt=[
			CURLOPT_URL=>$base.$uri,
			CURLOPT_HTTPHEADER=>[
				'Authorization: '.$auth,
				'Content-Type: application/json'
			],
			CURLOPT_USERAGENT=>'Plugin WooCommerce Shipnow/'.self::version,
			CURLOPT_RETURNTRANSFER=>true
		];

		if($isPost) $opt[CURLOPT_POST]=1;

		if($isPut) $opt[CURLOPT_CUSTOMREQUEST]='PUT';

		if(($isPost||$isPut)&&$data) {
			$opt[CURLOPT_POSTFIELDS]=json_encode($data);
			$opt[CURLOPT_HTTPHEADER][]='Content-Length: '.strlen($opt[CURLOPT_POSTFIELDS]);
		}

		$curl=curl_init();
		curl_setopt_array($curl,$opt);

		$resp=curl_exec($curl);
		$this->lastCode=curl_getinfo($curl,CURLINFO_HTTP_CODE);

		curl_close($curl);

		if(!$resp||($this->lastCode!=200&&$this->lastCode!=201)) {
			$this->lastError=true;
			$this->lastErrorCode=$this->lastCode;
		}

		$obj=json_decode($resp);

		if(!$obj) {
			$this->lastError=true;
			return null;
		}

		if(property_exists($obj,'error')) {
			$this->lastError=$obj->error;
			return null;
		}

		if(property_exists($obj,'results'))
			return $obj->results;

		return $obj;
	}

	/**
	 * Devuelve el código de respuesta de la última solicitud.
	 * @return int
	 */
	public function getLastResponseCode() {
		return $this->lastCode;
	}

	/**
	 * Devuelve el código de respuesta de la última solicitud, si la misma resultó en un error.
	 * @return int|null
	 */
	public function getLastErrorCode() {
		return $this->lastErrorCode;
	}

	/**
	 * Devuelve la descripción del último error, `true` si la descripción no está disponible, o `false` si no hubo error en la 
	 * última solicitud.
	 * @return string|bool
	 */
	public function getLastError() {
		return $this->lastError;
	}
}