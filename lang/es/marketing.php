<?php

return [
    // Customers
    'customers_title' => 'Clientes', 'customers_sub' => 'Todos los que dejaron sus datos con un pedido. Fideliza, identifica a los habituales y escribe a quienes lo aceptaron.',
    'search_customers' => 'Buscar nombre, correo o teléfono', 'filter_all' => 'Todos', 'filter_opted' => 'Aceptaron marketing', 'filter_repeat' => 'Habituales (2+ pedidos)',
    'sort_recent' => 'Último pedido', 'sort_orders' => 'Más pedidos', 'sort_spent' => 'Mayor gasto', 'sort_name' => 'Nombre',
    'col_customer' => 'Cliente', 'col_orders' => 'Pedidos', 'col_spent' => 'Gastado', 'col_last' => 'Último pedido', 'col_marketing' => 'Marketing',
    'stat_all' => 'Clientes', 'stat_opted' => 'Localizables por correo', 'stat_repeat' => 'Habituales',
    'consent_yes' => 'Aceptó', 'consent_no' => 'No', 'consent_unsub' => 'Dado de baja', 'anonymous' => 'Invitado', 'export' => 'Exportar CSV',
    'customers_empty' => 'Aún no hay clientes', 'customers_empty_text' => 'Los clientes aparecen aquí cuando dejan un nombre, teléfono o correo con un pedido.',
    'customer_since' => 'Cliente desde el :date', 'agreed_on' => 'Aceptó marketing el :date', 'unsubscribed_on' => 'Se dio de baja el :date', 'no_consent' => 'No ha aceptado recibir correos de marketing.',
    'notes' => 'Notas privadas', 'notes_help' => 'Solo las ve tu equipo. Alergias, preferencias, cualquier cosa que valga la pena recordar.', 'order_history' => 'Pedidos', 'no_orders' => 'Sin pedidos.',
    'delete_customer' => 'Eliminar este cliente', 'delete_help' => 'Elimina el registro y borra el nombre, teléfono, correo y dirección de sus pedidos. Las cifras de ventas se conservan. No se puede deshacer.', 'delete_confirm' => '¿Eliminar a este cliente y borrar sus datos personales?',
    'customer_deleted' => 'Cliente eliminado y datos personales borrados.', 'avg_order' => 'Pedido promedio',

    // Promo codes
    'promos_title' => 'Códigos promocionales', 'promos_sub' => 'Códigos de descuento que tus clientes introducen al pagar. No los confundas con los cupones de suscripción de la plataforma.',
    'new_promo' => 'Nuevo código', 'edit_promo' => 'Editar código', 'promo_code' => 'Código', 'promo_desc' => 'Descripción', 'promo_desc_help' => 'Solo tú lo ves.',
    'promo_type' => 'Tipo de descuento', 'type_percent' => 'Porcentaje de descuento', 'type_fixed' => 'Importe de descuento', 'promo_value' => 'Valor', 'promo_min' => 'Pedido mínimo', 'promo_max_uses' => 'Usos máximos', 'promo_max_help' => 'Déjalo vacío para ilimitado.',
    'promo_starts' => 'Empieza', 'promo_ends' => 'Termina', 'promo_active' => 'Activo', 'promo_uses' => 'Usado', 'promo_status' => 'Estado',
    'status_active' => 'Activo', 'status_off' => 'Desactivado', 'status_expired' => 'Caducado', 'status_scheduled' => 'Programado', 'status_used_up' => 'Agotado', 'reward_badge' => 'Recompensa de fidelidad', 'reward_for' => 'Para :name',
    'promo_saved' => 'Código promocional guardado.', 'promo_deleted' => 'Código promocional eliminado.', 'promos_empty' => 'Aún no hay códigos promocionales', 'promos_empty_text' => 'Crea un código como BIENVENIDO10 y compártelo en redes sociales o en tus campañas.',
    'turn_off' => 'Desactivar', 'turn_on' => 'Activar', 'delete_promo_confirm' => '¿Eliminar este código promocional?', 'min_order_label' => 'desde :amount',
    'reward_percent' => ':value% de descuento', 'reward_fixed' => ':amount de descuento',

    // Guest checkout
    'promo_field' => 'Código promocional', 'promo_apply' => 'Aplicar', 'promo_remove' => 'Quitar', 'promo_applied' => 'Código :code aplicado: :amount de descuento.', 'promo_discount' => 'Descuento',
    'opt_in' => 'Quiero recibir ofertas y novedades de :name por correo. Puedo darme de baja cuando quiera.',

    // Reviews
    'reviews_title' => 'Reseñas', 'reviews_sub' => 'Lo que dijeron los clientes después de comer.', 'reviews_empty' => 'Aún no hay reseñas', 'reviews_empty_text' => 'Se invita a los clientes a valorar su pedido una vez completado.',
    'average' => 'Valoración promedio', 'review_count' => '{1} 1 reseña|[2,*] :count reseñas', 'filter_low' => 'Valoraciones bajas', 'filter_unanswered' => 'Sin responder',
    'low_alert' => '{1} 1 valoración baja aún necesita respuesta.|[2,*] :count valoraciones bajas aún necesitan respuesta.', 'low_flag' => 'Requiere atención',
    'reply' => 'Tu respuesta', 'reply_save' => 'Guardar respuesta', 'reply_help' => 'Se muestra al cliente en la página de su pedido.', 'hide' => 'Ocultar del menú', 'show' => 'Mostrar en el menú', 'hidden' => 'Oculta', 'order_ref' => 'Pedido :number',
    'public_note' => 'Visible en tu menú', 'stars' => '{1} :count estrella|[2,*] :count de 5 estrellas',

    // Guest review form
    'rate_title' => '¿Qué tal estuvo?', 'rate_sub' => 'Valora tu pedido. Toma diez segundos.', 'rate_comment' => '¿Algo que añadir? (opcional)', 'rate_public' => 'Mostrar mi valoración (con mi nombre de pila) en el menú',
    'rate_send' => 'Enviar mi valoración', 'rate_thanks' => '¡Gracias por tus comentarios!', 'rate_yours' => 'Tu valoración', 'rate_reply' => 'Respuesta del restaurante',
    'review_error_closed' => 'Las valoraciones no están abiertas para este pedido.', 'review_error_done' => 'Ya valoraste este pedido.', 'review_error_rating' => 'Elige entre 1 y 5 estrellas.',
    'rating_on_menu' => ':average (:count)',

    // Loyalty & settings
    'loyalty_title' => 'Fidelidad y reseñas', 'loyalty_sub' => 'Recompensa automáticamente a los habituales y pregunta a los clientes qué tal les fue.',
    'loyalty_section' => 'Recompensa de fidelidad', 'loyalty_enabled' => 'Recompensar a los clientes habituales', 'loyalty_enabled_help' => 'Tras cada N pedidos completados, el cliente gana un código promocional de un solo uso que solo él puede usar. Aparece en la página de su pedido y en un correo.',
    'loyalty_every' => 'Recompensar cada … pedidos completados', 'loyalty_type' => 'Recompensa', 'loyalty_value' => 'Valor', 'loyalty_percent_hint' => 'Porcentaje del pedido', 'loyalty_fixed_hint' => 'Importe en tu moneda', 'loyalty_valid' => 'Válido durante (días)',
    'reviews_section' => 'Reseñas', 'reviews_enabled' => 'Pedir a los clientes que valoren los pedidos completados', 'review_email' => 'Pedirlo también por correo cuando el cliente dejó una dirección', 'show_rating' => 'Mostrar la valoración promedio en el menú público',
    'messages_section' => 'Mensajes de texto', 'calling_code' => 'Prefijo telefónico del país', 'calling_code_help' => 'Se usa para enviar SMS y WhatsApp a clientes que escribieron un número local, por ejemplo 52 para México. Déjalo vacío para escribir solo a números que empiezan por +.',
    'email_section' => 'Envío de correo', 'daily_cap' => 'Correos de campaña por día', 'daily_cap_help' => 'La plataforma permite como máximo :max por restaurante al día. Así tu cuenta de correo mantiene buena reputación.',
    'reward_earned' => '¡Ganaste una recompensa!', 'reward_earned_text' => 'Gracias por pedir una y otra vez. Usa este código en tu próximo pedido:', 'reward_valid_until' => 'Válido hasta el :date, una sola vez, solo para ti.',
    'there' => 'hola',

    // Campaigns
    'campaigns_title' => 'Campañas', 'campaigns_sub' => 'Escribe por correo a los clientes que aceptaron saber de ti. Cada mensaje tiene un enlace para darse de baja.',
    'new_campaign' => 'Nueva campaña', 'edit_campaign' => 'Editar campaña', 'campaign_name' => 'Nombre', 'campaign_name_help' => 'Solo tú lo ves.', 'campaign_subject' => 'Asunto', 'campaign_body' => 'Mensaje',
    'campaign_body_help' => 'Texto plano con formato sencillo (**negrita**, [enlace](https://…)). Usa {{name}} y {{restaurant}} para personalizar.', 'campaign_audience' => 'Quién lo recibe', 'min_orders' => 'Solo clientes con al menos … pedidos', 'min_orders_help' => '0 significa todos los que aceptaron.',
    'audience_now' => '{0} Nadie puede recibir esto todavía.|{1} 1 cliente recibirá esto.|[2,*] :count clientes recibirán esto.', 'audience_note' => 'Solo clientes que marcaron la casilla de correo al pagar, tienen una dirección y no se han dado de baja.',
    'campaign_saved' => 'Campaña guardada.', 'campaign_deleted' => 'Campaña eliminada.', 'campaign_sending' => 'Tu campaña va en camino. Puede tardar unos minutos.',
    'campaign_error_sent' => 'Esta campaña ya fue enviada.', 'campaign_error_empty' => 'Nadie puede recibir esta campaña todavía.',
    'status_draft' => 'Borrador', 'status_sending' => 'Enviando', 'status_sent' => 'Enviada', 'campaigns_empty' => 'Aún no hay campañas', 'campaigns_empty_text' => 'Cuenta a tus habituales sobre un plato nuevo o una oferta para días tranquilos.',
    'sent_count' => 'Enviados', 'skipped_count' => 'No enviados', 'recipients' => 'Destinatarios', 'send_test' => 'Envíame una prueba', 'send_now' => 'Enviar campaña', 'send_confirm' => '¿Enviar esta campaña ahora? No se puede deshacer.',
    'test_sent' => 'Correo de prueba enviado a :email.', 'today_room' => 'Hoy pueden salir :count correos más (límite :cap).', 'cap_note' => 'Los clientes que superen el límite de hoy se omiten. Vuelve a enviar mañana con una nueva campaña.', 'sent_on' => 'Enviada el :date',
    'preview' => 'Vista previa', 'unsub_footer' => 'Recibes esto porque aceptaste recibir ofertas de :name.', 'unsub_link' => 'Darse de baja',

    // Unsubscribe page
    'unsub_title' => 'Darse de baja', 'unsub_ask' => '¿Dejar de recibir correos de :name?', 'unsub_ask_text' => 'Ya no recibirás ofertas ni novedades. Las actualizaciones de tus pedidos seguirán llegando.', 'unsub_button' => 'Sí, darme de baja',
    'unsub_done' => 'Te has dado de baja', 'unsub_done_text' => 'Ya no recibirás correos de marketing de :name.', 'unsub_already' => 'Ya te habías dado de baja de :name.',

    // Banners and pop-ups
    'banners_title' => 'Banners y ventanas emergentes', 'banners_sub' => 'Muestra un plato nuevo, una oferta o un aviso en la parte superior de tu menú.', 'new_banner' => 'Nuevo banner', 'edit_banner' => 'Editar banner',
    'banners_empty' => 'Aún no hay banners', 'banners_empty_text' => 'Añade un banner para informar a los clientes de una oferta, un plato nuevo o los horarios.', 'banner_saved' => 'Banner guardado.', 'banner_deleted' => 'Banner eliminado.',
    'banner_title' => 'Titular', 'banner_text' => 'Texto breve', 'banner_link' => 'Enlace', 'banner_link_hint' => 'Una dirección https://, o #cat-12 para ir a una sección del menú. Opcional.', 'banner_button' => 'Texto del botón',
    'banner_starts' => 'Mostrar desde', 'banner_ends' => 'Mostrar hasta', 'banner_popup' => 'Mostrar como ventana emergente (una vez por visita) en lugar de banner', 'popup' => 'Ventana emergente', 'status_off' => 'Desactivado',

    'review_url' => 'Enlace de reseña pública', 'review_url_hint' => 'Por ejemplo tu enlace de reseñas de Google. Se invita a los clientes satisfechos a compartir allí su valoración.', 'review_min' => 'Ofrecerlo desde (estrellas)', 'review_public_invite' => '¡Nos alegra que te haya gustado! ¿Lo compartirías también en Google?', 'review_public_button' => 'Escribir una reseña pública',

    'ai_assistant' => 'Asistente de menú en el menú para clientes (IA)', 'ai_assistant_hint' => 'Los clientes pueden preguntar por platos, dietas y alérgenos. Responde solo con la información de tu menú y cada respuesta usa un crédito de IA.',

    // Growth: channels, segments, happy hour, gift cards, NPS, pixels, link page, flyer, widget.
    'campaign_channel' => 'Canal', 'channel_email' => 'Correo', 'channel_sms' => 'SMS', 'channel_whatsapp' => 'WhatsApp', 'channel_push' => 'Notificación push',
    'channel_push_help' => 'Se envía a los navegadores de clientes que tocaron "Recibir ofertas" en tu menú. El nombre de la campaña es el título y el texto es el mensaje (180 caracteres como máximo). Los segmentos no se aplican.', 'push_offer' => 'Recibir ofertas', 'push_on' => 'Recibirás nuestras ofertas.', 'push_denied' => 'Las notificaciones están bloqueadas o no son compatibles con este navegador.',
    'channel_help' => 'Se envía a clientes que aceptaron marketing y dejaron un número de teléfono. Requiere un proveedor de SMS/WhatsApp en Ajustes y usa tu cupo mensual de mensajes. Máximo 600 caracteres; usa {{name}} para el nombre del cliente.',
    'campaign_segment' => 'Audiencia', 'segment_all' => 'Todos', 'segment_new' => 'Clientes nuevos (0-1 pedidos)', 'segment_regulars' => 'Habituales (nivel plata o superior)', 'segment_vip' => 'VIP (nivel oro)', 'segment_lapsed' => 'Clientes inactivos',
    'sms_stop' => 'Para dejar de recibir mensajes: :url',
    'growth_section' => 'Niveles, encuestas y piloto automático', 'tier_silver' => 'Nivel plata desde (pedidos)', 'tier_gold' => 'Nivel oro desde (pedidos)', 'tier_help' => 'Los niveles agrupan a los clientes para las campañas. Se cuentan a partir de los pedidos completados.',
    'nps_enabled' => 'Hacer la pregunta 0-10 "¿nos recomendarías?" junto con la valoración',
    'autopilot_winback' => 'Piloto automático: recuperar clientes inactivos', 'autopilot_help' => 'Una vez al día, los clientes que aceptaron marketing y llevan tiempo sin venir reciben por correo un código personal de un solo uso (como máximo una vez cada 90 días).',
    'autopilot_days' => 'Ausente durante (días)', 'autopilot_percent' => 'Descuento (%)',
    'pixels_section' => 'Seguimiento de anuncios', 'pixels_help' => 'Pega solo el ID. Los scripts se cargan en el menú para clientes y se omiten cuando el cliente envía No rastrear. Menciónalos en tu política de privacidad.',
    'pixel_meta' => 'ID del píxel de Meta (Facebook)', 'pixel_ga' => 'ID de etiqueta de Google', 'pixel_tiktok' => 'ID del píxel de TikTok',
    'links_section' => 'Página de enlaces, volante y botón para el sitio web', 'links_help' => 'Tu página de enlaces para perfiles de redes sociales: :url',
    'link_phone' => 'Teléfono', 'link_whatsapp' => 'Número de WhatsApp', 'link_whatsapp_hint' => 'Dígitos con prefijo del país, p. ej. 5215512345678', 'link_instagram' => 'URL de Instagram', 'link_facebook' => 'URL de Facebook', 'link_website' => 'URL del sitio web',
    'link_label_menu' => 'Ver el menú y pedir', 'link_label_whatsapp' => 'Chatear por WhatsApp', 'link_label_phone' => 'Llámanos', 'link_label_instagram' => 'Instagram', 'link_label_facebook' => 'Facebook', 'link_label_website' => 'Nuestro sitio web', 'link_label_review' => 'Deja una reseña',
    'flyer_open' => 'Volante imprimible', 'flyer_print' => 'Imprimir', 'flyer_scan' => 'Escanea para ver nuestro menú y pedir', 'widget_open' => 'Botón para el sitio web', 'widget_button' => 'Pide en línea',
    'widget_title' => 'Botón para el sitio web', 'widget_sub' => 'Permite que los visitantes de tu propio sitio web abran el menú sin salir de la página.', 'widget_button_title' => 'Botón flotante "Pide en línea"', 'widget_button_help' => 'Pégalo antes de la etiqueta de cierre del body de tu sitio web.',
    'widget_iframe_title' => 'Incrustar el menú en una página', 'widget_iframe_help' => 'O muestra el menú completo dentro de una página.',
    'nps_question' => '¿Qué tan probable es que nos recomiendes a un amigo?', 'nps_low' => 'Poco probable', 'nps_high' => 'Muy probable',
    'nps_score' => 'Net Promoter Score', 'nps_breakdown' => ':p promotores, :n pasivos, :d detractores',
    'templates' => 'Empezar desde una plantilla', 'template_first_order' => 'Bienvenida 10%', 'template_weekend' => 'Fin de semana 15%', 'template_big_basket' => 'Bono por pedido grande', 'template_flash' => 'Oferta relámpago 20%',
    'pricing_title' => 'Happy hour', 'pricing_sub' => 'Descuentos automáticos para ciertos días y horas. El servidor los aplica al calcular el carrito; gana la mejor regla y las reglas nunca se acumulan.',
    'rule_name' => 'Nombre', 'rule_name_ph' => 'Bebidas en happy hour', 'rule_percent' => 'Porcentaje de descuento', 'rule_from' => 'Desde', 'rule_to' => 'Hasta', 'rule_scope' => 'Se aplica a', 'rule_all_menu' => 'Todo el menú',
    'rule_days' => 'Días', 'rule_days_help' => 'Déjalos todos sin marcar para todos los días.', 'rule_add' => 'Añadir regla', 'rule_saved' => 'Regla guardada.', 'rule_deleted' => 'Regla eliminada.', 'every_day' => 'Todos los días',
    'rules_empty' => 'Aún no hay happy hour', 'rules_empty_text' => 'Añade una regla como 20% de descuento en bebidas entre semana de 16:00 a 18:00.', 'delete_rule_confirm' => '¿Eliminar esta regla?',
    'day_0' => 'Dom', 'day_1' => 'Lun', 'day_2' => 'Mar', 'day_3' => 'Mié', 'day_4' => 'Jue', 'day_5' => 'Vie', 'day_6' => 'Sáb',
    'gifts_title' => 'Tarjetas de regalo', 'gifts_sub' => 'Vende o regala tarjetas de regalo. Los clientes escriben el código al pagar y el saldo se va gastando.',
    'gift_amount' => 'Importe', 'gift_valid_days' => 'Válida durante (días)', 'gift_valid_help' => 'Vacío = no caduca.', 'gift_email' => 'Enviar a (correo)', 'gift_email_help' => 'Opcional. El destinatario recibe el código por correo.', 'gift_note' => 'Mensaje',
    'gift_issue' => 'Emitir tarjeta de regalo', 'gift_issued' => 'Tarjeta de regalo :code creada.', 'gifts_empty' => 'Aún no hay tarjetas de regalo', 'gifts_empty_text' => 'Emite una arriba y entrega el código al comprador.',
    'gift_balance' => 'Saldo :balance de :initial', 'gift_expires' => 'caduca el :date',
    'weekly_digest' => 'Enviarme por correo un resumen de la semana pasada cada lunes',
    'privacy_section' => 'Privacidad', 'retention_months' => 'Borrar los datos personales de los clientes después de (meses)',
    'retention_help' => '0 lo conserva todo. De lo contrario, los clientes que no han pedido en ese tiempo se eliminan y su nombre, teléfono, correo y dirección se borran de los pedidos antiguos. Los pedidos en sí (importes, platos) se conservan para tu contabilidad.',
    'segment_birthday' => 'Cumpleaños este mes', 'autopilot_birthday' => 'Piloto automático: detalle de cumpleaños', 'birthday_percent' => 'Descuento de cumpleaños (%)',
    'segments_title' => 'Segmentos', 'segments_sub' => 'Guarda una audiencia una vez y elígela en cualquier campaña. Un cliente debe cumplir todas las reglas que rellenes.',
    'segment_name' => 'Nombre', 'segment_name_ph' => 'Habituales que dejaron de venir', 'segment_rules_help' => 'Deja una regla vacía para ignorarla. Solo se contacta a clientes que aceptaron marketing.', 'segment_save' => 'Guardar segmento',
    'rule_min_orders' => 'Al menos esta cantidad de pedidos', 'rule_max_orders' => 'Como máximo esta cantidad de pedidos', 'rule_min_spent' => 'Gastó al menos', 'rule_active_within' => 'Pidió en los últimos (días)', 'rule_inactive_for' => 'Sin pedir desde hace (días)', 'rule_joined_within' => 'Nuevo en los últimos (días)',
    'rule_birthday' => 'Cumpleaños', 'rule_birthday_this' => 'Este mes', 'rule_birthday_next' => 'El mes que viene', 'rule_tier' => 'Nivel',
    'rule_value_this_month' => 'este mes', 'rule_value_next_month' => 'el mes que viene', 'rule_value_bronze' => 'bronce', 'rule_value_silver' => 'plata', 'rule_value_gold' => 'oro',
    'rule_label_min_orders' => ':value+ pedidos', 'rule_label_max_orders' => 'hasta :value pedidos', 'rule_label_min_spent' => 'gastó :value+', 'rule_label_active_within_days' => 'pidió en los últimos :value días', 'rule_label_inactive_for_days' => 'sin pedir hace :value+ días',
    'rule_label_joined_within_days' => 'nuevo en los últimos :value días', 'rule_label_birthday' => 'cumpleaños :value', 'rule_label_tier' => 'nivel :value',
    'segment_everyone' => 'Todos', 'segment_reach' => '{0} no se puede contactar a nadie|{1} se puede contactar a :count cliente|[2,*] se puede contactar a :count clientes', 'segment_saved' => 'Segmento guardado.', 'segment_deleted' => 'Segmento eliminado.', 'segment_delete_confirm' => '¿Eliminar este segmento?', 'segments_empty' => 'No hay segmentos guardados', 'segments_empty_text' => 'Crea uno arriba y elígelo al redactar una campaña.',
];
