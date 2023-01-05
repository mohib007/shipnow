#### Preparación para distribución

1. La constante `\shipnow_shipping\plugin::minifiedAssets` debe quedar establecida a `true`.

#### Instalación

1. Instalar y activar el plugin normalmente.

2. Ingresar en WooCommerce - Ajustes - Envíos - Envío Shipnow.

3. Ingresar el token y guardar para validar el mismo.

4. Completar la configuración.

5. Ingresar en Zonas de envío - Argentina o la zona en la que se desee brindar el servicio (Editar).

6. Hacer click en Agregar método de envío y seleccionar Envío Shipnow.

#### Configurar tipos de envío

Los tipos de envío son definidos por la clase `api` en `api.php` método `getShippingTypes()`.

#### Traducción

De acuerdo a las prácticas de WooCommerce, toda la extensión fue desarrollada en inglés. Pueden utilizarse `generar-pot.bat` para regenerar el archivo `.pot` (si se agregan nuevas cadenas a traducir) y `compilar-po.bat` (requiere `gettext`; modificar rutas de los archivos de entrada y salida) para generar un nuevo archivo `.mo`.

Las traducciones deben almacenarse como (relativo al directorio del plugin)`languages/shipnow-shipping-{codigo}.mo`.