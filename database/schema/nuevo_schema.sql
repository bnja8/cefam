-- Esquema completo de Farmacia Asistencial y tablas de soporte Laravel.
-- Solo contiene estructura; no incluye datos de negocio ni el historial de migraciones.
-- Importar en una base vacia y ejecutar `php artisan migrate` para registrar las migraciones.

SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE `alertas` (
  `alerta_id` bigint NOT NULL AUTO_INCREMENT,
  `tipo_alerta` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prioridad` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `producto_id` bigint DEFAULT NULL,
  `lote_id` bigint DEFAULT NULL,
  `ubicacion_id` bigint DEFAULT NULL,
  `responsable_rut` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clave_deduplicacion` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reconocida_por_rut` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_generacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `nivel_actual` decimal(12,2) DEFAULT NULL,
  `mensaje` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `atendida` tinyint(1) NOT NULL DEFAULT '0',
  `atendida_por_rut` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `atendida_at` timestamp NULL DEFAULT NULL,
  `resuelta_at` timestamp NULL DEFAULT NULL,
  `reconocida_en` timestamp NULL DEFAULT NULL,
  `detectada_en` timestamp NULL DEFAULT NULL,
  `ultima_evaluacion_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`alerta_id`),
  KEY `fk_alertas_producto_id` (`producto_id`),
  KEY `fk_alertas_lote_id` (`lote_id`),
  KEY `fk_alertas_ubicacion_id` (`ubicacion_id`),
  KEY `fk_alertas_atendida_por_rut` (`atendida_por_rut`),
  KEY `idx_alertas_producto_ubicacion_estado` (`tipo_alerta`,`producto_id`,`ubicacion_id`,`atendida`),
  KEY `idx_alertas_resuelta_fecha` (`resuelta_at`,`fecha_generacion`),
  KEY `idx_alertas_resuelta_responsable_fecha` (`resuelta_at`,`responsable_rut`,`fecha_generacion`),
  KEY `idx_alertas_tipo_resuelta_atendida` (`tipo_alerta`,`resuelta_at`,`atendida`),
  CONSTRAINT `fk_alertas_atendida_por_rut` FOREIGN KEY (`atendida_por_rut`) REFERENCES `usuarios` (`rut`),
  CONSTRAINT `fk_alertas_lote_id` FOREIGN KEY (`lote_id`) REFERENCES `lotes` (`lote_id`),
  CONSTRAINT `fk_alertas_producto_id` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`producto_id`),
  CONSTRAINT `fk_alertas_ubicacion_id` FOREIGN KEY (`ubicacion_id`) REFERENCES `ubicaciones` (`ubicacion_id`),
  CONSTRAINT `alertas_tipo_alerta_check` CHECK ((`tipo_alerta` in (_utf8mb4'STOCK_CRITICO',_utf8mb4'VENCIMIENTO',_utf8mb4'DESCUADRE_CONTROLADO',_utf8mb4'SIN_EXISTENCIAS',_utf8mb4'STOCK_BAJO',_utf8mb4'VENCIDO')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `auditoria` (
  `auditoria_id` bigint NOT NULL AUTO_INCREMENT,
  `usuario_rut` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_hora` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `accion` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidad` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `registro_id` bigint DEFAULT NULL,
  `resultado` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detalle` json DEFAULT NULL,
  `ip_origen` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`auditoria_id`),
  KEY `fk_auditoria_usuario_rut` (`usuario_rut`),
  KEY `idx_auditoria_fecha` (`fecha_hora`),
  CONSTRAINT `fk_auditoria_usuario_rut` FOREIGN KEY (`usuario_rut`) REFERENCES `usuarios` (`rut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `carros` (
  `carro_id` bigint NOT NULL AUTO_INCREMENT,
  `ubicacion_id` bigint NOT NULL,
  `tipo_carro` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`carro_id`),
  UNIQUE KEY `ubicacion_id` (`ubicacion_id`),
  UNIQUE KEY `nombre` (`nombre`),
  CONSTRAINT `fk_carros_ubicacion_id` FOREIGN KEY (`ubicacion_id`) REFERENCES `ubicaciones` (`ubicacion_id`),
  CONSTRAINT `carros_chk_1` CHECK ((`tipo_carro` in (_utf8mb4'PARO',_utf8mb4'UNIDOSIS')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `categorias_producto` (
  `categoria_id` bigint NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`categoria_id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cuotas_paciente` (
  `cuota_id` bigint NOT NULL AUTO_INCREMENT,
  `paciente_programa_id` bigint NOT NULL,
  `producto_id` bigint NOT NULL,
  `periodo_inicio` date NOT NULL,
  `periodo_termino` date NOT NULL,
  `cantidad_autorizada` decimal(12,2) NOT NULL,
  `cantidad_utilizada` decimal(12,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`cuota_id`),
  KEY `fk_cuotas_paciente_paciente_programa_id` (`paciente_programa_id`),
  KEY `fk_cuotas_paciente_producto_id` (`producto_id`),
  CONSTRAINT `fk_cuotas_paciente_paciente_programa_id` FOREIGN KEY (`paciente_programa_id`) REFERENCES `paciente_programa` (`paciente_programa_id`),
  CONSTRAINT `fk_cuotas_paciente_producto_id` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`producto_id`),
  CONSTRAINT `cuotas_paciente_chk_1` CHECK ((`periodo_termino` >= `periodo_inicio`)),
  CONSTRAINT `cuotas_paciente_chk_2` CHECK ((`cantidad_autorizada` >= 0)),
  CONSTRAINT `cuotas_paciente_chk_3` CHECK ((`cantidad_utilizada` >= 0)),
  CONSTRAINT `cuotas_paciente_chk_4` CHECK ((`cantidad_utilizada` <= `cantidad_autorizada`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `entrega_detalle` (
  `entrega_detalle_id` bigint NOT NULL AUTO_INCREMENT,
  `entrega_id` bigint NOT NULL,
  `lote_id` bigint NOT NULL,
  `producto_id` bigint NOT NULL,
  `cantidad` decimal(12,2) NOT NULL,
  `cuota_id` bigint DEFAULT NULL,
  `dosis` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `horario` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `indicaciones` text COLLATE utf8mb4_unicode_ci,
  `producto_nombre_snapshot` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unidad_base_codigo_snapshot` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero_lote_snapshot` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_vencimiento_lote_snapshot` date DEFAULT NULL,
  PRIMARY KEY (`entrega_detalle_id`),
  KEY `fk_entrega_detalle_entrega_id` (`entrega_id`),
  KEY `fk_entrega_detalle_lote_id` (`lote_id`),
  KEY `fk_entrega_detalle_cuota_id` (`cuota_id`),
  KEY `idx_entrega_detalle_producto` (`producto_id`),
  CONSTRAINT `fk_entrega_detalle_cuota_id` FOREIGN KEY (`cuota_id`) REFERENCES `cuotas_paciente` (`cuota_id`),
  CONSTRAINT `fk_entrega_detalle_entrega_id` FOREIGN KEY (`entrega_id`) REFERENCES `entregas` (`entrega_id`),
  CONSTRAINT `fk_entrega_detalle_lote_id` FOREIGN KEY (`lote_id`) REFERENCES `lotes` (`lote_id`),
  CONSTRAINT `fk_entrega_detalle_producto_id` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`producto_id`),
  CONSTRAINT `entrega_detalle_chk_1` CHECK ((`cantidad` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `entregas` (
  `entrega_id` bigint NOT NULL AUTO_INCREMENT,
  `paciente_rut` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ficha_id` bigint unsigned DEFAULT NULL,
  `persona_autorizada_id` bigint DEFAULT NULL,
  `receta_id` bigint DEFAULT NULL,
  `usuario_rut` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_hora` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CONFIRMADA',
  `estado_ficha` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RECIENTE',
  `observacion` text COLLATE utf8mb4_unicode_ci,
  `receptor_tipo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PACIENTE',
  `receptor_eventual_rut` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receptor_eventual_nombre` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receptor_eventual_apellido_paterno` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receptor_eventual_apellido_materno` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receptor_eventual_vinculo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paciente_nombre_snapshot` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `persona_autorizada_nombre_snapshot` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `persona_autorizada_rut_snapshot` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`entrega_id`),
  KEY `fk_entregas_persona_autorizada_id` (`persona_autorizada_id`),
  KEY `fk_entregas_receta_id` (`receta_id`),
  KEY `fk_entregas_usuario_rut` (`usuario_rut`),
  KEY `idx_entregas_paciente_fecha` (`paciente_rut`,`fecha_hora`),
  KEY `idx_entregas_estado_fecha` (`estado`,`fecha_hora`),
  KEY `entregas_estado_ficha_fecha_idx` (`estado_ficha`,`fecha_hora`),
  KEY `entregas_ficha_id_foreign` (`ficha_id`),
  CONSTRAINT `fk_entregas_paciente_rut` FOREIGN KEY (`paciente_rut`) REFERENCES `pacientes` (`rut`),
  CONSTRAINT `entregas_ficha_id_foreign` FOREIGN KEY (`ficha_id`) REFERENCES `fichas_pacientes` (`ficha_id`),
  CONSTRAINT `fk_entregas_receta_id` FOREIGN KEY (`receta_id`) REFERENCES `recetas` (`receta_id`),
  CONSTRAINT `fk_entregas_persona_autorizada_id` FOREIGN KEY (`persona_autorizada_id`) REFERENCES `personas_autorizadas` (`persona_autorizada_id`),
  CONSTRAINT `fk_entregas_usuario_rut` FOREIGN KEY (`usuario_rut`) REFERENCES `usuarios` (`rut`),
  CONSTRAINT `entregas_chk_1` CHECK ((`estado` in (_utf8mb4'PENDIENTE',_utf8mb4'CONFIRMADA',_utf8mb4'RECHAZADA',_utf8mb4'ANULADA')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `formas_farmaceuticas` (
  `forma_farmaceutica_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`forma_farmaceutica_id`),
  UNIQUE KEY `formas_farmaceuticas_nombre_unique` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `historial_alertas` (
  `historial_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `alerta_id` bigint NOT NULL,
  `usuario_rut` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_evento` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado_anterior` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado_nuevo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responsable_anterior_rut` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responsable_nuevo_rut` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `movimiento_id` bigint unsigned DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `datos` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`historial_id`),
  KEY `historial_alertas_alerta_id_created_at_index` (`alerta_id`,`created_at`),
  KEY `historial_alertas_usuario_rut_foreign` (`usuario_rut`),
  KEY `historial_alertas_responsable_anterior_rut_foreign` (`responsable_anterior_rut`),
  KEY `historial_alertas_responsable_nuevo_rut_foreign` (`responsable_nuevo_rut`),
  CONSTRAINT `historial_alertas_alerta_id_foreign` FOREIGN KEY (`alerta_id`) REFERENCES `alertas` (`alerta_id`) ON DELETE CASCADE,
  CONSTRAINT `historial_alertas_usuario_rut_foreign` FOREIGN KEY (`usuario_rut`) REFERENCES `usuarios` (`rut`),
  CONSTRAINT `historial_alertas_responsable_anterior_rut_foreign` FOREIGN KEY (`responsable_anterior_rut`) REFERENCES `usuarios` (`rut`),
  CONSTRAINT `historial_alertas_responsable_nuevo_rut_foreign` FOREIGN KEY (`responsable_nuevo_rut`) REFERENCES `usuarios` (`rut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inventario` (
  `inventario_id` bigint NOT NULL AUTO_INCREMENT,
  `lote_id` bigint NOT NULL,
  `ubicacion_id` bigint NOT NULL,
  `cantidad` decimal(12,2) NOT NULL DEFAULT '0.00',
  `cantidad_recepcion` decimal(12,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`inventario_id`),
  UNIQUE KEY `lote_id` (`lote_id`,`ubicacion_id`),
  KEY `idx_inventario_ubicacion` (`ubicacion_id`),
  CONSTRAINT `fk_inventario_lote_id` FOREIGN KEY (`lote_id`) REFERENCES `lotes` (`lote_id`),
  CONSTRAINT `fk_inventario_ubicacion_id` FOREIGN KEY (`ubicacion_id`) REFERENCES `ubicaciones` (`ubicacion_id`),
  CONSTRAINT `inventario_chk_1` CHECK ((`cantidad` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `kardex_inventario` (
  `kardex_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inventario_id_origen` bigint NOT NULL,
  `lote_id` bigint NOT NULL,
  `ubicacion_id` bigint NOT NULL,
  `cantidad_final` decimal(12,2) NOT NULL DEFAULT '0.00',
  `motivo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `referencia_tipo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referencia_id` bigint DEFAULT NULL,
  `documento_recepcion` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacion` text COLLATE utf8mb4_unicode_ci,
  `usuario_rut` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_hora` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`kardex_id`),
  UNIQUE KEY `kardex_inventario_origen_unique` (`inventario_id_origen`),
  KEY `kardex_lote_ubicacion_fecha_idx` (`lote_id`,`ubicacion_id`,`fecha_hora`),
  KEY `kardex_fecha_idx` (`fecha_hora`),
  KEY `fk_kardex_ubicacion` (`ubicacion_id`),
  KEY `fk_kardex_usuario` (`usuario_rut`),
  CONSTRAINT `fk_kardex_lote` FOREIGN KEY (`lote_id`) REFERENCES `lotes` (`lote_id`),
  CONSTRAINT `fk_kardex_ubicacion` FOREIGN KEY (`ubicacion_id`) REFERENCES `ubicaciones` (`ubicacion_id`),
  CONSTRAINT `fk_kardex_usuario` FOREIGN KEY (`usuario_rut`) REFERENCES `usuarios` (`rut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `lotes` (
  `lote_id` bigint NOT NULL AUTO_INCREMENT,
  `producto_id` bigint NOT NULL,
  `numero_lote` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_fabricacion` date DEFAULT NULL,
  `fecha_vencimiento` date NOT NULL,
  `fecha_recepcion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`lote_id`),
  UNIQUE KEY `producto_id` (`producto_id`,`numero_lote`),
  KEY `idx_lotes_producto_vencimiento` (`producto_id`,`fecha_vencimiento`),
  KEY `idx_lotes_estado_vencimiento` (`estado`,`fecha_vencimiento`),
  CONSTRAINT `fk_lotes_producto_id` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`producto_id`),
  CONSTRAINT `lotes_chk_1` CHECK ((`estado` in (_utf8mb4'ACTIVO',_utf8mb4'INACTIVO')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `movimientos_controlados` (
  `movimiento_controlado_id` bigint NOT NULL AUTO_INCREMENT,
  `producto_id` bigint NOT NULL,
  `lote_id` bigint NOT NULL,
  `tipo_movimiento` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantidad` decimal(12,2) NOT NULL,
  `fecha_hora` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_responsable_rut` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuario_autoriza_rut` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `firma_autorizacion` text COLLATE utf8mb4_unicode_ci,
  `referencia_tipo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referencia_id` bigint DEFAULT NULL,
  `observacion` text COLLATE utf8mb4_unicode_ci,
  `firmado` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`movimiento_controlado_id`),
  KEY `fk_movimientos_controlados_producto_id` (`producto_id`),
  KEY `fk_movimientos_controlados_lote_id` (`lote_id`),
  KEY `fk_movimientos_controlados_usuario_responsable_rut` (`usuario_responsable_rut`),
  KEY `fk_movimientos_controlados_usuario_autoriza_rut` (`usuario_autoriza_rut`),
  KEY `idx_movimientos_controlados_fecha` (`fecha_hora`),
  CONSTRAINT `fk_movimientos_controlados_lote_id` FOREIGN KEY (`lote_id`) REFERENCES `lotes` (`lote_id`),
  CONSTRAINT `fk_movimientos_controlados_producto_id` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`producto_id`),
  CONSTRAINT `fk_movimientos_controlados_usuario_autoriza_rut` FOREIGN KEY (`usuario_autoriza_rut`) REFERENCES `usuarios` (`rut`),
  CONSTRAINT `fk_movimientos_controlados_usuario_responsable_rut` FOREIGN KEY (`usuario_responsable_rut`) REFERENCES `usuarios` (`rut`),
  CONSTRAINT `movimientos_controlados_chk_1` CHECK ((`tipo_movimiento` in (_utf8mb4'INGRESO',_utf8mb4'SALIDA',_utf8mb4'AJUSTE'))),
  CONSTRAINT `movimientos_controlados_chk_2` CHECK ((`cantidad` > 0)),
  CONSTRAINT `movimientos_controlados_chk_3` CHECK (((`firmado` = false) or ((`usuario_autoriza_rut` is not null) and (`firma_autorizacion` is not null))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `movimientos_inventario` (
  `movimiento_id` bigint NOT NULL AUTO_INCREMENT,
  `lote_id` bigint NOT NULL,
  `ubicacion_id` bigint NOT NULL,
  `tipo_movimiento` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantidad` decimal(12,2) NOT NULL,
  `fecha_hora` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_rut` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `referencia_tipo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referencia_id` bigint DEFAULT NULL,
  `observacion` text COLLATE utf8mb4_unicode_ci,
  `cantidad_original` decimal(12,2) DEFAULT NULL,
  `unidad_ingresada` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `factor_conversion` decimal(12,4) DEFAULT NULL,
  `cantidad_envases` decimal(12,2) DEFAULT NULL,
  `unidades_sueltas` decimal(12,2) DEFAULT NULL,
  `presentacion_nombre` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unidad_base_codigo` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`movimiento_id`),
  KEY `fk_movimientos_inventario_lote_id` (`lote_id`),
  KEY `fk_movimientos_inventario_ubicacion_id` (`ubicacion_id`),
  KEY `fk_movimientos_inventario_usuario_rut` (`usuario_rut`),
  KEY `idx_movimientos_inventario_fecha` (`fecha_hora`),
  CONSTRAINT `fk_movimientos_inventario_lote_id` FOREIGN KEY (`lote_id`) REFERENCES `lotes` (`lote_id`),
  CONSTRAINT `fk_movimientos_inventario_ubicacion_id` FOREIGN KEY (`ubicacion_id`) REFERENCES `ubicaciones` (`ubicacion_id`),
  CONSTRAINT `fk_movimientos_inventario_usuario_rut` FOREIGN KEY (`usuario_rut`) REFERENCES `usuarios` (`rut`),
  CONSTRAINT `movimientos_inventario_chk_1` CHECK ((`tipo_movimiento` in (_utf8mb4'RECEPCION',_utf8mb4'SALIDA',_utf8mb4'TRASPASO_ENTRADA',_utf8mb4'TRASPASO_SALIDA',_utf8mb4'AJUSTE_ENTRADA',_utf8mb4'AJUSTE_SALIDA'))),
  CONSTRAINT `movimientos_inventario_chk_2` CHECK ((`cantidad` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notificaciones` (
  `notificacion_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_rut` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mensaje` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `accion_texto` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accion_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `leida_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notificacion_id`),
  KEY `notificaciones_usuario_lectura_fecha_idx` (`usuario_rut`,`leida_at`,`created_at`),
  CONSTRAINT `notificaciones_usuario_rut_foreign` FOREIGN KEY (`usuario_rut`) REFERENCES `usuarios` (`rut`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ordenes_entrega` (
  `orden_entrega_id` bigint NOT NULL AUTO_INCREMENT,
  `entrega_id` bigint NOT NULL,
  `numero_orden` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_generacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `plantilla` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`orden_entrega_id`),
  UNIQUE KEY `entrega_id` (`entrega_id`),
  UNIQUE KEY `numero_orden` (`numero_orden`),
  CONSTRAINT `fk_ordenes_entrega_entrega_id` FOREIGN KEY (`entrega_id`) REFERENCES `entregas` (`entrega_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `paciente_programa` (
  `paciente_programa_id` bigint NOT NULL AUTO_INCREMENT,
  `paciente_rut` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `programa_id` bigint NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_termino` date DEFAULT NULL,
  `cobertura` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`paciente_programa_id`),
  UNIQUE KEY `paciente_rut` (`paciente_rut`,`programa_id`,`fecha_inicio`),
  KEY `fk_paciente_programa_programa_id` (`programa_id`),
  CONSTRAINT `fk_paciente_programa_paciente_rut` FOREIGN KEY (`paciente_rut`) REFERENCES `pacientes` (`rut`),
  CONSTRAINT `fk_paciente_programa_programa_id` FOREIGN KEY (`programa_id`) REFERENCES `programas_salud` (`programa_id`),
  CONSTRAINT `paciente_programa_chk_1` CHECK (((`fecha_termino` is null) or (`fecha_termino` >= `fecha_inicio`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pacientes` (
  `rut` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `apellidoPaterno` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellidoMaterno` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `telefono` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`rut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `fichas_pacientes` (
  `ficha_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `paciente_rut` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`ficha_id`),
  UNIQUE KEY `fichas_pacientes_paciente_rut_unique` (`paciente_rut`),
  CONSTRAINT `fichas_pacientes_paciente_rut_foreign` FOREIGN KEY (`paciente_rut`) REFERENCES `pacientes` (`rut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `preparacion_unidosis_detalle` (
  `preparacion_detalle_id` bigint NOT NULL AUTO_INCREMENT,
  `preparacion_id` bigint NOT NULL,
  `lote_id` bigint NOT NULL,
  `cantidad` decimal(12,2) NOT NULL,
  `horario` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`preparacion_detalle_id`),
  KEY `fk_preparacion_unidosis_detalle_preparacion_id` (`preparacion_id`),
  KEY `fk_preparacion_unidosis_detalle_lote_id` (`lote_id`),
  CONSTRAINT `fk_preparacion_unidosis_detalle_lote_id` FOREIGN KEY (`lote_id`) REFERENCES `lotes` (`lote_id`),
  CONSTRAINT `fk_preparacion_unidosis_detalle_preparacion_id` FOREIGN KEY (`preparacion_id`) REFERENCES `preparaciones_unidosis` (`preparacion_id`),
  CONSTRAINT `preparacion_unidosis_detalle_chk_1` CHECK ((`cantidad` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `preparaciones_unidosis` (
  `preparacion_id` bigint NOT NULL AUTO_INCREMENT,
  `tratamiento_id` bigint NOT NULL,
  `carro_id` bigint NOT NULL,
  `fecha_preparacion` date NOT NULL,
  `periodo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuario_prepara_rut` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuario_confirma_rut` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_confirmacion` timestamp NULL DEFAULT NULL,
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PREPARANDO',
  PRIMARY KEY (`preparacion_id`),
  UNIQUE KEY `tratamiento_id` (`tratamiento_id`,`fecha_preparacion`,`periodo`),
  KEY `idx_preparaciones_unidosis_fecha_estado` (`fecha_preparacion`,`estado`),
  KEY `fk_preparaciones_unidosis_carro_id` (`carro_id`),
  KEY `fk_preparaciones_unidosis_usuario_prepara_rut` (`usuario_prepara_rut`),
  KEY `fk_preparaciones_unidosis_usuario_confirma_rut` (`usuario_confirma_rut`),
  CONSTRAINT `fk_preparaciones_unidosis_carro_id` FOREIGN KEY (`carro_id`) REFERENCES `carros` (`carro_id`),
  CONSTRAINT `fk_preparaciones_unidosis_tratamiento_id` FOREIGN KEY (`tratamiento_id`) REFERENCES `tratamientos` (`tratamiento_id`),
  CONSTRAINT `fk_preparaciones_unidosis_usuario_confirma_rut` FOREIGN KEY (`usuario_confirma_rut`) REFERENCES `usuarios` (`rut`),
  CONSTRAINT `fk_preparaciones_unidosis_usuario_prepara_rut` FOREIGN KEY (`usuario_prepara_rut`) REFERENCES `usuarios` (`rut`),
  CONSTRAINT `preparaciones_unidosis_chk_1` CHECK ((`estado` in (_utf8mb4'PREPARANDO',_utf8mb4'CONFIRMADA',_utf8mb4'ANULADA')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `presentaciones_producto` (
  `presentacion_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `producto_id` bigint NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `factor_conversion` decimal(12,4) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`presentacion_id`),
  UNIQUE KEY `presentaciones_producto_producto_id_nombre_unique` (`producto_id`,`nombre`),
  KEY `presentaciones_producto_producto_id_activo_index` (`producto_id`,`activo`),
  CONSTRAINT `presentaciones_producto_producto_id_foreign` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`producto_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `principios_activos` (
  `principio_activo_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_normalizado` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`principio_activo_id`),
  UNIQUE KEY `principios_activos_nombre_normalizado_unique` (`nombre_normalizado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `productos` (
  `producto_id` bigint NOT NULL AUTO_INCREMENT,
  `numero_serie` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `presentaciones_comerciales` json DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `categoria_id` bigint NOT NULL,
  `es_controlado` tinyint(1) NOT NULL DEFAULT '0',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `es_medicamento` tinyint(1) DEFAULT NULL,
  `forma_farmaceutica_id` bigint unsigned DEFAULT NULL,
  `via_administracion` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contenido_envase_cantidad` decimal(10,3) DEFAULT NULL,
  `contenido_envase_unidad_id` bigint unsigned DEFAULT NULL,
  `tipo_presentacion` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bioequivalencia_estado` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `tipo_control_legal` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registro_sanitario_isp` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado_registro_sanitario` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `unidad_base_inventario_id` bigint unsigned DEFAULT NULL,
  `tipo_producto` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INSUMO_CLINICO',
  PRIMARY KEY (`producto_id`),
  UNIQUE KEY `numero_serie` (`numero_serie`),
  KEY `idx_productos_categoria` (`categoria_id`),
  KEY `productos_registro_sanitario_isp_index` (`registro_sanitario_isp`),
  KEY `productos_forma_farmaceutica_fk` (`forma_farmaceutica_id`),
  KEY `productos_contenido_unidad_fk` (`contenido_envase_unidad_id`),
  CONSTRAINT `fk_productos_categoria_id` FOREIGN KEY (`categoria_id`) REFERENCES `categorias_producto` (`categoria_id`),
  CONSTRAINT `productos_contenido_unidad_fk` FOREIGN KEY (`contenido_envase_unidad_id`) REFERENCES `unidades_medida` (`unidad_medida_id`),
  CONSTRAINT `productos_forma_farmaceutica_fk` FOREIGN KEY (`forma_farmaceutica_id`) REFERENCES `formas_farmaceuticas` (`forma_farmaceutica_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `productos_codigo_barra` (
  `codigo_barra_id` bigint NOT NULL AUTO_INCREMENT,
  `producto_id` bigint NOT NULL,
  `codigo_barra` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`codigo_barra_id`),
  UNIQUE KEY `codigo_barra` (`codigo_barra`),
  KEY `fk_productos_codigo_barra_producto_id` (`producto_id`),
  CONSTRAINT `fk_productos_codigo_barra_producto_id` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`producto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `productos_principios_activos` (
  `producto_id` bigint NOT NULL,
  `principio_activo_id` bigint unsigned NOT NULL,
  `concentracion_cantidad` decimal(10,4) NOT NULL,
  `concentracion_unidad_id` bigint unsigned NOT NULL,
  `concentracion_base_cantidad` decimal(10,4) DEFAULT NULL,
  `concentracion_base_unidad_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`producto_id`,`principio_activo_id`),
  KEY `producto_principios_principio_fk` (`principio_activo_id`),
  KEY `producto_principios_unidad_fk` (`concentracion_unidad_id`),
  KEY `producto_principios_unidad_base_fk` (`concentracion_base_unidad_id`),
  CONSTRAINT `producto_principios_principio_fk` FOREIGN KEY (`principio_activo_id`) REFERENCES `principios_activos` (`principio_activo_id`),
  CONSTRAINT `producto_principios_producto_fk` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`producto_id`) ON DELETE CASCADE,
  CONSTRAINT `producto_principios_unidad_base_fk` FOREIGN KEY (`concentracion_base_unidad_id`) REFERENCES `unidades_medida` (`unidad_medida_id`),
  CONSTRAINT `producto_principios_unidad_fk` FOREIGN KEY (`concentracion_unidad_id`) REFERENCES `unidades_medida` (`unidad_medida_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `profesionales_salud` (
  `rut` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `apellidoPaterno` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellidoMaterno` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_profesional` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`rut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `programas_salud` (
  `programa_id` bigint NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`programa_id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `receta_detalle` (
  `receta_detalle_id` bigint NOT NULL AUTO_INCREMENT,
  `receta_id` bigint NOT NULL,
  `producto_id` bigint NOT NULL,
  `dosis` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `frecuencia` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cantidad_indicada` decimal(12,2) NOT NULL,
  `indicaciones` text COLLATE utf8mb4_unicode_ci,
  `via_administracion` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unidades_por_toma` decimal(12,2) DEFAULT NULL,
  `duracion_dias` smallint unsigned DEFAULT NULL,
  `es_sos` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`receta_detalle_id`),
  KEY `fk_receta_detalle_receta_id` (`receta_id`),
  KEY `fk_receta_detalle_producto_id` (`producto_id`),
  CONSTRAINT `fk_receta_detalle_producto_id` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`producto_id`),
  CONSTRAINT `fk_receta_detalle_receta_id` FOREIGN KEY (`receta_id`) REFERENCES `recetas` (`receta_id`),
  CONSTRAINT `receta_detalle_chk_1` CHECK ((`cantidad_indicada` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `recetas` (
  `receta_id` bigint NOT NULL AUTO_INCREMENT,
  `paciente_rut` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ficha_id` bigint unsigned DEFAULT NULL,
  `profesional_rut` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero_receta` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_emision` date NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `tipo_receta` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VIGENTE',
  `fecha_registro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estado_ficha` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RECIENTE',
  `observacion` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`receta_id`),
  UNIQUE KEY `numero_receta` (`numero_receta`),
  KEY `fk_recetas_profesional_rut` (`profesional_rut`),
  KEY `idx_recetas_paciente` (`paciente_rut`),
  KEY `idx_recetas_fecha_emision` (`fecha_emision`),
  KEY `recetas_estado_ficha_registro_idx` (`estado_ficha`,`fecha_registro`),
  KEY `recetas_ficha_id_foreign` (`ficha_id`),
  CONSTRAINT `fk_recetas_paciente_rut` FOREIGN KEY (`paciente_rut`) REFERENCES `pacientes` (`rut`),
  CONSTRAINT `recetas_ficha_id_foreign` FOREIGN KEY (`ficha_id`) REFERENCES `fichas_pacientes` (`ficha_id`),
  CONSTRAINT `fk_recetas_profesional_rut` FOREIGN KEY (`profesional_rut`) REFERENCES `profesionales_salud` (`rut`),
  CONSTRAINT `recetas_chk_1` CHECK ((`tipo_receta` in (_utf8mb4'ESTANDAR',_utf8mb4'RETENIDA'))),
  CONSTRAINT `recetas_chk_2` CHECK ((`estado` in (_utf8mb4'VIGENTE',_utf8mb4'VENCIDA',_utf8mb4'ANULADA',_utf8mb4'UTILIZADA'))),
  CONSTRAINT `recetas_chk_3` CHECK ((`fecha_vencimiento` >= `fecha_emision`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `remesa_detalle` (
  `remesa_detalle_id` bigint NOT NULL AUTO_INCREMENT,
  `remesa_id` bigint NOT NULL,
  `producto_id` bigint NOT NULL,
  `numero_lote` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_fabricacion` date DEFAULT NULL,
  `fecha_vencimiento` date NOT NULL,
  `cantidad` decimal(12,2) NOT NULL,
  `cantidad_aceptada` decimal(12,2) DEFAULT NULL,
  `cantidad_rechazada` decimal(12,2) NOT NULL DEFAULT '0.00',
  `motivo_rechazo` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cantidad_envases` decimal(12,2) NOT NULL DEFAULT '0.00',
  `unidades_sueltas` decimal(12,2) NOT NULL DEFAULT '0.00',
  `presentacion_nombre` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `factor_conversion` decimal(12,4) DEFAULT NULL,
  `unidad_base_codigo` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`remesa_detalle_id`),
  UNIQUE KEY `remesa_id` (`remesa_id`,`producto_id`,`numero_lote`),
  KEY `fk_remesa_detalle_producto_id` (`producto_id`),
  CONSTRAINT `fk_remesa_detalle_producto_id` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`producto_id`),
  CONSTRAINT `fk_remesa_detalle_remesa_id` FOREIGN KEY (`remesa_id`) REFERENCES `remesas` (`remesa_id`),
  CONSTRAINT `remesa_detalle_chk_1` CHECK ((`cantidad` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `remesas` (
  `remesa_id` bigint NOT NULL AUTO_INCREMENT,
  `codigo_remesa` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `origen` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_recepcion` timestamp NOT NULL,
  `solicitud_id` bigint DEFAULT NULL,
  `usuario_rut` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`remesa_id`),
  UNIQUE KEY `codigo_remesa` (`codigo_remesa`),
  KEY `fk_remesas_solicitud_id` (`solicitud_id`),
  KEY `fk_remesas_usuario_rut` (`usuario_rut`),
  KEY `idx_remesas_fecha` (`fecha_recepcion`),
  CONSTRAINT `fk_remesas_solicitud_id` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes_reposicion` (`solicitud_id`),
  CONSTRAINT `fk_remesas_usuario_rut` FOREIGN KEY (`usuario_rut`) REFERENCES `usuarios` (`rut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `roles` (
  `rol_id` bigint NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`rol_id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rotulos_unidosis` (
  `rotulo_id` bigint NOT NULL AUTO_INCREMENT,
  `preparacion_detalle_id` bigint NOT NULL,
  `identificador` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contenido_generado` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_generacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`rotulo_id`),
  UNIQUE KEY `preparacion_detalle_id` (`preparacion_detalle_id`),
  UNIQUE KEY `identificador` (`identificador`),
  CONSTRAINT `fk_rotulos_unidosis_preparacion_detalle_id` FOREIGN KEY (`preparacion_detalle_id`) REFERENCES `preparacion_unidosis_detalle` (`preparacion_detalle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `solicitud_reposicion_detalle` (
  `solicitud_detalle_id` bigint NOT NULL AUTO_INCREMENT,
  `solicitud_id` bigint NOT NULL,
  `producto_id` bigint NOT NULL,
  `cantidad_solicitada` decimal(12,2) NOT NULL,
  PRIMARY KEY (`solicitud_detalle_id`),
  UNIQUE KEY `solicitud_id` (`solicitud_id`,`producto_id`),
  KEY `fk_solicitud_reposicion_detalle_producto_id` (`producto_id`),
  CONSTRAINT `fk_solicitud_reposicion_detalle_producto_id` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`producto_id`),
  CONSTRAINT `fk_solicitud_reposicion_detalle_solicitud_id` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes_reposicion` (`solicitud_id`),
  CONSTRAINT `solicitud_reposicion_detalle_chk_1` CHECK ((`cantidad_solicitada` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `solicitudes_ajuste_inventario` (
  `solicitud_ajuste_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lote_id` bigint NOT NULL,
  `ubicacion_id` bigint NOT NULL,
  `tipo_ajuste` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantidad` decimal(12,2) NOT NULL,
  `motivo` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `usuario_solicita_rut` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `solicitante_es_qf` tinyint(1) NOT NULL DEFAULT '0',
  `usuario_autoriza_rut` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `movimiento_id` bigint DEFAULT NULL,
  `motivo_resolucion` text COLLATE utf8mb4_unicode_ci,
  `fecha_solicitud` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_resolucion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`solicitud_ajuste_id`),
  KEY `solicitudes_ajuste_estado_fecha_idx` (`estado`,`fecha_solicitud`),
  KEY `solicitudes_ajuste_solicitante_fecha_idx` (`usuario_solicita_rut`,`fecha_solicitud`),
  KEY `solicitudes_ajuste_lote_fk` (`lote_id`),
  KEY `solicitudes_ajuste_ubicacion_fk` (`ubicacion_id`),
  KEY `solicitudes_ajuste_autorizador_fk` (`usuario_autoriza_rut`),
  KEY `solicitudes_ajuste_movimiento_fk` (`movimiento_id`),
  CONSTRAINT `solicitudes_ajuste_autorizador_fk` FOREIGN KEY (`usuario_autoriza_rut`) REFERENCES `usuarios` (`rut`),
  CONSTRAINT `solicitudes_ajuste_lote_fk` FOREIGN KEY (`lote_id`) REFERENCES `lotes` (`lote_id`),
  CONSTRAINT `solicitudes_ajuste_movimiento_fk` FOREIGN KEY (`movimiento_id`) REFERENCES `movimientos_inventario` (`movimiento_id`),
  CONSTRAINT `solicitudes_ajuste_solicitante_fk` FOREIGN KEY (`usuario_solicita_rut`) REFERENCES `usuarios` (`rut`),
  CONSTRAINT `solicitudes_ajuste_ubicacion_fk` FOREIGN KEY (`ubicacion_id`) REFERENCES `ubicaciones` (`ubicacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `solicitudes_reposicion` (
  `solicitud_id` bigint NOT NULL AUTO_INCREMENT,
  `fecha_solicitud` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estado` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `usuario_rut` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`solicitud_id`),
  KEY `idx_solicitudes_reposicion_fecha_estado` (`fecha_solicitud`,`estado`),
  KEY `fk_solicitudes_reposicion_usuario_rut` (`usuario_rut`),
  CONSTRAINT `fk_solicitudes_reposicion_usuario_rut` FOREIGN KEY (`usuario_rut`) REFERENCES `usuarios` (`rut`),
  CONSTRAINT `solicitudes_reposicion_chk_1` CHECK ((`estado` in (_utf8mb4'PENDIENTE',_utf8mb4'SOLICITADA',_utf8mb4'RECIBIDA',_utf8mb4'CANCELADA')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `stock_minimos_ubicacion` (
  `stock_minimo_id` bigint NOT NULL AUTO_INCREMENT,
  `producto_id` bigint NOT NULL,
  `ubicacion_id` bigint NOT NULL,
  `stock_minimo` decimal(12,2) NOT NULL DEFAULT '0.00',
  `stock_maximo` decimal(12,2) DEFAULT NULL,
  PRIMARY KEY (`stock_minimo_id`),
  UNIQUE KEY `producto_id` (`producto_id`,`ubicacion_id`),
  KEY `fk_stock_minimo_ubicacion_id` (`ubicacion_id`),
  CONSTRAINT `fk_stock_minimo_producto_id` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`producto_id`),
  CONSTRAINT `fk_stock_minimo_ubicacion_id` FOREIGN KEY (`ubicacion_id`) REFERENCES `ubicaciones` (`ubicacion_id`),
  CONSTRAINT `stock_minimos_ubicacion_chk_1` CHECK ((`stock_minimo` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `personas_autorizadas` (
  `persona_autorizada_id` bigint NOT NULL AUTO_INCREMENT,
  `paciente_rut` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rut` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellidoPaterno` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellidoMaterno` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vinculo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`persona_autorizada_id`),
  UNIQUE KEY `paciente_rut` (`paciente_rut`,`rut`),
  CONSTRAINT `fk_personas_autorizadas_paciente_rut` FOREIGN KEY (`paciente_rut`) REFERENCES `pacientes` (`rut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `traspaso_detalle` (
  `traspaso_detalle_id` bigint NOT NULL AUTO_INCREMENT,
  `traspaso_id` bigint NOT NULL,
  `lote_id` bigint NOT NULL,
  `cantidad` decimal(12,2) NOT NULL,
  PRIMARY KEY (`traspaso_detalle_id`),
  KEY `fk_traspaso_detalle_traspaso_id` (`traspaso_id`),
  KEY `fk_traspaso_detalle_lote_id` (`lote_id`),
  CONSTRAINT `fk_traspaso_detalle_lote_id` FOREIGN KEY (`lote_id`) REFERENCES `lotes` (`lote_id`),
  CONSTRAINT `fk_traspaso_detalle_traspaso_id` FOREIGN KEY (`traspaso_id`) REFERENCES `traspasos_inventario` (`traspaso_id`),
  CONSTRAINT `traspaso_detalle_chk_1` CHECK ((`cantidad` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `traspasos_inventario` (
  `traspaso_id` bigint NOT NULL AUTO_INCREMENT,
  `fecha_hora` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `origen_id` bigint NOT NULL,
  `destino_id` bigint NOT NULL,
  `usuario_rut` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `observacion` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`traspaso_id`),
  KEY `fk_traspasos_inventario_origen_id` (`origen_id`),
  KEY `fk_traspasos_inventario_destino_id` (`destino_id`),
  KEY `fk_traspasos_inventario_usuario_rut` (`usuario_rut`),
  CONSTRAINT `fk_traspasos_inventario_destino_id` FOREIGN KEY (`destino_id`) REFERENCES `ubicaciones` (`ubicacion_id`),
  CONSTRAINT `fk_traspasos_inventario_origen_id` FOREIGN KEY (`origen_id`) REFERENCES `ubicaciones` (`ubicacion_id`),
  CONSTRAINT `fk_traspasos_inventario_usuario_rut` FOREIGN KEY (`usuario_rut`) REFERENCES `usuarios` (`rut`),
  CONSTRAINT `traspasos_inventario_chk_1` CHECK ((`origen_id` <> `destino_id`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tratamientos` (
  `tratamiento_id` bigint NOT NULL AUTO_INCREMENT,
  `receta_id` bigint DEFAULT NULL,
  `paciente_rut` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_termino` date DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`tratamiento_id`),
  UNIQUE KEY `tratamientos_receta_id_unique` (`receta_id`),
  KEY `fk_tratamientos_paciente_rut` (`paciente_rut`),
  CONSTRAINT `fk_tratamientos_receta_id` FOREIGN KEY (`receta_id`) REFERENCES `recetas` (`receta_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tratamientos_paciente_rut` FOREIGN KEY (`paciente_rut`) REFERENCES `pacientes` (`rut`),
  CONSTRAINT `tratamientos_chk_1` CHECK (((`fecha_termino` is null) or (`fecha_termino` >= `fecha_inicio`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ubicaciones` (
  `ubicacion_id` bigint NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`ubicacion_id`),
  UNIQUE KEY `nombre` (`nombre`),
  CONSTRAINT `ubicaciones_chk_1` CHECK ((`tipo` in (_utf8mb4'BODEGA',_utf8mb4'VITRINA',_utf8mb4'CARRO_PARO',_utf8mb4'CARRO_UNIDOSIS')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `unidades_medida` (
  `unidad_medida_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`unidad_medida_id`),
  UNIQUE KEY `unidades_medida_codigo_unique` (`codigo`),
  KEY `unidades_medida_tipo_index` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuario_rol` (
  `usuario_rut` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol_id` bigint NOT NULL,
  `asignado_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`usuario_rut`,`rol_id`),
  KEY `fk_usuario_rol_rol_id` (`rol_id`),
  CONSTRAINT `fk_usuario_rol_rol_id` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`rol_id`),
  CONSTRAINT `fk_usuario_rol_usuario_rut` FOREIGN KEY (`usuario_rut`) REFERENCES `usuarios` (`rut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuarios` (
  `rut` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `apellidoPaterno` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellidoMaterno` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`rut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `farmacia_migration_ownership` (
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `vw_stock_producto_ubicacion` AS select `p`.`producto_id` AS `producto_id`,`p`.`nombre` AS `producto`,`u`.`ubicacion_id` AS `ubicacion_id`,`u`.`nombre` AS `ubicacion`,coalesce(sum((case when ((`l`.`estado` = 'ACTIVO') and (`l`.`fecha_vencimiento` >= curdate())) then coalesce(`i`.`cantidad`,0) else 0 end)),0) AS `stock_disponible`,`sm`.`stock_minimo` AS `stock_minimo`,(case when (coalesce(sum((case when ((`l`.`estado` = 'ACTIVO') and (`l`.`fecha_vencimiento` >= curdate())) then coalesce(`i`.`cantidad`,0) else 0 end)),0) <= `sm`.`stock_minimo`) then true else false end) AS `es_stock_critico` from ((((`stock_minimos_ubicacion` `sm` join `productos` `p` on((`p`.`producto_id` = `sm`.`producto_id`))) join `ubicaciones` `u` on((`u`.`ubicacion_id` = `sm`.`ubicacion_id`))) left join `lotes` `l` on((`l`.`producto_id` = `sm`.`producto_id`))) left join `inventario` `i` on(((`i`.`lote_id` = `l`.`lote_id`) and (`i`.`ubicacion_id` = `sm`.`ubicacion_id`)))) where (`p`.`activo` = true) group by `p`.`producto_id`,`p`.`nombre`,`u`.`ubicacion_id`,`u`.`nombre`,`sm`.`stock_minimo`;

SET FOREIGN_KEY_CHECKS=1;

INSERT IGNORE INTO roles (nombre, descripcion) VALUES
    ('Administrador', 'Gestion global del sistema'),
    ('Técnico Farmacéutico', 'Operación de farmacia e inventario'),
    ('Químico Farmacéutico', 'Supervisión y validación farmacéutica');

INSERT IGNORE INTO categorias_producto (nombre, descripcion) VALUES
    ('Medicamentos', 'Catalogo de medicamentos de demostracion'),
    ('Insumos clínicos', 'Catalogo de insumos clinicos de demostracion');

INSERT IGNORE INTO ubicaciones (nombre, tipo, descripcion) VALUES
    ('Bodega Central', 'BODEGA', 'Ubicacion de almacenamiento principal'),
    ('Vitrina Farmacia', 'VITRINA', 'Ubicacion de dispensacion'),
    ('Carro de Paro', 'CARRO_PARO', 'Carro de emergencia'),
    ('Carro Unidosis', 'CARRO_UNIDOSIS', 'Preparaciones de unidosis');

INSERT IGNORE INTO formas_farmaceuticas (nombre, activo) VALUES
    ('Comprimido', 1),
    ('Cápsula', 1),
    ('Jarabe', 1),
    ('Inhalador', 1);

INSERT IGNORE INTO unidades_medida (codigo, nombre, tipo, activo) VALUES
    ('unidad', 'unidad base', 'PRESENTACION', 1),
    ('mg', 'miligramo', 'MASA', 1),
    ('mL', 'mililitro', 'VOLUMEN', 1),
    ('comprimido', 'comprimido', 'PRESENTACION', 1),
    ('cápsula', 'cápsula', 'PRESENTACION', 1),
    ('dosis', 'dosis', 'PRESENTACION', 1);