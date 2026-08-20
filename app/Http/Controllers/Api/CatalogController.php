<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Departamento;
use App\Models\Disponibilidad;
use App\Models\DocumentoLegal;
use App\Models\Habilidad;
use App\Models\Municipio;
use App\Models\Zona;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catálogos de solo lectura para el front (selects / chips).
 */
class CatalogController extends Controller
{
    public function zonas(): JsonResponse
    {
        return response()->json([
            'data' => Zona::query()
                ->where('activo', true)
                ->orderBy('orden')
                ->get(['id', 'slug', 'nombre', 'municipio']),
        ]);
    }

    public function departamentos(Request $request): JsonResponse
    {
        $query = Departamento::query()
            ->orderByDesc('emergencia')
            ->orderBy('nombre');

        if (! $request->boolean('incluir_inactivos')) {
            $query->where('activo', true);
        }

        return response()->json([
            'data' => $query->get(['id', 'slug', 'nombre', 'codigo', 'emergencia']),
        ]);
    }

    public function municipios(Request $request): JsonResponse
    {
        $query = Municipio::query()
            ->with('departamento:id,nombre,slug,emergencia')
            ->orderByDesc('emergencia')
            ->orderBy('nombre');

        if (! $request->boolean('incluir_inactivos')) {
            $query->where('activo', true);
        }

        if ($request->filled('departamento_id')) {
            $query->where('departamento_id', (int) $request->input('departamento_id'));
        }

        return response()->json([
            'data' => $query->get(['id', 'departamento_id', 'slug', 'nombre', 'emergencia']),
        ]);
    }

    public function categorias(): JsonResponse
    {
        return response()->json([
            'data' => Categoria::query()
                ->where('activo', true)
                ->orderBy('orden')
                ->get(['id', 'slug', 'nombre', 'descripcion']),
        ]);
    }

    public function habilidades(): JsonResponse
    {
        return response()->json([
            'data' => Habilidad::query()
                ->where('activo', true)
                ->orderBy('tipo')
                ->orderBy('orden')
                ->get(['id', 'slug', 'nombre', 'tipo']),
        ]);
    }

    public function disponibilidades(): JsonResponse
    {
        return response()->json([
            'data' => Disponibilidad::query()
                ->where('activo', true)
                ->orderBy('orden')
                ->get(['id', 'slug', 'nombre']),
        ]);
    }

    public function documentosLegales(): JsonResponse
    {
        return response()->json([
            'data' => DocumentoLegal::query()
                ->where('vigente', true)
                ->get(['id', 'tipo', 'version', 'titulo', 'contenido', 'publicado_at']),
        ]);
    }
}
