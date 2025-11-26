-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: pdb1049.awardspace.net
-- Tiempo de generación: 22-11-2025 a las 03:29:12
-- Versión del servidor: 8.0.32
-- Versión de PHP: 8.1.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `4701135_control`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`4701135_control`@`%` PROCEDURE `actualizar_asignados` ()   BEGIN
  DECLARE done INT DEFAULT FALSE;
  DECLARE clase_id INT;
  DECLARE total INT;
  DECLARE cur CURSOR FOR 
    SELECT id_clase, COUNT(*) 
    FROM asignacion 
    GROUP BY id_clase;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

  OPEN cur;
  read_loop: LOOP
    FETCH cur INTO clase_id, total;
    IF done THEN
      LEAVE read_loop;
    END IF;
    UPDATE clase 
    SET asignado = total 
    WHERE id_clase = clase_id;
  END LOOP;
  CLOSE cur;
END$$

CREATE DEFINER=`4701135_control`@`%` PROCEDURE `actualizar_tareas_vencidas` ()   BEGIN
    UPDATE tareas 
    SET estado = 'cerrada' 
    WHERE estado = 'activa' 
    AND fecha_limite < NOW();
    
    SELECT ROW_COUNT() AS 'tareas_actualizadas';
END$$

--
-- Funciones
--
CREATE DEFINER=`4701135_control`@`%` FUNCTION `actualizar_especialidad_alumno` (`p_id_alumno` INT, `p_id_especialidad` INT) RETURNS TINYINT(1) DETERMINISTIC READS SQL DATA BEGIN
    DECLARE v_nombre_especialidad VARCHAR(50);
    DECLARE v_especialidad_existe INT;
    
    
    SELECT COUNT(*) INTO v_especialidad_existe 
    FROM especialidad 
    WHERE id_especialidad = p_id_especialidad;
    
    IF v_especialidad_existe = 0 THEN
        RETURN FALSE;
    END IF;
    
    
    SELECT nombre INTO v_nombre_especialidad 
    FROM especialidad 
    WHERE id_especialidad = p_id_especialidad;
    
    
    UPDATE alumno 
    SET id_especialidad = p_id_especialidad,
        especialidad = v_nombre_especialidad
    WHERE id_alumno = p_id_alumno;
    
    RETURN TRUE;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `acciones`
--

CREATE TABLE `acciones` (
  `id_accion` int NOT NULL,
  `accion` varchar(20) NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `fecha_modificacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `acciones`
--

INSERT INTO `acciones` (`id_accion`, `accion`, `activo`, `fecha_modificacion`) VALUES
(1, 'modificar_calificaci', 1, '2025-11-19 21:29:22'),
(2, 'subir_calificaciones', 1, '2025-11-18 13:32:09'),
(3, 'aprobacion_estricta', 1, '2025-11-18 18:31:53'),
(4, 'verano', 1, '2025-11-19 15:29:20'),
(5, 'monitoreo_grupos', 1, '2025-11-18 21:29:13'),
(6, 'inscripcion_monitore', 1, '2025-11-19 20:57:56');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumno`
--

CREATE TABLE `alumno` (
  `id_alumno` int NOT NULL,
  `id_usuario` int DEFAULT NULL,
  `promedio` decimal(10,2) NOT NULL DEFAULT '0.00',
  `semestre` int NOT NULL DEFAULT '1',
  `especialidad` varchar(50) DEFAULT 'sin especificar',
  `id_especialidad` int NOT NULL DEFAULT '1',
  `año_inscripcion` datetime NOT NULL,
  `estado` enum('1','2','3','4') NOT NULL,
  `fecha_modificacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--


--
-- Disparadores `alumno`
--
DELIMITER $$
CREATE TRIGGER `before_update_alumno_estado` BEFORE UPDATE ON `alumno` FOR EACH ROW BEGIN
    IF OLD.estado != NEW.estado THEN
        SET NEW.fecha_modificacion = NOW();
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asignacion`
--

CREATE TABLE `asignacion` (
  `id_asignacion` int NOT NULL,
  `id_clase` int DEFAULT NULL,
  `id_alumno` int DEFAULT NULL,
  `oportunidad` enum('Ordinario','Recurse','Especial','Global') DEFAULT 'Ordinario',
  `semestre` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `asistencia` (
  `id_asistencia` int NOT NULL,
  `id_clase` int NOT NULL,
  `id_alumno` int NOT NULL,
  `fecha` date NOT NULL,
  `hora_clase` time NOT NULL,
  `estado_asistencia` enum('presente','ausente','justificado','tardanza') NOT NULL,
  `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP,
  `id_profesor` int NOT NULL,
  `comentario` text,
  `unidad` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `calificacion_clase` (
  `id_asignacion` int DEFAULT NULL,
  `unidad` int DEFAULT NULL,
  `calificacion` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `calificacion_clase`
--


CREATE TABLE `carrera` (
  `id_carrera` int NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `semestres` int NOT NULL,
  `creditos` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `chats` (
  `id_chat` int NOT NULL,
  `usuario1` int NOT NULL,
  `usuario2` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `chats`
--

-------------------------------

--
-- Estructura de tabla para la tabla `clase`
--

CREATE TABLE `clase` (
  `id_clase` int NOT NULL,
  `id_salon` int NOT NULL,
  `id_materia` int NOT NULL,
  `id_profesor` int NOT NULL,
  `capacidad` int NOT NULL DEFAULT '35',
  `periodo` varchar(100) DEFAULT NULL,
  `asignado` int NOT NULL DEFAULT '0',
  `activo` tinyint(1) DEFAULT '1',
  `grupo` varchar(15) DEFAULT NULL,
  `fecha_modificacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `clase`
--
--
-- Disparadores `clase`
--
DELIMITER $$
CREATE TRIGGER `before_update_clase_activo` BEFORE UPDATE ON `clase` FOR EACH ROW BEGIN
    IF OLD.activo != NEW.activo THEN
        SET NEW.fecha_modificacion = NOW();
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `coordinador`
--

CREATE TABLE `coordinador` (
  `id_coordinador` int NOT NULL,
  `id_usuario` int DEFAULT NULL,
  `id_carrera` int DEFAULT NULL,
  `sueldo` decimal(10,2) NOT NULL,
  `estado` enum('1','2','3','4') NOT NULL,
  `fecha_modificacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `coordinador`
--

--
-- Disparadores `coordinador`
--
DELIMITER $$
CREATE TRIGGER `before_update_coordinador_estado` BEFORE UPDATE ON `coordinador` FOR EACH ROW BEGIN
    IF OLD.estado != NEW.estado THEN
        SET NEW.fecha_modificacion = NOW();
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `entregas_tareas`
--

CREATE TABLE `entregas_tareas` (
  `id_entrega` int NOT NULL,
  `id_tarea` int NOT NULL,
  `id_alumno` int NOT NULL,
  `archivo_alumno` varchar(255) NOT NULL,
  `nombre_archivo_original` varchar(255) NOT NULL,
  `fecha_entrega` datetime DEFAULT CURRENT_TIMESTAMP,
  `comentario_alumno` text,
  `calificacion` decimal(5,2) DEFAULT NULL,
  `comentario_profesor` text,
  `fecha_calificacion` datetime DEFAULT NULL,
  `estado` enum('entregado','calificado','rechazado') DEFAULT 'entregado',
  `fecha_modificacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `entregas_tareas`
--

--
DELIMITER $$
CREATE TRIGGER `after_insert_entrega_notificacion` AFTER INSERT ON `entregas_tareas` FOR EACH ROW BEGIN
    DECLARE profesor_id INT;
    DECLARE alumno_nombre VARCHAR(100);
    DECLARE tarea_titulo VARCHAR(200);
    DECLARE materia_nombre VARCHAR(50);
    
    
    SELECT 
        p.id_profesor,
        CONCAT(u_alumno.nombre, ' ', u_alumno.apellidos),
        t.titulo,
        m.nombre
    INTO 
        profesor_id,
        alumno_nombre,
        tarea_titulo,
        materia_nombre
    FROM tareas t
    INNER JOIN clase c ON t.id_clase = c.id_clase
    INNER JOIN profesor p ON c.id_profesor = p.id_profesor
    INNER JOIN materia m ON c.id_materia = m.id_materia
    INNER JOIN alumno a ON NEW.id_alumno = a.id_alumno
    INNER JOIN usuario u_alumno ON a.id_usuario = u_alumno.id_usuario
    WHERE t.id_tarea = NEW.id_tarea;
    
    
    INSERT INTO notificaciones (id_usuario, titulo, mensaje)
    SELECT 
        u_prof.id_usuario,
        'Nueva entrega de tarea',
        CONCAT('El alumno ', alumno_nombre, ' ha entregado la tarea "', tarea_titulo, '" de ', materia_nombre)
    FROM profesor p
    INNER JOIN usuario u_prof ON p.id_usuario = u_prof.id_usuario
    WHERE p.id_profesor = profesor_id;
    
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_update_entregas_tareas_estado` BEFORE UPDATE ON `entregas_tareas` FOR EACH ROW BEGIN
    IF OLD.estado != NEW.estado THEN
        SET NEW.fecha_modificacion = NOW();
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `especialidad`
--

CREATE TABLE `especialidad` (
  `id_especialidad` int NOT NULL,
  `id_carrera` int NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text,
  `activo` tinyint DEFAULT '1',
  `fecha_modificacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `especialidad`
--
--
-- Disparadores `especialidad`
--
DELIMITER $$
CREATE TRIGGER `before_update_especialidad_activo` BEFORE UPDATE ON `especialidad` FOR EACH ROW BEGIN
    IF OLD.activo != NEW.activo THEN
        SET NEW.fecha_modificacion = NOW();
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horarios_clase`
--

CREATE TABLE `horarios_clase` (
  `id_clase` int NOT NULL,
  `dia` int NOT NULL,
  `hora` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `horarios_clase`
--



CREATE TABLE `likes_usuarios` (
  `id_like` int NOT NULL,
  `id_usuario` int NOT NULL,
  `id_noticia` int NOT NULL,
  `fecha_like` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `likes_usuarios`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materia`
--

CREATE TABLE `materia` (
  `id_materia` int NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `creditos` int NOT NULL,
  `unidades` int NOT NULL DEFAULT '5',
  `semestre_sugerido` int NOT NULL DEFAULT '1',
  `id_carrera` int NOT NULL,
  `id_especialidad` int DEFAULT '1',
  `id_prerrequisito` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `materia`
--


CREATE TABLE `materia_cursada` (
  `id_materia_cursada` int NOT NULL,
  `id_materia` int NOT NULL,
  `id_clase` int NOT NULL,
  `id_alumno` int NOT NULL,
  `cal_final` decimal(10,2) NOT NULL,
  `oportunidad` enum('ordinario','recursamiento','especial','global') DEFAULT NULL,
  `periodo` datetime DEFAULT NULL,
  `aprobado` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `materia_cursada`
--



CREATE TABLE `mensajes` (
  `id_mensaje` int NOT NULL,
  `id_chat` int NOT NULL,
  `id_usuario_envia` int NOT NULL,
  `mensaje` varchar(500) NOT NULL,
  `fecha_envio` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `leido` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `mensajes`
--


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `noticias`
--

CREATE TABLE `noticias` (
  `id_noticia` int NOT NULL,
  `titulo` varchar(100) NOT NULL,
  `info` varchar(1200) NOT NULL,
  `publicacion` datetime NOT NULL,
  `imagen` varchar(255) DEFAULT 'default.png',
  `id_usuario` int NOT NULL,
  `visitas` int DEFAULT '0',
  `likes` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `noticias`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id` int NOT NULL,
  `id_usuario` int NOT NULL,
  `titulo` varchar(100) NOT NULL,
  `mensaje` text NOT NULL,
  `leido` tinyint(1) DEFAULT '0',
  `fecha` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `notificaciones`
--
--
-- Estructura de tabla para la tabla `profesor`
--

CREATE TABLE `profesor` (
  `id_profesor` int NOT NULL,
  `id_usuario` int DEFAULT NULL,
  `sueldo` decimal(10,2) NOT NULL,
  `estado` enum('1','2','3','4') NOT NULL,
  `id_coordinador` int DEFAULT NULL,
  `fecha_modificacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `profesor`
--

--
-- Disparadores `profesor`
--
DELIMITER $$
CREATE TRIGGER `before_update_profesor_estado` BEFORE UPDATE ON `profesor` FOR EACH ROW BEGIN
    IF OLD.estado != NEW.estado THEN
        SET NEW.fecha_modificacion = NOW();
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `salon`
--

CREATE TABLE `salon` (
  `id_salon` int NOT NULL,
  `nombre` char(3) NOT NULL,
  `edificio` enum('A','B','TICS','E') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `salon`
--
-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tareas`
--

CREATE TABLE `tareas` (
  `id_tarea` int NOT NULL,
  `id_clase` int NOT NULL,
  `unidad` int NOT NULL DEFAULT '1',
  `id_profesor` int NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text,
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_limite` datetime NOT NULL,
  `puntos_maximos` decimal(5,2) NOT NULL DEFAULT '100.00',
  `archivo_profesor` varchar(255) DEFAULT NULL,
  `estado` enum('activa','cerrada','cancelada') DEFAULT 'activa',
  `fecha_modificacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `tareas`
--

-- Disparadores `tareas`
--
DELIMITER $$
CREATE TRIGGER `after_insert_tarea_notificacion` AFTER INSERT ON `tareas` FOR EACH ROW BEGIN
    DECLARE profesor_nombre VARCHAR(100);
    DECLARE materia_nombre VARCHAR(50);
    
    
    SELECT 
        CONCAT(u.nombre, ' ', u.apellidos),
        m.nombre
    INTO 
        profesor_nombre,
        materia_nombre
    FROM clase c
    INNER JOIN profesor p ON c.id_profesor = p.id_profesor
    INNER JOIN usuario u ON p.id_usuario = u.id_usuario
    INNER JOIN materia m ON c.id_materia = m.id_materia
    WHERE c.id_clase = NEW.id_clase;
    
    
    INSERT INTO notificaciones (id_usuario, titulo, mensaje)
    SELECT 
        u.id_usuario,
        'Nueva tarea asignada',
        CONCAT('El profesor ', profesor_nombre, ' ha asignado una nueva tarea: "', NEW.titulo, '" para ', materia_nombre, '. Fecha límite: ', DATE_FORMAT(NEW.fecha_limite, '%d/%m/%Y %H:%i'))
    FROM asignacion a
    INNER JOIN alumno al ON a.id_alumno = al.id_alumno
    INNER JOIN usuario u ON al.id_usuario = u.id_usuario
    WHERE a.id_clase = NEW.id_clase;
    
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_update_tareas_estado` BEFORE UPDATE ON `tareas` FOR EACH ROW BEGIN
    IF OLD.estado != NEW.estado THEN
        SET NEW.fecha_modificacion = NOW();
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_archivo_permitidos`
--

CREATE TABLE `tipos_archivo_permitidos` (
  `id_tipo` int NOT NULL,
  `extension` varchar(10) NOT NULL,
  `tipo_mime` varchar(100) NOT NULL,
  `descripcion` varchar(100) NOT NULL,
  `max_size_mb` int DEFAULT '10',
  `activo` tinyint(1) DEFAULT '1',
  `fecha_modificacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `tipos_archivo_permitidos`
--

INSERT INTO `tipos_archivo_permitidos` (`id_tipo`, `extension`, `tipo_mime`, `descripcion`, `max_size_mb`, `activo`, `fecha_modificacion`) VALUES
(1, 'pdf', 'application/pdf', 'Documento PDF', 20, 1, '2025-11-18 18:31:54'),
(2, 'doc', 'application/msword', 'Documento Word', 15, 1, '2025-11-18 18:31:54'),
(3, 'docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'Documento Word', 15, 1, '2025-11-18 18:31:54'),
(4, 'txt', 'text/plain', 'Archivo de texto', 5, 1, '2025-11-18 18:31:54'),
(5, 'zip', 'application/zip', 'Archivo comprimido', 50, 1, '2025-11-18 18:31:54'),
(6, 'rar', 'application/x-rar-compressed', 'Archivo comprimido RAR', 50, 1, '2025-11-18 18:31:54'),
(7, 'jpg', 'image/jpeg', 'Imagen JPEG', 10, 1, '2025-11-18 18:31:54'),
(8, 'jpeg', 'image/jpeg', 'Imagen JPEG', 10, 1, '2025-11-18 18:31:54'),
(9, 'png', 'image/png', 'Imagen PNG', 10, 1, '2025-11-18 18:31:54'),
(10, 'pdf', 'application/pdf', 'Documento PDF', 20, 1, '2025-11-18 18:31:54'),
(11, 'doc', 'application/msword', 'Documento Word', 15, 1, '2025-11-18 18:31:54'),
(12, 'docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'Documento Word', 15, 1, '2025-11-18 18:31:54'),
(13, 'txt', 'text/plain', 'Archivo de texto', 5, 1, '2025-11-18 18:31:54'),
(14, 'zip', 'application/zip', 'Archivo comprimido', 50, 1, '2025-11-18 18:31:54'),
(15, 'rar', 'application/x-rar-compressed', 'Archivo comprimido RAR', 50, 1, '2025-11-18 18:31:54'),
(16, 'jpg', 'image/jpeg', 'Imagen JPEG', 10, 1, '2025-11-18 18:31:54'),
(17, 'jpeg', 'image/jpeg', 'Imagen JPEG', 10, 1, '2025-11-18 18:31:54'),
(18, 'png', 'image/png', 'Imagen PNG', 10, 1, '2025-11-18 18:31:54'),
(19, 'pdf', 'application/pdf', 'Documento PDF', 20, 1, '2025-11-18 18:31:54'),
(20, 'doc', 'application/msword', 'Documento Word', 15, 1, '2025-11-18 18:31:54'),
(21, 'docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'Documento Word', 15, 1, '2025-11-18 18:31:54'),
(22, 'txt', 'text/plain', 'Archivo de texto', 5, 1, '2025-11-18 18:31:54'),
(23, 'zip', 'application/zip', 'Archivo comprimido', 50, 1, '2025-11-18 18:31:54'),
(24, 'rar', 'application/x-rar-compressed', 'Archivo comprimido RAR', 50, 1, '2025-11-18 18:31:54'),
(25, 'jpg', 'image/jpeg', 'Imagen JPEG', 10, 1, '2025-11-18 18:31:54'),
(26, 'jpeg', 'image/jpeg', 'Imagen JPEG', 10, 1, '2025-11-18 18:31:54'),
(27, 'png', 'image/png', 'Imagen PNG', 10, 1, '2025-11-18 18:31:54');

--
-- Disparadores `tipos_archivo_permitidos`
--
DELIMITER $$
CREATE TRIGGER `before_update_tipos_archivo_activo` BEFORE UPDATE ON `tipos_archivo_permitidos` FOR EACH ROW BEGIN
    IF OLD.activo != NEW.activo THEN
        SET NEW.fecha_modificacion = NOW();
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `id_usuario` int NOT NULL,
  `correo` varchar(100) NOT NULL,
  `contraseña` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `rol` enum('1','2','3') DEFAULT NULL,
  `foto` varchar(255) DEFAULT 'default.jpg',
  `clave` char(9) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `apellidos` varchar(60) NOT NULL,
  `fecha_nacimiento` datetime NOT NULL,
  `id_carrera` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `usuario`
--

-- Estructura de tabla para la tabla `variables_globales`
--

CREATE TABLE `variables_globales` (
  `id` int NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `valor` int NOT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `variables_globales`
--

INSERT INTO `variables_globales` (`id`, `nombre`, `valor`, `fecha_actualizacion`) VALUES
(1, 'calificacion_aprobatoria', 60, '2025-11-20 03:28:53'),
(2, 'semestre_asignacion_especialidad', 5, '2025-11-20 02:52:29'),
(3, 'semestres_maximos', 13, '2025-11-20 03:28:53');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `acciones`
--
ALTER TABLE `acciones`
  ADD PRIMARY KEY (`id_accion`),
  ADD UNIQUE KEY `accion` (`accion`);

--
-- Indices de la tabla `alumno`
--
ALTER TABLE `alumno`
  ADD PRIMARY KEY (`id_alumno`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `alumno_ibfk_2` (`id_especialidad`);

--
-- Indices de la tabla `asignacion`
--
ALTER TABLE `asignacion`
  ADD PRIMARY KEY (`id_asignacion`),
  ADD KEY `id_clase` (`id_clase`),
  ADD KEY `asignacion_ibfk_2` (`id_alumno`);

--
-- Indices de la tabla `asistencia`
--
ALTER TABLE `asistencia`
  ADD PRIMARY KEY (`id_asistencia`),
  ADD UNIQUE KEY `unique_asistencia_diaria` (`id_clase`,`id_alumno`,`fecha`),
  ADD KEY `idx_asistencia_clase` (`id_clase`),
  ADD KEY `idx_asistencia_alumno` (`id_alumno`),
  ADD KEY `idx_asistencia_fecha` (`fecha`),
  ADD KEY `idx_asistencia_profesor` (`id_profesor`);

--
-- Indices de la tabla `calificacion_clase`
--
ALTER TABLE `calificacion_clase`
  ADD KEY `id_asignacion` (`id_asignacion`);

--
-- Indices de la tabla `carrera`
--
ALTER TABLE `carrera`
  ADD PRIMARY KEY (`id_carrera`);

--
-- Indices de la tabla `chats`
--
ALTER TABLE `chats`
  ADD PRIMARY KEY (`id_chat`),
  ADD UNIQUE KEY `unique_chat` (`usuario1`,`usuario2`);

--
-- Indices de la tabla `clase`
--
ALTER TABLE `clase`
  ADD PRIMARY KEY (`id_clase`),
  ADD KEY `id_salon` (`id_salon`),
  ADD KEY `id_materia` (`id_materia`),
  ADD KEY `id_profesor` (`id_profesor`);

--
-- Indices de la tabla `coordinador`
--
ALTER TABLE `coordinador`
  ADD PRIMARY KEY (`id_coordinador`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_carrera` (`id_carrera`);

--
-- Indices de la tabla `entregas_tareas`
--
ALTER TABLE `entregas_tareas`
  ADD PRIMARY KEY (`id_entrega`),
  ADD UNIQUE KEY `unique_entrega` (`id_tarea`,`id_alumno`),
  ADD KEY `idx_entregas_tarea` (`id_tarea`),
  ADD KEY `idx_entregas_alumno` (`id_alumno`),
  ADD KEY `idx_entregas_estado` (`estado`);

--
-- Indices de la tabla `especialidad`
--
ALTER TABLE `especialidad`
  ADD PRIMARY KEY (`id_especialidad`),
  ADD KEY `id_carrera` (`id_carrera`);

--
-- Indices de la tabla `horarios_clase`
--
ALTER TABLE `horarios_clase`
  ADD KEY `id_clase` (`id_clase`);

--
-- Indices de la tabla `likes_usuarios`
--
ALTER TABLE `likes_usuarios`
  ADD PRIMARY KEY (`id_like`),
  ADD UNIQUE KEY `id_usuario` (`id_usuario`,`id_noticia`),
  ADD KEY `id_noticia` (`id_noticia`);

--
-- Indices de la tabla `materia`
--
ALTER TABLE `materia`
  ADD PRIMARY KEY (`id_materia`),
  ADD KEY `id_carrera` (`id_carrera`),
  ADD KEY `fk_materia_prerrequisito` (`id_prerrequisito`),
  ADD KEY `materia_ibfk_3` (`id_especialidad`);

--
-- Indices de la tabla `materia_cursada`
--
ALTER TABLE `materia_cursada`
  ADD PRIMARY KEY (`id_materia_cursada`),
  ADD KEY `id_materia` (`id_materia`),
  ADD KEY `id_clase` (`id_clase`),
  ADD KEY `materia_cursada_ibfk_1` (`id_alumno`);

--
-- Indices de la tabla `mensajes`
--
ALTER TABLE `mensajes`
  ADD PRIMARY KEY (`id_mensaje`),
  ADD KEY `id_chat` (`id_chat`);

--
-- Indices de la tabla `noticias`
--
ALTER TABLE `noticias`
  ADD PRIMARY KEY (`id_noticia`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `profesor`
--
ALTER TABLE `profesor`
  ADD PRIMARY KEY (`id_profesor`),
  ADD KEY `id_coordinador` (`id_coordinador`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `salon`
--
ALTER TABLE `salon`
  ADD PRIMARY KEY (`id_salon`);

--
-- Indices de la tabla `tareas`
--
ALTER TABLE `tareas`
  ADD PRIMARY KEY (`id_tarea`),
  ADD KEY `idx_tareas_clase` (`id_clase`),
  ADD KEY `idx_tareas_profesor` (`id_profesor`),
  ADD KEY `idx_tareas_fecha_limite` (`fecha_limite`);

--
-- Indices de la tabla `tipos_archivo_permitidos`
--
ALTER TABLE `tipos_archivo_permitidos`
  ADD PRIMARY KEY (`id_tipo`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `clave` (`clave`),
  ADD KEY `id_carrera` (`id_carrera`);

--
-- Indices de la tabla `variables_globales`
--
ALTER TABLE `variables_globales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `acciones`
--
ALTER TABLE `acciones`
  MODIFY `id_accion` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `asignacion`
--
ALTER TABLE `asignacion`
  MODIFY `id_asignacion` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=179;

--
-- AUTO_INCREMENT de la tabla `asistencia`
--
ALTER TABLE `asistencia`
  MODIFY `id_asistencia` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=135;

--
-- AUTO_INCREMENT de la tabla `carrera`
--
ALTER TABLE `carrera`
  MODIFY `id_carrera` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `chats`
--
ALTER TABLE `chats`
  MODIFY `id_chat` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `clase`
--
ALTER TABLE `clase`
  MODIFY `id_clase` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT de la tabla `entregas_tareas`
--
ALTER TABLE `entregas_tareas`
  MODIFY `id_entrega` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `especialidad`
--
ALTER TABLE `especialidad`
  MODIFY `id_especialidad` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `likes_usuarios`
--
ALTER TABLE `likes_usuarios`
  MODIFY `id_like` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de la tabla `materia`
--
ALTER TABLE `materia`
  MODIFY `id_materia` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT de la tabla `materia_cursada`
--
ALTER TABLE `materia_cursada`
  MODIFY `id_materia_cursada` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=439;

--
-- AUTO_INCREMENT de la tabla `mensajes`
--
ALTER TABLE `mensajes`
  MODIFY `id_mensaje` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT de la tabla `noticias`
--
ALTER TABLE `noticias`
  MODIFY `id_noticia` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9965;

--
-- AUTO_INCREMENT de la tabla `salon`
--
ALTER TABLE `salon`
  MODIFY `id_salon` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de la tabla `tareas`
--
ALTER TABLE `tareas`
  MODIFY `id_tarea` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de la tabla `tipos_archivo_permitidos`
--
ALTER TABLE `tipos_archivo_permitidos`
  MODIFY `id_tipo` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id_usuario` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=527;

--
-- AUTO_INCREMENT de la tabla `variables_globales`
--
ALTER TABLE `variables_globales`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `alumno`
--
ALTER TABLE `alumno`
  ADD CONSTRAINT `alumno_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`),
  ADD CONSTRAINT `alumno_ibfk_2` FOREIGN KEY (`id_especialidad`) REFERENCES `especialidad` (`id_especialidad`);

--
-- Filtros para la tabla `asignacion`
--
ALTER TABLE `asignacion`
  ADD CONSTRAINT `asignacion_ibfk_1` FOREIGN KEY (`id_clase`) REFERENCES `clase` (`id_clase`);

--
-- Filtros para la tabla `asistencia`
--
ALTER TABLE `asistencia`
  ADD CONSTRAINT `fk_asistencia_clase` FOREIGN KEY (`id_clase`) REFERENCES `clase` (`id_clase`),
  ADD CONSTRAINT `fk_asistencia_profesor` FOREIGN KEY (`id_profesor`) REFERENCES `profesor` (`id_profesor`);

--
-- Filtros para la tabla `calificacion_clase`
--
ALTER TABLE `calificacion_clase`
  ADD CONSTRAINT `calificacion_clase_ibfk_1` FOREIGN KEY (`id_asignacion`) REFERENCES `asignacion` (`id_asignacion`);

--
-- Filtros para la tabla `clase`
--
ALTER TABLE `clase`
  ADD CONSTRAINT `clase_ibfk_1` FOREIGN KEY (`id_salon`) REFERENCES `salon` (`id_salon`),
  ADD CONSTRAINT `clase_ibfk_2` FOREIGN KEY (`id_materia`) REFERENCES `materia` (`id_materia`),
  ADD CONSTRAINT `clase_ibfk_3` FOREIGN KEY (`id_profesor`) REFERENCES `profesor` (`id_profesor`);

--
-- Filtros para la tabla `coordinador`
--
ALTER TABLE `coordinador`
  ADD CONSTRAINT `coordinador_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`),
  ADD CONSTRAINT `coordinador_ibfk_2` FOREIGN KEY (`id_carrera`) REFERENCES `carrera` (`id_carrera`);

--
-- Filtros para la tabla `entregas_tareas`
--
ALTER TABLE `entregas_tareas`
  ADD CONSTRAINT `entregas_tareas_ibfk_1` FOREIGN KEY (`id_tarea`) REFERENCES `tareas` (`id_tarea`);

--
-- Filtros para la tabla `especialidad`
--
ALTER TABLE `especialidad`
  ADD CONSTRAINT `especialidad_ibfk_1` FOREIGN KEY (`id_carrera`) REFERENCES `carrera` (`id_carrera`);

--
-- Filtros para la tabla `horarios_clase`
--
ALTER TABLE `horarios_clase`
  ADD CONSTRAINT `horarios_clase_ibfk_1` FOREIGN KEY (`id_clase`) REFERENCES `clase` (`id_clase`);

--
-- Filtros para la tabla `likes_usuarios`
--
ALTER TABLE `likes_usuarios`
  ADD CONSTRAINT `likes_usuarios_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`),
  ADD CONSTRAINT `likes_usuarios_ibfk_2` FOREIGN KEY (`id_noticia`) REFERENCES `noticias` (`id_noticia`);

--
-- Filtros para la tabla `materia`
--
ALTER TABLE `materia`
  ADD CONSTRAINT `fk_materia_prerrequisito` FOREIGN KEY (`id_prerrequisito`) REFERENCES `materia` (`id_materia`),
  ADD CONSTRAINT `materia_ibfk_1` FOREIGN KEY (`id_carrera`) REFERENCES `carrera` (`id_carrera`),
  ADD CONSTRAINT `materia_ibfk_3` FOREIGN KEY (`id_especialidad`) REFERENCES `especialidad` (`id_especialidad`);

--
-- Filtros para la tabla `materia_cursada`
--
ALTER TABLE `materia_cursada`
  ADD CONSTRAINT `materia_cursada_ibfk_2` FOREIGN KEY (`id_materia`) REFERENCES `materia` (`id_materia`),
  ADD CONSTRAINT `materia_cursada_ibfk_3` FOREIGN KEY (`id_clase`) REFERENCES `clase` (`id_clase`);

--
-- Filtros para la tabla `mensajes`
--
ALTER TABLE `mensajes`
  ADD CONSTRAINT `mensajes_ibfk_1` FOREIGN KEY (`id_chat`) REFERENCES `chats` (`id_chat`);

--
-- Filtros para la tabla `noticias`
--
ALTER TABLE `noticias`
  ADD CONSTRAINT `noticias_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `profesor`
--
ALTER TABLE `profesor`
  ADD CONSTRAINT `profesor_ibfk_1` FOREIGN KEY (`id_coordinador`) REFERENCES `coordinador` (`id_coordinador`),
  ADD CONSTRAINT `profesor_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `tareas`
--
ALTER TABLE `tareas`
  ADD CONSTRAINT `tareas_ibfk_1` FOREIGN KEY (`id_clase`) REFERENCES `clase` (`id_clase`),
  ADD CONSTRAINT `tareas_ibfk_2` FOREIGN KEY (`id_profesor`) REFERENCES `profesor` (`id_profesor`);

--
-- Filtros para la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD CONSTRAINT `usuario_ibfk_1` FOREIGN KEY (`id_carrera`) REFERENCES `carrera` (`id_carrera`);
COMMIT;

-- 1. Tabla para el portafolio electrónico del profesor
CREATE TABLE `portafolio_profesor` (
  `id_portafolio` int NOT NULL AUTO_INCREMENT,
  `id_profesor` int NOT NULL,
  `tipo_documento` enum('certificado_universitario','preparatoria','curso','diploma','otro') NOT NULL,
  `nombre_documento` varchar(255) NOT NULL,
  `ruta_archivo` varchar(500) NOT NULL,
  `fecha_emision` date DEFAULT NULL,
  `institucion` varchar(200) DEFAULT NULL,
  `descripcion` text,
  `fecha_subida` datetime DEFAULT CURRENT_TIMESTAMP,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_portafolio`),
  KEY `idx_portafolio_profesor` (`id_profesor`),
  KEY `idx_portafolio_tipo` (`tipo_documento`),
  CONSTRAINT `fk_portafolio_profesor` FOREIGN KEY (`id_profesor`) REFERENCES `profesor` (`id_profesor`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

------------------------------------------------------------------------
-- TABLAS AUN SIN IMPLEMENTAR
------------------------------------------------------------------------

-- TABLA ACADEMIA - VINCULADA CON CARRERA O ESPECIALIDAD
CREATE TABLE `academia` (
  `id_academia` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `id_carrera` int DEFAULT NULL,      -- Puede ser NULL si se vincula con especialidad
  `id_especialidad` int DEFAULT NULL,  -- Puede ser NULL si se vincula con carrera
  `id_presidente` int NOT NULL,
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `activo` tinyint(1) DEFAULT '1',
  `fecha_modificacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_academia`),
  UNIQUE KEY `unique_academia_nombre` (`nombre`),
  KEY `idx_academia_carrera` (`id_carrera`),
  KEY `idx_academia_especialidad` (`id_especialidad`),
  KEY `idx_academia_presidente` (`id_presidente`),
  -- Restricción: debe tener al menos una vinculación (carrera O especialidad)
  CONSTRAINT `chk_academia_vinculacion` CHECK (
    (id_carrera IS NOT NULL AND id_especialidad IS NULL) OR 
    (id_carrera IS NULL AND id_especialidad IS NOT NULL)
  ),
  CONSTRAINT `fk_academia_carrera` FOREIGN KEY (`id_carrera`) REFERENCES `carrera` (`id_carrera`),
  CONSTRAINT `fk_academia_especialidad` FOREIGN KEY (`id_especialidad`) REFERENCES `especialidad` (`id_especialidad`),
  CONSTRAINT `fk_academia_presidente` FOREIGN KEY (`id_presidente`) REFERENCES `profesor` (`id_profesor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 3. Tabla de relación Profesor-Academia (muchos a muchos)
CREATE TABLE `profesor_academia` (
  `id_profesor_academia` int NOT NULL AUTO_INCREMENT,
  `id_profesor` int NOT NULL,
  `id_academia` int NOT NULL,
  `rol` enum('miembro','secretario','vicepresidente') DEFAULT 'miembro',
  `fecha_ingreso` datetime DEFAULT CURRENT_TIMESTAMP,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_profesor_academia`),
  UNIQUE KEY `unique_profesor_academia` (`id_profesor`, `id_academia`),
  KEY `idx_profesor_academia_profesor` (`id_profesor`),
  KEY `idx_profesor_academia_academia` (`id_academia`),
  CONSTRAINT `fk_profesor_academia_profesor` FOREIGN KEY (`id_profesor`) REFERENCES `profesor` (`id_profesor`) ON DELETE CASCADE,
  CONSTRAINT `fk_profesor_academia_academia` FOREIGN KEY (`id_academia`) REFERENCES `academia` (`id_academia`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 4. Tabla de Tareas de Academia
CREATE TABLE `tareas_academia` (
  `id_tarea_academia` int NOT NULL AUTO_INCREMENT,
  `id_academia` int NOT NULL,
  `id_profesor_asigna` int NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text,
  `tipo_tarea` enum('avance_grupo','informe','revision','otro') NOT NULL,
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_limite` datetime NOT NULL,
  `archivo_adjunto` varchar(255) DEFAULT NULL,
  `estado` enum('activa','cerrada','cancelada') DEFAULT 'activa',
  `fecha_modificacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_tarea_academia`),
  KEY `idx_tareas_academia_academia` (`id_academia`),
  KEY `idx_tareas_academia_profesor` (`id_profesor_asigna`),
  KEY `idx_tareas_academia_fecha` (`fecha_limite`),
  CONSTRAINT `fk_tareas_academia_academia` FOREIGN KEY (`id_academia`) REFERENCES `academia` (`id_academia`),
  CONSTRAINT `fk_tareas_academia_profesor` FOREIGN KEY (`id_profesor_asigna`) REFERENCES `profesor` (`id_profesor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 5. Tabla de Entregas de Tareas de Academia
CREATE TABLE `entregas_tareas_academia` (
  `id_entrega_academia` int NOT NULL AUTO_INCREMENT,
  `id_tarea_academia` int NOT NULL,
  `id_profesor_entrega` int NOT NULL,
  `archivo_entrega` varchar(255) NOT NULL,
  `nombre_archivo_original` varchar(255) NOT NULL,
  `comentario_entrega` text,
  `fecha_entrega` datetime DEFAULT CURRENT_TIMESTAMP,
  `calificacion` decimal(5,2) DEFAULT NULL,
  `comentario_evaluador` text,
  `fecha_calificacion` datetime DEFAULT NULL,
  `estado` enum('entregado','calificado','rechazado') DEFAULT 'entregado',
  `fecha_modificacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_entrega_academia`),
  UNIQUE KEY `unique_entrega_academia` (`id_tarea_academia`, `id_profesor_entrega`),
  KEY `idx_entregas_academia_tarea` (`id_tarea_academia`),
  KEY `idx_entregas_academia_profesor` (`id_profesor_entrega`),
  CONSTRAINT `fk_entregas_academia_tarea` FOREIGN KEY (`id_tarea_academia`) REFERENCES `tareas_academia` (`id_tarea_academia`),
  CONSTRAINT `fk_entregas_academia_profesor` FOREIGN KEY (`id_profesor_entrega`) REFERENCES `profesor` (`id_profesor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 6. Tabla de Grupos
CREATE TABLE `grupo` (
  `id_grupo` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `id_carrera` int NOT NULL,
  `id_especialidad` int DEFAULT NULL,
  `semestre` int NOT NULL,
  `capacidad_maxima` int DEFAULT '40',
  `tutor_asignado` int DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `activo` tinyint(1) DEFAULT '1',
  `fecha_modificacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_grupo`),
  UNIQUE KEY `unique_grupo_nombre` (`nombre`),
  KEY `idx_grupo_carrera` (`id_carrera`),
  KEY `idx_grupo_especialidad` (`id_especialidad`),
  KEY `idx_grupo_tutor` (`tutor_asignado`),
  CONSTRAINT `fk_grupo_carrera` FOREIGN KEY (`id_carrera`) REFERENCES `carrera` (`id_carrera`),
  CONSTRAINT `fk_grupo_especialidad` FOREIGN KEY (`id_especialidad`) REFERENCES `especialidad` (`id_especialidad`),
  CONSTRAINT `fk_grupo_tutor` FOREIGN KEY (`tutor_asignado`) REFERENCES `profesor` (`id_profesor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 7. Tabla de relación Alumno-Grupo
CREATE TABLE `alumno_grupo` (
  `id_alumno_grupo` int NOT NULL AUTO_INCREMENT,
  `id_alumno` int NOT NULL,
  `id_grupo` int NOT NULL,
  `fecha_ingreso` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_salida` datetime DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_alumno_grupo`),
  UNIQUE KEY `unique_alumno_grupo_activo` (`id_alumno`, `activo`),
  KEY `idx_alumno_grupo_alumno` (`id_alumno`),
  KEY `idx_alumno_grupo_grupo` (`id_grupo`),
  CONSTRAINT `fk_alumno_grupo_alumno` FOREIGN KEY (`id_alumno`) REFERENCES `alumno` (`id_alumno`) ON DELETE CASCADE,
  CONSTRAINT `fk_alumno_grupo_grupo` FOREIGN KEY (`id_grupo`) REFERENCES `grupo` (`id_grupo`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 8. Actualizar tabla usuario para agregar rol de Tutor
ALTER TABLE `usuario` 
MODIFY `rol` ENUM('1','2','3','4') DEFAULT NULL COMMENT '1=Admin, 2=Profesor, 3=Alumno, 4=Tutor';

-- 9. Tabla para Tutores (padres/encargados)
CREATE TABLE `tutor` (
  `id_tutor` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int DEFAULT NULL,
  `parentesco` enum('padre','madre','tutor_legal','encargado','otro') NOT NULL,
  `telefono_contacto` varchar(15) DEFAULT NULL,
  `direccion` text,
  `estado` enum('1','2','3','4') NOT NULL DEFAULT '1',
  `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_tutor`),
  KEY `idx_tutor_usuario` (`id_usuario`),
  CONSTRAINT `fk_tutor_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 10. Tabla de relación Tutor-Alumno
CREATE TABLE `tutor_alumno` (
  `id_tutor_alumno` int NOT NULL AUTO_INCREMENT,
  `id_tutor` int NOT NULL,
  `id_alumno` int NOT NULL,
  `relacion` enum('hijo','hija','tutelado','otro') DEFAULT 'hijo',
  `es_principal` tinyint(1) DEFAULT '1',
  `fecha_asignacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_tutor_alumno`),
  UNIQUE KEY `unique_tutor_alumno` (`id_tutor`, `id_alumno`),
  KEY `idx_tutor_alumno_tutor` (`id_tutor`),
  KEY `idx_tutor_alumno_alumno` (`id_alumno`),
  CONSTRAINT `fk_tutor_alumno_tutor` FOREIGN KEY (`id_tutor`) REFERENCES `tutor` (`id_tutor`) ON DELETE CASCADE,
  CONSTRAINT `fk_tutor_alumno_alumno` FOREIGN KEY (`id_alumno`) REFERENCES `alumno` (`id_alumno`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Triggers para mantener la integridad de datos

-- Trigger para actualizar fecha_modificacion en academia
DELIMITER $$
CREATE TRIGGER `before_update_academia_activo` BEFORE UPDATE ON `academia` FOR EACH ROW 
BEGIN
    IF OLD.activo != NEW.activo THEN
        SET NEW.fecha_modificacion = NOW();
    END IF;
END$$
DELIMITER ;

-- Trigger para notificaciones de tareas de academia
DELIMITER $$
CREATE TRIGGER `after_insert_tarea_academia_notificacion` AFTER INSERT ON `tareas_academia` FOR EACH ROW 
BEGIN
    DECLARE presidente_nombre VARCHAR(100);
    DECLARE academia_nombre VARCHAR(100);
    
    -- Obtener información del presidente y academia
    SELECT 
        CONCAT(u.nombre, ' ', u.apellidos),
        a.nombre
    INTO 
        presidente_nombre,
        academia_nombre
    FROM academia a
    INNER JOIN profesor p ON a.id_presidente = p.id_profesor
    INNER JOIN usuario u ON p.id_usuario = u.id_usuario
    WHERE a.id_academia = NEW.id_academia;
    
    -- Insertar notificaciones para todos los miembros de la academia
    INSERT INTO notificaciones (id_usuario, titulo, mensaje)
    SELECT 
        u.id_usuario,
        'Nueva tarea de academia',
        CONCAT('El presidente ', presidente_nombre, ' ha asignado una nueva tarea en la academia ', academia_nombre, ': "', NEW.titulo, '". Fecha límite: ', DATE_FORMAT(NEW.fecha_limite, '%d/%m/%Y %H:%i'))
    FROM profesor_academia pa
    INNER JOIN profesor p ON pa.id_profesor = p.id_profesor
    INNER JOIN usuario u ON p.id_usuario = u.id_usuario
    WHERE pa.id_academia = NEW.id_academia AND pa.activo = 1;
    
END$$
DELIMITER ;

-- Trigger para actualizar estado de tareas de academia vencidas
DELIMITER $$
CREATE TRIGGER `actualizar_tareas_academia_vencidas` BEFORE UPDATE ON `tareas_academia` FOR EACH ROW 
BEGIN
    IF OLD.estado = 'activa' AND NEW.fecha_limite < NOW() THEN
        SET NEW.estado = 'cerrada';
    END IF;
END$$
DELIMITER ;

-- Procedimiento para actualizar tareas de academia vencidas
DELIMITER $$
CREATE PROCEDURE `actualizar_tareas_academia_vencidas_proc`()
BEGIN
    UPDATE tareas_academia 
    SET estado = 'cerrada' 
    WHERE estado = 'activa' 
    AND fecha_limite < NOW();
END$$
DELIMITER ;