<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Plant;

echo "=== Verificación de Plantas ===\n\n";

$total = Plant::count();
echo "Total de plantas: {$total}\n";

$conPrecio = Plant::whereNotNull('precio_venta')->where('precio_venta', '>', 0)->count();
echo "Plantas con precio_venta > 0: {$conPrecio}\n";

$conPrecioBase = Plant::whereNotNull('precio_base')->where('precio_base', '>', 0)->count();
echo "Plantas con precio_base > 0: {$conPrecioBase}\n";

$conPrecioLista = Plant::whereNotNull('precio_lista')->where('precio_lista', '>', 0)->count();
echo "Plantas con precio_lista > 0: {$conPrecioLista}\n\n";

if ($total > 0) {
    echo "=== Primera planta de ejemplo ===\n";
    $plant = Plant::with('proyecto')->first();

    echo "ID: {$plant->id}\n";
    echo "Nombre: {$plant->name}\n";
    echo 'Precio venta: '.($plant->precio_venta ?? 'NULL')."\n";
    echo "Programa: {$plant->programa}\n";
    echo 'Proyecto: '.($plant->proyecto->name ?? 'No tiene proyecto')."\n";
    echo 'Comuna: '.($plant->proyecto->comuna ?? 'No especificada')."\n\n";

    echo "=== JSON de respuesta API simulada ===\n";
    $mapped = [
        'id' => $plant->id,
        'name' => $plant->name,
        'precio_venta' => $plant->precio_venta,
        'programa' => $plant->programa,
        'proyecto' => $plant->proyecto ? [
            'name' => $plant->proyecto->name,
            'comuna' => $plant->proyecto->comuna,
        ] : null,
    ];
    echo json_encode($mapped, JSON_PRETTY_PRINT)."\n\n";

    echo "=== Mapeo frontend (como debería verse) ===\n";
    $frontendMapped = [
        'nombre' => $plant->name,
        'precio' => $plant->precio_venta,
        'categoria' => $plant->programa,
        'proyectoNombre' => $plant->proyecto->name ?? null,
        'proyectoComuna' => $plant->proyecto->comuna ?? null,
    ];
    echo json_encode($frontendMapped, JSON_PRETTY_PRINT)."\n";
}
