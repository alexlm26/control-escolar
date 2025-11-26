<?php

$to = "s21120228@alumnos.itsur.edu.mx";
$subject = "Correo de prueba desde AwardSpace";
$message = "Hola, este es un correo de prueba.";
$headers = "From: tu-correo@tu-dominio.com";

if (mail($to, $subject, $message, $headers)) {
    echo "Correo enviado correctamente.";
} else {
    echo "Error: AwardSpace bloqueó el correo o configuraste mal el remitente.";
}
?>
