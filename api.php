<?php
/**
 * api.php  –  Backend PHP para Lista de Invitados XV Jani
 * =========================================================
 * ENDPOINTS (todos reciben/devuelven JSON):
 *
 *   GET  api.php?action=lista
 *        Devuelve todas las mesas con sus invitados y estado de asistencia.
 *
 *   GET  api.php?action=buscar&q=TERMINO
 *        Busca invitados por nombre de familia (like %termino%).
 *        Soporta múltiples términos separados por coma.
 *
 *   GET  api.php?action=qr&codigo=MESA1-1
 *        ── QR LOOKUP ──────────────────────────────────────────────────
 *        RECIBE EL TEXTO EXACTO DEL QR ESCANEADO (ej. "MESA1-1").
 *        DEVUELVE LOS DATOS DEL INVITADO PARA QUE EL FRONTEND
 *        LO MUESTRE IGUAL QUE SI SE HUBIERA BUSCADO POR NOMBRE.
 *        ────────────────────────────────────────────────────────────────
 *
 *   POST api.php?action=confirmar  body: { "id": 5 }
 *        Marca al invitado como asistido y guarda el timestamp.
 *
 *   POST api.php?action=desmarcar  body: { "id": 5 }
 *        Elimina la confirmación de asistencia del invitado.
 */

// ── CONFIGURACIÓN DE BASE DE DATOS ─────────────────────────────────────────
// CAMBIA ESTOS VALORES SEGÚN TU SERVIDOR
define('DB_HOST', 'localhost');
define('DB_NAME', 'xv_jani');
define('DB_USER', 'root');        // TU USUARIO MYSQL
define('DB_PASS', '45612310'); // ← cambia esto            // TU CONTRASEÑA MYSQL
define('DB_PORT', 3306);
// ───────────────────────────────────────────────────────────────────────────

// Permitir peticiones desde el HTML (mismo servidor o diferente origen)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ── CONEXIÓN PDO ────────────────────────────────────────────────────────────
function conectar(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

// ── HELPERS ─────────────────────────────────────────────────────────────────
function ok(mixed $data): void {
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}
function err(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
function body(): array {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

// ── ROUTER ──────────────────────────────────────────────────────────────────
$action = $_GET['action'] ?? '';

try {
    match ($action) {
        'lista'    => accionLista(),
        'buscar'   => accionBuscar(),
        'confirmar'=> accionConfirmar(),
        'desmarcar'=> accionDesmarcar(),
        default    => err('Acción no reconocida', 404),
    };
} catch (PDOException $e) {
    err('Error de base de datos: ' . $e->getMessage(), 500);
}

// ── ACCIONES ────────────────────────────────────────────────────────────────

/**
 * LISTA COMPLETA
 * Agrupa los invitados por mesa para que el frontend pueda
 * construir las tarjetas tal como están en el HTML estático.
 */
function accionLista(): void {
    $pdo  = conectar();
    $rows = $pdo->query("
        SELECT i.id, i.mesa_id, m.nombre AS mesa_nombre, m.total AS mesa_total,
               i.familia, i.personas, i.asistio,
               i.confirmado_en
        FROM invitados i
        JOIN mesas m ON m.id = i.mesa_id
        ORDER BY i.mesa_id, i.id
    ")->fetchAll();

    // Agrupar por mesa
    $mesas = [];
    foreach ($rows as $r) {
        $mid = $r['mesa_id'];
        if (!isset($mesas[$mid])) {
            $mesas[$mid] = [
                'id'         => $mid,
                'nombre'     => $r['mesa_nombre'],
                'total'      => (int)$r['mesa_total'],
                'invitados'  => [],
            ];
        }
        $mesas[$mid]['invitados'][] = [
            'id'            => (int)$r['id'],
            'familia'       => $r['familia'],
            'personas'      => (int)$r['personas'],
            'asistio'       => (bool)$r['asistio'],
            'confirmado_en' => $r['confirmado_en'],
        ];
    }
    ok(array_values($mesas));
}

/**
 * BÚSQUEDA POR NOMBRE
 * Acepta varios términos separados por coma (mismo comportamiento que el HTML).
 * Devuelve los invitados que coincidan con cualquiera de los términos.
 */
function accionBuscar(): void {
    $raw    = trim($_GET['q'] ?? '');
    if ($raw === '') err('Parámetro q vacío');

    $terms  = array_filter(array_map('trim', explode(',', $raw)));
    $pdo    = conectar();

    // Construir WHERE dinámico: familia LIKE %t1% OR familia LIKE %t2% …
    $wheres = [];
    $params = [];
    foreach ($terms as $t) {
        $wheres[] = 'i.familia LIKE ?';
        $params[] = "%$t%";
    }

    $sql  = "SELECT i.id, i.mesa_id, m.nombre AS mesa_nombre,
                    i.familia, i.personas, i.asistio
             FROM invitados i
             JOIN mesas m ON m.id = i.mesa_id
             WHERE " . implode(' OR ', $wheres) . "
             ORDER BY i.mesa_id, i.id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    ok($stmt->fetchAll());
}

/**
 * CONFIRMAR ASISTENCIA
 * Body JSON: { "id": 5 }
 * Marca asistio=1 y guarda el momento exacto de confirmación.
 */
function accionConfirmar(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Método no permitido', 405);
    $body = body();
    $id   = (int)($body['id'] ?? 0);
    if ($id < 1) err('id inválido');

    $pdo  = conectar();
    $stmt = $pdo->prepare("
        UPDATE invitados
        SET asistio = 1, confirmado_en = NOW()
        WHERE id = ? AND asistio = 0
    ");
    $stmt->execute([$id]);
    ok(['afectados' => $stmt->rowCount()]);
}

/**
 * DESMARCAR ASISTENCIA
 * Body JSON: { "id": 5 }
 * Revierte la confirmación (con la verificación del modal en el frontend).
 */
function accionDesmarcar(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Método no permitido', 405);
    $body = body();
    $id   = (int)($body['id'] ?? 0);
    if ($id < 1) err('id inválido');

    $pdo  = conectar();
    $stmt = $pdo->prepare("
        UPDATE invitados
        SET asistio = 0, confirmado_en = NULL
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    ok(['afectados' => $stmt->rowCount()]);
}
