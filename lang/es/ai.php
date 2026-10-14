<?php

return [
    'error_not_configured' => 'La IA aún no está configurada. Pide al administrador de la plataforma que añada una clave de IA.',
    'error_no_credits' => 'Has usado todos los créditos de IA de tu plan este mes. Se renuevan el día 1, o puedes mejorar tu plan.',
    'error_provider_error' => 'El servicio de IA no respondió. Por favor, inténtalo de nuevo en un momento.',
    'error_rate_limited' => 'El servicio de IA está ocupado ahora mismo. Por favor, inténtalo de nuevo en un minuto.',
    'error_bad_response' => 'Esta vez la IA no pudo generar una respuesta utilizable. Inténtalo de nuevo o añade más detalle.',
    'error_limit_categories' => 'Importar este menú superaría el límite de categorías de tu plan.',
    'error_limit_products' => 'Importar este menú superaría el límite de productos de tu plan.',

    'write' => 'Escribir con IA', 'writing' => 'Escribiendo…', 'hints' => 'Ingredientes o notas (opcional)', 'tone' => 'Estilo',
    'tone_appetizing' => 'Apetitoso', 'tone_short' => 'Corto y sencillo', 'tone_elegant' => 'Elegante', 'tone_playful' => 'Divertido',
    'translate' => 'Traducir con IA', 'translating' => 'Traduciendo…', 'translate_hint' => 'Rellena los campos vacíos de los otros idiomas a partir de tu idioma principal.',
    'suggest_tags' => 'Sugerir alérgenos con IA', 'suggesting' => 'Revisando…',
    'tags_notice' => 'Sugerido por la IA. Revisa tú mismo cada alérgeno: los clientes confían en esta información.', 'tags_none' => 'No se sugirió ningún alérgeno ni etiqueta de dieta.',
    'tags_applied' => 'Marcados: :list',
    'credits_left' => ':count créditos restantes este mes', 'credits_unlimited' => 'Los créditos de IA no tienen límite en tu plan', 'credits_cost' => 'Cuesta :count crédito(s)',
    'review_note' => 'Revisa el texto antes de guardar. La IA puede cometer errores.',

    'import_title' => 'Importar un menú con IA', 'import_sub' => 'Pega tu menú desde un PDF, un sitio web o un documento. La IA lo ordena en categorías y platos, y tú lo revisas todo antes de añadirlo.',
    'import_paste' => 'Pega el texto de tu menú', 'import_placeholder' => "ENTRADAS\nBruschetta - 7.50\nSopa del día - 6\n\nPLATOS PRINCIPALES\nPizza margherita, tomate y albahaca - 12", 'import_chars' => ':count de :max caracteres',
    'import_read' => 'Leer mi menú', 'import_cost' => 'Cuesta :count créditos', 'import_review' => 'Revisar', 'import_found' => ':count platos encontrados',
    'import_no_price' => 'No se encontró precio: estos platos se añaden ocultos hasta que les pongas precio.', 'import_add' => 'Añadir a mi menú', 'import_adding' => 'Añadiendo…',
    'import_done' => 'Se añadieron :categories categorías y :products platos. :hidden están ocultos hasta que tengan precio.', 'import_remove' => 'Quitar', 'import_again' => 'Empezar de nuevo',
    'import_not_configured_title' => 'La IA aún no está disponible', 'import_how' => 'Cómo funciona',
    'import_step1' => 'Pega el texto de tu menú.', 'import_step2' => 'La IA lo lee y lo agrupa. Edita nombres y precios si hace falta.', 'import_step3' => 'Añádelo a tu menú con un clic.',
    'category' => 'Categoría', 'dish' => 'Nombre del plato', 'price' => 'Precio', 'description' => 'Descripción',
    'button_import' => 'Importar con IA',
    'need_name' => 'Escribe primero el nombre.',

    'import_photo' => 'O una foto del menú', 'import_photo_cost' => 'Una foto cuesta :count créditos de IA (se cuenta cada página, hasta 5). Funciona con menús impresos y capturas de pantalla.', 'import_pdf' => 'O un menú en PDF', 'import_pdf_hint' => 'Solo PDF con texto real. Si el PDF es un escaneo, haz una foto de la página.',
    'error_bad_file' => 'No se pudo leer ese archivo. Prueba con un JPG, PNG o PDF nítido.', 'error_pdf_no_text' => 'Este PDF no tiene texto legible (probablemente es un escaneo). Usa la importación por foto.',
    'bulk_title' => 'Traducir todo el menú', 'bulk_text' => 'Rellena en segundo plano todas las traducciones que faltan de platos y categorías. Los idiomas que bloqueaste no se tocan. Cada elemento consume créditos de traducción.', 'bulk_start' => 'Traducir todo lo que falta',
    'bulk_running' => 'Traduciendo…', 'bulk_done' => 'Listo: :n textos traducidos.', 'bulk_stopped' => 'Se detuvo antes de tiempo (sin créditos o el servicio de IA no está disponible). Lo ya hecho se conserva.',
    'draft_reply' => 'Redactar una respuesta con IA', 'draft_note' => 'Lee y edita el borrador antes de enviarlo.', 'insights_title' => 'Ideas a partir de tus números', 'insights_button' => 'Obtener ideas', 'insights_note' => 'Escritas por una IA a partir de las cifras de arriba. Tómalas como sugerencias.',
    'assistant_name' => 'Asistente del menú', 'assistant_ask' => 'Pregunta sobre el menú', 'assistant_placeholder' => '¿Hay algo vegetariano? ¿Qué acompaña a la hamburguesa?', 'assistant_send' => 'Enviar', 'assistant_hint' => 'Las respuestas salen solo de este menú. Pide al personal que confirme las alergias.', 'assistant_error' => 'El asistente no está disponible en este momento.',
];
