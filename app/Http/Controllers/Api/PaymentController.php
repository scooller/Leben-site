<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $payments = $request->user()
            ->payments()
            ->latest()
            ->paginate(15);

        return response()->json($payments);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gateway' => 'required|string|in:transbank,mercadopago',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|in:CLP,USD',
            'metadata' => 'nullable|array',
        ]);

        $payment = $request->user()->payments()->create([
            'gateway' => $validated['gateway'],
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'status' => 'pending',
            'metadata' => $validated['metadata'] ?? [],
        ]);

        // TODO: Integrar con servicio de pago correspondiente
        // $paymentService = $validated['gateway'] === 'transbank'
        //     ? app(TransbankService::class)
        //     : app(MercadoPagoService::class);

        return response()->json($payment, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $payment = $request->user()
            ->payments()
            ->findOrFail($id);

        return response()->json($payment);
    }
}
