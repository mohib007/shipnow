/**
 * Widget de cotización de envíos Shipnow.
 */
(function() {
	"use strict";

	var $=jQuery,
		lastValue=null,
		xhr=null,
		popupElem=null,
		popupBodyElem=null,
		selectedPostalOffice=null,
		queryTimer;
   		window.ajax_loading = false;
	
	
	/**
	 * check if ajax run function.
	*/	
    $.hasAjaxRunning = function() {
        return window.ajax_loading;
    };
	
    $(document).ajaxStart(function() {
        window.ajax_loading = true;
    });
    $(document).ajaxStop(function() {
        window.ajax_loading = false;
    });
	
	/**
	 * Determina y devuelve si un valor es un código postal válido.
	 * @param {*} value - Valor a analizar.
	 * @returns {boolean}
	 */
	function isValidZip(value) {
		return !isNaN(value)&&value&&String(value).length==4;
	}

	/**
	 * Realiza la consulta al servidor.
	 * @param {string} action - Acción.
	 * @param {Object} params - Parámetros.
	 * @param {function} callback - Función de retorno.
	 * @returns {Promise}
	 */
	function query(action,params,callback) {
		params.action=action;
		return $.post(shipnow.ajaxurl,params,function(data) {
			callback(data);
		},"json").fail(function() {
			callback(null);
		});
	}

	/**
	 * Realiza la consulta al servidor. Ver `widget.php`.
	 * @param {string} id - ID del producto.
	 * @param {string} zip - Código postal.
	 * @param {function} callback - Función de retorno.
	 * @returns {Promise}
	 */
	function queryShippingCost(id,zip,callback) {
		return query("shipnow_widget_get_estimate",{
			id:id,
			zip:zip
		},callback);
	}

	/**
	 * Realiza la consulta de sucursales de correo al servidor. Ver `widget.php`.
	 * @param {function} callback - Función de retorno.
	 * @returns{Promise}
	 */
	 function queryPostOffices(callback) {
		return query("shipnow_widget_get_post_offices",{},callback);
	}

	/**
	 * Realiza la consulta para seleccionar el método de envío especificado. Ver `widget.php`.
	 * @param {string} id - ID del método de envío u oficina de correo.
	 * @param {function} callback - Función de retorno.
	 * @returns {Promise}
	 */
	function querySelectPostOffice(id,callback) {	
		var a = '.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table, .cart-collaterals, .woocommerce-cart-form';
		selectedPostalOffice = id;
		return query("shipnow_widget_set_post_office",{
			id:id
		},callback);
	}

	/**
	 * Realiza la consulta para establecer un nuevo código postal. Ver `widget.php`.
	 * @param {string} zip - Nuevo código postal
	 * @param {function} callback - Función de retorno.
	 * @returns {Promise}
	 */
	function querySetZip(zip,callback) {
		return query("shipnow_widget_set_zip_code",{
			zip:zip
		},callback);
	}

	/**
	 * Manejador de los eventos de modificación de los campos.
	 * @param {*} [ev] - Evento.
	 */
	function fieldChanged(ev) {	
		var form=$(this).closest("form");

		clearTimeout(queryTimer);
		queryTimer=setTimeout(function() {			
			var idField=form.find(".shipnow-id-field"),
				zipField=form.find(".shipnow-zip-field"),
				resultsElem=form.find(".shipnow-estimator-results"),
				errorElem=form.find(".shipnow-estimator-error"),
				id=idField.val(),
				zip=zipField.val();

			if(zip==lastValue) return;
			lastValue=zip;

			if(xhr) xhr.abort();

			$.when(
				errorElem
					.stop()
					.slideUp(),
				resultsElem
					.stop()
					.slideUp()
			)
			.then(function() {
				if(!isValidZip(zip)) return;

				form.addClass("shipnow-estimator-working");

				xhr=queryShippingCost(id,zip,function(data) {
					form.removeClass("shipnow-estimator-working");

					if(!data||!data.html) {
						errorElem.slideDown();
						return;
					}

					resultsElem
						.html(data.html)
						.slideDown();

					resultsElem.find(".shipnow-view-postoffices").on("click",function(ev) {
						ev.preventDefault();
						showPostOffices($(this));
					});
				});
			});
		},800);
	}

	/**
	 * Bloquea o desbloquea el formulario de WooCommerce.
	 * @param {boolean} [block=true] - Bloquear o desbloquear.
	 */
	function setBlockWcUi(block) {
		//TODO
	}

	/**
	 * Intenta refrescar los datos de WooCommerce.
	 */
	function triggerUpdateWcUi() {
		$("body")
			.trigger("update_checkout")
			.trigger("wc_update_cart");
	}

	/**
	 * Construye y abre el diálogo (pop-up).
	 * @param {string} html - HTML del cuerpo.
	 * @param {function} [callback] - Función de retorno.
	 */
	function openPopup(html,callback) {
		
		if(!popupElem) {
			if($('body').find('.shipnow-popup').html()){
				$('body').find('.shipnow-popup').remove();
			}				
			popupElem=$(shipnow._popupHtml).insertAfter($('.shipnow-select-postoffice').parents('li'));
			popupBodyElem=popupElem.children("div").children("div");

			popupElem.find(".shipnow-popup-shadow,.shipnow-close").on("click",function(ev) {
				ev.preventDefault();
				closePopup();
			});
		}

		popupElem.stop();	
		popupBodyElem.html(html);
		popupElem
			.show()
			.fadeTo(200,1,function() {
				if(typeof callback=="function") callback();
			});
		popupElem=null;
	}

	/**
	 * Cierra el diálogo (pop-up).
	 */
	function closePopup() {
		$(".shipnow-select-postoffice").removeClass("prevent-pointer-event");
		if(!popupElem) return;
		popupElem
			.stop()
			.fadeTo(200,0,function() {
				popupElem.hide();
			});
	}

	/**
	 * Muestra el listado de sucursales del correo.
	 * @param {*} btn - Botón o link clickeado.
	 */
	function showPostOffices(btn) {
		var a = '.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table, .cart-collaterals, .woocommerce-cart-form';
		var setupEvents=function() {
			popupBodyElem.find(".shipnow-select-button").on("click",function(ev) {
				ev.preventDefault();

				var id=$(this).data("id");
				//setBlockWcUi();
				closePopup();

				if(xhr) xhr.abort();

				
				xhr=querySelectPostOffice(id,function() {
					//triggerUpdateWcUi();
				});
				
				if(xhr){
					$(a).unblock();
				}
				
			});
		};
		
		if(typeof btn=="object"&&btn) {
			var item=btn.parents(".shipnow-estimate"),
				html=item.find(".shipnow-estimate-postoffices").html();
			openPopup(html,setupEvents);
			$('.shipnow-close').next('div').css("padding-top", 0);
			if(selectedPostalOffice){
				$("#"+selectedPostalOffice).attr('checked', true);
			}	
			closePopup();
			return;
		}

		//setBlockWcUi();

		xhr=queryPostOffices(function(data) {
			//setBlockWcUi(false);

			if(!data||!data.html) {
				// turned of as now it always shows, even on start when no shipzone selected
				//alert(shipnow._unableToGetPostOffices)
				return;
			}
			openPopup(data.html,setupEvents);
			if(selectedPostalOffice){
				$("#"+selectedPostalOffice).attr('checked', true);
			}
			$('.shipnow-close').next('div').css("padding-top", 0);
			closePopup();
		});
	}

	/**
	 * Muestra el diálogo de cambio de código postal.
	 */
	function showChangeZipPopup() {
		var changeZipOk=function() {
			var zipField=popupBodyElem.find(".shipnow-zip-field"),
				zip=zipField.val();

			if(!isValidZip(zip)) {
				alert(shipnow._invalidZipError);
				zip.focus();
				return;
			}

			$("#ship-to-different-address-checkbox").prop("checked",true);
			$("#shipping_postcode").val(zip);

			//setBlockWcUi();
			closePopup();

			if(xhr) xhr.abort();
			xhr=querySetZip(zip,function(data) {
				//setBlockWcUi(false);
				//triggerUpdateWcUi();
			});
		};

		openPopup(shipnow._changeZipHtml,function() { 			
			$(popupBodyElem).find(".shipnow-ok-button").on("click",function(ev) {
				ev.preventDefault();
				changeZipOk();
			});

			popupBodyElem.find("input").focus();
		});
	}

	/**
	 * Procesa el evento `updated_shipping_method`.
	 */
	function updatedShippingMethod() {
		var destinationElem=$(".woocommerce-shipping-destination,.woocommerce-shipping-calculator,.woocommerce-shipping-fields").show();
		var a = '.woocommerce-checkout-payment, .woocommerce-checkout-review-order-table, .cart-collaterals, .woocommerce-cart-form';		
		$(".shipnow-options").remove();		

		$("input.shipping_method[value^='shipnow_shipping_']").each(function() {
			var postOfficeLink=$("<a href='#' class='shipnow-select-postoffice'>"+shipnow._selectPostOffice+"</a>"),
				zipLink=$("<a href='#' class='shipnow-change-zipcode'>"+shipnow._changeZipCode+"</a>"),
				t=$(this),
				li=t.parents("li");	

		$("#shipping_method").find(".shipnow-select-button").on("click",function(ev) {
			if(xhr) xhr.abort();
			ev.preventDefault();
			var id=$(this).data("id");
			setBlockWcUi();
			var shipping_method_id = $(this).parents("li").children(".shipping_method").attr("id");
			//$(shipping_method_id).trigger("click");
			
			xhr=querySelectPostOffice(id,function(response) {
				if(xhr.status == 200){
					//$(".calculated_shipping").parent().html(xhr.responseText);
					triggerUpdateWcUi();
					closePopup();			
				}

			});
			
			if(xhr){			
				$(a).unblock();
			}					
		});		
			
			
			if(/_pas_/.test(t.attr("id"))) {
				$(this).attr("disabled","disabled");
				$(this).addClass("shipping_pas");				
				$(this).next("label").addClass("shipping_pas");
				if(t.prop("checked")) {
					li.append($("<div class='shipnow-options' style='display:none !important;'>")
						.append(zipLink)
						.append(postOfficeLink)
					);

					/* enable for direct view */	
					//setBlockWcUi();
					$(".shipnow-select-postoffice").addClass("prevent-pointer-event");
					//showPostOffices();

					postOfficeLink.on("click",function(ev) {
						$(".shipnow-select-postoffice").addClass("prevent-pointer-event");
						ev.preventDefault();
						showPostOffices();
					});

					zipLink.on("click",function(ev) {
						ev.preventDefault();
						showChangeZipPopup();
					});

					//destinationElem.hide();
					//$("#ship-to-different-address-checkbox").prop("checked",true);
				}
			}else{
				$(this).addClass("shipping_pap");				
				$(this).next("label").addClass("shipping_pap");
			}
		});
	}

	/**
	 * Configura el widget del cotizador de Shipnow.
	 */
	function setupWidget() {
		$("body")
			.on("input paste change",".shipping-cost .shipnow-zip-field",fieldChanged)
			.on("keydown",function(ev) {
				if(ev.which==13) ev.preventDefault();
			});
		fieldChanged();
		updatedShippingMethod();
	}

	/**
	 * Inicializa los eventos.
	 */
	function events() {
		$("body").on("init_checkout updated_checkout updated_wc_div updated_cart_totals country_to_state_changed updated_shipping_method cart_page_refreshed cart_totals_refreshed wc_fragments_loaded",
			updatedShippingMethod);
	}

	$(window).on("load",function() {
		setupWidget();
		events();
	});
})();