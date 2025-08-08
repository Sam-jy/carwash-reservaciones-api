<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * Modelo Servicio
 * Maneja los servicios disponibles en el car wash
 */
class Servicio extends BaseModel
{
    protected $table = 'servicios';
    protected $fillable = [
        'nombre', 'descripcion', 'precio_base', 'precio_domicilio',
        'disponible_domicilio', 'disponible_centro', 'tiempo_estimado', 'activo'
    ];

    /**
     * Obtener servicios activos
     */
    public function getActivos()
    {
        return $this->all(['activo' => 1], 'nombre ASC');
    }

    /**
     * Obtener servicios disponibles para una ubicación específica
     */
    public function getByUbicacion($tipoUbicacion)
    {
        $field = $tipoUbicacion === 'domicilio' ? 'disponible_domicilio' : 'disponible_centro';
        
        return $this->all([
            'activo' => 1,
            $field => 1
        ], 'nombre ASC');
    }

    /**
     * Calcular precio total del servicio
     */
    public function calcularPrecio($servicioId, $tipoUbicacion = 'centro', $vehiculoData = null)
    {
        $servicio = $this->find($servicioId);
        
        if (!$servicio) {
            throw new Exception('Servicio no encontrado');
        }

        $precio = (float)$servicio['precio_base'];

        // Agregar costo de domicilio si aplica
        if ($tipoUbicacion === 'domicilio' && $servicio['disponible_domicilio']) {
            $precio += (float)$servicio['precio_domicilio'];
        }

        // Lógica especial para cambio de aceite (depende del vehículo)
        if (strtolower($servicio['nombre']) === 'cambio de aceite' && $vehiculoData) {
            $precio = $this->calcularPrecioCambioAceite($vehiculoData);
        }

        return $precio;
    }

    /**
     * Calcular precio de cambio de aceite según el vehículo
     */
    private function calcularPrecioCambioAceite($vehiculoData)
    {
        // Precios base según tipo de vehículo/marca
        $precios = [
            'sedan' => 800,
            'suv' => 1200,
            'pickup' => 1500,
            'compacto' => 600,
            'deportivo' => 1800
        ];

        // Categorizar por marca y modelo
        $marca = strtolower($vehiculoData['marca'] ?? '');
        $modelo = strtolower($vehiculoData['modelo'] ?? '');

        // Marcas premium
        $marcasPremium = ['bmw', 'mercedes', 'audi', 'lexus', 'infiniti', 'acura'];
        if (in_array($marca, $marcasPremium)) {
            return 2000;
        }

        // SUVs y pickups grandes
        if (strpos($modelo, 'suv') !== false || strpos($modelo, 'truck') !== false) {
            return $precios['suv'];
        }

        // Pickups
        if (strpos($modelo, 'pickup') !== false || strpos($modelo, 'tacoma') !== false || 
            strpos($modelo, 'frontier') !== false) {
            return $precios['pickup'];
        }

        // Vehículos deportivos
        if (strpos($modelo, 'sport') !== false || strpos($modelo, 'gt') !== false) {
            return $precios['deportivo'];
        }

        // Por defecto, precio para sedan
        return $precios['sedan'];
    }

    /**
     * Verificar disponibilidad del servicio
     */
    public function isDisponible($servicioId, $tipoUbicacion, $fechaServicio = null)
    {
        $servicio = $this->find($servicioId);
        
        if (!$servicio || !$servicio['activo']) {
            return false;
        }

        // Verificar disponibilidad por ubicación
        if ($tipoUbicacion === 'domicilio' && !$servicio['disponible_domicilio']) {
            return false;
        }

        if ($tipoUbicacion === 'centro' && !$servicio['disponible_centro']) {
            return false;
        }

        // TODO: Aquí se podría agregar lógica adicional para verificar
        // disponibilidad por fecha/hora según capacidad del centro de servicio

        return true;
    }

    /**
     * Obtener servicios más populares
     */
    public function getMasPopulares($limit = 5)
    {
        $query = "SELECT s.*, COUNT(c.id) as total_cotizaciones
                 FROM {$this->table} s
                 LEFT JOIN cotizaciones c ON s.id = c.servicio_id
                 WHERE s.activo = 1
                 GROUP BY s.id
                 ORDER BY total_cotizaciones DESC, s.nombre ASC
                 LIMIT {$limit}";
        
        $stmt = $this->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crear servicio con validaciones
     */
    public function createServicio($data)
    {
        // Validar precios
        if ($data['precio_base'] < 0) {
            throw new Exception('El precio base no puede ser negativo');
        }

        if (isset($data['precio_domicilio']) && $data['precio_domicilio'] < 0) {
            throw new Exception('El precio de domicilio no puede ser negativo');
        }

        // Validar tiempo estimado
        if (isset($data['tiempo_estimado']) && $data['tiempo_estimado'] <= 0) {
            throw new Exception('El tiempo estimado debe ser mayor a 0');
        }

        return $this->create($data);
    }

    /**
     * Actualizar servicio con validaciones
     */
    public function updateServicio($id, $data)
    {
        // Validar precios si se están actualizando
        if (isset($data['precio_base']) && $data['precio_base'] < 0) {
            throw new Exception('El precio base no puede ser negativo');
        }

        if (isset($data['precio_domicilio']) && $data['precio_domicilio'] < 0) {
            throw new Exception('El precio de domicilio no puede ser negativo');
        }

        if (isset($data['tiempo_estimado']) && $data['tiempo_estimado'] <= 0) {
            throw new Exception('El tiempo estimado debe ser mayor a 0');
        }

        return $this->update($id, $data);
    }

    /**
     * Obtener estadísticas de servicios
     */
    public function getStats()
    {
        $query = "SELECT 
                    COUNT(*) as total_servicios,
                    AVG(precio_base) as precio_promedio,
                    SUM(CASE WHEN disponible_domicilio = 1 THEN 1 ELSE 0 END) as disponibles_domicilio,
                    SUM(CASE WHEN disponible_centro = 1 THEN 1 ELSE 0 END) as disponibles_centro
                 FROM {$this->table}
                 WHERE activo = 1";
        
        $stmt = $this->query($query);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar servicios por nombre o descripción
     */
    public function search($term)
    {
        $query = "SELECT * FROM {$this->table}
                 WHERE activo = 1 AND (
                    nombre LIKE :term OR 
                    descripcion LIKE :term
                 )
                 ORDER BY nombre ASC";
        
        $searchTerm = '%' . $term . '%';
        $stmt = $this->query($query, [':term' => $searchTerm]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener servicios por rango de precio
     */
    public function getByPriceRange($minPrice, $maxPrice)
    {
        $query = "SELECT * FROM {$this->table}
                 WHERE activo = 1 AND precio_base BETWEEN :min_price AND :max_price
                 ORDER BY precio_base ASC";
        
        $stmt = $this->query($query, [
            ':min_price' => $minPrice,
            ':max_price' => $maxPrice
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
