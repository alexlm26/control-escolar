<?php
session_start();
unset($_SESSION['usuario_intentado']);
echo "Session limpiada";
?>