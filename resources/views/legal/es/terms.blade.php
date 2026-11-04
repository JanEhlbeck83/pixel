@extends('legal.layout')

@section('title', 'Condiciones de uso')

@section('content')
    <h2>1. Ámbito de aplicación</h2>
    <p>
        Estas condiciones se aplican al uso de PixRiot, operado por <x-legal-value key="name" />. El uso del
        muro es gratuito. Los servicios adicionales (paquetes de píxeles, ampliación del muro) son de pago.
    </p>

    <h2>2. Reglas del juego</h2>
    <ul>
        <li>Cualquiera puede sobrescribir cualquier píxel, también el tuyo. No existe derecho a que un píxel o una imagen se conserve.</li>
        <li>Los píxeles recién colocados quedan bloqueados brevemente (10 minutos).</li>
        <li>Los invitados reciben 50 píxeles una sola vez. Con cuenta, se regenera un píxel cada 30 segundos, hasta un máximo de 50 en reserva.</li>
        <li>Los píxeles extra comprados se guardan por separado de la reserva gratuita y solo se usan cuando esta está vacía. No caducan. Una cuenta puede tener como máximo 10.000 píxeles extra; no se pueden comprar paquetes que superen ese límite.</li>
        <li>Están prohibidas las imágenes de odio, los símbolos anticonstitucionales o prohibidos, los insultos, la pornografía, las representaciones de violencia y los contenidos que vulneren derechos de terceros.</li>
        <li>No se permiten los accesos automatizados, bots ni scripts para eludir los límites de píxeles.</li>
    </ul>

    <h2>3. Cuenta</h2>
    <p>
        Debes facilitar datos veraces y mantener en secreto tus credenciales de acceso. Está prevista una cuenta
        por persona. Podemos suspender o eliminar cuentas si incumples estas condiciones. Puedes eliminar tu
        cuenta en cualquier momento; consulta <a href="{{ route('legal.data-deletion') }}">Eliminar datos</a>.
    </p>

    <h2>4. Contenidos y derechos</h2>
    <p>
        El muro se crea de forma colectiva. Nos concedes el derecho no exclusivo y gratuito a mostrar, almacenar
        y reproducir los píxeles que coloques en el marco del servicio, y a mostrarlos en imágenes y en la sección Pixelart del
        muro. Los colores de los píxeles por sí solos, por regla general, no generan derechos de autor sobre
        píxeles individuales. Podemos retirar los contenidos que infrinjan estas condiciones o la ley.
    </p>

    <h2>5. Servicios de pago</h2>
    <p>
        Los precios se muestran en euros como precios finales. Aplicamos el régimen alemán para pequeñas
        empresas conforme al § 19 de la Ley alemana del IVA (UStG); por tanto, no se cobra ni se indica el IVA.
        El pago se realiza a través de los proveedores de servicios de pago ofrecidos allí. Los paquetes de
        píxeles comprados se abonan en tu cuenta; la ampliación del muro se aplica tras el pago correcto.
        Para comprar es necesaria una cuenta.
    </p>

    <h2 id="withdrawal">6. Derecho de desistimiento sobre contenidos digitales</h2>
    <p>
        Los consumidores tienen, por regla general, un derecho de desistimiento de 14 días en los contratos a
        distancia. Nuestros productos —saldo de píxeles y ampliaciones del muro— son contenidos digitales que
        no se suministran en un soporte material. Conforme al art. 356, apdo. 6, del Código Civil alemán (BGB),
        el derecho de desistimiento se extingue cuando comenzamos la ejecución, siempre que hayas consentido
        expresamente que comencemos antes de que expire el plazo de desistimiento y hayas confirmado que sabes
        que tu derecho se extingue cuando comienza la ejecución. Solicitamos este consentimiento y confirmación
        antes de iniciar el pago. Después recibirás la confirmación del pedido por correo electrónico.
    </p>
    <p>
        Esto no supone una exclusión general de todos los derechos de reembolso o garantía. Se mantienen los
        derechos legales por defectos y los demás derechos imperativos de los consumidores. Si no se cumplen
        los requisitos legales aplicables para la extinción del derecho de desistimiento, este sigue vigente.
    </p>

    <h2>7. Disponibilidad y responsabilidad</h2>
    <p>
        Nos esforzamos por ofrecer un servicio ininterrumpido, pero no garantizamos una disponibilidad
        permanente. Respondemos sin limitación en caso de dolo y negligencia grave, por daños a la vida, la
        integridad física y la salud, y conforme a la Ley de responsabilidad por productos. En caso de negligencia
        leve respondemos solo por el incumplimiento de obligaciones contractuales esenciales y limitado al daño
        previsible y típico.
    </p>

    <h2>8. Notificación de infracciones</h2>
    <p>
        Puedes notificar contenidos ilícitos a <x-legal-value key="email" />. Indica la posición (x/y) en el
        muro y el motivo.
    </p>

    <h2>9. Disposiciones finales</h2>
    <p>
        Se aplica el derecho alemán, con exclusión de la Convención de las Naciones Unidas sobre compraventa
        internacional de mercaderías. En el caso de los consumidores, se mantienen las disposiciones imperativas
        de protección del consumidor de su país de residencia. Si alguna disposición fuera inválida, el resto
        seguirá siendo válido.
    </p>
    <p>
        La versión alemana de estas condiciones es la jurídicamente vinculante. Las versiones en inglés y en
        español son traducciones que se ofrecen únicamente a título informativo. En caso de dudas de
        interpretación, prevalece la versión alemana.
    </p>

    <p>Más información: <a href="{{ route('legal.privacy') }}">Política de privacidad</a>, <a href="{{ route('legal.impressum') }}">Aviso legal</a>.</p>
@endsection
