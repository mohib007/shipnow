<?php
namespace shipnow_shipping;

defined('ABSPATH') or exit;

/**
 * Método de envío.
 */
class shipping extends \WC_Shipping_Method {
	/**
	 * Constructor.
	 */
	function __construct($instance_id=0) {
		$this->id=plugin::id;
		$this->method_title=__('Shipnow','shipnow-shipping');
		$this->title=$this->method_title;
		$this->method_description=__('Custom shipping method for Shipnow.','shipnow-shipping'); 
		$this->supports=[
			'shipping-zones',
			'settings'
		];
		$this->instance_id=absint($instance_id);
		$this->init();
	}

	/**
	 * 
	 */
	protected function init() {
		//print_r(wc_get_order(21));
		
		$this->addActions();
		$this->init_settings();
		$this->setupConfigForm();
	}

	/**
	 * 
	 */
	protected function addActions() {
		add_action('woocommerce_update_options_shipping_'.$this->id,[$this,'process_admin_options']);
	}
 
	/**
	 * 
	 */
 	protected function setupConfigForm() {
 		$token=$this->settings['token'];
 		$validToken=null;

 		$api=plugin::api();
 		if($token) {
 			$validToken=$api
 				->setToken($token)
 				->validateToken();
 		}

 		$this->form_fields=[
			'enabled'=>[
				'title'=>__('Enable Shipnow','shipnow-shipping'),
				'description'=>__('Enable Shipnow for your store.','shipnow-shipping'),
				'type'=>'checkbox',
				'default'=>'yes'
			],
			'token'=>[
				'title'=>__('Token','shipnow-shipping'),
				'description'=>__('Enter the token you can find in your Shipnow account > Stores > Woocommerce > Checkout configuration.','shipnow-shipping'),
				'type'=>'text'
			]
		];

		if($token&&!$validToken) {
			if($validToken===null) {
				$error=__('Looks like the value you entered is not a valid Shipnow token. Fix it or <a href="https://shipnow.com.ar" target="_blank">contact us</a> to continue.','shipnow-shipping');
			} else {
				$error=__('Looks like the token is a valid Shipnow token, but it isn\'t a WooCommerce token. Enter your WooCommerce token to continue or <a href="https://shipnow.com.ar" target="_blank">contact us</a> if you have any questions.','shipnow-shipping');
			}

			$this->form_fields['error']=[
				'type'=>'title',
				'description'=>'<div class="inline error"><p><strong>'.
								__('Invalid token!','shipnow-shipping').
								'</strong><br>'.
								$error.
								'</p></div>'
			];
		} elseif($token) {
			//$opt=[];
			//foreach($stores as $store)
			//	$opt[$store->id]=$store->name;
			
			$this->form_fields=array_merge($this->form_fields,[
				//'store'=>[
				//	'title'=>__('Store','shipnow-shipping'),
				//	'type'=>'select',
				//	'description'=>__('Select the store you want to use for your Shipnow orders.','shipnow-shipping'),
				//	'options'=>$opt
				//],
				'enable_widget'=>[
					'title'=>__('Enable widget','shipnow-shipping'),
					'description'=>__('Enable the shipping cost estimator widget on the product page.','shipnow-shipping'),
					'type'=>'checkbox',
					'default'=>'yes'
				],
				'widget_title'=>[
					'title'=>__('Widget title','shipnow-shipping'),
					'description'=>__('Add a title above the widget, if enabled (optional).','shipnow-shipping'),
					'type'=>'text'
				],
				'round'=>[
					'title'=>__('Round shipping cost','shipnow-shipping'),
					'description'=>__('Check this option to round the shipping cost (ex. $500), or unckeck it to keep the cost rounded to two decimal places (ex. $500,00).','shipnow-shipping'),
					'type'=>'checkbox'
				],
				'display_days'=>[
					'title'=>__('Display estimated delivery time','shipnow-shipping'),
					'description'=>__('Check to display the estimated delivery time (in business days) in the widgets and the shopping cart.','shipnow-shipping'),
					'type'=>'checkbox'
				]
			]);

			foreach($api->getShippingTypes() as $type=>$options) {
				$this->form_fields=array_merge($this->form_fields,[					
					$type.'_title'=>[
						'type'=>'title',
						'title'=>$options->title
					],
					$type.'_enabled'=>[
						'title'=>__('Enable this shipping option','shipnow-shipping'),
						'type'=>'checkbox',
						'default'=>$options->default?'yes':'no'
					],
					$type.'_description'=>[
						'title'=>__('Description','shipnow-shipping'),
						'description'=>__('Description that your users will see.','shipnow-shipping'),
						'type'=>'text',
						'default'=>$options->description
					],
					$type.'_discount_type'=>[
						'title'=>__('Set an additional cost or discount','shipnow-shipping'),
						'description'=>__('Additional cost or discount value over the shipping cost estimate (optional)','shipnow-shipping'),
						'type'=>'select',
						'default'=>'0',
						'options'=>[
							'0'=>__('No','shipnow-shipping'),
							'1'=>__('Additional cost','shipnow-shipping'),
							'2'=>__('Discount','shipnow-shipping')
						]
					],
					$type.'_discount_value'=>[
						'title'=>__('Additional cost or discount value','shipnow-shipping'),
						'description'=>__('In ARS','shipnow-shipping'),
						'type'=>'number',
						'default'=>'0'
					],
					$type.'_days'=>[
						'title'=>__('Additional days','shipnow-shipping'),
						'description'=>__('Additional business days over the estimated delivery time (optional).','shipnow-shipping'),
						'type'=>'number',
						'default'=>'0'
					]
				]);
			}
		}
 	}

	/**
	 * 
	 */
	public function process_admin_options() {
		$data=$this->get_post_data();
		$prefix='woocommerce_'.plugin::id.'_';

		if($data['enable_widget']=='yes') {
			foreach(plugin::api()->getShippingTypes() as $type=>$options) {
				$val=$data[$prefix.$type.'_days'];
				if($val!==''&&!preg_match('/^[0-9]+$/',$val)) {
					\WC_Admin_Settings::add_error(__('Additional days value is not valid. Enter numbers only.','shipnow-shipping'));
					return false;
				}

				$val=$data[$prefix.$type.'_discount_value'];
				if($val!==''&&!preg_match('/^[0-9]+$/',$val)) {
					\WC_Admin_Settings::add_error(__('Additional cost or discount value is not valid. Enter numbers only.','shipnow-shipping'));
					return false;
				}
			}
		}

		return parent::process_admin_options();
	}

	/**
	 * Devuelve la configuración del método de envío.
	 * @return object
	 */
	public function getSettings() {
		return $this->settings?(object)$this->settings:null;
	}

	/**
	 * Realiza el cálculo de envío en el carro de compras.
	 * @param array $package
	 */
	public function calculate_shipping($package=[]) {	
		if($package['destination']['country']!='AR') return;

		$pasSelected=woocommerce::getSelectedPostOffice();
		$pasSelectedGroupId=null;

		$weight=0;
		$volume=0;
		$zip=woocommerce::getZip();
		if(!$zip) return;
		
    	foreach($package['contents'] as $id=>$values) { 
        	$product=$values['data']; 
			//Se asume kg, cm
			//TODO (Ver) Si se requiere conversión, debería reauizarse aquí
        	$weight+=$product->get_weight()*$values['quantity']; 
        	$volume+=$product->get_length()*$product->get_width()*$product->get_height()*$values['quantity']; 
    	}
	
		$offices=[];
		$estimates=plugin::getEstimatesAvailableShippingOptions($zip,$weight,$volume,$offices);
		if(!$estimates) return;

		foreach($estimates as $estimate) {
			$code=$estimate->code;
			$id=$this->id.'_'.$estimate->typeName.'_'.$code;
			$label=$estimate->title;
			$cost=$estimate->costNumeric;
			$selectedOffice=0;

			if($estimate->typeName=='pas') {
            if($estimate->carrier=='Javit') {
			$label.=' - Farmacia';
			} else {
			$label.=' - '.$estimate->carrier;
			}
				
				foreach($offices[$estimate->groupCode] as $i=>$office) {
					if($office->code==$pasSelected) {
						$selectedOffice=$i+1;
						$pasSelectedGroupId=$id;

						$label.=' - '.$office->to;
						$cost=$office->costNumeric;
						$code=$office->code;
					}
				}
			}	

			if($cost==0) $label.=': '.__('Free','shipnow-shipping');

			$this->add_rate([
				'office_code'=>$code,
				'id'=>$id,
				'label'=>$label,
				'cost'=>$cost,
				'taxes'=>false,
				'meta_data'=>[
					//base64 porque WC altera el texto y rompe el serializado
					'shipnow'=>base64_encode(serialize((object)[
						'available_options'=>$offices[$estimate->groupCode],
						'selected_option'=>$selectedOffice
					])),
					'office_code'=>$code
				]
			]);
		}

		$method=woocommerce::getSelectedShippingMethod();
		if(!$method||$method!=$pasSelectedGroupId)
			woocommerce::setShippingMethod($pasSelectedGroupId);
	}

	/**
	 * Determina si el método de envío está disponible para el pedido.
	 * @return bool
	 */
	public function is_available($package) {
		return true;
	}
}