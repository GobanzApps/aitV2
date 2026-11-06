<?php
/*=============================================
allticket_core.php
Funciones compartidas entre:
  - vistas/modulos/inicio/allticket.php  (render inicial)
  - ajax/allticket.ajax.php              (auto-refresh cada 60 s)
=============================================*/

date_default_timezone_set('America/Caracas');

if (!function_exists('allticketCompute')) {

    function allticketCompute()
    {
        $idUser = $_SESSION["id"] ?? 0;
        if ($idUser <= 0) {
            return [
                'ok' => false, 'error' => 'Sesión no válida',
                'tickets' => [], 'esAdminReal' => false, 'esCentroAtencion' => false
            ];
        }

        $userActual = ControladorUser::ctrMostrarUser("id", $idUser);
        if (!$userActual) {
            return [
                'ok' => false, 'error' => 'Usuario no encontrado',
                'tickets' => [], 'esAdminReal' => false, 'esCentroAtencion' => false
            ];
        }

        $todosRoles = ControladorRol::ctrMostrarRol(null, null) ?: [];
        $mapaRoles  = [];
        foreach ($todosRoles as $r) { $mapaRoles[(int)$r["id"]] = $r; }

        /* ¿Administrador? */
        $esAdminReal = false;
        $rolesIds = json_decode($userActual["id_rol"] ?? '[]', true) ?: [];
        foreach ($rolesIds as $idRol) {
            $idRol = (int)$idRol;
            if (isset($mapaRoles[$idRol]) && (int)$mapaRoles[$idRol]["estado"] === 1) {
                if (strcasecmp($mapaRoles[$idRol]["rol"], "Administrador") === 0) {
                    $esAdminReal = true;
                    break;
                }
            }
        }

        /* Coordinaciones del usuario */
        $coordsUser = array_map('intval', json_decode($userActual["id_coordinacion"] ?? '[]', true) ?: []);

        /* ¿Centro de Atención? */
        $esCentroAtencion = false;
        if (!empty($coordsUser)) {
            $todasCoords = ControladorCoordinacion::ctrMostrarCoordinacion(null, null) ?: [];
            $mapaCoords  = [];
            foreach ($todasCoords as $c) { $mapaCoords[(int)$c["id"]] = $c; }

            foreach ($coordsUser as $idC) {
                if (isset($mapaCoords[$idC]) && (int)$mapaCoords[$idC]["estado"] === 1) {
                    $nombreC = mb_strtolower(trim($mapaCoords[$idC]["coordinacion"]));
                    if (strpos($nombreC, 'centro de atenci') !== false) {
                        $esCentroAtencion = true;
                        break;
                    }
                }
            }
        }

        /* Tickets pendientes (estado=1 y check=0) */
        $todosTickets     = ControladorTicket::ctrMostrarTicket(null, null) ?: [];
        $ticketsFiltrados = [];

        foreach ($todosTickets as $t) {
            if ((int)($t["estado"] ?? 0) !== 1) continue;
            if ((int)($t["check"]  ?? 0) !== 0) continue;

            if ($esAdminReal || $esCentroAtencion) {
                $ticketsFiltrados[] = $t;
                continue;
            }

            $tieneAcceso = false;

            $tcoords = ControladorTcoordinacion::ctrMostrarTcoordinacionPorTicket((int)$t["id"]);
            if ($tcoords && is_array($tcoords)) {
                foreach ($tcoords as $tc) {
                    $ids = json_decode($tc["id_coordinacion"] ?? '[]', true) ?: [];
                    foreach ($ids as $idC) {
                        if (in_array((int)$idC, $coordsUser, true)) {
                            $tieneAcceso = true;
                            break 2;
                        }
                    }
                }
            }

            if (!$tieneAcceso) {
                $tusers = ControladorTuser::ctrMostrarTuserPorTicket((int)$t["id"]);
                if ($tusers && is_array($tusers)) {
                    foreach ($tusers as $tu) {
                        $ids = json_decode($tu["id_user"] ?? '[]', true) ?: [];
                        foreach ($ids as $idU) {
                            if ((int)$idU === (int)$idUser) {
                                $tieneAcceso = true;
                                break 2;
                            }
                        }
                    }
                }
            }

            if ($tieneAcceso) $ticketsFiltrados[] = $t;
        }

        return [
            'ok'               => true,
            'error'            => null,
            'esAdminReal'      => $esAdminReal,
            'esCentroAtencion' => $esCentroAtencion,
            'tickets'          => $ticketsFiltrados
        ];
    }
}

/* ────────── Helpers compartidos ────────── */

if (!function_exists('allticketColorUsuario')) {
    function allticketColorUsuario($usuario, $mapaRoles)
    {
        $ids = json_decode($usuario["id_rol"] ?? '[]', true);
        if (!is_array($ids) || empty($ids)) return "#6c757d";
        foreach ($ids as $idRol) {
            $idRol = (int)$idRol;
            if (isset($mapaRoles[$idRol]) && (int)$mapaRoles[$idRol]["estado"] === 1) {
                return $mapaRoles[$idRol]["color"] ?: "#0d6efd";
            }
        }
        return "#6c757d";
    }
}

if (!function_exists('allticketIniciales')) {
    function allticketIniciales($nombre)
    {
        $nombre = trim((string)$nombre);
        if ($nombre === '') return '?';
        $partes = preg_split('/\s+/', $nombre);
        $ini = '';
        foreach (array_slice($partes, 0, 2) as $p) {
            $ini .= mb_strtoupper(mb_substr($p, 0, 1));
        }
        return $ini ?: '?';
    }
}

if (!function_exists('allticketFormatearTiempo')) {
    function allticketFormatearTiempo($seg)
    {
        $seg = max(0, (int)$seg);
        $min = (int)floor($seg / 60);
        if ($min < 1)  return 'menos de 1 min';
        if ($min < 60) return $min . ' min';

        $hrs     = (int)floor($min / 60);
        $minRest = $min % 60;
        if ($hrs < 24) {
            return $hrs . ' h' . ($minRest > 0 ? ' ' . $minRest . ' min' : '');
        }

        $d       = (int)floor($hrs / 24);
        $hrsRest = $hrs % 24;
        return $d . ' d' . ($hrsRest > 0 ? ' ' . $hrsRest . ' h' : '');
    }
}

if (!function_exists('allticketNombreUsuario')) {
    function allticketNombreUsuario($usuario)
    {
        if (!$usuario) return null;
        return !empty($usuario["name"]) ? $usuario["name"] : $usuario["user"];
    }
}

/* ────────── Render del <tbody> ────────── */

if (!function_exists('allticketRenderTbody')) {

    function allticketRenderTbody($resultado)
    {
        if (empty($resultado['ok']) || empty($resultado['tickets'])) {
            return '';
        }

        $tickets = $resultado['tickets'];

        /* Catálogos precargados */
        $todosUsuarios = ControladorUser::ctrMostrarUser(null, null) ?: [];
        $mapaUsuarios  = [];
        foreach ($todosUsuarios as $u) { $mapaUsuarios[(int)$u["id"]] = $u; }

        $todosRoles = ControladorRol::ctrMostrarRol(null, null) ?: [];
        $mapaRoles  = [];
        foreach ($todosRoles as $r) { $mapaRoles[(int)$r["id"]] = $r; }

        $todasCoords = ControladorCoordinacion::ctrMostrarCoordinacion(null, null) ?: [];
        $mapaCoords  = [];
        foreach ($todasCoords as $c) { $mapaCoords[(int)$c["id"]] = $c; }

        $todasEnts = ControladorEntidad::ctrMostrarEntidad(null, null) ?: [];
        $mapaEnts  = [];
        foreach ($todasEnts as $e) { $mapaEnts[(int)$e["id"]] = $e; }

        $todasPris = ControladorPrioridad::ctrMostrarPrioridad(null, null) ?: [];
        $mapaPris  = [];
        foreach ($todasPris as $p) { $mapaPris[(int)$p["id"]] = $p; }

        $todosStatus = ControladorStatus::ctrMostrarStatus(null, null) ?: [];
        $mapaStatus  = [];
        foreach ($todosStatus as $s) { $mapaStatus[(int)$s["id"]] = $s; }

        $html = '';
        $i    = 0;

        foreach ($tickets as $ticket) {
            $i++;
            $idTicket = (int)$ticket["id"];

            /* ═══════════════ Entidad(es) ═══════════════ */
            $entsHtml = '';
            $idsEnts  = json_decode($ticket["id_entidad"] ?? '[]', true) ?: [];
            foreach ($idsEnts as $idE) {
                $idE = (int)$idE;
                if (isset($mapaEnts[$idE])) {
                    $e     = $mapaEnts[$idE];
                    $color = $e["color"] ?: "#0d6efd";
                    $entsHtml .= '<span class="badge me-1 mb-1" style="background-color:' . htmlspecialchars($color) . '; color:#fff; padding:5px 9px; font-size:0.72rem;">'
                              .  '<i class="fas fa-city me-1"></i>' . htmlspecialchars($e["entidad"])
                              .  '</span>';
                }
            }
            if ($entsHtml === '') $entsHtml = '<span class="text-muted">—</span>';

            /* ═══════════════════════════════════════════════════════
               ÚLTIMO STATUS
               ═══════════════════════════════════════════════════════ */
            $statusHtml = '<span class="text-muted">—</span>';
            $hist = ControladorTstatus::ctrMostrarTstatusPorTicket($idTicket);

            if ($hist && is_array($hist) && count($hist) > 0) {
                $ultimo       = $hist[0]; // ORDER BY id DESC → siempre el más reciente
                $idSt         = (int)($ultimo["id_status"] ?? 0);
                $checkNotif   = (int)($ultimo["check"]     ?? 0);

                $fechaCreacion   = $ultimo["fecha"]        ?? ($ultimo["modificacion"] ?? '');
                $fechaComentario = $ultimo["modificacion"] ?? '';
                $fechaCheck      = $ultimo["fecha_check"]  ?? '';
                $comentario      = trim($ultimo["comentario"] ?? '');

                /* Badge del status */
                $esNotificableAllTicket = false;
                if (isset($mapaStatus[$idSt])) {
                    $st    = $mapaStatus[$idSt];
                    $color = $st["color"] ?: "#0d6efd";
                    $statusBadge = '<span class="badge" style="background-color:' . htmlspecialchars($color) . '; color:#fff; padding:4px 8px; font-size:0.7rem;">'
                                .  '<i class="fas fa-tasks me-1"></i>' . htmlspecialchars($st["status"])
                                .  '</span>';
                    $esNotificableAllTicket = in_array($st["status"], ["Nuevo", "Reemplazo"], true);
                } else {
                    $statusBadge = '<span class="badge bg-secondary">ID ' . $idSt . '</span>';
                }

                /* Chips de tiempo */
                $tiempoHtml = '';

                if ($checkNotif === 1 && !empty($fechaCheck) && !empty($fechaCreacion)) {
                    /* ── Chip verde: tiempo hasta ser aceptada ── */
                    $seg = strtotime($fechaCheck) - strtotime($fechaCreacion);
                    $txt = allticketFormatearTiempo($seg);
                    $tiempoHtml .= '<span class="tstatus-readtime m-0" title="Tiempo hasta ser aceptada">'
                                .   '<i class="far fa-hourglass-half"></i> en ' . htmlspecialchars($txt)
                                . '</span>';

                    /* ═══ NUEVO ═══  Chip azul: tiempo sin cambios desde la aceptación
                       Solo se muestra si el status es Nuevo o Reemplazo (ya aceptado).
                       Cada fila de allticket YA representa el ÚLTIMO status del ticket,
                       por lo que no hace falta validar "es el último". */
                    if ($esNotificableAllTicket) {
                        $segSinCambios = time() - strtotime($fechaCheck);
                        $txtSinCambios = allticketFormatearTiempo($segSinCambios);
                        $tiempoHtml .= '<span class="tstatus-waitingtime m-0"'
                                    .   ' data-desde="' . htmlspecialchars($fechaCheck) . '"'
                                    .   ' title="Tiempo transcurrido desde que fue aceptada, sin cambiar a otro status">'
                                    .   '<i class="fas fa-hourglass-end"></i>'
                                    .   '<span class="tstatus-waitingtime__text">hace ' . htmlspecialchars($txtSinCambios) . ' sin cambios</span>'
                                    . '</span>';
                    }
                    /* ═══ FIN NUEVO ═══ */

                } elseif ($checkNotif === 0 && !empty($fechaCreacion)) {
                    /* ── Chip naranja: pendiente de aceptar ── */
                    $seg = time() - strtotime($fechaCreacion);
                    $txt = allticketFormatearTiempo($seg);
                    $tiempoHtml = '<span class="tstatus-pendingtime m-0"'
                                .   ' data-desde="' . htmlspecialchars($fechaCreacion) . '"'
                                .   ' title="Tiempo desde que se creó la notificación">'
                                .   '<i class="far fa-hourglass-half"></i>'
                                .   '<span class="tstatus-pendingtime__text">hace ' . htmlspecialchars($txt) . '</span>'
                                . '</span>';
                }

                /* Caja del comentario */
                $comentHtml = '';
                if ($comentario !== '') {
                    $fechaComentFmt = !empty($fechaComentario)
                        ? date("d/m/Y H:i", strtotime($fechaComentario))
                        : '';
                    $comentHtml = '<div class="tstatus-comment mt-2">'
                                .   '<div class="tstatus-comment__icon">'
                                .      '<i class="fas fa-comment-dots"></i>'
                                .   '</div>'
                                .   '<div class="tstatus-comment__body">'
                                .      '<div class="tstatus-comment__header">'
                                .         '<span class="tstatus-comment__label">Comentario</span>'
                                .         '<span class="tstatus-comment__time">'
                                .            '<i class="far fa-clock me-1"></i>' . htmlspecialchars($fechaComentFmt)
                                .         '</span>'
                                .      '</div>'
                                .      '<div class="tstatus-comment__text">' . htmlspecialchars($comentario) . '</div>'
                                .   '</div>'
                                . '</div>';
                }

                $statusHtml = '<div style="min-width:0;">'
                            .   '<div class="d-flex align-items-center flex-wrap gap-2">'
                            .       $statusBadge
                            .       ($tiempoHtml !== '' ? $tiempoHtml : '')
                            .   '</div>'
                            .   $comentHtml
                            . '</div>';
            }

            /* ═══════════════ Usuarios asignados ═══════════════ */
            $usersHtml = '';
            $tusers    = ControladorTuser::ctrMostrarTuserPorTicket($idTicket);
            $idsUsers  = [];
            if ($tusers && is_array($tusers)) {
                foreach ($tusers as $tu) {
                    $ids = json_decode($tu["id_user"] ?? '[]', true) ?: [];
                    foreach ($ids as $idU) $idsUsers[] = (int)$idU;
                }
            }
            $idsUsers = array_values(array_unique($idsUsers));

            foreach ($idsUsers as $idU) {
                if (isset($mapaUsuarios[$idU])) {
                    $u       = $mapaUsuarios[$idU];
                    $nombreU = allticketNombreUsuario($u);
                    $colorU  = allticketColorUsuario($u, $mapaRoles);
                    $usersHtml .= '<span class="badge me-1 mb-1" style="background-color:' . htmlspecialchars($colorU) . '; color:#fff; padding:5px 9px; font-size:0.72rem;">'
                               .  '<i class="fas fa-user me-1"></i>' . htmlspecialchars($nombreU)
                               .  '</span>';
                } else {
                    $usersHtml .= '<span class="badge bg-secondary me-1 mb-1">ID ' . $idU . '</span>';
                }
            }
            if ($usersHtml === '') $usersHtml = '<span class="text-muted">—</span>';

            /* ═══════════════ Coordinaciones ═══════════════ */
            $coordsHtml = '';
            $tcoords    = ControladorTcoordinacion::ctrMostrarTcoordinacionPorTicket($idTicket);
            $idsCoords  = [];
            if ($tcoords && is_array($tcoords)) {
                foreach ($tcoords as $tc) {
                    $ids = json_decode($tc["id_coordinacion"] ?? '[]', true) ?: [];
                    foreach ($ids as $idC) $idsCoords[] = (int)$idC;
                }
            }
            $idsCoords = array_values(array_unique($idsCoords));

            foreach ($idsCoords as $idC) {
                if (isset($mapaCoords[$idC])) {
                    $c      = $mapaCoords[$idC];
                    $colorC = $c["color"] ?: "#0d6efd";
                    $coordsHtml .= '<span class="badge me-1 mb-1" style="background-color:' . htmlspecialchars($colorC) . '; color:#fff; padding:5px 9px; font-size:0.72rem;">'
                                .  '<i class="fas fa-sitemap me-1"></i>' . htmlspecialchars($c["coordinacion"])
                                .  '</span>';
                }
            }
            if ($coordsHtml === '') $coordsHtml = '<span class="text-muted">—</span>';

            /* ═══════════════ Prioridades ═══════════════ */
            $prisHtml = '';
            $idsPris  = json_decode($ticket["id_prioridad"] ?? '[]', true) ?: [];
            foreach ($idsPris as $idP) {
                $idP = (int)$idP;
                if (isset($mapaPris[$idP])) {
                    $p      = $mapaPris[$idP];
                    $colorP = $p["color"] ?: "#0d6efd";
                    $prisHtml .= '<span class="badge me-1 mb-1" style="background-color:' . htmlspecialchars($colorP) . '; color:#fff; padding:5px 9px; font-size:0.72rem;">'
                              .  '<i class="fas fa-flag me-1"></i>' . htmlspecialchars($p["prioridad"])
                              .  '</span>';
                }
            }
            if ($prisHtml === '') $prisHtml = '<span class="text-muted">—</span>';

            /* ═══════════════ Fila completa ═══════════════ */
            $html .= '<tr>'
                  .  '<td class="text-center">' . $i . '</td>'
                  .  '<td><a href="index.php?ruta=ticket&id=' . $idTicket . '" class="badge text-bg-primary text-decoration-none" title="Ver ticket">'
                  .      '<i class="fas fa-ticket-alt me-1"></i>Ticket #' . $idTicket
                  .  '</a></td>'
                  .  '<td>' . $entsHtml   . '</td>'
                  .  '<td>' . $statusHtml . '</td>'
                  .  '<td>' . $usersHtml  . '</td>'
                  .  '<td>' . $coordsHtml . '</td>'
                  .  '<td>' . $prisHtml   . '</td>'
                  .  '<td class="text-center">'
                  .      '<a href="index.php?ruta=ticket&id=' . $idTicket . '" class="btn btn-sm btn-info" title="Ver">'
                  .          '<i class="fas fa-eye"></i>'
                  .      '</a>'
                  .  '</td>'
                  .  '</tr>';
        }

        return $html;
    }
}