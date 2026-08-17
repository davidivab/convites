<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoCentro;
use App\Enums\TipoCentro;
use App\Http\Controllers\Controller;
use App\Http\Resources\CentroResource;
use App\Models\Centro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Centros de interés (lectura pública + gestión moderador).
 */
class CentroController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Centro::query()
            ->with(['zona', 'municipio.departamento'])
            ->where('activo', true)
            ->orderByDesc('emergencia')
            ->orderBy('orden');

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->string('tipo'));
        }

        if ($request->filled('zona')) {
            $query->whereHas('zona', fn ($q) => $q->where('slug', $request->string('zona')));
        }

        if ($request->filled('municipio_id')) {
            $query->where('municipio_id', (int) $request->input('municipio_id'));
        }

        return CentroResource::collection($query->get());
    }

    public function show(Centro $centro): CentroResource
    {
        abort_unless($centro->activo, 404);

        return new CentroResource($centro->load(['zona', 'municipio.departamento']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $centro = Centro::query()->create(array_merge($data, [
            'activo' => true,
            'orden' => $data['orden'] ?? 0,
        ]));

        return (new CentroResource($centro->load(['zona', 'municipio.departamento'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Centro $centro): CentroResource
    {
        $centro->fill($this->validated($request))->save();

        return new CentroResource($centro->fresh(['zona', 'municipio.departamento']));
    }

    public function destroy(Centro $centro): JsonResponse
    {
        $centro->delete();

        return response()->json(['message' => 'Centro eliminado']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'tipo' => ['required', Rule::enum(TipoCentro::class)],
            'nombre' => ['required', 'string', 'max:180'],
            'zona_id' => ['required', 'integer', 'exists:zonas,id'],
            'municipio_id' => ['nullable', 'integer', 'exists:municipios,id'],
            'direccion' => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'horario' => ['nullable', 'string', 'max:120'],
            'estado' => ['required', Rule::enum(EstadoCentro::class)],
            'descripcion' => ['required', 'string', 'max:2000'],
            'necesita' => ['nullable', 'array'],
            'necesita.*' => ['string', 'max:120'],
            'no_recibe' => ['nullable', 'array'],
            'no_recibe.*' => ['string', 'max:120'],
            'capacidad_total' => ['nullable', 'integer', 'min:0'],
            'capacidad_ocupada' => ['nullable', 'integer', 'min:0'],
            'emergencia' => ['sometimes', 'boolean'],
            'orden' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
