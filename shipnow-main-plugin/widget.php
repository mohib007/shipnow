<?php
namespace shipnow_shipping;

defined('ABSPATH') or exit;

/**
 * Widget.
 */
class widget extends \WP_Widget {
	/**
	 * Constructor.
	 */
	function __construct() {
		//Para agregar como widget embebible (actualmente se inserta automáticamente en la vista de producto ampliado)
		//parent::__construct(
		//	'shipnow_shipping_widget',
		//	__('Shipnow shipping cost estimator','shipnow-shipping'),
		//	null
		//);
	}

	/**
	 * Inicializa el widget.
	 */
	public static function init() {
		add_action('wp_ajax_shipnow_widget_get_estimate',self::class.'::shipnow_widget_get_estimate');
		add_action('wp_ajax_nopriv_shipnow_widget_get_estimate',self::class.'::shipnow_widget_get_estimate');

		add_action('wp_ajax_shipnow_widget_get_post_offices',self::class.'::shipnow_widget_get_post_offices');
		add_action('wp_ajax_nopriv_shipnow_widget_get_post_offices',self::class.'::shipnow_widget_get_post_offices');

		add_action('wp_ajax_shipnow_widget_set_post_office',self::class.'::shipnow_widget_set_post_office');
		add_action('wp_ajax_nopriv_shipnow_widget_set_post_office',self::class.'::shipnow_widget_set_post_office');

		add_action('wp_ajax_shipnow_widget_set_zip_code',self::class.'::shipnow_widget_set_zip_code');
		add_action('wp_ajax_nopriv_shipnow_widget_set_zip_code',self::class.'::shipnow_widget_set_zip_code');
		
		add_action( 'woocommerce_after_shipping_rate', self::class.'::action_woocommerce_after_shipping_selection', 10, 2);
		add_action('woocommerce_checkout_update_order_review', self::class.'::testing_function');
	}

	
	public static function testing_function() {
		//die(var_dump($_POST));
	}
	
	public static function action_woocommerce_after_shipping_selection( $method, $index ) {
		// Target a specific method ID
		if( $method->method_id == "shipnow_shipping"){
			if(str_contains($method->id, '_pas_')){
				echo "<div class='post_office_group'>";
				echo self::shipnow_get_post_offices_html($method);
				echo "</div>";
			}
		}

	}

	
	/**
	 * Acciones `wp_ajax_shipnow_widget_get_estimate` y `wp_ajax_nopriv_shipnow_widget_get_estimate`. Responde a la
	 * solicitud AJAX desde el sitio público.
	 */
	public static function shipnow_widget_get_estimate() {
		$zip=substr(filter_input(INPUT_POST,'zip',FILTER_SANITIZE_STRING),0,4);
		$id=filter_input(INPUT_POST,'id',FILTER_SANITIZE_NUMBER_INT);

		//$_SESSION['_shipnow_temp_zip']=$zip;

		if(!$id||!$zip) wp_die();

		$product=woocommerce::getProduct($id);
		if(!$product) wp_die();

		$offices=[];

		$estimates=plugin::getEstimatesAvailableShippingOptions($zip,
			$product->weight,
			$product->volume,
			$offices
		);
		if(!$estimates) wp_die();

		$elem=file_get_contents(__DIR__.'/html/estimate-elem.html');
		$officeElem=file_get_contents(__DIR__.'/html/estimate-postoffice-elem.html');
		$html='';
		foreach($estimates as $estimate) {
			$carrier='';
			$link='';
			$postOfficesHtml='';

			if($estimate->typeName=='pas') {
				$carrier=' - '.$estimate->carrier;

				if($estimate->description) $link.=' - ';
				$link.='<a href="#" class="shipnow-view-postoffices">'.__('View post offices','shipnow-shipping').'</a>';
				
				foreach($offices[$estimate->groupCode] as $office) {
					$postOfficesHtml.=plugin::strReplaceArray([
						'{title}'=>$office->to,
						'{code}'=>$office->code,
						'{description}'=>$office->days,
						'{cost}'=>$office->cost,
						'{input}'=>''
					],$officeElem);
				}
			}

			$html.=plugin::strReplaceArray([
				'{title}'=>$estimate->title.$carrier,
				'{description}'=>$estimate->description.$link,
				'{cost}'=>$estimate->cost,
				'{fromLabel}'=>__('Starting at','shipnow-shipping'),
				'{class}'=>$estimate->values_from?'shipnow-estimate-from':'',
				'{postOffices}'=>$postOfficesHtml,
				'{postOfficesFooter}'=>__('You will select the post office in the shopping cart.','shipnow-shipping')
			],$elem);
		}

		echo json_encode([
			'html'=>$html
		]);
		wp_die();
	}

	/**
	 * Acciones `wp_ajax_nopriv_shipnow_widget_set_post_office` y `wp_ajax_shipnow_widget_set_post_office`. Responde a la solicitud AJAX desde
	 * el sitio público.
	 */
	public static function shipnow_widget_get_post_offices() {
		$html='';

		$officeElem=file_get_contents(__DIR__.'/html/estimate-postoffice-elem.html');

		$selected=woocommerce::getSelectedShippingMethod();
	
		if($selected && !empty($selected)) {
			$meta=$selected->get_meta_data();
			$shipnow_pas_id = $selected->meta_data['office_code'];			
			if($meta&&$meta['shipnow']) {
				$shipnow=unserialize(base64_decode($meta['shipnow']));
				$offices=$shipnow->available_options;
				if(is_array($offices) && !empty($offices)) {
					foreach($offices as $office) {						
						if($office->code == $shipnow_pas_id){
							$html.=plugin::strReplaceArray([
								'{title}'=>$office->to,
								'{label_id}'=>$office->code,
								'{description}'=>$office->days,
								'{cost}'=>$office->cost,
								'{input}'=>'<input type="radio" id="'.$office->code.'" name="shipnow-select-button[]" class="shipnow-select-button" checked="checked" data-id="'.$office->code.'">'
							],$officeElem);
						}
						else{
							$html.=plugin::strReplaceArray([
								'{title}'=>$office->to,
								'{label_id}'=>$office->code,
								'{description}'=>$office->days,
								'{cost}'=>$office->cost,
								'{input}'=>'<input type="radio" id="'.$office->code.'" name="shipnow-select-button[]" class="shipnow-select-button" data-id="'.$office->code.'">'
							],$officeElem);							
						}
					}
				}else{
					$html.= __('Their is some network error, please change and re-select the shipment location.','shipnow-shipping');
				}
			}
		} else{
			__('Their is some network error, please change and re-select the shipment location.','shipnow-shipping');
		}

		echo json_encode([
			'html'=>$html
		]);
		wp_die();
	}

	public static function shipnow_get_post_offices_html($method) {
			$html='';

			$officeElem=file_get_contents(__DIR__.'/html/estimate-postoffice-elem.html');

			$selected=$method;
			$selectedforCheckout=woocommerce::getSelectedShippingMethod();		
			if($selected && !empty($selected)) {
				$meta=$selected->get_meta_data();
				$shipnow_pas_id = $selectedforCheckout->meta_data['office_code'];			
				if($meta&&$meta['shipnow']) {
					$shipnow=unserialize(base64_decode($meta['shipnow']));
					$offices=$shipnow->available_options;
					if(is_array($offices) && !empty($offices)) {
						foreach($offices as $office) {						
							if($office->code == $shipnow_pas_id){
								$html.=plugin::strReplaceArray([
									'{title}'=>$office->to,
									'{label_id}'=>$office->code,
									'{description}'=>$office->days,
									'{cost}'=>$office->cost,
									'{input}'=>'<input type="radio" id="'.$office->code.'" name="shipnow-select-button[]" class="shipnow-select-button" checked="checked" data-id="'.$office->code.'">'
								],$officeElem);
							}
							else{
								$html.=plugin::strReplaceArray([
									'{title}'=>$office->to,
									'{label_id}'=>$office->code,
									'{description}'=>$office->days,
									'{cost}'=>$office->cost,
									'{input}'=>'<input type="radio" id="'.$office->code.'" name="shipnow-select-button[]" class="shipnow-select-button" data-id="'.$office->code.'">'
								],$officeElem);							
							}
						}
					}else{
						$html.= __('Their is some network error, please change and re-select the shipment location.','shipnow-shipping');
					}
				}
			} else{
				__('Their is some network error, please change and re-select the shipment location.','shipnow-shipping');
			}
			return $html;
		}	
	
	
	/**
	 * Acciones `wp_ajax_shipnow_widget_get_post_offices` y `wp_ajax_nopriv_shipnow_widget_get_post_offices`. Responde a la solicitud AJAX desde
	 * el sitio público.
	 */
	public static function shipnow_widget_set_post_office() {
		$id=substr(filter_input(INPUT_POST,'id',FILTER_SANITIZE_STRING),0,255);
		
		if(preg_match('/^[\d_]+$/',$id)) {
			woocommerce::setSelectedPostOffice($id);
		}
		echo 'ok';
		wp_die();
		//check_ajax_referer( 'update-shipping-method', 'security' );

		//wc_maybe_define_constant( 'WOOCOMMERCE_CART', true );

		//$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
		//$posted_shipping_methods = isset( $_POST['shipping_method'] ) ? wc_clean( wp_unslash( $_POST['shipping_method'] ) ) : array();

		//if ( is_array( $posted_shipping_methods ) ) {
			//foreach ( $posted_shipping_methods as $i => $value ) {
		//		$chosen_shipping_methods[ $i ] = $value;
		//	}
		//}

		//WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );

		wc_maybe_define_constant( 'WOOCOMMERCE_CART', true );
		WC()->cart->calculate_totals();
		woocommerce_cart_totals();
		wp_die();
	}

	/**
	 * Acciones `wp_ajax_shipnow_widget_set_zip_code` y `wp_ajax_shipnow_widget_set_zip_code`. Responde a la solicitud AJAX desde
	 * el sitio público.
	 */
	public static function shipnow_widget_set_zip_code() {
		$zip=substr(filter_input(INPUT_POST,'zip',FILTER_SANITIZE_STRING),0,4);

		woocommerce::setZip($zip);

		echo 'ok';
		wp_die();
	}

	/**
	 * Devuelve el cuerpo HTML del widget.
	 * @return string
	 */
	public function getHtml() {
		global $product;
		$config=plugin::getSettings();

		$options='';
		foreach(plugin::getEnabledShippingTypes() as $key=>$value)
			$options.='<option value="'.$key.'">'.__($value).'</option>';

		$title=trim($config->widget_title);

		return plugin::strReplaceArray([
			'{title}'=>$title?__($title):'',
			'{titleClass}'=>$title?'':'shipnow-hidden',
			'{id}'=>$product->get_id(),
			'{zipLabel}'=>__('Zip code:','shipnow-shipping'),
			'{error}'=>__('We were unable to get the shipping cost for that Zip code.','shipnow-shipping'),
			'{zip}'=>''
		],file_get_contents(__DIR__.'/html/widget.html'));
	}
}