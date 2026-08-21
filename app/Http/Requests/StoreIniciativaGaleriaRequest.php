<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * D-H: `tipo` (imagen|video) es SIEMPRE inferido server-side desde el MIME
 * del archivo subido, nunca aceptado como input del cliente — por eso este
 * request no valida ningún campo `tipo`, solo el archivo `archivo`.
 *
 * Límites (P54, mirror de `StoreIniciativaAvanceMediaRequest`): imagen máx
 * 5MB; video máx 50MB + `duracion_segundos` 1..120 (rechaza > 2 min con 422).
 */
class StoreIniciativaGaleriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $mime = $this->file('archivo')?->getMimeType();
        $esVideo = is_string($mime) && str_starts_with($mime, 'video/');

        if ($esVideo) {
            return [
                'archivo' => ['required', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm', 'max:51200'],
                'duracion_segundos' => ['required', 'integer', 'min:1', 'max:120'],
            ];
        }

        return [
            'archivo' => ['required', 'image', 'max:5120'],
        ];
    }
}
