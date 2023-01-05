<?php
namespace shipnow_shipping;

defined('ABSPATH') or exit;

/**
 * Interfaz con WooCommerce.
 */
class woocommerce {
	/**
	 * Determina y devuelve si WooCommerce está instalado y activo.
	 * @return bool
	 */
	public static function isAvailable() {
		return in_array('woocommerce/woocommerce.php',apply_filters('active_plugins',get_option('active_plugins')));
	}

	/**
	 * Inicializa el plugin.
	 */
	public static function init() {
		$c=self::class.'::';
		add_action('woocommerce_shipping_init',$c.'woocommerce_shipping_init');
		add_filter('woocommerce_shipping_methods',$c.'woocommerce_shipping_methods');
		add_action('woocommerce_before_add_to_cart_form',$c.'woocommerce_before_add_to_cart_form');
		//add_action('woocommerce_review_order_before_cart_contents',$c.'woocommerce_review_order_before_cart_contents',10);
		add_action('woocommerce_after_checkout_validation',$c.'woocommerce_after_checkout_validation',10);
		add_action('woocommerce_checkout_create_order',$c.'woocommerce_checkout_create_order',20,2);
	}

	/**
	 * Acción `woocommerce_checkout_create_order`.
	 */
	public static function woocommerce_checkout_create_order($order,$data) {
		$order->update_meta_data('shipnow_option',self::processSelectedShippingMethod());
		$order->update_meta_data('shipnow_version',plugin::version);
		//$order->update_meta_data('shipnow_token',$settings->token);
		//$order->update_meta_data('shipnow_store',$settings->store);		
		$order->save();
	}

	/**
	 * Acción `woocommerce_shipping_init`.
	 */
	public static function woocommerce_shipping_init() {
		$wc=WC();

		//if($wc->session) {
		//	$zip=$wc->session->get('shipnow_set_zipcode');
		//	if($zip) $wc->customer->set_shipping_postcode($zip);
		//}

		include_once(__DIR__.'/shipping.php');
	}

	/**
	 * Filtro `woocommerce_shipping_methods`.
	 */
	public static function woocommerce_shipping_methods($methods) {
		$methods[plugin::id]='\\shipnow_shipping\\shipping';
		return $methods;
	}

	/**
	 * Acciones `woocommerce_after_checkout_validation`.
	 */
	public static function woocommerce_after_checkout_validation($fields) {
		if(self::processSelectedShippingMethod()===false)
			wc_add_notice(__('Select the post office.','shipnow-shipping'),'error');
	}

	/**
	 * Devuelve el objeto final del método de envío, `false` si no es válida, o `null` si no es Shipnow.
	 * @return object|null
	 */
	protected static function processSelectedShippingMethod() {
		$method=self::getSelectedShippingMethod();
	
		if(!$method) return null;
		
		$id=$method->get_id();

		if(!preg_match('/^'.plugin::id.'_/',$id)) return null;

		$meta=$method->get_meta_data();
		if(!is_array($meta)||!$meta['shipnow']) return null;
		$shipnow=unserialize(base64_decode($meta['shipnow']));
		if(!$shipnow||!is_array($shipnow->available_options)) return null;
		
		if(preg_match('/_pas_/',$id)) {
			if(!$shipnow->selected_option||!$shipnow->selected_option) return false;
			return $shipnow->available_options[$shipnow->selected_option-1];
		}
		
		return $shipnow->available_options[0];
	}

	/**
	 * Acción `woocommerce_before_add_to_cart_form`.
	 */
	public static function woocommerce_before_add_to_cart_form() {
		$config=plugin::getSettings();
		if(!$config->enabled||!$config->enable_widget||!$config->token||!self::isAvailable()) return;
		echo (new widget)->getHtml();
	}

	/**
	 * Devuelve los parámetros de un producto relevantes para el plugin, dado su ID.
	 * @param int $id ID.
	 * @return object
	 */
	public static function getProduct($id) {
		$product=wc_get_product($id);
		if(!$product) return null;
		return (object)[
			'id'=>$id,
			'title'=>$product->get_name(),
			'code'=>$product->get_sku(),
			//Se asume kg, cm
			//Si se requiere conversión, debería realizarse aquí
			'weight'=>$product->get_weight(),
			'volume'=>$product->get_length()*$product->get_width()*$product->get_height()
		];
	}

	/**
	 * Devuelve el ID del paquete. Por el momento, solo soporta un único paquete.
	 * @return int|null
	 */
	public static function getPackageId() {
		$wc=WC();

		if($wc->cart)
			foreach($wc->cart->get_shipping_packages() as $id=>$package)
				return $id;

		return null;
	}

	/**
	 * Devuelve los parámetros de la forma de envío seleccionada.
	 * @return object|null
	 */
	public static function getSelectedShippingMethod() {
		$wc=WC();
		if(!$wc->session) return null;

		$id=self::getPackageId();
		if($id===null) return null;

		$selected=$wc->session->get('chosen_shipping_methods');
	
		if(!$selected) return null;
	
		$rates=$wc->session->get('shipping_for_package_'.$id)['rates'];
		if(is_array($rates))
			foreach($rates as $key=>$rate)
				if($key==$selected[$id]) return $rate;
			
		return null;
	}

	/**
	 * Establece la sucursal de correo seleccionada.
	 * @var int $id ID de la sucursal.
	 */
	public static function setSelectedPostOffice($id) {
		$package=self::getPackageId();
		if($package===null) return;

		$sess=WC()->session;
		if($sess) {
			$sess->set('shipnow_chosen_postoffice',$id);
			$sess->__unset('shipping_for_package_'.$package);
		}
	}

	/**
	 * Devuelve el código de la sucursal de correo seleccionada.
	 * @return string|null
	 */
	public static function getSelectedPostOffice() {
		$sess=WC()->session;
		if(!$sess) return null;
		return $sess->get('shipnow_chosen_postoffice');
	}

	/**
	 * Establece la forma de envío.
	 * @var string $id ID de la forma de envío.
	 */
	public static function setShippingMethod($id) {
		$package=self::getPackageId();
		if($package===null) return;
		
		$sess=WC()->session;
		if($sess) {
			$sess->set('chosen_shipping_methods',[$package=>$id]);
			$sess->__unset('shipnow_chosen_postoffice');
		}
	}

	/**
	 * Devuelve el código postal.
	 * @return string
	 */
	public static function getZip() {
		$wc=WC();

		//if($wc->session) {
		//	$zip=$wc->session->get('shipnow_actual_zipcode');
		//	if($zip) return $zip;
		//}

		if($wc->customer) {
			$zip=$wc->customer->get_shipping_postcode();
			if($zip) {
				//if($wc->session) $wc->session->set('shipnow_set_zipcode',$zip);
				return $zip;
			}
		}
		
		return null;
	}

	/**
	 * Establce un nuevo código postal.
	 * @var string $zip Código postal.
	 */
	public static function setZip($zip) {
		$wc=WC();
		if($wc->customer) $wc->customer->set_shipping_postcode($zip);
		//if($wc->session) $wc->session->set('shipnow_set_zipcode',$zip);
		self::setShippingMethod(null);
	}
}
