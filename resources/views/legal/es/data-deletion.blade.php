@extends('legal.layout')

@section('title', 'Eliminar datos')

@section('content')
    <p>
        Puedes solicitar en cualquier momento la eliminación de tu cuenta y de los datos personales asociados.
        Esto también se aplica si iniciaste sesión con <strong>Facebook</strong> o Google.
    </p>

    <h2>Opción 1: Eliminarla tú mismo (inmediato)</h2>
    <ol>
        <li><a href="{{ route('login') }}">Inicia sesión</a> (también con Facebook).</li>
        <li>Abre tu <a href="{{ route('profile.edit') }}">perfil</a>.</li>
        <li>Elige «Eliminar cuenta» y confirma.</li>
    </ol>
    <p>
        Nota: si iniciaste sesión solo con Facebook o Google, es posible que no conozcas ninguna contraseña. En
        ese caso usa <a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a> con el correo de tu
        cuenta para establecer una, o elige la opción 2.
    </p>

    <h2>Opción 2: Solicitarlo por correo electrónico</h2>
    <p>
        Escribe a <x-legal-value key="email" /> con el asunto «Eliminación de datos». Indica el correo
        electrónico de tu cuenta (si usas Facebook, el correo de tu cuenta de Facebook). Tramitaremos la
        solicitud en un plazo de 30 días y te confirmaremos la eliminación.
    </p>

    <h2>¿Qué se elimina?</h2>
    <ul>
        <li>La cuenta con nombre, correo electrónico, hash de la contraseña y el ID de Facebook o de Google</li>
        <li>La asociación de tus píxeles y actividades con tu cuenta (los píxeles permanecen en el muro de forma anónima)</li>
        <li>Las sesiones de acceso y las credenciales</li>
    </ul>
    <p>
        Los pedidos se conservan como justificante contable sin vínculo con ninguna cuenta, en la medida en que
        debamos conservarlos por motivos fiscales y mercantiles.
    </p>

    <h2>Eliminar la vinculación en Facebook</h2>
    <ol>
        <li>En Facebook, abre <em>Configuración y privacidad → Configuración</em>.</li>
        <li>Elige <em>Aplicaciones y sitios web</em> (o «Tu actividad fuera de Facebook»).</li>
        <li>Selecciona «Pixel Chaos» y haz clic en <em>Quitar</em>.</li>
    </ol>
    <p>
        Así, Pixel Chaos ya no podrá recibir más datos de Facebook. Los datos ya almacenados puedes eliminarlos
        como se describe arriba. Más información: <a href="{{ route('legal.privacy') }}">Política de privacidad</a>.
    </p>
@endsection

