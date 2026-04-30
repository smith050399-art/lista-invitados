<?php
// ============================================================
//  index.php  –  Lista de Invitados XV Jani
//  El PHP conecta a la BD, carga los datos y los inyecta
//  directamente en el HTML. Todo en un solo archivo.
// ============================================================

// ── CONFIGURACIÓN ────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'xv_jani');
define('DB_USER', 'root');      // CAMBIA POR TU USUARIO
define('DB_PASS', 'TU_CONTRASEÑA_APPSERV'); // ← cambia esto          // CAMBIA POR TU CONTRASEÑA
define('DB_PORT', 3306);

// ── CONEXIÓN Y CARGA DE DATOS ────────────────────────────────
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME),
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // Traer mesas e invitados en una sola consulta, ordenados
    $rows = $pdo->query("
        SELECT m.id AS mesa_id, m.nombre AS mesa_nombre, m.total AS mesa_total,
               i.id AS inv_id, i.familia, i.personas, i.asistio
        FROM mesas m
        JOIN invitados i ON i.mesa_id = m.id
        ORDER BY m.id, i.id
    ")->fetchAll();

    // Agrupar por mesa
    $mesas = [];
    foreach ($rows as $r) {
        $mid = $r['mesa_id'];
        if (!isset($mesas[$mid])) {
            $mesas[$mid] = [
                'id'        => $mid,
                'nombre'    => $r['mesa_nombre'],
                'total'     => (int)$r['mesa_total'],
                'invitados' => [],
            ];
        }
        $mesas[$mid]['invitados'][] = [
            'id'       => (int)$r['inv_id'],
            'familia'  => $r['familia'],
            'personas' => (int)$r['personas'],

            'asistio'  => (bool)$r['asistio'],
        ];
    }
    $db_ok = true;

} catch (PDOException $e) {
    // Si falla la BD, el HTML igual carga (modo offline)
    $mesas = [];
    $db_ok = false;
    $db_error = $e->getMessage();
}

// ── HELPER: escapar HTML ─────────────────────────────────────
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// ── HELPER: generar filas de una mesa ────────────────────────
function renderMesa(array $mesa): void {
    $mid   = $mesa['id'];
    $total = $mesa['total'];
    $col   = $mid === 15 ? 'Nombre' : 'Familia'; // Mesa 15 usa "Nombre"
    echo "\n<!-- MESA {$mid} -->\n";
    echo "<div class=\"mesa-card\" data-mesa=\"{$mid}\">\n";
    echo "  <div class=\"mesa-header\">"
       . "<span class=\"mesa-number\">🌹 {$mesa['nombre']}</span>"
       . "<span class=\"mesa-total\">{$total} personas</span>"
       . "</div>\n";
    echo "  <table><thead><tr>"
       . "<th>{$col}</th><th class=\"th-count\">Personas</th>"
       . "</tr></thead><tbody>\n";

    $pos = 1; // posición dentro de la mesa (para data-key)
    foreach ($mesa['invitados'] as $inv) {
        $key      = "{$mid}-{$pos}";
        $dbId     = $inv['id'];
        $familia  = h($inv['familia']);
        $personas = h((string)$inv['personas']);
        // Si ya asistió (cargado desde la BD), marcamos la fila
        $attended = $inv['asistio'] ? ' class="attended-row"' : '';
        $dotClass = $inv['asistio'] ? ' class="dot attended"' : ' class="dot"';
        $btnClass = $inv['asistio'] ? ' class="btn-asistio attended"' : ' class="btn-asistio"';
        $btnText  = $inv['asistio'] ? '✓ Confirmado' : '✓ Asistió';
        echo "    <tr data-key=\"{$key}\" data-db-id=\"{$dbId}\"{$attended}>"
           . "<td class=\"td-name\"><span class=\"fname\">{$familia}</span>"
           . "<button{$btnClass}>{$btnText}</button></td>"
           . "<td class=\"td-count\"><div class=\"count-wrap\">"
           . "<span class=\"num\">{$personas}</span>"
           . "<button{$dotClass} title=\"" . ($inv['asistio'] ? 'Haz clic para eliminar asistencia' : 'Marcar asistencia') . "\"></button>"
           . "</div></td></tr>\n";
        $pos++;
    }

    echo "    <tr class=\"total-row\"><td>TOTAL</td><td class=\"td-count\">{$total}</td></tr>\n";
    echo "  </tbody></table>\n";
    echo "</div>\n";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lista de Invitados – XV Jani</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jsQR/1.4.0/jsQR.min.js"></script>
<style>
  :root {
    --teal:   #5FD1B8;
    --dark:   #137a6a;
    --cream:  #f0fdfb;
    --blush:  #e0f7f3;
    --text:   #1a3330;
    --muted:  #5a9088;
    --white:  #ffffff;
    --green:  #22c55e;
    --red:    #ef4444;
    --shadow: 0 4px 24px rgba(95,209,184,.18);
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Lato', sans-serif; background: var(--cream); color: var(--text); min-height: 100vh; }

  /* ── HEADER ── */
  header {
    background: linear-gradient(135deg, #5FD1B8 0%, #2aaa93 50%, #137a6a 100%);
    text-align: center; padding: 2.5rem 1rem 2rem; position: relative; overflow: hidden;
  }
  header::before {
    content: ''; position: absolute; inset: 0;
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='60' height='60'%3E%3Ccircle cx='30' cy='30' r='1.5' fill='rgba(255,255,255,.12)'/%3E%3Ccircle cx='10' cy='10' r='1' fill='rgba(255,255,255,.08)'/%3E%3Ccircle cx='50' cy='50' r='1' fill='rgba(255,255,255,.08)'/%3E%3C/svg%3E") repeat;
  }
  header .crown { font-size: 2.5rem; margin-bottom: .4rem; position: relative; }
  header h1 { font-family: 'Playfair Display', serif; font-size: clamp(1.8rem, 5vw, 3rem); color: #fff; letter-spacing: 2px; position: relative; }
  header .sub { font-family: 'Playfair Display', serif; font-style: italic; color: rgba(255,255,255,.75); font-size: 1rem; margin-top: .4rem; position: relative; }
  header .divider { display: flex; align-items: center; justify-content: center; gap: .8rem; margin: 1rem auto 0; max-width: 300px; position: relative; }
  header .divider span { flex: 1; height: 1px; background: rgba(255,255,255,.35); }

  /* ── CONTROLS ── */
  .controls { background: var(--white); padding: 1.2rem 1rem; box-shadow: 0 2px 12px rgba(0,0,0,.08); position: sticky; top: 0; z-index: 100; }
  .controls-inner { max-width: 900px; margin: 0 auto; display: flex; gap: .8rem; flex-wrap: wrap; align-items: center; }
  .search-wrap { flex: 1; min-width: 200px; display: flex; border-radius: 50px; overflow: hidden; border: 2px solid var(--teal); transition: box-shadow .2s; }
  .search-wrap:focus-within { box-shadow: 0 0 0 3px rgba(95,209,184,.25); }
  #searchInput { flex: 1; border: none; outline: none; padding: .65rem 1.1rem; font-family: 'Lato', sans-serif; font-size: .92rem; color: var(--text); background: transparent; }
  #searchInput::placeholder { color: var(--muted); font-size: .82rem; }

  .pill-btn { border: none; cursor: pointer; font-family: 'Lato', sans-serif; font-weight: 700; letter-spacing: .5px; transition: background .2s; white-space: nowrap; }
  #btnBuscar { background: var(--teal); color: #fff; padding: .65rem 1.3rem; font-size: .88rem; letter-spacing: 1px; }
  #btnBuscar:hover { background: #3aab93; }
  #btnQR { background: var(--text); color: #fff; padding: .65rem 1.1rem; border-radius: 50px; font-size: .82rem; display: flex; align-items: center; gap: .45rem; }
  #btnQR:hover { background: #137a6a; }
  #btnQR svg { width: 17px; height: 17px; flex-shrink: 0; }
  #resultInfo { width: 100%; text-align: center; font-size: .82rem; color: var(--muted); min-height: 1.1rem; }

  /* ── MODALS ── */
  .modal { display: none; position: fixed; inset: 0; z-index: 999; background: rgba(0,0,0,.75); align-items: center; justify-content: center; }
  .modal.open { display: flex; }
  .modal-box { background: var(--white); border-radius: 16px; max-width: 380px; width: 90%; text-align: center; box-shadow: 0 8px 40px rgba(0,0,0,.4); animation: popIn .22s ease; }
  @keyframes popIn { from { transform: scale(.88); opacity: 0; } to { transform: scale(1); opacity: 1; } }

  .qr-box { padding: 1.8rem; }
  .qr-box h3 { font-family: 'Playfair Display', serif; color: var(--teal); font-size: 1.3rem; margin-bottom: .4rem; }
  .qr-box p  { font-size: .82rem; color: var(--muted); margin-bottom: 1rem; }
  #qrVideo   { width: 100%; border-radius: 10px; background: #000; max-height: 280px; object-fit: cover; }
  #qrCanvas  { display: none; }
  #qrResult  { margin-top: .8rem; font-size: .9rem; color: var(--text); font-weight: 700; min-height: 1.2rem; }
  #btnCloseQR { margin-top: 1rem; background: var(--muted); color: #fff; border-radius: 50px; padding: .5rem 1.5rem; font-size: .85rem; }
  #btnCloseQR:hover { background: #4a7870; }

  #unmarkModal { z-index: 1000; }
  .unmark-box { padding: 2rem 1.8rem; border-radius: 18px; }
  .unmark-icon { font-size: 2.4rem; margin-bottom: .5rem; }
  .unmark-box h3 { font-family: 'Playfair Display', serif; color: var(--text); font-size: 1.25rem; margin-bottom: .5rem; }
  .unmark-box p  { font-size: .87rem; color: var(--muted); margin-bottom: 1.4rem; line-height: 1.5; }
  .unmark-btns { display: flex; gap: .8rem; justify-content: center; }
  .unmark-btns button { flex: 1; padding: .6rem .5rem; border-radius: 50px; font-family: 'Lato', sans-serif; font-size: .85rem; font-weight: 700; cursor: pointer; transition: background .2s, color .2s; }
  #btnUnmarkNo { border: 2px solid var(--muted); background: transparent; color: var(--muted); }
  #btnUnmarkNo:hover { background: var(--muted); color: #fff; }
  #btnUnmarkSi { border: none; background: var(--red); color: #fff; }
  #btnUnmarkSi:hover { background: #dc2626; }

  /* ── GRID ── */
  main { max-width: 1100px; margin: 2rem auto; padding: 0 1rem 3rem; display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); align-items: start; gap: 1.6rem; }

  /* ── MESA CARD ── */
  .mesa-card { background: var(--white); border-radius: 16px; box-shadow: var(--shadow); overflow: hidden; transition: transform .2s, box-shadow .2s; border: 1px solid rgba(95,209,184,.18); }
  .mesa-card:hover  { transform: translateY(-3px); box-shadow: 0 8px 32px rgba(95,209,184,.28); }
  .mesa-card.hidden { display: none; }
  .mesa-card.highlight { outline: 3px solid var(--teal); animation: pulse .5s ease; }
  @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(95,209,184,.5); } 100% { box-shadow: 0 0 0 14px rgba(95,209,184,0); } }

  .mesa-header { background: linear-gradient(135deg, var(--teal), var(--dark)); color: #fff; padding: .9rem 1.2rem; display: flex; align-items: center; justify-content: space-between; }
  .mesa-number { font-family: 'Playfair Display', serif; font-size: 1.2rem; letter-spacing: 1px; }
  .mesa-total  { background: rgba(255,255,255,.2); border-radius: 20px; padding: .2rem .7rem; font-size: .75rem; font-weight: 700; letter-spacing: .5px; }

  /* ── TABLE ── */
  table { width: 100%; border-collapse: collapse; }
  thead tr { background: var(--blush); }
  thead th { padding: .5rem .8rem; text-align: left; font-size: .7rem; letter-spacing: 1.2px; text-transform: uppercase; color: var(--teal); font-weight: 700; }
  thead th.th-count { text-align: right; }
  tbody tr { border-bottom: 1px solid #d8f5f0; transition: background .15s; }
  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover           { background: #edfaf7; }
  tbody tr.attended-row    { background: #f0fdf4 !important; }
  tbody tr.attended-row:hover { background: #dcfce7 !important; }

  .td-name { padding: .5rem .8rem; font-size: .88rem; color: var(--text); display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
  .fname { flex: 1; }

  .btn-asistio { display: none; border: none; cursor: pointer; border-radius: 20px; padding: .22rem .7rem; font-size: .72rem; font-weight: 700; letter-spacing: .5px; font-family: 'Lato', sans-serif; transition: background .2s, transform .1s; white-space: nowrap; align-items: center; gap: .25rem; background: var(--teal); color: #fff; }
  .btn-asistio:hover          { background: #3aab93; transform: scale(1.06); }
  .btn-asistio.attended       { background: var(--green); cursor: default; }
  .btn-asistio.attended:hover { background: var(--green); transform: none; }

  #grid.searching tr[data-key] .btn-asistio               { display: inline-flex; }
  #grid.searching tr[data-key][style*="none"] .btn-asistio { display: none; }
  #grid.searching tr[data-key] .dot                       { display: none; }
  #grid.searching tr[data-key][style*="none"] .dot        { display: inline-block; }

  .td-count   { padding: .5rem .8rem; text-align: right; white-space: nowrap; vertical-align: middle; }
  .count-wrap { display: inline-flex; align-items: center; gap: .55rem; justify-content: flex-end; }
  .num        { font-weight: 700; color: var(--teal); font-size: .88rem; }

  .dot { width: 14px; height: 14px; border-radius: 50%; border: none; cursor: pointer; flex-shrink: 0; background: var(--red); box-shadow: 0 0 0 2px rgba(239,68,68,.2); transition: background .25s, transform .15s, box-shadow .2s; }
  .dot:hover          { transform: scale(1.3); }
  .dot.attended       { background: var(--green); box-shadow: 0 0 0 2px rgba(34,197,94,.25); cursor: default; }
  .dot.attended:hover { transform: none; }

  .total-row td { background: var(--blush); font-size: .82rem; font-weight: 700; color: var(--teal) !important; padding: .5rem .8rem; }
  .subtotal-row td { background: #e6faf5; font-size: .8rem; font-weight: 700; padding: .45rem .8rem; border-top: 1px dashed rgba(95,209,184,.4); }
  .subtotal-row .sub-label { color: var(--muted); }
  .subtotal-row .sub-count { color: var(--green); text-align: right; }
  .subtotal-row .sub-zero  { color: var(--muted); }

  mark { background: #b2f0e6; border-radius: 2px; padding: 0 2px; }

  #noResults { display: none; grid-column: 1/-1; text-align: center; padding: 3rem; color: var(--muted); }
  #noResults h2 { font-family: 'Playfair Display', serif; font-size: 1.5rem; }
  #noResults p  { font-size: .9rem; margin-top: .4rem; }

  .db-alert { grid-column: 1/-1; background: #fff3cd; border: 1px solid #ffc107; border-radius: 10px; padding: .8rem 1.2rem; font-size: .82rem; color: #856404; text-align: center; }

  footer { text-align: center; padding: 1.5rem; font-size: .78rem; color: var(--muted); border-top: 1px solid rgba(95,209,184,.2); }
  footer span { color: var(--teal); }
</style>
</head>
<body>

<header>
  <div class="crown">👑</div>
  <h1>XV Años – Jani</h1>
  <p class="sub">Lista de Invitados</p>
  <div class="divider"><span></span>✦<span></span></div>
</header>

<div class="controls">
  <div class="controls-inner">
    <div class="search-wrap">
      <input id="searchInput" type="text" placeholder="Buscar nombre o familia… (coma para varios)" autocomplete="off">
      <button id="btnBuscar" class="pill-btn">BUSCAR</button>
    </div>
    <button id="btnQR" class="pill-btn">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
        <rect x="3" y="14" width="7" height="7" rx="1"/>
        <rect x="5" y="5" width="3" height="3" fill="currentColor" stroke="none"/>
        <rect x="16" y="5" width="3" height="3" fill="currentColor" stroke="none"/>
        <rect x="5" y="16" width="3" height="3" fill="currentColor" stroke="none"/>
        <path d="M14 14h3v3h-3zM17 17h3v3h-3zM14 20h3"/>
      </svg>
      Escanear QR
    </button>
    <div id="resultInfo"></div>
  </div>
</div>

<!-- QR MODAL -->
<div id="qrModal" class="modal">
  <div class="modal-box qr-box">
    <h3>📷 Escanear QR</h3>
    <p>Apunta la cámara al código QR de la invitación</p>
    <video id="qrVideo" autoplay playsinline muted></video>
    <canvas id="qrCanvas"></canvas>
    <div id="qrResult"></div>
    <button id="btnCloseQR" class="pill-btn">Cerrar</button>
  </div>
</div>

<!-- UNMARK MODAL -->
<div id="unmarkModal" class="modal">
  <div class="modal-box unmark-box">
    <div class="unmark-icon">⚠️</div>
    <h3>¿Eliminar asistencia?</h3>
    <p>¿Segura que deseas eliminar la asistencia de este invitado?</p>
    <div class="unmark-btns">
      <button id="btnUnmarkNo">✕ No, regresar</button>
      <button id="btnUnmarkSi">✓ Sí, confirmar</button>
    </div>
  </div>
</div>

<main id="grid">

<?php if (!$db_ok): ?>
  <div class="db-alert">
    ⚠️ Sin conexión a la base de datos — mostrando datos estáticos.
    <?php if (isset($db_error)): echo '<br><small>' . h($db_error) . '</small>'; endif; ?>
  </div>
<?php endif; ?>

<?php if (!empty($mesas)): ?>
  <?php foreach ($mesas as $mesa): renderMesa($mesa); endforeach; ?>
<?php else: ?>
  <!-- FALLBACK: datos estáticos si la BD no responde -->
  <?php
  // Datos de respaldo en caso de que la BD no esté disponible
  $fallback = [
    [1,'Mesa 1',10,[['Cruz Gerónimo',6,1],['Doña Carmita (Puebla)',2,2],['Licho',2,3]]],
    [2,'Mesa 2',10,[['Félix Gerónimo',4,4],['Félix Arias',2,5],['Reyes Gerónimo',4,6]]],
    [3,'Mesa 3',10,[['López Antonio',5,7],['Morales Peralta',5,8]]],
    [4,'Mesa 4',10,[['Cruz Camacho',10,9]]],
    [5,'Mesa 5',10,[['Dominguez Ferral',3,10],['Martínez Morales',3,11],['Bonilla Dominguez',4,12]]],
    [6,'Mesa 6',9,[['Ramírez Gerónimo',4,13],['Alay hno. Edgar',1,14],['Marisol',1,15],['Moctezuma',2,16],['Juan-Doña Amalia',1,17]]],
    [7,'Mesa 7',10,[['León Hernández',4,18],['León Salvador',4,19],['Maestra Beti',2,20]]],
    [8,'Mesa 8',10,[['Luna Salvador',3,21],['Aguilar Hernández',4,22],['Karla',1,23],['Sebastián',1,24],['Sandra',1,25]]],
    [9,'Mesa 9',10,[['Conchola Priego',4,26],['Zurita Zamudio',4,27],['De la Cruz Sosa',2,28]]],
    [10,'Mesa 10',10,[['Alonso Pérez',3,29],['Morales Gonzales',6,30],['Marcos',1,31]]],
    [11,'Mesa 11',10,[['Gonzales Lievano',5,32],['David',4,33],['Álvarez Manzanares-el esposo',1,34]]],
    [12,'Mesa 12',10,[['Álvarez Manzanares',4,35],['Martínez Chan',4,36],['Falcon Avalos',2,37]]],
    [13,'Mesa 13',10,[['Geronimo Arias',3,38],['León Geronimo',4,39],['Gómez Vasquez',3,40]]],
    [14,'Mesa 14',10,[['Geronimo Diaz',2,41],['Geronimo Hernández',4,42],['Valencia Mijango',3,43],['Dana paola-novia de diego',1,44]]],
    [15,'Mesa 15',10,[['Yanilé',2,45],['Diego Duran',1,46],['Gabriel Duran',1,47],['Jennifer',1,48],['Romina',1,49],['Jesús',1,50],['Lira',1,51],['Pedro',1,52],['Emily',1,53]]],
    [16,'Mesa 16',10,[['Rodríguez López',4,54],['Félix Hernández',3,55],['Luis',2,56],['María Fernanda',1,57]]],
  ];
  foreach ($fallback as [$mid, $nombre, $total, $invitados]) {
      $col = $mid === 15 ? 'Nombre' : 'Familia';
      echo "\n<!-- MESA {$mid} -->\n";
      echo "<div class=\"mesa-card\" data-mesa=\"{$mid}\">\n";
      echo "  <div class=\"mesa-header\"><span class=\"mesa-number\">🌹 {$nombre}</span><span class=\"mesa-total\">{$total} personas</span></div>\n";
      echo "  <table><thead><tr><th>{$col}</th><th class=\"th-count\">Personas</th></tr></thead><tbody>\n";
      $pos = 1;
      foreach ($invitados as [$fam, $per, $dbId]) {
          $key = "{$mid}-{$pos}";
          echo "    <tr data-key=\"{$key}\" data-db-id=\"{$dbId}\"><td class=\"td-name\">"
             . "<span class=\"fname\">" . h($fam) . "</span>"
             . "<button class=\"btn-asistio\">✓ Asistió</button></td>"
             . "<td class=\"td-count\"><div class=\"count-wrap\">"
             . "<span class=\"num\">{$per}</span>"
             . "<button class=\"dot\" title=\"Marcar asistencia\"></button>"
             . "</div></td></tr>\n";
          $pos++;
      }
      echo "    <tr class=\"total-row\"><td>TOTAL</td><td class=\"td-count\">{$total}</td></tr>\n";
      echo "  </tbody></table>\n</div>\n";
  }
  ?>
<?php endif; ?>

<div id="noResults">
  <h2>🔍 Sin resultados</h2>
  <p>No se encontró ninguna familia o invitado con ese nombre.</p>
</div>

</main>

<footer>🌹 Con amor para los XV Años de <span>Jani</span> 🌹</footer>

<script>
/* ═══════════════════════════
   UTILS
═══════════════════════════ */
const $        = id => document.getElementById(id);
const normalize = s => s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

/* ── URL DE LA API (mismo archivo, acción via ?action=) ── */
const API_URL = 'api.php';

const api = {
  get:  (action, params = {}) => {
    const qs = new URLSearchParams({ action, ...params });
    return fetch(`${API_URL}?${qs}`).then(r => r.json());
  },
  post: (action, body = {}) =>
    fetch(`${API_URL}?action=${action}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    }).then(r => r.json()),
};

/* ═══════════════════════════
   ATTENDANCE
═══════════════════════════ */
const STORAGE_KEY = 'xv_jani_attendance_v1';
let attendance = {};

const loadLocal = () => { try { attendance = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}'); } catch { attendance = {}; } };
const saveLocal = () => { try { localStorage.setItem(STORAGE_KEY, JSON.stringify(attendance)); } catch {} };

// PHP ya renderizó el estado inicial desde la BD,
// pero también cargamos localStorage para mayor velocidad offline
function initAttendanceFromDOM() {
  document.querySelectorAll('tr[data-key].attended-row').forEach(row => {
    attendance[row.dataset.key] = true;
  });
  saveLocal();
}

function applyRowState(row) {
  const attended = !!attendance[row.dataset.key];
  const dot = row.querySelector('.dot');
  const btn = row.querySelector('.btn-asistio');
  row.classList.toggle('attended-row', attended);
  dot.classList.toggle('attended', attended);
  dot.title      = attended ? 'Haz clic para eliminar asistencia' : 'Marcar asistencia';
  btn.classList.toggle('attended', attended);
  btn.textContent = attended ? '✓ Confirmado' : '✓ Asistió';
}

async function markAttended(key) {
  if (attendance[key]) return;
  attendance[key] = true;
  saveLocal();
  const row = document.querySelector(`tr[data-key="${key}"]`);
  if (!row) return;
  applyRowState(row);
  updateSubtotal(row.closest('.mesa-card'));

  /* PERSISTIR EN BD VIA api.php */
  const dbId = row.dataset.dbId;
  if (dbId) api.post('confirmar', { id: parseInt(dbId) }).catch(() => {});

  if (!$('grid').classList.contains('searching')) return;
  Object.assign(row.style, { transition: 'opacity .4s, transform .4s', opacity: '0', transform: 'translateX(12px)' });
  setTimeout(() => {
    row.dataset.confirmed = 'true';
    Object.assign(row.style, { display: 'none', opacity: '', transform: '', transition: '' });
    const card = row.closest('.mesa-card');
    if (card) {
      const anyVisible = [...card.querySelectorAll('tbody tr[data-key]:not([data-confirmed="true"])')].some(r => r.style.display !== 'none');
      if (!anyVisible) card.classList.add('hidden');
    }
  }, 420);
}

/* ── UNMARK MODAL ── */
let pendingKey = null;
const unmarkModal = $('unmarkModal');
const toggleUnmarkModal = (key = null) => { pendingKey = key; unmarkModal.classList.toggle('open', key !== null); };

$('btnUnmarkNo').addEventListener('click', () => toggleUnmarkModal());
$('btnUnmarkSi').addEventListener('click', () => {
  if (!pendingKey) return;
  const key = pendingKey;
  toggleUnmarkModal();
  delete attendance[key];
  saveLocal();
  const row = document.querySelector(`tr[data-key="${key}"]`);
  if (row) { applyRowState(row); updateSubtotal(row.closest('.mesa-card')); }
  /* PERSISTIR EN BD */
  const dbId = row?.dataset.dbId;
  if (dbId) api.post('desmarcar', { id: parseInt(dbId) }).catch(() => {});
});
unmarkModal.addEventListener('click', e => { if (e.target === unmarkModal) toggleUnmarkModal(); });

/* ═══════════════════════════
   SUBTOTAL POR MESA
═══════════════════════════ */
function buildSubtotalRows() {
  document.querySelectorAll('.mesa-card').forEach(card => {
    const tr = document.createElement('tr');
    tr.className = 'subtotal-row';
    tr.innerHTML = `<td class="sub-label">✓ Confirmados</td><td class="sub-count sub-zero">0</td>`;
    card.querySelector('tr.total-row').before(tr);
  });
}
function updateSubtotal(card) {
  const sub = card?.querySelector('.subtotal-row .sub-count');
  if (!sub) return;
  const total = [...card.querySelectorAll('tbody tr[data-key]')].reduce((s, r) =>
    s + (attendance[r.dataset.key] ? (parseInt(r.querySelector('.num').textContent) || 0) : 0), 0);
  sub.textContent = total;
  sub.className = `sub-count${total > 0 ? '' : ' sub-zero'}`;
}
const updateAllSubtotals = () => document.querySelectorAll('.mesa-card').forEach(updateSubtotal);

/* ═══════════════════════════
   INIT
═══════════════════════════ */
function init() {
  loadLocal();
  initAttendanceFromDOM(); // sincroniza estado que PHP ya pintó
  buildSubtotalRows();
  document.querySelectorAll('tr[data-key]').forEach(row => {
    applyRowState(row);
    row.querySelector('.dot').addEventListener('click', () =>
      attendance[row.dataset.key] ? toggleUnmarkModal(row.dataset.key) : markAttended(row.dataset.key)
    );
    row.querySelector('.btn-asistio').addEventListener('click', () => markAttended(row.dataset.key));
  });
  updateAllSubtotals();
}

/* ═══════════════════════════
   SEARCH
═══════════════════════════ */
function doSearch(raw) {
  const terms = raw.split(',').map(t => normalize(t.trim())).filter(Boolean);
  const grid  = $('grid');
  grid.classList.toggle('searching', terms.length > 0);

  let visible = 0;
  document.querySelectorAll('.mesa-card').forEach(card => {
    const rows     = card.querySelectorAll('tbody tr[data-key]');
    const totalRow = card.querySelector('tr.total-row');
    let match = false;

    rows.forEach(row => {
      const fname    = row.querySelector('.fname');
      const original = (fname.dataset.original ??= fname.textContent);
      fname.innerHTML = original;

      if (!terms.length) { delete row.dataset.confirmed; row.style.display = ''; return; }
      if (row.dataset.confirmed === 'true') { row.style.display = 'none'; return; }

      const hit = terms.some(t => normalize(original).includes(t));
      row.style.display = hit ? '' : 'none';
      if (hit) {
        match = true;
        fname.innerHTML = terms.reduce((html, t) =>
          html.replace(new RegExp(`(${t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi'), '<mark>$1</mark>'), original);
      }
    });

    if (!terms.length) {
      card.classList.remove('hidden', 'highlight');
      if (totalRow) totalRow.style.display = '';
      visible++;
      return;
    }
    card.classList.toggle('hidden', !match);
    if (match) {
      card.classList.add('highlight');
      setTimeout(() => card.classList.remove('highlight'), 800);
      if (totalRow) totalRow.style.display = 'none';
      visible++;
    } else if (totalRow) {
      totalRow.style.display = '';
    }
  });

  const info  = $('resultInfo');
  const noRes = $('noResults');
  if (!terms.length) { info.textContent = ''; noRes.style.display = 'none'; return; }
  if (!visible)      { info.textContent = ''; noRes.style.display = 'block'; return; }
  const lbl = terms.length > 1 ? `${terms.length} búsquedas · ` : '';
  info.textContent    = `${lbl}${visible} mesa${visible !== 1 ? 's' : ''} encontrada${visible !== 1 ? 's' : ''}`;
  noRes.style.display = 'none';
}

const inp = $('searchInput');
inp.addEventListener('input',   e => doSearch(e.target.value));
inp.addEventListener('keydown', e => e.key === 'Enter' && doSearch(e.target.value));
$('btnBuscar').addEventListener('click', () => doSearch(inp.value));

/* ═══════════════════════════════════════════════════════════════
   QR SCANNER  –  SIMPLE
   ────────────────────────────────────────────────────────────
   FLUJO:
   1. JSQR LEE EL CONTENIDO DEL QR
   2. SI ES UNA URL → SE ABRE EN EL NAVEGADOR (window.open)
   3. SI NO ES URL  → SE MUESTRA EL TEXTO LEÍDO
════════════════════════════════════════════════════════════════ */
let qrStream = null, qrTimer = null;
const qrModal = $('qrModal');
const video   = $('qrVideo');
const canvas  = $('qrCanvas');
const qrCtx   = canvas.getContext('2d');
const qrRes   = $('qrResult');

async function openQR() {
  qrModal.classList.add('open');
  qrRes.textContent = 'Iniciando cámara…';
  try {
    qrStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
    video.srcObject = qrStream;
    await video.play();
    qrRes.textContent = 'Buscando código QR…';
    qrTimer = setInterval(scanFrame, 250);
  } catch { qrRes.textContent = '⚠️ No se pudo acceder a la cámara.'; }
}
function closeQR() {
  qrModal.classList.remove('open');
  clearInterval(qrTimer);
  qrStream?.getTracks().forEach(t => t.stop());
  qrStream = null;
  qrRes.textContent = '';
}
function scanFrame() {
  if (video.readyState !== video.HAVE_ENOUGH_DATA) return;
  canvas.width = video.videoWidth; canvas.height = video.videoHeight;
  qrCtx.drawImage(video, 0, 0, canvas.width, canvas.height);
  const img  = qrCtx.getImageData(0, 0, canvas.width, canvas.height);
  const code = jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
  if (!code) return;

  const texto = code.data.trim();
  closeQR();

  /* SI EL QR CONTIENE UNA URL → ABRIRLA DIRECTAMENTE */
  if (/^https?:\/\//i.test(texto)) {
    window.open(texto, '_blank');
  } else {
    /* SI NO ES URL → MOSTRAR EL TEXTO LEÍDO */
    qrRes.innerHTML = `✅ QR leído: <strong>${texto}</strong>`;
  }
}

$('btnQR').addEventListener('click', openQR);
$('btnCloseQR').addEventListener('click', closeQR);
qrModal.addEventListener('click', e => { if (e.target === qrModal) closeQR(); });

/* ── ARRANCAR ── */
init();
</script>
</body>
</html>
