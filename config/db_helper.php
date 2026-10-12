<?php
/**
 * Database Helper — Prepared Statement Wrapper
 * Restoran Kita — Upgrade Phase 2
 * 
 * Cara pakai:
 *   db_query($sql, $params)   → mysqli_result (untuk SELECT)
 *   db_row($sql, $params)     → array | null (single row)
 *   db_val($sql, $params)     → mixed (single value)
 *   db_execute($sql, $params) → int (affected rows untuk INSERT/UPDATE/DELETE)
 *   db_insert_id()            → int (last insert id)
 */

/**
 * Bangun & eksekusi prepared statement
 * @param mysqli $conn
 * @param string $sql   Query dengan placeholder '?' atau named ':param'
 * @param array  $params Parameter (positional array atau associative untuk named)
 * @return mysqli_stmt|false
 */
function db_prepare_and_execute($conn, $sql, $params = []) {
    if (empty($params)) {
        // Tidak ada parameter → query langsung
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            error_log("DB Prepare Error: " . mysqli_error($conn) . " | SQL: $sql");
            return false;
        }
        mysqli_stmt_execute($stmt);
        return $stmt;
    }
    
    // Deteksi named parameter (:name) atau positional (?)
    $is_named = (strpos($sql, ':') !== false && preg_match('/:[a-zA-Z_][a-zA-Z0-9_]*/', $sql));
    
    if ($is_named) {
        // Named parameter
        preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $matches);
        $param_names = $matches[1];
        $values = [];
        $types = '';
        
        foreach ($param_names as $name) {
            if (!array_key_exists($name, $params)) {
                error_log("DB Bind Error: parameter :$name tidak ditemukan");
                return false;
            }
            $val = $params[$name];
            $values[] = $val;
            
            // Auto-detect type
            if (is_int($val)) $types .= 'i';
            elseif (is_float($val)) $types .= 'd';
            else $types .= 's';
        }
        
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            error_log("DB Prepare Error: " . mysqli_error($conn) . " | SQL: $sql");
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, $types, ...$values);
    } else {
        // Positional parameter
        $values = array_values($params);
        $types = '';
        foreach ($values as $val) {
            if (is_int($val)) $types .= 'i';
            elseif (is_float($val)) $types .= 'd';
            else $types .= 's';
        }
        
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            error_log("DB Prepare Error: " . mysqli_error($conn) . " | SQL: $sql");
            return false;
        }
        
        if (!empty($values)) {
            mysqli_stmt_bind_param($stmt, $types, ...$values);
        }
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        error_log("DB Execute Error: " . mysqli_stmt_error($stmt) . " | SQL: $sql");
        return false;
    }
    
    return $stmt;
}

/**
 * SELECT → mysqli_result
 * @return mysqli_result|false
 */
function db_query($conn, $sql, $params = []) {
    $stmt = db_prepare_and_execute($conn, $sql, $params);
    if (!$stmt) return false;
    
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

/**
 * SELECT → single row (assoc array) atau null
 */
function db_row($conn, $sql, $params = []) {
    $result = db_query($conn, $sql, $params);
    if (!$result || mysqli_num_rows($result) === 0) return null;
    $row = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    return $row;
}

/**
 * SELECT → single value (kolom pertama baris pertama)
 */
function db_val($conn, $sql, $params = [], $default = null) {
    $row = db_row($conn, $sql, $params);
    if (!$row) return $default;
    return array_values($row)[0] ?? $default;
}

/**
 * INSERT / UPDATE / DELETE → jumlah affected rows
 */
function db_execute($conn, $sql, $params = []) {
    $stmt = db_prepare_and_execute($conn, $sql, $params);
    if (!$stmt) return 0;
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $affected;
}

/**
 * Ambil ID terakhir dari INSERT
 */
function db_insert_id($conn) {
    return mysqli_insert_id($conn);
}