<div class="community-crop" data-width="{{ $project->width }}" data-height="{{ $project->height }}" data-image-url="{{ $imageUrl ?? '' }}"
    data-size-error="{{ __('Das Gesamtbild muss mindestens :width × :height Pixel groß sein. Der Ausschnitt muss vollständig im Bild liegen.', ['width' => $project->width, 'height' => $project->height]) }}"
    data-load-error="{{ __('Das Vorlagenbild konnte nicht geladen werden.') }}" data-max-error="{{ __('Bitte lade PNG, JPG oder WebP mit höchstens 4096 Pixeln je Seite hoch.') }}">
    <p>{{ __('Wähle die linke obere Ecke im Gesamtbild oder über die Koordinaten. Der Ausschnitt bleibt exakt :width × :height Pixel groß; es wird nicht skaliert.', ['width' => $project->width, 'height' => $project->height]) }}</p>
    <div class="community-size-fields">
        <label>{{ __('Ausschnitt X') }}<input type="number" name="crop_x" value="{{ old('crop_x', $cropX ?? 0) }}" min="0" step="1" required></label>
        <label>{{ __('Ausschnitt Y') }}<input type="number" name="crop_y" value="{{ old('crop_y', $cropY ?? 0) }}" min="0" step="1" required></label>
    </div>
    <canvas class="community-crop-source" hidden aria-label="{{ __('Gesamtbild mit ausgewähltem Ausschnitt') }}"></canvas>
    <p class="community-crop-status" role="status"></p>
    <canvas class="community-crop-preview" width="{{ $project->width }}" height="{{ $project->height }}" hidden aria-label="{{ __('Ausgewählter Ausschnitt') }}"></canvas>
</div>
