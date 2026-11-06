<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar Sesión</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4/dist/css/adminlte.min.css">
</head>
<style>
.login-logo-img {
    max-height: 250px;   /* ajusta según necesites */
    width: auto;
    display: block;
    margin: 0 auto;
}
</style>
<body class="login-page bg-body-secondary">
    <div class="login-box">
        <div class="login-logo">
            <a href="#" class="login-brand">
                <img src="vistas/img/plantilla/icono-negro.png" alt="MiPanel" class="login-logo-img">
            </a>
        </div>
        <div class="card">
            <div class="card-body login-card-body">
                <p class="login-box-msg">Inicia sesión para comenzar</p>

                <form method="post" action="">
                    <div class="input-group mb-3">
                        <input type="text" name="ingUser" class="form-control" placeholder="Usuario" required>
                        <div class="input-group-text"><span class="fas fa-user"></span></div>
                    </div>
                    <div class="input-group mb-3">
                        <input type="password" name="ingPassword" class="form-control" placeholder="Contraseña" required>
                        <div class="input-group-text"><span class="fas fa-lock"></span></div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary w-100">Ingresar</button>
                        </div>
                    </div>
                </form>

                <?php
                // Ejecutar el controlador de login cuando se envíe el formulario
                $login = new ControladorUser();
                $login->ctrIngresoUser();
                ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4/dist/js/adminlte.min.js"></script>
    <!-- SweetAlert para los mensajes de error -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>