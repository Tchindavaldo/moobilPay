<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'MoobilPay API',
        'version' => '1.0.0',
        'documentation' => url('/api/documentation'),
        'status' => 'OK'
    ]);
});

Route::get('/docs', function () {
    return redirect('/api/documentation');
});

// Route manuelle pour Swagger en cas de problème
Route::get('/api/documentation', function () {
    try {
        $swaggerFile = storage_path('api-docs/api-docs.json');
        if (!file_exists($swaggerFile)) {
            return response()->json(['error' => 'Documentation not generated'], 404);
        }
        
        $swaggerJson = file_get_contents($swaggerFile);
        $swaggerData = json_decode($swaggerJson, true);
        
        return view('swagger-ui');
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Swagger documentation error',
            'message' => $e->getMessage()
        ], 500);
    }
});

Route::get('/api/docs.json', function () {
    $swaggerFile = storage_path('api-docs/api-docs.json');
    if (!file_exists($swaggerFile)) {
        return response()->json(['error' => 'Documentation not found'], 404);
    }
    
    return response()->file($swaggerFile, [
        'Content-Type' => 'application/json'
    ]);
});

Route::get('/test', function () {
    return response()->json([
        'status' => 'OK',
        'message' => 'Laravel fonctionne correctement',
        'timestamp' => now()
    ]);
});
