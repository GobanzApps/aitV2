<?php
// Archivo: vistas/modulos/ticket-pdf.php
// Generador de PDF — Adaptado al sistema SAIT
// Estilo IDÉNTICO a generar_pdf_ticket.php (FPDF)

$rootPath = dirname(__FILE__) . '/../../';

// Incluir FPDF
require_once $rootPath . 'vistas/fpdf/fpdf.php';

// Limpiar cualquier salida previa
while (ob_get_level() > 0) { ob_end_clean(); }

// Verificar sesión
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION["iniciarSesion"]) || $_SESSION["iniciarSesion"] != "ok") {
    die("Acceso no autorizado");
}

// URL base del proyecto (para redirecciones con SweetAlert)
$baseUrl = 'index.php?ruta=tickets';

// Obtener el ID del ticket
$idTicket = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Función para mostrar alerta y redirigir
function mostrarAlertaYRedirigir($icono, $titulo, $mensaje, $url) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body>
        <script>
            Swal.fire({
                icon: "' . $icono . '",
                title: "' . $titulo . '",
                text: "' . $mensaje . '",
                confirmButtonText: "OK"
            }).then(function(result) {
                window.location.href = "' . $url . '";
            });
        </script>
    </body>
    </html>';
    exit();
}

// Validación 1: ID válido
if ($idTicket == 0) {
    mostrarAlertaYRedirigir("error", "ID Inválido", "El ID del ticket no es válido", $baseUrl);
}

// ==================== VERIFICAR TICKET ====================

$ticketInfo = ControladorTicket::ctrMostrarTicket("id", $idTicket);

// Validación 2: Ticket existe
if (!$ticketInfo) {
    mostrarAlertaYRedirigir("error", "Ticket No Encontrado", "El ticket que intentas acceder no existe", $baseUrl);
}

// Validación 3: Ticket debe estar finalizado (check = 1)
if ((int)$ticketInfo['check'] !== 1) {
    mostrarAlertaYRedirigir("error", "Ticket No Finalizado", "Si el ticket no esta finalizado no puede generar PDF", $baseUrl);
}

// ==================== VERIFICAR PERMISOS ====================

// Coordinaciones asignadas al ticket (tcoordinacion.id_coordinacion es JSON)
// ⚠️ Este array de IDs se usa SÓLO para el chequeo de permisos más abajo.
//    NO se imprime en el PDF.
$coordinacionesTicket = [];
$tcoords = ControladorTcoordinacion::ctrMostrarTcoordinacionPorTicket($idTicket);
if ($tcoords && is_array($tcoords)) {
    foreach ($tcoords as $tc) {
        $ids = json_decode($tc['id_coordinacion'] ?? '[]', true) ?: [];
        foreach ($ids as $idC) {
            $coordinacionesTicket[] = (int)$idC;
        }
    }
}
$coordinacionesTicket = array_values(array_unique($coordinacionesTicket));

// Usuario actual
$userActual = ControladorUser::ctrMostrarUser("id", $_SESSION["id"]);
if (!$userActual) {
    mostrarAlertaYRedirigir("warning", "Acceso Denegado", "Usuario no encontrado", $baseUrl);
}

// ¿Es Administrador? (id_rol es JSON array)
$esAdmin = false;
$rolesUser = json_decode($userActual["id_rol"] ?? '[]', true) ?: [];
foreach ($rolesUser as $idRol) {
    $rol = ControladorRol::ctrMostrarRol("id", (int)$idRol);
    if ($rol && $rol["rol"] === "Administrador" && (int)$rol["estado"] === 1) {
        $esAdmin = true;
        break;
    }
}

// Coordinaciones del usuario (una sola vez)
$coordUser = array_map('intval', json_decode($userActual["id_coordinacion"] ?? '[]', true) ?: []);

// ── Check 1: ¿Tiene alguna coordinación asignada al ticket? ──
$tienePermiso = $esAdmin;
if (!$tienePermiso) {
    foreach ($coordUser as $idC) {
        if (in_array($idC, $coordinacionesTicket, true)) {
            $tienePermiso = true;
            break;
        }
    }
}

// ✅ NUEVO Check 2: ¿Pertenece a la coordinación "Centro de Atención"?
// (Los usuarios de Centro de Atención también pueden generar el PDF)
$esCentroAtencion = false;
if (!$tienePermiso) {
    foreach ($coordUser as $idC) {
        $coord = ControladorCoordinacion::ctrMostrarCoordinacion("id", $idC);
        if ($coord && (int)($coord["estado"] ?? 0) === 1) {
            $nombreCoord = mb_strtolower(trim($coord["coordinacion"]));
            if (strpos($nombreCoord, 'centro de atenci') !== false) {
                $esCentroAtencion = true;
                break;
            }
        }
    }
}
$tienePermiso = $tienePermiso || $esCentroAtencion;

if (!$tienePermiso) {
    mostrarAlertaYRedirigir("warning", "Acceso Denegado", "No tienes permisos para acceder a este ticket", $baseUrl);
}

// ==================== HELPERS ====================

function _u($texto) {
    if ($texto === null) return '';
    return function_exists('utf8_decode') ? utf8_decode((string)$texto) : (string)$texto;
}

// ==================== OBTENER DATOS ====================

// Entidades (id_entidad es JSON)
$nombresEntes = [];
foreach ((json_decode($ticketInfo['id_entidad'] ?? '[]', true) ?: []) as $idE) {
    $e = ControladorEntidad::ctrMostrarEntidad("id", (int)$idE);
    if ($e) $nombresEntes[] = $e["entidad"];
}
$nombreEnte = empty($nombresEntes) ? 'No especificado' : implode(', ', $nombresEntes);

// Medios
$nombresMedios = [];
foreach ((json_decode($ticketInfo['id_medio'] ?? '[]', true) ?: []) as $idM) {
    $m = ControladorMedio::ctrMostrarMedio("id", (int)$idM);
    if ($m) $nombresMedios[] = $m["medio"];
}
$nombreMedio = empty($nombresMedios) ? 'No especificado' : implode(', ', $nombresMedios);

// ❌ ELIMINADO: Prioridad(es) — ya no se calcula ni se muestra en el PDF.

// Último status
$datosStatus = ['status' => 'Pendiente', 'color' => '#f0ad4e'];
$histStatus = ControladorTstatus::ctrMostrarTstatusPorTicket($idTicket);
if ($histStatus && is_array($histStatus) && count($histStatus) > 0) {
    $ultimo = $histStatus[0]; // ORDER BY id DESC
    $st = ControladorStatus::ctrMostrarStatus("id", (int)$ultimo['id_status']);
    if ($st) $datosStatus = $st;
}

// ❌ ELIMINADO: Nombres de coordinaciones — sólo se usaban para el bloque
//             "Coordinaciones Asignadas" que ya no se imprime.
//             Nota: $coordinacionesTicket (IDs) se conserva arriba para permisos.

// Usuarios asignados + primer usuario para la firma
$usuarios = [];
$tusers = ControladorTuser::ctrMostrarTuserPorTicket($idTicket);
if ($tusers && is_array($tusers)) {
    // Ordenar por id ASC (igual que el original: primer usuario asignado)
    usort($tusers, function($a, $b){ return $a['id'] <=> $b['id']; });
    foreach ($tusers as $tu) {
        $ids = json_decode($tu['id_user'] ?? '[]', true) ?: [];
        foreach ($ids as $idU) {
            $u = ControladorUser::ctrMostrarUser("id", (int)$idU);
            if ($u) {
                $usuarios[] = !empty($u["name"]) ? $u["name"] : $u["user"];
            }
        }
    }
}
$usuarios = array_values(array_unique($usuarios));
$primerUsuarioAsignado = !empty($usuarios) ? $usuarios[0] : '_____________________';

// ❌ ELIMINADO: Creador del ticket — ya no se calcula ni se muestra en el PDF.

// Servicios (tservicio: id_tag JSON, id_host JSON, cantidad, descripcion Quill)
$servicios = [];
$tservs = ControladorTservicio::ctrMostrarTservicioPorTicket($idTicket);
if ($tservs && is_array($tservs)) {
    foreach ($tservs as $srv) {
        $tagsNombres = [];
        foreach ((json_decode($srv['id_tag'] ?? '[]', true) ?: []) as $idT) {
            $tag = ControladorTag::ctrMostrarTag("id", (int)$idT);
            if ($tag) $tagsNombres[] = $tag["tag"];
        }
        $hostsNombres = [];
        foreach ((json_decode($srv['id_host'] ?? '[]', true) ?: []) as $idH) {
            $host = ControladorHost::ctrMostrarHost("id", (int)$idH);
            if ($host) $hostsNombres[] = $host["host"];
        }
        // Limpiar HTML de Quill → texto plano
        $desc = html_entity_decode(strip_tags($srv['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8');
        $desc = preg_replace('/\s+/', ' ', $desc);
        $desc = trim($desc);

        $servicios[] = [
            'servicio'    => implode(', ', $tagsNombres),
            'item'        => implode(', ', $hostsNombres),
            'descripcion' => $desc,
            'cantidad'    => $srv['cantidad']
        ];
    }
}

// Documentos (doc.id_ticket es JSON — buscar coincidencia)
$documentos = [];
try {
    $db = Conexion::conectar();
    $stmt = $db->prepare("SELECT * FROM doc WHERE estado = 1");
    $stmt->execute();
    $allDocs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allDocs as $d) {
        $ids = json_decode($d['id_ticket'] ?? '[]', true) ?: [];
        if (in_array($idTicket, array_map('intval', $ids), true)) {
            $tiposNombres = [];
            foreach ((json_decode($d['id_doc_tipo'] ?? '[]', true) ?: []) as $idT) {
                $t = ControladorDocTipo::ctrMostrarDocTipo("id", (int)$idT);
                if ($t) $tiposNombres[] = $t["doc_tipo"];
            }
            $documentos[] = [
                'documento' => $d['doc'],
                'tipo'      => implode(', ', $tiposNombres),
                'fecha'     => $d['modificacion'],
                'emision'   => $d['emision'],
                'asunto'    => $d['asunto']
            ];
        }
    }
} catch (Exception $e) {
    // Ignorar errores
}

// Evidencias (tevidencia)
$evidencias = [];
$tevids = ControladorTevidencia::ctrMostrarTevidenciaPorTicket($idTicket);
if ($tevids && is_array($tevids)) {
    foreach ($tevids as $ev) {
        $evidencias[] = [
            'id'           => $ev['id'],
            'ruta'         => $ev['foto'],
            'fecha_subida' => $ev['fecha'] ?? ''
        ];
    }
}

// ==================== CLASE PDF (idéntica al original) ====================

class PDF_Ticket extends FPDF {
    private $rutaImagen;
    private $primerUsuario;
    public $numeroTicket;

    const MARGEN_SUPERIOR  = 20;
    const MARGEN_INFERIOR  = 30;
    const MARGEN_IZQUIERDO = 25;
    const MARGEN_DERECHO   = 25;

    function __construct($primerUsuario = '') {
        parent::__construct('P', 'mm', 'Letter');
        $this->SetMargins(self::MARGEN_IZQUIERDO, self::MARGEN_SUPERIOR, self::MARGEN_DERECHO);
        $this->SetAutoPageBreak(true, self::MARGEN_INFERIOR);
        $this->primerUsuario = $primerUsuario;
        $this->numeroTicket  = '';
    }

    function setPrimerUsuario($primerUsuario) {
        $this->primerUsuario = $primerUsuario;
    }

    function Header() {
        if ($this->PageNo() == 1) {
            $rutaImagen = dirname(__FILE__) . '/../img/plantilla/icono-negro.png';
            if (file_exists($rutaImagen)) {
                $this->Image($rutaImagen, self::MARGEN_IZQUIERDO, self::MARGEN_SUPERIOR - 15, 30);
            }

            $this->SetY(self::MARGEN_SUPERIOR);
            $this->SetFont('Arial', 'B', 15);
            $this->Cell(0, 10, _u('Sistema de Gestion de Tickets - AIT'), 0, 1, 'C');
            $this->SetFont('Arial', '', 11);
            $this->Cell(0, 6, _u('Direccion de Automatizacion, Informatica y Telecomunicaciones'), 0, 1, 'C');
            $this->Cell(0, 6, _u('Gobernacion del Estado Anzoategui'), 0, 1, 'C');

            $this->Ln(5);
            $rutaImagenPrincipal = dirname(__FILE__) . '/../img/plantilla/logo-blanco-pdf.png';
            if (file_exists($rutaImagenPrincipal)) {
                $anchoImagen = 35;
                $xCentro = ($this->GetPageWidth() - $anchoImagen) / 2;
                $this->Image($rutaImagenPrincipal, $xCentro, $this->GetY(), $anchoImagen);
                $this->SetY($this->GetY() + 30);
            } else {
                $this->Ln(10);
            }
            $this->Ln(1);
        }
    }

    function Footer() {
        $this->SetY(-self::MARGEN_INFERIOR + 10);
        $this->SetFont('Arial', 'I', 8);
        if (!empty($this->numeroTicket)) {
            $this->Cell(0, 10, _u('Ticket #' . $this->numeroTicket . ' - Página ') . $this->PageNo(), 0, 0, 'C');
        } else {
            $this->Cell(0, 10, _u('Página ') . $this->PageNo(), 0, 0, 'C');
        }
    }

    function agregarTablaFirmas() {
        $alturaRequerida = 70;
        $posicionActualY = $this->GetY();
        $restoPagina = $this->GetPageHeight() - $posicionActualY - self::MARGEN_INFERIOR;

        if ($restoPagina < $alturaRequerida) {
            $this->AddPage();
        }

        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, _u('VALIDACIONES DEL SERVICIO'), 0, 1, 'C');
        $this->Ln(1);

        $this->SetFont('Arial', 'B', 11);

        $anchoDisponible = $this->GetPageWidth() - self::MARGEN_IZQUIERDO - self::MARGEN_DERECHO;
        $anchoColumna = $anchoDisponible / 3;

        $this->SetFillColor(240, 240, 240);
        $this->Cell($anchoColumna, 10, _u('ELABORADO POR'), 1, 0, 'C', true);
        $this->Cell($anchoColumna, 10, _u('REVISADO POR'), 1, 0, 'C', true);
        $this->Cell($anchoColumna, 10, _u('APROBADO POR'), 1, 1, 'C', true);

        $this->SetFont('Arial', '', 9);
        $this->Cell($anchoColumna, 25, '', 1, 0, 'C');
        $this->Cell($anchoColumna, 25, '', 1, 0, 'C');
        $this->Cell($anchoColumna, 25, '', 1, 1, 'C');

        $this->SetFont('Arial', '', 10);
        $this->Cell($anchoColumna, 10, _u($this->primerUsuario), 1, 0, 'C');
        $this->Cell($anchoColumna, 10, _u('T.S.U. Miguel Garcia'), 1, 0, 'C');
        $this->Cell($anchoColumna, 10, _u('Ing. Carlos Adueza'), 1, 1, 'C');
    }

    function agregarEvidencias($evidencias) {
        if (empty($evidencias)) {
            return;
        }

        $alturaRequerida = 100;
        $posicionActualY = $this->GetY();
        $restoPagina = $this->GetPageHeight() - $posicionActualY - self::MARGEN_INFERIOR;

        if ($restoPagina < $alturaRequerida) {
            $this->AddPage();
        }

        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, _u('EVIDENCIAS FOTOGRÁFICAS'), 0, 1, 'C');
        $this->Ln(5);

        $basePath = dirname(__FILE__) . '/../../';

        $anchoDisponible       = $this->GetPageWidth() - self::MARGEN_IZQUIERDO - self::MARGEN_DERECHO;
        $anchoImagen           = 75;
        $altoImagen            = 60;
        $espacioEntreImagenes  = 15;

        // CASO: 2 imágenes (una al lado de la otra)
        if (count($evidencias) == 2) {
            $xInicial = self::MARGEN_IZQUIERDO;
            $yInicial = $this->GetY();

            // Primera
            $rutaImagen1 = $basePath . $evidencias[0]['ruta'];
            $yImagen = $this->GetY();
            if (file_exists($rutaImagen1)) {
                $this->Image($rutaImagen1, $xInicial, $yImagen, $anchoImagen, $altoImagen);
            } else {
                $this->SetXY($xInicial, $yImagen);
                $this->SetFont('Arial', '', 8);
                $this->Cell($anchoImagen, $altoImagen, _u('Imagen no encontrada'), 1, 0, 'C');
            }
            $yTextoDebajo = $yImagen + $altoImagen + 3;

            // Segunda
            $xSegundaImagen = $xInicial + $anchoImagen + $espacioEntreImagenes;
            $rutaImagen2 = $basePath . $evidencias[1]['ruta'];
            if (file_exists($rutaImagen2)) {
                $this->Image($rutaImagen2, $xSegundaImagen, $yImagen, $anchoImagen, $altoImagen);
            } else {
                $this->SetXY($xSegundaImagen, $yImagen);
                $this->SetFont('Arial', '', 8);
                $this->Cell($anchoImagen, $altoImagen, _u('Imagen no encontrada'), 1, 0, 'C');
            }

            $this->SetY($yTextoDebajo + 2);
        }
        // CASO: 1 imagen (centrada)
        else if (count($evidencias) == 1) {
            $xCentro = ($this->GetPageWidth() - $anchoImagen) / 2;
            $yInicial = $this->GetY();
            $rutaImagen = $basePath . $evidencias[0]['ruta'];

            $yImagen = $this->GetY();
            if (file_exists($rutaImagen)) {
                $this->Image($rutaImagen, $xCentro, $yImagen, $anchoImagen, $altoImagen);
            } else {
                $this->SetXY($xCentro, $yImagen);
                $this->SetFont('Arial', '', 8);
                $this->Cell($anchoImagen, $altoImagen, _u('Imagen no encontrada'), 1, 0, 'C');
            }
            $yTextoDebajo = $yImagen + $altoImagen + 3;
            $this->SetY($yTextoDebajo + 15);
        }
        // CASO: 3+ imágenes (grid 2x2)
        else {
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 6, _u('Hay ' . count($evidencias) . ' imágenes disponibles. Mostrando las primeras 4:'), 0, 1, 'L');
            $this->Ln(5);

            $maxImagenes      = min(4, count($evidencias));
            $imagenesPorFila  = 2;
            $anchoTotalGrid   = ($anchoImagen * $imagenesPorFila) + ($espacioEntreImagenes * ($imagenesPorFila - 1));
            $xInicial         = ($this->GetPageWidth() - $anchoTotalGrid) / 2;
            $yActual          = $this->GetY();

            for ($i = 0; $i < $maxImagenes; $i++) {
                $columna = $i % $imagenesPorFila;
                $fila    = floor($i / $imagenesPorFila);

                $x = $xInicial + ($columna * ($anchoImagen + $espacioEntreImagenes));
                $y = $yActual + ($fila * ($altoImagen + 20));

                $rutaImagen = $basePath . $evidencias[$i]['ruta'];

                $this->SetXY($x, $y);
                $this->SetFont('Arial', 'B', 8);
                $this->Cell($anchoImagen, 5, _u('Imagen ' . ($i+1)), 0, 1, 'C');

                $yImagen = $y + 5;
                if (file_exists($rutaImagen)) {
                    $this->Image($rutaImagen, $x, $yImagen, $anchoImagen, $altoImagen);
                } else {
                    $this->SetXY($x, $yImagen);
                    $this->SetFont('Arial', '', 7);
                    $this->Cell($anchoImagen, $altoImagen, _u('No encontrada'), 1, 0, 'C');
                }

                $this->SetXY($x, $yImagen + $altoImagen + 2);
                $this->SetFont('Arial', 'I', 7);
                $fechaCorta = substr($evidencias[$i]['fecha_subida'], 0, 10);
                $this->Cell($anchoImagen, 4, _u($fechaCorta), 0, 1, 'C');
            }

            $this->SetY($yActual + (ceil($maxImagenes / $imagenesPorFila) * ($altoImagen + 25)));
        }

        $this->Ln(5);
    }

    function LineaPunteada($y, $x1 = null, $x2 = null) {
        if ($x1 === null) $x1 = self::MARGEN_IZQUIERDO;
        if ($x2 === null) $x2 = $this->GetPageWidth() - self::MARGEN_DERECHO;

        $this->SetDrawColor(150, 150, 150);
        $this->SetLineWidth(0.3);

        $espacio = 3;
        $longitud = $x2 - $x1;
        $numPuntos = floor($longitud / $espacio);

        for ($i = 0; $i <= $numPuntos; $i++) {
            $x = $x1 + ($i * $espacio);
            $this->Line($x, $y, $x + 1, $y);
        }
    }
}

// ==================== CREAR Y GENERAR PDF ====================

$pdf = new PDF_Ticket($primerUsuarioAsignado);
$pdf->numeroTicket = $ticketInfo['id'];
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

// Título
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, _u('INFORME DE SERVICIO TÉCNICO'), 0, 1, 'C');

// Fecha actual formateada
$pdf->SetFont('Arial', '', 10);
$fechaActual = date('d');
$mesActual   = date('n');
$anioActual  = date('Y');

$meses = array(
    1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
    5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
);

$fechaFormateada = 'Barcelona, ' . $fechaActual . ' de ' . $meses[$mesActual] . ' del ' . $anioActual;
$pdf->Cell(0, 6, _u($fechaFormateada), 0, 1, 'C');
$pdf->Ln(5);

// Datos del ticket
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, _u('Datos del Ticket #' . $ticketInfo['id']), 0, 1, 'L');
$pdf->SetFont('Arial', '', 10);

$pdf->Cell(50, 6, _u('Dependencia:'), 0, 0, 'L');
$pdf->Cell(0, 6, _u($nombreEnte), 0, 1, 'L');

$pdf->Cell(50, 6, _u('Solicitante:'), 0, 0, 'L');
$pdf->Cell(0, 6, _u($ticketInfo['solicitante']), 0, 1, 'L');

$pdf->Cell(50, 6, _u('Descripción:'), 0, 0, 'L');
$pdf->MultiCell(0, 6, _u($ticketInfo['descripcion']), 0, 'L');

$pdf->Cell(50, 6, _u('Medio:'), 0, 0, 'L');
$pdf->Cell(0, 6, _u($nombreMedio), 0, 1, 'L');

// ❌ ELIMINADO: Prioridad (no se imprime)

$pdf->Cell(50, 6, _u('Fecha Creación:'), 0, 0, 'L');
$pdf->Cell(0, 6, $ticketInfo['modificacion'], 0, 1, 'L');

// ❌ ELIMINADO: Creado por (no se imprime)

$pdf->Ln(5);

// Documentos adjuntos
if (!empty($documentos)) {
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, _u('Documentos Adjuntos'), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 10);

    foreach ($documentos as $doc) {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 6, _u($doc['documento']), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Ln(3);
    }
    $pdf->Ln(5);
}

// ❌ ELIMINADO: Bloque "Coordinaciones Asignadas" (no se imprime)

// Usuarios
if (!empty($usuarios)) {
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, _u('Especialistas Asignados'), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 10);
    foreach ($usuarios as $user) {
        $pdf->Cell(10, 6, '', 0, 0, 'L');
        $pdf->Cell(0, 6, _u('- ' . $user), 0, 1, 'L');
    }
    $pdf->Ln(5);
}

// Servicios
if (!empty($servicios)) {
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, _u('Servicios'), 0, 1, 'L');
    $pdf->Ln(2);

    $contadorServicios = count($servicios);
    $indice = 0;

    foreach ($servicios as $serv) {
        $indice++;

        // Encabezado: Tags - Hosts
        $encabezado = $serv['servicio'];
        if (!empty($serv['item'])) {
            $encabezado .= ' - ' . $serv['item'];
        }
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 6, _u($encabezado), 0, 1, 'L');

        $pdf->SetFont('Arial', 'I', 9);
        $pdf->Cell(30, 5, _u('Cantidad:'), 0, 0, 'R');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(20, 5, $serv['cantidad'], 0, 1, 'L');

        $pdf->SetFont('Arial', 'I', 9);
        $pdf->Cell(30, 5, _u('Descripción:'), 0, 0, 'R');
        $pdf->SetFont('Arial', '', 9);
        $pdf->MultiCell(0, 5, _u($serv['descripcion']), 0, 'L');

        if ($indice < $contadorServicios) {
            $yActual = $pdf->GetY();
            $pdf->LineaPunteada($yActual + 2);
            $pdf->Ln(4);
        } else {
            $pdf->Ln(3);
        }
    }
    $pdf->Ln(5);
}

// Evidencias
if (!empty($evidencias)) {
    $pdf->agregarEvidencias($evidencias);
}

// Tabla de firmas
$pdf->agregarTablaFirmas();

// Generar PDF
$pdf->Output('I', 'Ticket_' . $idTicket . '.pdf');
?>