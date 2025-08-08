<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * Modelo Vehiculo
 * Maneja los vehículos de los usuarios
 */
class Vehiculo extends BaseModel
{
    protected $table = 'vehiculos';
    protected $fillable = [
        'usuario_id', 'marca', 'modelo', 'anio', 'placa', 
        'tipo_aceite', 'color', 'activo'
    ];

    /**
     * Obtener vehículos de un usuario
     */
    public function getByUsuario($usuarioId)
    {
        return $this->all(['usuario_id' => $usuarioId, 'activo' => 1], 'fecha_creacion DESC');
    }

    /**
     * Crear vehículo con validaciones
     */
    public function createVehiculo($data)
    {
        // Validar que la placa no esté duplicada para el mismo usuario
        if ($this->existePlaca($data['placa'], $data['usuario_id'])) {
            throw new Exception('Ya tienes un vehículo registrado con esta placa');
        }

        // Validar año del vehículo
        $currentYear = date('Y');
        if ($data['anio'] < 1900 || $data['anio'] > ($currentYear + 1)) {
            throw new Exception('Año del vehículo no válido');
        }

        return $this->create($data);
    }

    /**
     * Actualizar vehículo con validaciones
     */
    public function updateVehiculo($id, $data)
    {
        // Si se está actualizando la placa, verificar que no esté duplicada
        if (isset($data['placa'])) {
            $vehiculo = $this->find($id);
            if ($vehiculo && $this->existePlaca($data['placa'], $vehiculo['usuario_id'], $id)) {
                throw new Exception('Ya tienes un vehículo registrado con esta placa');
            }
        }

        // Validar año si se está actualizando
        if (isset($data['anio'])) {
            $currentYear = date('Y');
            if ($data['anio'] < 1900 || $data['anio'] > ($currentYear + 1)) {
                throw new Exception('Año del vehículo no válido');
            }
        }

        return $this->update($id, $data);
    }

    /**
     * Verificar si existe una placa para un usuario
     */
    private function existePlaca($placa, $usuarioId, $excludeId = null)
    {
        $query = "SELECT id FROM {$this->table} 
                 WHERE placa = :placa AND usuario_id = :usuario_id AND activo = 1";
        
        $params = [':placa' => $placa, ':usuario_id' => $usuarioId];
        
        if ($excludeId) {
            $query .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        
        $stmt = $this->query($query, $params);
        return $stmt->fetch() !== false;
    }

    /**
     * Buscar vehículo por placa
     */
    public function findByPlaca($placa)
    {
        $query = "SELECT v.*, u.nombre, u.apellido, u.telefono 
                 FROM {$this->table} v
                 INNER JOIN usuarios u ON v.usuario_id = u.id
                 WHERE v.placa = :placa AND v.activo = 1 AND u.activo = 1";
        
        $stmt = $this->query($query, [':placa' => $placa]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener vehículos con información del propietario
     */
    public function getWithOwner($id)
    {
        $query = "SELECT v.*, u.nombre, u.apellido, u.email, u.telefono
                 FROM {$this->table} v
                 INNER JOIN usuarios u ON v.usuario_id = u.id
                 WHERE v.id = :id AND v.activo = 1 AND u.activo = 1";
        
        $stmt = $this->query($query, [':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Eliminar vehículo (soft delete)
     */
    public function deleteVehiculo($id, $usuarioId)
    {
        // Verificar que el vehículo pertenece al usuario
        $vehiculo = $this->find($id);
        if (!$vehiculo || $vehiculo['usuario_id'] != $usuarioId) {
            throw new Exception('Vehículo no encontrado');
        }

        return $this->update($id, ['activo' => 0]);
    }

    /**
     * Obtener marcas más populares
     */
    public function getMarcasPopulares($limit = 10)
    {
        $query = "SELECT marca, COUNT(*) as total
                 FROM {$this->table}
                 WHERE activo = 1
                 GROUP BY marca
                 ORDER BY total DESC
                 LIMIT {$limit}";
        
        $stmt = $this->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener modelos por marca
     */
    public function getModelosByMarca($marca)
    {
        $query = "SELECT DISTINCT modelo
                 FROM {$this->table}
                 WHERE marca = :marca AND activo = 1
                 ORDER BY modelo";
        
        $stmt = $this->query($query, [':marca' => $marca]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Obtener estadísticas de vehículos
     */
    public function getStats()
    {
                $query = "SELECT 
                     COUNT(*) as total_vehiculos,
                     COUNT(DISTINCT usuario_id) as usuarios_con_vehiculos,
                     COUNT(DISTINCT marca) as marcas_diferentes,
                     AVG(anio) as anio_promedio
                  FROM {$this->table}
                  WHERE activo = 1";
        
        $stmt = $this->query($query);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar vehículos por criterios
     */
    public function search($criteria)
    {
        $conditions = ['activo' => 1];
        
        if (!empty($criteria['marca'])) {
            $conditions['marca'] = $criteria['marca'];
        }
        
        if (!empty($criteria['modelo'])) {
            $conditions['modelo'] = $criteria['modelo'];
        }
        
        if (!empty($criteria['anio'])) {
            $conditions['anio'] = $criteria['anio'];
        }

        $orderBy = 'fecha_creacion DESC';
        $limit = isset($criteria['limit']) ? (int)$criteria['limit'] : null;

        return $this->all($conditions, $orderBy, $limit);
    }

    /**
     * Verificar si el usuario puede agregar más vehículos
     */
    public function canAddMore($usuarioId, $maxVehiculos = 5)
    {
        $count = count($this->getByUsuario($usuarioId));
        return $count < $maxVehiculos;
    }
}
