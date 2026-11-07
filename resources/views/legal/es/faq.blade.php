@extends('legal.layout')

@section('title', 'Preguntas frecuentes (FAQ)')

@php($max = number_format((int) config('payments.limits.max_credits'), 0, ',', '.'))

@section('content')
    <div class="faq">
        <h2>Comunidad y proyectos de grupo</h2>

        <details open>
            <summary>¿Cómo funciona la comunidad de PixRiot?</summary>
            <p>Únete a un grupo aprobado: cada persona solo puede pertenecer a uno. Puedes solicitar la creación de un grupo, sujeto a la aprobación del administrador. En el grupo, proponéis ideas, votáis un diseño y lo creáis juntos en un lienzo protegido.</p>
        </details>

        <details>
            <summary>¿Cómo se proponen e inician los proyectos comunitarios?</summary>
            <p>Al comenzar un proyecto, los miembros del grupo tienen 7 días para enviar propuestas con texto, imagen y tamaño deseado. Después, el grupo vota durante 3 días. La dirección del proyecto reserva el área elegida. La imagen del proyecto permanece separada del muro público hasta su publicación conjunta.</p>
        </details>

        <details>
            <summary>¿Cuándo se publica un proyecto?</summary>
            <p>Los participantes pueden crear en el lienzo privado del proyecto durante un máximo de 14 días y conversar allí. Si todos aceptan finalizar, la imagen se publica de inmediato, incluso antes de que transcurran los 14 días. Los píxeles se transfieren al área reservada del muro y la obra se añade a la galería.</p>
        </details>

        <details>
            <summary>¿Cuánto tiempo queda reservada una imagen publicada?</summary>
            <p>Tras la publicación, el área queda reservada inicialmente para el grupo durante 7 semanas. Al menos 5 miembros actuales deben aceptar la prórroga; cada votación aprobada añade otras 7 semanas. La página del proyecto muestra una cuenta atrás. Si vence el plazo sin suficientes votos, el área vuelve a estar disponible para todos.</p>
        </details>

        <details>
            <summary>¿Quién puede leer el chat del proyecto?</summary>
            <p>Solo los participantes pueden ver y usar el chat durante la creación. Después de la publicación, pueden seguir leyendo todo el historial, pero será de solo lectura.</p>
        </details>

        <h2>Píxeles</h2>

        <details open>
            <summary>¿Cuántos píxeles puedo colocar gratis?</summary>
            <p>Como invitado tienes 50 píxeles una sola vez. Con una cuenta gratuita tienes una reserva de hasta 50 píxeles que se recarga 1 cada 30 segundos.</p>
        </details>

        <details>
            <summary>¿Qué pasa cuando se vacía mi reserva gratuita?</summary>
            <p>Se regenera un píxel cada 30 segundos. Puedes esperar o comprar píxeles extra en la tienda y seguir al instante.</p>
        </details>

        <details>
            <summary>¿Qué son los píxeles extra?</summary>
            <p>Son píxeles comprados. Se guardan aparte de tu reserva gratuita, no caducan y están disponibles de inmediato.</p>
        </details>

        <details>
            <summary>¿Qué píxeles se usan primero: los gratuitos o los comprados?</summary>
            <p>Siempre primero la reserva gratuita. Los píxeles extra solo se usan cuando esta está vacía. Así tu reserva gratuita empieza a recargarse enseguida y no pierdes regeneración mientras usas píxeles extra.</p>
        </details>

        <details>
            <summary>¿Cómo leo el contador de abajo a la derecha?</summary>
            <p>El número grande (p. ej. <strong>50/50</strong>) es tu reserva gratuita: disponible ahora / máximo. El número amarillo pequeño de debajo (p. ej. <strong>+1.000</strong>) son tus píxeles extra comprados.</p>
        </details>

        <details>
            <summary>¿Hay un límite máximo de píxeles extra?</summary>
            <p>Sí, puedes tener como máximo {{ $max }} píxeles extra. Los paquetes que superarían el límite aparecen en gris en la tienda. Usa algunos píxeles y podrás comprar más.</p>
        </details>

        <details>
            <summary>¿Y si alcanzo el límite mientras pago?</summary>
            <p>Los píxeles comprados se te acreditan igualmente: no pierdes nada.</p>
        </details>

        <h2>El muro</h2>

        <details>
            <summary>¿Pueden sobrescribir mi píxel?</summary>
            <p>Sí, cualquiera puede sobrescribir cualquier píxel. Los píxeles recién colocados quedan bloqueados para los demás 10 minutos, para que puedas defenderlos un momento. Durante ese tiempo tú puedes seguir cambiando el píxel que acabas de colocar (cada cambio cuesta un píxel de tu reserva). En un píxel bloqueado ves sus coordenadas y cuándo vuelve a estar libre.</p>
        </details>

        <details>
            <summary>¿Por qué mi píxel aparece más tarde en la imagen de fondo?</summary>
            <p>Tu píxel se guarda al instante y lo ve todo el que mire esa zona. La imagen de fondo del muro se compone de teselas que se regeneran cada 10 minutos, así que los píxeles cambiados y las ampliaciones aparecen allí con hasta 10 minutos de retraso.</p>
        </details>

        <details>
            <summary>¿Para qué sirve la ampliación del muro?</summary>
            <p>Con una ampliación el muro crece hacia la derecha o hacia abajo, y el nuevo espacio queda disponible para todos los jugadores.</p>
        </details>

        <h2>Cuenta, pago y datos</h2>

        <details>
            <summary>¿Necesito una cuenta para comprar?</summary>
            <p>Sí. Así tus compras quedan guardadas de forma segura en tu perfil. La cuenta es gratuita (correo, Google o Facebook).</p>
        </details>

        <details>
            <summary>¿Puedo desistir de una compra?</summary>
            <p>Para el saldo de píxeles, el derecho de desistimiento puede extinguirse cuando comienza la ejecución, tras tu consentimiento y confirmación expresos. Para la ampliación del muro, puede extinguirse únicamente cuando el servicio se haya prestado por completo. Antes del pago solicitamos las declaraciones exigidas por la ley. Consulta las <a href="{{ route('legal.terms') }}#withdrawal">Condiciones de uso</a>.</p>
        </details>

        <details>
            <summary>¿Cómo elimino mi cuenta?</summary>
            <p>Consulta <a href="{{ route('legal.data-deletion') }}">Eliminar datos</a>. Los píxeles extra que no hayas usado caducan al hacerlo.</p>
        </details>

        <details>
            <summary>¿Cómo denuncio contenidos ilícitos?</summary>
            <p>Escribe a <x-legal-value key="email" /> indicando la posición (x/y) en el muro y el motivo.</p>
        </details>
    </div>

    <p>Más información: <a href="{{ route('legal.terms') }}">Condiciones de uso</a>, <a href="{{ route('legal.privacy') }}">Política de privacidad</a>.</p>
@endsection
