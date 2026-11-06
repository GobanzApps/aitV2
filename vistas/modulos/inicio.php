<?php
if(!isset($_SESSION["iniciarSesion"]) || $_SESSION["iniciarSesion"] != "ok"){
    echo '<script>window.location = "/";</script>';
    exit();
}
?>



<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-home me-2"></i> Bienvenido al Panel</h3>
    </div>
    <div class="card-body">
        <p class="mb-0">Hola <strong><?php echo htmlspecialchars($_SESSION["user"] ?? ''); ?></strong>, bienvenido a tu panel de administración.</p>
    </div>
</div>

<?php include "inicio/button.php"; ?>

<?php include "inicio/dashboard.php"; ?>

<?php include "inicio/allticket.php"; ?>

<?php include "inicio/tabs2.php"; ?>

<?php include "inicio/members.php"; ?>
