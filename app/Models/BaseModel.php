<?php

/**
 * Clase base para todos los modelos
 * Proporciona funcionalidad común para operaciones CRUD
 */
abstract class BaseModel
{
    protected $conexion;
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $hidden = [];

    public function __construct($db = null)
    {
        if ($db === null) {
            require_once __DIR__ . '/../../database.php';
            $database = new Database();
            $this->conexion = $database->getConnection();
        } else {
            $this->conexion = $db;
        }
    }

    /**
     * Buscar un registro por ID
     */
    public function find($id)
    {
        $query = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
        $stmt = $this->conexion->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $this->hideAttributes($result) : null;
    }

    /**
     * Obtener todos los registros
     */
    public function all($conditions = [], $orderBy = null, $limit = null)
    {
        $query = "SELECT * FROM {$this->table}";
        
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $key => $value) {
                $whereClause[] = "{$key} = :{$key}";
            }
            $query .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        if ($orderBy) {
            $query .= " ORDER BY {$orderBy}";
        }
        
        if ($limit) {
            $query .= " LIMIT {$limit}";
        }
        
        $stmt = $this->conexion->prepare($query);
        
        foreach ($conditions as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map([$this, 'hideAttributes'], $results);
    }

    /**
     * Crear un nuevo registro
     */
    public function create($data)
    {
        $data = $this->filterFillable($data);
        $data = $this->sanitizeData($data);
        
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $query = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->conexion->prepare($query);
        
        foreach ($data as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        if ($stmt->execute()) {
            return $this->find($this->conexion->lastInsertId());
        }
        
        return false;
    }

    /**
     * Actualizar un registro
     */
    public function update($id, $data)
    {
        $data = $this->filterFillable($data);
        $data = $this->sanitizeData($data);
        
        $setParts = [];
        foreach ($data as $key => $value) {
            $setParts[] = "{$key} = :{$key}";
        }
        
        $query = "UPDATE {$this->table} SET " . implode(', ', $setParts) . " WHERE {$this->primaryKey} = :id";
        $stmt = $this->conexion->prepare($query);
        
        $stmt->bindValue(':id', $id);
        foreach ($data as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        return $stmt->execute();
    }

    /**
     * Eliminar un registro
     */
    public function delete($id)
    {
        $query = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->conexion->prepare($query);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }

    /**
     * Filtrar solo los campos permitidos
     */
    protected function filterFillable($data)
    {
        if (empty($this->fillable)) {
            return $data;
        }
        
        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * Sanitizar datos de entrada
     */
    protected function sanitizeData($data)
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = htmlspecialchars(strip_tags($value));
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    /**
     * Ocultar atributos sensibles
     */
    protected function hideAttributes($data)
    {
        if (empty($this->hidden)) {
            return $data;
        }
        
        foreach ($this->hidden as $field) {
            unset($data[$field]);
        }
        
        return $data;
    }

    /**
     * Ejecutar consulta personalizada
     */
    protected function query($sql, $params = [])
    {
        $stmt = $this->conexion->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt;
    }
}
