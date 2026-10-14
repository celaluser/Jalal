<?php

return [
    'title' => 'Complementos', 'sub' => 'Funciones adicionales entregadas como paquetes firmados. Se ejecutan con acceso total a la aplicación, así que instala solo complementos de fuentes de confianza.',
    'install' => 'Instalar un complemento', 'install_help' => 'Sube el .zip que recibiste. Primero se verifican la firma y cada archivo. Los complementos nuevos empiezan desactivados.',
    'trust_warning' => 'Un complemento puede leer y modificar todo, incluida tu base de datos. Haz una copia de seguridad antes.', 'install_button' => 'Verificar e instalar',
    'installed_title' => 'Complementos instalados', 'installed' => ':name instalado. Actívalo para empezar a usarlo.', 'updated' => 'Complemento actualizado.', 'removed' => 'Complemento eliminado. Sus datos permanecen en la base de datos.',
    'on' => 'Activado', 'off' => 'Desactivado', 'turn_on' => 'Activar', 'turn_off' => 'Desactivar', 'remove_confirm' => '¿Eliminar este complemento? Se borran sus archivos; sus tablas de base de datos permanecen.',
    'empty' => 'No hay complementos instalados', 'empty_text' => 'Los complementos que compres o crees aparecerán aquí.',
    'not_an_addon' => 'Este paquete no es un complemento (todos los archivos deben estar en addons/<name>/).', 'invalid_manifest' => 'La descripción del complemento (addon.json) falta o no es válida.',
    'needs_core' => 'Requiere la versión :version de la aplicación o una superior.', 'provider_missing' => 'No se pudo cargar el código del complemento.', 'not_found' => 'Complemento no encontrado.',
];
