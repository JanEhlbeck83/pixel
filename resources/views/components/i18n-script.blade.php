@php
    $i18nLocale = app()->getLocale();
    $i18nFile = lang_path('i18n/js/'.$i18nLocale.'.json');
    $i18nMessages = is_file($i18nFile) ? (json_decode(file_get_contents($i18nFile), true) ?: []) : [];
@endphp
<script>
    window.I18N = {{ \Illuminate\Support\Js::from([
        'locale' => $i18nLocale,
        'prefix' => \App\Support\Locales::prefix($i18nLocale),
        'messages' => (object) $i18nMessages,
    ]) }};
</script>
