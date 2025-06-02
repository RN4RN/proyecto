<?php
declare(strict_types=1);

class MovimientoRepository {
    private mysqli $db;

    public function __construct(mysqli $db) {
        $this->db = $db;
    }

    public function getFilteredMovimientos(array $filters, int $limit, int $offset): array {
        $sql_base = "SELECT m.*, eq.nombre_equipo AS nombre_equipo_tabla, eq.serie, eq.estacion, eq.tipo_equipo, 
                            u.nombre AS nombre_usuario_tabla, u_reg.nombre AS nombre_registrado_por
                     FROM movimientos m
                     LEFT JOIN equipos eq ON m.id_equipo = eq.id_equipo
                     LEFT JOIN usuarios u ON m.id_usuario = u.id_usuario
                     LEFT JOIN usuarios u_reg ON m.registrado_por_id_usuario = u_reg.id_usuario";

        $sql_conditions = [];
        $params_types = "";
        $params_values = [];

        if (!empty($filters['fecha_desde'])) {
            $sql_conditions[] = "DATE(m.fecha_salida) >= ?";
            $params_types .= "s";
            $params_values[] = $filters['fecha_desde'];
        }
        if (!empty($filters['fecha_hasta'])) {
            $sql_conditions[] = "DATE(m.fecha_salida) <= ?";
            $params_types .= "s";
            $params_values[] = $filters['fecha_hasta'];
        }
        if (!empty($filters['id_usuario']) && $filters['id_usuario'] > 0) {
            $sql_conditions[] = "m.id_usuario = ?";
            $params_types .= "i";
            $params_values[] = $filters['id_usuario'];
        }
        if (!empty($filters['tipo_movimiento'])) {
            $sql_conditions[] = "m.tipo_movimiento = ?";
            $params_types .= "s";
            $params_values[] = $filters['tipo_movimiento'];
        }
        if (!empty($filters['tipo_equipo'])) {
            $sql_conditions[] = "eq.tipo_equipo = ?";
            $params_types .= "s";
            $params_values[] = $filters['tipo_equipo'];
        }

        $sql_where = "";
        if (!empty($sql_conditions)) {
            $sql_where = " WHERE " . implode(" AND ", $sql_conditions);
        }

        $sql_data = $sql_base . $sql_where . " ORDER BY m.id_movimiento DESC LIMIT ? OFFSET ?";
        $final_params_types = $params_types . "ii";
        $final_params_values = array_merge($params_values, [$limit, $offset]);

        $stmt = $this->db->prepare($sql_data);
        if (!$stmt) {
            error_log("Error preparando getFilteredMovimientos: " . $this->db->error);
            return [];
        }

        if (!empty($final_params_types)) {
            $stmt->bind_param($final_params_types, ...$final_params_values);
        }
        
        $movimientos = [];
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $movimientos[] = $row;
            }
        } else {
            error_log("Error ejecutando getFilteredMovimientos: " . $stmt->error);
        }
        $stmt->close();
        return $movimientos;
    }

    public function countFilteredMovimientos(array $filters): int {
        $sql_base = "SELECT COUNT(DISTINCT m.id_movimiento) as total 
                     FROM movimientos m
                     LEFT JOIN equipos eq ON m.id_equipo = eq.id_equipo
                     LEFT JOIN usuarios u ON m.id_usuario = u.id_usuario";
        
        // Reutiliza la lógica de construcción de WHERE y parámetros de getFilteredMovimientos
        $sql_conditions = [];
        $params_types = "";
        $params_values = [];
        // (Copiar la lógica de construcción de $sql_conditions, $params_types, $params_values de arriba)
        if (!empty($filters['fecha_desde'])) { $sql_conditions[] = "DATE(m.fecha_salida) >= ?"; $params_types .= "s"; $params_values[] = $filters['fecha_desde']; }
        if (!empty($filters['fecha_hasta'])) { $sql_conditions[] = "DATE(m.fecha_salida) <= ?"; $params_types .= "s"; $params_values[] = $filters['fecha_hasta']; }
        if (!empty($filters['id_usuario']) && $filters['id_usuario'] > 0) { $sql_conditions[] = "m.id_usuario = ?"; $params_types .= "i"; $params_values[] = $filters['id_usuario']; }
        if (!empty($filters['tipo_movimiento'])) { $sql_conditions[] = "m.tipo_movimiento = ?"; $params_types .= "s"; $params_values[] = $filters['tipo_movimiento']; }
        if (!empty($filters['tipo_equipo'])) { $sql_conditions[] = "eq.tipo_equipo = ?"; $params_types .= "s"; $params_values[] = $filters['tipo_equipo']; }

        $sql_where = "";
        if (!empty($sql_conditions)) {
            $sql_where = " WHERE " . implode(" AND ", $sql_conditions);
        }
        // --- Fin de la copia ---
        
        $sql_count = $sql_base . $sql_where;
        $stmt = $this->db->prepare($sql_count);

        if (!$stmt) {
            error_log("Error preparando countFilteredMovimientos: " . $this->db->error);
            return 0;
        }

        if (!empty($params_types)) {
            $stmt->bind_param($params_types, ...$params_values);
        }
        
        $total = 0;
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $total = (int)$result->fetch_assoc()['total'];
        } else {
            error_log("Error ejecutando countFilteredMovimientos: " . $stmt->error);
        }
        $stmt->close();
        return $total;
    }

    public function getMovimientoDetailsById(int $id_movimiento): ?array {
        $sql_detalle = "SELECT m.*, eq.nombre_equipo AS nombre_equipo_tabla, eq.serie, eq.estacion, eq.tipo_equipo, 
                               u.nombre AS nombre_usuario_tabla, u_reg.nombre AS nombre_registrado_por
                        FROM movimientos m
                        LEFT JOIN equipos eq ON m.id_equipo = eq.id_equipo
                        LEFT JOIN usuarios u ON m.id_usuario = u.id_usuario
                        LEFT JOIN usuarios u_reg ON m.registrado_por_id_usuario = u_reg.id_usuario
                        WHERE m.id_movimiento = ? LIMIT 1";
        $stmt = $this->db->prepare($sql_detalle);
        if (!$stmt) {
            error_log("Error preparando getMovimientoDetailsById: " . $this->db->error);
            return null;
        }
        $stmt->bind_param('i', $id_movimiento);
        $detalle_data = null;
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $detalle_data = $result->fetch_assoc();
        } else {
            error_log("Error ejecutando getMovimientoDetailsById: " . $stmt->error);
        }
        $stmt->close();
        return $detalle_data ?: null;
    }
}

// Igualmente, podrías tener UserRepository para get_all_users
class UserRepository {
    private mysqli $db;
    public function __construct(mysqli $db) { $this->db = $db; }
    public function getAllUsersOrderedByName(): array {
        $users = [];
        $sql = "SELECT id_usuario, nombre FROM usuarios ORDER BY nombre ASC";
        $result = $this->db->query($sql);
        if ($result) { while ($row = $result->fetch_assoc()) { $users[] = $row; } }
        return $users;
    }
}
?>