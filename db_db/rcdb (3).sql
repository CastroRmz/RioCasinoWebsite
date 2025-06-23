-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 23-06-2025 a las 20:36:51
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `rcdb`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cargos`
--

CREATE TABLE `cargos` (
  `Cargo_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cargos`
--

INSERT INTO `cargos` (`Cargo_id`, `nombre`) VALUES
(1, 'Runner'),
(2, 'Jefe de Sala'),
(3, 'Cajeras'),
(4, 'Practiacante de Sistemas'),
(6, 'Técnico de Sistemas'),
(7, 'Gerente'),
(8, 'Boveda');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `departamentos`
--

CREATE TABLE `departamentos` (
  `departamento_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `departamentos`
--

INSERT INTO `departamentos` (`departamento_id`, `nombre`) VALUES
(1, 'Sistemas'),
(2, 'Área de Cajas'),
(4, 'S/N'),
(6, 'ISLA 1'),
(7, 'ISLA 2'),
(8, 'ISLA 3'),
(9, 'ISLA 4');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empleados`
--

CREATE TABLE `empleados` (
  `empleado_id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `cargoID` int(11) NOT NULL,
  `departamentoID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `empleados`
--

INSERT INTO `empleados` (`empleado_id`, `nombre`, `cargoID`, `departamentoID`) VALUES
(1, 'Christian Antonio Castro Ramirez', 4, 1),
(2, 'Jose luis Garcia Cedillo', 6, 1),
(3, 'Roman de Jesus Estrada Alvarez', 6, 1),
(4, 'Juan Manuel Barrios', 2, 4);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_maquinas`
--

CREATE TABLE `historial_maquinas` (
  `id` int(11) NOT NULL,
  `idmac` int(11) NOT NULL,
  `descripcion` text NOT NULL,
  `fecha_cambio` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario` varchar(100) DEFAULT 'SuperAdmin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `historial_maquinas`
--

INSERT INTO `historial_maquinas` (`id`, `idmac`, `descripcion`, `fecha_cambio`, `usuario`) VALUES
(1, 1, 'Máquina editada', '2025-06-19 19:17:51', 'SuperAdmin'),
(2, 2, 'Máquina creada', '2025-06-19 21:27:57', 'SuperAdmin');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mapas`
--

CREATE TABLE `mapas` (
  `id` int(11) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `nombre_mostrar` varchar(255) DEFAULT NULL,
  `fecha_subida` datetime DEFAULT current_timestamp(),
  `usuario` varchar(100) DEFAULT 'SuperAdmin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `mapas`
--

INSERT INTO `mapas` (`id`, `nombre_archivo`, `nombre_mostrar`, `fecha_subida`, `usuario`) VALUES
(3, 'mapa_1750696112.png', 'mapa', '2025-06-23 09:28:32', 'SuperAdmin');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `maquinas`
--

CREATE TABLE `maquinas` (
  `idmac` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `marca` varchar(100) DEFAULT NULL,
  `numero_serie` varchar(100) DEFAULT NULL,
  `ebox_mac` varchar(100) DEFAULT NULL,
  `estado` enum('Alta','Baja','Proceso') DEFAULT 'Alta',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario` varchar(100) DEFAULT 'SuperAdmin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `maquinas`
--

INSERT INTO `maquinas` (`idmac`, `nombre`, `modelo`, `marca`, `numero_serie`, `ebox_mac`, `estado`, `fecha_creacion`, `usuario`) VALUES
(1, '022-WONDER DREAMS', 'WONDER DREAMS', 'ZITRO', '123456789', '001B-EB22-4085', 'Alta', '2025-06-19 17:18:48', 'admin'),
(2, '023-WONDER DREAMS', 'WONDER DREAMS', 'ZITRO', '789456123', '001B-EB22-4F85', 'Alta', '2025-06-19 21:27:57', 'SuperAdmin');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `empleado_id` int(11) NOT NULL,
  `numero_serie` varchar(100) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` enum('Abierto','En proceso','Cerrado') DEFAULT 'Abierto',
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `fecha_proceso` datetime DEFAULT NULL,
  `fecha_cierre` datetime DEFAULT NULL,
  `asignado_a` int(11) DEFAULT NULL,
  `creado_por` int(11) NOT NULL,
  `comentarios` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tickets`
--

INSERT INTO `tickets` (`id`, `empleado_id`, `numero_serie`, `descripcion`, `estado`, `fecha_creacion`, `fecha_proceso`, `fecha_cierre`, `asignado_a`, `creado_por`, `comentarios`) VALUES
(1, 4, '001B-EB22-4F85', 'nin', 'Abierto', '2025-06-19 16:50:29', NULL, NULL, NULL, 4, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `Usuario_id` int(11) NOT NULL,
  `usuario` varchar(100) DEFAULT NULL,
  `Correo` varchar(100) NOT NULL,
  `Contrasena` varchar(255) NOT NULL,
  `Rol` enum('SuperAdmin','Admin','Usuario') NOT NULL DEFAULT 'Usuario'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`Usuario_id`, `usuario`, `Correo`, `Contrasena`, `Rol`) VALUES
(4, 'Admin1', 'Admin1@gmail.com', '$2y$10$qt4Ng1R31p4UkOTryMWj4uxLe9dKikhRaKbBgE.xXVaCrNFBF7LzC', 'SuperAdmin'),
(5, 'Admin2', 'Admin2@gmail.com', '$2y$10$wjrC8gp2hWzVEVyrST92O.EFbO4KCXi4WFq/xXJVgi.kGIP95mJXK', 'Usuario'),
(6, 'JL_Casino', 'JLprueba@outlook.com', '$2y$10$EL7u95lmGb6OrnMer8G.zeJOdoCIVCjw/uwQsgMLadTVCefveGEmq', 'Admin');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `cargos`
--
ALTER TABLE `cargos`
  ADD PRIMARY KEY (`Cargo_id`);

--
-- Indices de la tabla `departamentos`
--
ALTER TABLE `departamentos`
  ADD PRIMARY KEY (`departamento_id`);

--
-- Indices de la tabla `empleados`
--
ALTER TABLE `empleados`
  ADD PRIMARY KEY (`empleado_id`),
  ADD KEY `cargo_id_id` (`cargoID`),
  ADD KEY `departamento_id_id` (`departamentoID`);

--
-- Indices de la tabla `historial_maquinas`
--
ALTER TABLE `historial_maquinas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idmac` (`idmac`);

--
-- Indices de la tabla `mapas`
--
ALTER TABLE `mapas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `maquinas`
--
ALTER TABLE `maquinas`
  ADD PRIMARY KEY (`idmac`);

--
-- Indices de la tabla `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `empleado_id` (`empleado_id`),
  ADD KEY `asignado_a` (`asignado_a`),
  ADD KEY `creado_por` (`creado_por`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`Usuario_id`),
  ADD UNIQUE KEY `correo` (`Correo`),
  ADD UNIQUE KEY `Nombre` (`usuario`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `cargos`
--
ALTER TABLE `cargos`
  MODIFY `Cargo_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `departamentos`
--
ALTER TABLE `departamentos`
  MODIFY `departamento_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `empleados`
--
ALTER TABLE `empleados`
  MODIFY `empleado_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `historial_maquinas`
--
ALTER TABLE `historial_maquinas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `mapas`
--
ALTER TABLE `mapas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `maquinas`
--
ALTER TABLE `maquinas`
  MODIFY `idmac` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `Usuario_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `empleados`
--
ALTER TABLE `empleados`
  ADD CONSTRAINT `empleados_ibfk_1` FOREIGN KEY (`cargoID`) REFERENCES `cargos` (`Cargo_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `empleados_ibfk_2` FOREIGN KEY (`departamentoID`) REFERENCES `departamentos` (`departamento_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `historial_maquinas`
--
ALTER TABLE `historial_maquinas`
  ADD CONSTRAINT `historial_maquinas_ibfk_1` FOREIGN KEY (`idmac`) REFERENCES `maquinas` (`idmac`) ON DELETE CASCADE;

--
-- Filtros para la tabla `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`empleado_id`),
  ADD CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`asignado_a`) REFERENCES `usuarios` (`Usuario_id`),
  ADD CONSTRAINT `tickets_ibfk_3` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`Usuario_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
