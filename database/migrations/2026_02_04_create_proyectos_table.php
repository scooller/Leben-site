<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('proyectos', function (Blueprint $table) {
            $table->id();

            // Salesforce reference
            $table->string('salesforce_id')->unique();

            // Información básica
            $table->string('name');
            $table->text('descripcion')->nullable();
            $table->string('direccion')->nullable();

            // Ubicación
            $table->string('comuna')->nullable();
            $table->string('provincia')->nullable();
            $table->string('region')->nullable();

            // Contacto
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->string('pagina_web')->nullable();

            // Empresa
            $table->string('razon_social')->nullable();
            $table->string('rut')->nullable();

            // Ventas
            $table->date('fecha_inicio_ventas')->nullable();
            $table->string('fecha_entrega')->nullable();
            $table->string('etapa')->nullable();
            $table->string('horario_atencion')->nullable();

            // Descuentos por Producto Principal (%)
            $table->decimal('dscto_m_x_prod_principal_porc', 5, 2)->default(0);
            $table->decimal('dscto_m_x_prod_principal_uf', 8, 2)->default(0);

            // Descuentos por Bodega
            $table->decimal('dscto_m_x_bodega_porc', 5, 2)->default(0);
            $table->decimal('dscto_m_x_bodega_uf', 8, 2)->default(0);

            // Descuentos por Estacionamiento
            $table->decimal('dscto_m_x_estac_porc', 5, 2)->default(0);
            $table->decimal('dscto_m_x_estac_uf', 8, 2)->default(0);

            // Descuentos por Otros Productos
            $table->decimal('dscto_max_otros_porc', 5, 2)->default(0);
            $table->decimal('dscto_max_otros_prod_uf', 8, 2)->default(0);

            // Descuentos máximos
            $table->decimal('dscto_maximo_aporte_leben', 5, 2)->default(0);

            // Años de financiamiento
            $table->integer('n_anos_1')->nullable();
            $table->integer('n_anos_2')->nullable();
            $table->integer('n_anos_3')->nullable();
            $table->integer('n_anos_4')->nullable();

            // Reserva
            $table->decimal('valor_reserva_exigido_defecto_peso', 12, 2)->nullable();
            $table->decimal('valor_reserva_exigido_min_peso', 12, 2)->nullable();

            // Otros
            $table->decimal('tasa', 10, 6)->nullable();
            $table->boolean('entrega_inmediata')->default(false);

            // Control
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proyectos');
    }
};
