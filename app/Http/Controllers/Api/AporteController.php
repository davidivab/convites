<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MarcarAporteRecibidoRequest;
use App\Http\Requests\StoreAporteRequest;
use App\Http\Resources\AporteResource;
use App\Models\Aporte;
use App\Models\Iniciativa;
use App\Services\AporteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Compromisos de aporte (materiales / asistencia).
 */
class AporteController extends Controller
{
    public function __construct(
        private readonly AporteService $aportes,
    ) {}

    public function mine(Request $request): AnonymousResourceCollection
    {
        $aportes = Aporte::query()
            ->with(['items.iniciativaItem', 'iniciativa', 'puntoAcopio.municipio'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return AporteResource::collection($aportes);
    }

    public function porIniciativa(Request $request, Iniciativa $iniciativa): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless(
            $user->id === $iniciativa->user_id || $user->canModerateIniciativa($iniciativa),
            403,
        );

        $aportes = Aporte::query()
            ->with(['items.iniciativaItem', 'user', 'iniciativa', 'puntoAcopio.municipio'])
            ->where('iniciativa_id', $iniciativa->id)
            ->orderByDesc('created_at')
            ->paginate(50);

        return AporteResource::collection($aportes);
    }

    public function store(StoreAporteRequest $request, Iniciativa $iniciativa): JsonResponse
    {
        $aporte = $this->aportes->confirmar($request->user(), $iniciativa, $request->validated());

        return (new AporteResource($aporte))
            ->response()
            ->setStatusCode(201);
    }

    public function marcarRecepcion(MarcarAporteRecibidoRequest $request, Aporte $aporte): AporteResource
    {
        $aporte = $this->aportes->marcarRecepcion(
            $request->user(),
            $aporte,
            (bool) $request->boolean('recibido'),
            $request->file('evidencia'),
        );

        return new AporteResource($aporte);
    }

    public function cancel(Request $request, Aporte $aporte): AporteResource
    {
        $aporte = $this->aportes->cancelar($request->user(), $aporte);

        return new AporteResource($aporte);
    }
}
