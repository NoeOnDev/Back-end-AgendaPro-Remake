<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Http\Requests\Api\V1\StoreProductRequest;
use App\Http\Requests\Api\V1\UpdateProductRequest;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    /**
     * Display a listing of products.
     */
    public function index(): JsonResponse
    {
        $products = Product::all();
        return response()->json([
            'data' => $products,
            'message' => 'Productos recuperados con éxito'
        ]);
    }

    /**
     * Store a newly created product.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return response()->json([
            'data' => $product,
            'message' => 'Producto creado con éxito'
        ], 201);
    }

    /**
     * Display the specified product.
     */
    public function show(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        return response()->json([
            'data' => $product,
            'message' => 'Producto recuperado con éxito'
        ]);
    }

    /**
     * Update the specified product.
     */
    public function update(UpdateProductRequest $request, string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->update($request->validated());

        return response()->json([
            'data' => $product,
            'message' => 'Producto actualizado con éxito'
        ]);
    }

    /**
     * Remove the specified product.
     */
    public function destroy(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json([
            'message' => 'Producto eliminado con éxito'
        ], 200);
    }
}
