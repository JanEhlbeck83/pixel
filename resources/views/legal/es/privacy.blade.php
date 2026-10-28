@extends('legal.layout')

@section('title', 'Política de privacidad')

@section('content')
    <h2>1. Responsable del tratamiento</h2>
    <p>
        <x-legal-value key="name" />, <x-legal-value key="street" />, <x-legal-value key="zip_city" />,
        {{ config('legal.country') }}<br>
        Correo electrónico: <x-legal-value key="email" />
    </p>

    <h2>2. Resumen</h2>
    <p>
        Pixel Chaos es un muro de píxeles compartido. Tratamos datos personales solo en la medida necesaria para
        el funcionamiento, las cuentas, los pagos y la seguridad. La base jurídica es el RGPD.
    </p>

    <h2>3. Alojamiento y registros del servidor</h2>
    <p>
        Al acceder al sitio, el servidor trata datos técnicamente necesarios (dirección IP, fecha y hora,
        dirección solicitada, datos del navegador). Esto sirve para un funcionamiento seguro y estable
        (art. 6, apdo. 1, letra f RGPD). Alojamiento: <x-legal-value key="hosting" />.
    </p>

    <h2>4. Cookies y almacenamiento local</h2>
    <p>
        Solo utilizamos cookies técnicamente necesarias: una cookie de sesión, una cookie de protección frente a
        ataques entre sitios (CSRF/XSRF) y, si usas «mantener la sesión iniciada», una cookie de inicio de
        sesión. Tras una elección deliberada de idioma se añade la cookie funcional «pixel_locale» (véase el
        apartado 15). Además, tu navegador recuerda en el almacenamiento local que has confirmado el aviso de
        cookies y las reglas del juego. Esta información no sale de tu navegador. La base jurídica es el
        art. 25, apdo. 2, n.º 2 TDDDG y el art. 6, apdo. 1, letra f RGPD. No utilizamos cookies de seguimiento
        ni publicitarias.
    </p>

    <h2>5. Jugar como invitado</h2>
    <p>
        Los invitados pueden colocar 50 píxeles una sola vez. Para ello almacenamos un hash (SHA-256) de tu
        dirección IP junto con el número de píxeles colocados. La dirección IP en sí no se almacena con este fin
        (art. 6, apdo. 1, letra f RGPD, protección frente a abusos).
    </p>

    <h2>6. Cuenta e inicio de sesión</h2>
    <p>
        Al registrarte almacenamos tu nombre, tu correo electrónico y una contraseña almacenada de forma cifrada
        (hash), así como los momentos de tu actividad y tu reserva de píxeles. Te enviamos correos para
        confirmar tu dirección y para restablecer tu contraseña (art. 6, apdo. 1, letra b RGPD).
    </p>

    <h2>7. Inicio de sesión con Facebook o Google</h2>
    <p>
        Puedes iniciar sesión con Facebook o Google. Es voluntario. En el primer inicio de sesión creamos una
        cuenta automáticamente.
    </p>
    <h3>Facebook</h3>
    <p>
        El proveedor de Facebook Login es Meta Platforms Ireland Limited, Merrion Road, Dublin 4, D04 X2K5,
        Irlanda. Si eliges «Facebook», tu navegador te redirige a Facebook. Con ello, Meta recibe datos técnicos
        (entre ellos tu dirección IP) y la información de que quieres iniciar sesión en Pixel Chaos. Tras tu
        consentimiento, Facebook nos transmite tu <strong>ID de Facebook</strong>, tu <strong>nombre</strong> y
        tu <strong>correo electrónico</strong>. Almacenamos estos datos (ID, nombre, correo) para gestionar tu
        cuenta. No publicamos nada en Facebook y no leemos listas de amigos, publicaciones ni otros contenidos.
        La base jurídica es tu consentimiento (art. 6, apdo. 1, letra a RGPD) y la ejecución del contrato
        (letra b). Más información en la
        <a href="https://www.facebook.com/privacy/policy/" target="_blank" rel="noopener noreferrer">Política de privacidad de Meta</a>.
        Puedes eliminar la vinculación en cualquier momento en la configuración de Facebook, en «Aplicaciones y
        sitios web». Consulta también las <a href="{{ route('legal.data-deletion') }}">instrucciones para eliminar datos</a>.
    </p>
    <h3>Google</h3>
    <p>
        El proveedor es Google Ireland Limited, Gordon House, Barrow Street, Dublin 4, Irlanda. Recibimos tu ID
        de Google, tu nombre y tu correo electrónico. La base jurídica es el art. 6, apdo. 1, letras a y b RGPD.
        Información sobre privacidad:
        <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">policies.google.com/privacy</a>.
    </p>
    <h3>Compartir en Facebook</h3>
    <p>
        El botón «Compartir en Facebook» es un simple enlace. Solo cuando haces clic en él se abre Facebook y se
        transmiten datos a Meta. El mero acceso a nuestro sitio no implica ninguna transmisión a Facebook.
    </p>
    <p>
        Meta y Google también pueden tratar datos en terceros países (p. ej., EE. UU.). Ambas empresas están
        certificadas conforme al Marco de Privacidad de Datos UE-EE. UU.
    </p>

    <h2>8. Píxeles, historial de actividad y galería</h2>
    <p>
        Cuando colocas un píxel, almacenamos la posición, el color, tu referencia de usuario y el momento. Un
        historial de actividad («quién hizo qué») e imágenes (instantáneas) del muro muestran el resultado
        públicamente. Tu nombre se hace visible públicamente en la medida en que se muestre. La base jurídica es
        el art. 6, apdo. 1, letra b RGPD (uso del muro). Si eliminas tu cuenta, se suprime el vínculo contigo en
        los píxeles y las actividades. Los propios píxeles permanecen como imagen anónima del muro.
    </p>

    <h2>9. Comentarios</h2>
    <p>
        En el formulario de comentarios almacenamos tu mensaje, tu correo electrónico opcional y, si has iniciado
        sesión, tu cuenta. Lo utilizamos únicamente para tramitar tu mensaje (art. 6, apdo. 1, letra f RGPD).
    </p>

    <h2>10. Compras y pagos</h2>
    <p>
        Puedes comprar paquetes de píxeles y ampliaciones del muro. Almacenamos el número de pedido, el
        producto, el importe, el proveedor de pago, la referencia de pago, el estado y las marcas de tiempo. El
        pago lo gestiona uno de los siguientes proveedores, según lo que elijas:
    </p>
    <ul>
        <li>Stripe Payments Europe Ltd., Dublín, Irlanda (<a href="https://stripe.com/es/privacy" target="_blank" rel="noopener noreferrer">privacidad</a>)</li>
        <li>PayPal (Europe) S.à r.l. et Cie, S.C.A., Luxemburgo (<a href="https://www.paypal.com/es/legalhub/privacy-full" target="_blank" rel="noopener noreferrer">privacidad</a>)</li>
        <li>Mollie B.V., Ámsterdam, Países Bajos (<a href="https://www.mollie.com/privacy" target="_blank" rel="noopener noreferrer">privacidad</a>)</li>
    </ul>
    <p>
        Nosotros no introducimos ni almacenamos tus datos de pago (p. ej., el número de tarjeta); van
        directamente al proveedor. La base jurídica es el art. 6, apdo. 1, letra b RGPD. Conservamos los datos
        de los pedidos hasta diez años por obligaciones fiscales y mercantiles (art. 6, apdo. 1, letra c RGPD).
        Al eliminar tu cuenta, el pedido se desvincula de la cuenta, pero se conserva como justificante contable.
    </p>

    <h2>11. Tipografías</h2>
    <p>
        En las páginas de inicio de sesión y de cuenta cargamos tipografías del proveedor Bunny Fonts (BunnyWay
        d.o.o., Eslovenia). Con ello se transmite tu dirección IP a Bunny. Bunny es un servicio respetuoso con la
        privacidad dentro de la UE que no mantiene registros con fines publicitarios
        (art. 6, apdo. 1, letra f RGPD).
    </p>

    <h2>12. Plazo de conservación</h2>
    <p>
        Eliminamos los datos tan pronto como deja de existir su finalidad. Los datos de la cuenta se eliminan
        cuando eliminas tu cuenta. Las obligaciones legales de conservación se mantienen.
    </p>

    <h2>13. Tus derechos</h2>
    <p>
        Tienes derecho de acceso (art. 15), rectificación (art. 16), supresión (art. 17), limitación
        (art. 18), portabilidad de los datos (art. 20) y oposición (art. 21 RGPD). Puedes retirar en cualquier
        momento un consentimiento otorgado, con efectos futuros. Para ello, escríbenos a
        <x-legal-value key="email" />. También puedes presentar una reclamación ante una autoridad de control de
        protección de datos, p. ej., la de tu lugar de residencia habitual. Si resides en España, puedes
        dirigirte a la autoridad española de protección de datos, la
        <a href="https://www.aepd.es" target="_blank" rel="noopener noreferrer">Agencia Española de Protección de Datos (AEPD)</a>.
    </p>

    <h2>14. Eliminar tú mismo tu cuenta</h2>
    <p>
        Con la sesión iniciada, puedes eliminar tu cuenta tú mismo en cualquier momento en
        <a href="{{ route('profile.edit') }}">Perfil</a>. Como alternativa, consulta
        <a href="{{ route('legal.data-deletion') }}">Eliminar datos</a>.
    </p>

    <h2>15. Selección de idioma, redirección automática y cookie de idioma</h2>
    <p>
        Pixel Chaos está disponible en inglés (sin prefijo de ruta, p. ej., «/»), alemán («/de») y español
        («/es»). El idioma se determina mediante la dirección (URL).
    </p>
    <p>
        En la primera visita a la página de inicio te redirigimos automáticamente al idioma adecuado. Para ello
        determinamos el país a partir de tu dirección IP mediante una <strong>base de datos local</strong>
        (DB-IP «IP to Country Lite»). Tu dirección IP <strong>no se cede a terceros</strong> y
        <strong>no se almacena</strong> con este fin; solo se trata de forma transitoria en la memoria de trabajo.
        Si no es posible la asignación, recurrimos como alternativa al encabezado Accept-Language de tu
        navegador. Los bots y rastreadores de buscadores no son redirigidos. La base jurídica es el art. 6,
        apdo. 1, letra f RGPD; nuestro interés legítimo es una presentación amigable en tu idioma. Puedes
        cambiar el idioma en cualquier momento con el selector de idioma. Así también puedes oponerte a la
        redirección (art. 21 RGPD); en ese caso se respetará tu elección.
    </p>
    <p>
        Cuando eliges deliberadamente un idioma, establecemos la cookie funcional <code>pixel_locale</code>
        (duración: 1 año, HttpOnly, SameSite=Lax) y además guardamos el idioma en tu sesión. La cookie solo se
        establece tras esta elección deliberada, sirve únicamente para recordar tu idioma y es técnicamente
        necesaria en el sentido del art. 25, apdo. 2, n.º 2 TDDDG. No se requiere consentimiento para ella.
    </p>

    <h2>16. Atribución</h2>
    <p>
        IP Geolocation by <a href="https://db-ip.com" target="_blank" rel="noopener noreferrer">DB-IP.com</a>
        (licencia: <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener noreferrer">CC BY 4.0</a>).
    </p>

    <h2>17. Versión determinante</h2>
    <p>
        La versión alemana de esta política de privacidad es la jurídicamente vinculante. Las versiones en
        inglés y en español son traducciones que se ofrecen únicamente a título informativo.
    </p>

    <h2>18. Modificaciones</h2>
    <p>Modificamos esta política cuando cambian nuestra oferta o la situación jurídica.</p>
@endsection

