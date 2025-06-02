<?php
declare(strict_types=1);

/**
 * Class UserRepository
 *
 * Responsable de las operaciones de base de datos para la entidad Usuario.
 */
class UserRepository {
    private mysqli $db; // Propiedad para almacenar la conexión a la base de datos

    /**
     * Constructor de UserRepository.
     *
     * @param mysqli $db Instancia de la conexión mysqli.
     */
    public function __construct(mysqli $db) {
        $this->db = $db;
    }

    /**
     * Obtiene un usuario específico por su ID.
     *
     * @param int $id_usuario El ID del usuario a buscar.
     * @return array|null Un array asociativo con los datos del usuario si se encuentra, o null si no.
     */
    public function findById(int $id_usuario): ?array {
        $sql = "SELECT id_usuario, nombre, email, foto_rostro, rol, fecha_creacion 
                FROM usuarios 
                WHERE id_usuario = ? 
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            // En un entorno de producción, loguearías este error en lugar de (o además de) mostrarlo.
            error_log("Error preparando la consulta findById: " . $this->db->error);
            return null; 
        }

        $stmt->bind_param('i', $id_usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc(); // fetch_assoc devuelve null si no hay filas
        
        $stmt->close();
        
        return $user; // Será null si no se encontró el usuario
    }

    /**
     * Obtiene un usuario por su nombre (para inicio de sesión o validación de sesión).
     * Asumiendo que el nombre es único o se usa para la sesión.
     *
     * @param string $nombre El nombre del usuario.
     * @return array|null
     */
    public function findByNombre(string $nombre): ?array {
        $sql = "SELECT id_usuario, nombre, password_hash, email, foto_rostro, rol 
                FROM usuarios 
                WHERE nombre = ? 
                LIMIT 1"; // Asumimos que el nombre es único para login

        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            error_log("Error preparando la consulta findByNombre: " . $this->db->error);
            return null;
        }

        $stmt->bind_param('s', $nombre);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        $stmt->close();

        return $user;
    }
    
    /**
     * Obtiene todos los usuarios, ordenados por nombre.
     * Útil para listas desplegables, como el filtro en la página de historial.
     *
     * @return array Un array de arrays asociativos, cada uno representando un usuario.
     */
    public function getAllUsersOrderedByName(): array {
        $users = [];
        $sql = "SELECT id_usuario, nombre FROM usuarios ORDER BY nombre ASC";
        
        $result = $this->db->query($sql); // query() es seguro aquí porque no hay entrada del usuario
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
            $result->free(); // Liberar el resultado
        } else {
            error_log("Error ejecutando la consulta getAllUsersOrderedByName: " . $this->db->error);
        }
        
        return $users;
    }

    /**
     * Crea un nuevo usuario en la base de datos.
     * (Ejemplo, podrías tener más campos)
     *
     * @param string $nombre
     * @param string $email
     * @param string $password_hash Hash de la contraseña
     * @param string $rol
     * @param string|null $foto_rostro
     * @return int|false El ID del nuevo usuario insertado, o false en caso de error.
     */
    public function createUser(string $nombre, string $email, string $password_hash, string $rol = 'usuario', ?string $foto_rostro = null): int|false {
        $sql = "INSERT INTO usuarios (nombre, email, password_hash, rol, foto_rostro, fecha_creacion) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            error_log("Error preparando la consulta createUser: " . $this->db->error);
            return false;
        }

        $stmt->bind_param('sssss', $nombre, $email, $password_hash, $rol, $foto_rostro);
        
        if ($stmt->execute()) {
            $new_user_id = $this->db->insert_id;
            $stmt->close();
            return (int)$new_user_id;
        } else {
            error_log("Error ejecutando la consulta createUser: " . $stmt->error);
            $stmt->close();
            return false;
        }
    }

    /**
     * Actualiza los datos de un usuario existente.
     * (Ejemplo, necesitarías decidir qué campos son actualizables)
     *
     * @param int $id_usuario
     * @param array $data Array asociativo con los campos a actualizar (ej: ['nombre' => 'Nuevo Nombre', 'email' => 'nuevo@email.com'])
     * @return bool True si la actualización fue exitosa, false en caso contrario.
     */
    public function updateUser(int $id_usuario, array $data): bool {
        if (empty($data)) {
            return false; // No hay nada que actualizar
        }

        $set_parts = [];
        $params_types = "";
        $params_values = [];

        // Construir la parte SET de la consulta dinámicamente
        foreach ($data as $column => $value) {
            // Validar que la columna sea permitida para evitar inyección SQL en nombres de columna
            // (Idealmente, tendrías una lista blanca de columnas actualizables)
            if (in_array($column, ['nombre', 'email', 'rol', 'foto_rostro' /* , 'password_hash' - con cuidado */])) {
                $set_parts[] = "`$column` = ?"; // Usar backticks para los nombres de columna
                $params_types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's'); // Determinar tipo
                $params_values[] = $value;
            }
        }

        if (empty($set_parts)) {
            error_log("No hay columnas válidas para actualizar en updateUser.");
            return false;
        }

        $sql = "UPDATE usuarios SET " . implode(", ", $set_parts) . " WHERE id_usuario = ?";
        $params_types .= 'i'; // Para el id_usuario del WHERE
        $params_values[] = $id_usuario;
        
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            error_log("Error preparando la consulta updateUser: " . $this->db->error);
            return false;
        }

        $stmt->bind_param($params_types, ...$params_values);
        
        $success = $stmt->execute();
        if (!$success) {
            error_log("Error ejecutando la consulta updateUser: " . $stmt->error);
        }
        $stmt->close();
        
        return $success;
    }

    /**
     * Elimina un usuario por su ID.
     * (Considerar borrado lógico vs físico en aplicaciones reales)
     *
     * @param int $id_usuario
     * @return bool True si se eliminó, false en caso contrario.
     */
    public function deleteUser(int $id_usuario): bool {
        $sql = "DELETE FROM usuarios WHERE id_usuario = ?";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            error_log("Error preparando la consulta deleteUser: " . $this->db->error);
            return false;
        }
        $stmt->bind_param('i', $id_usuario);
        $success = $stmt->execute();
        if (!$success) {
            error_log("Error ejecutando la consulta deleteUser: " . $stmt->error);
        }
        $stmt->close();
        return $success;
    }

    // Podrías añadir más métodos según las necesidades:
    // - countAllUsers()
    // - findByEmail(string $email)
    // - getPaginatedUsers(int $limit, int $offset, array $filters = [])
    // - etc.
}
?>