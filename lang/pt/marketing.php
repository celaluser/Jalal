<?php

return [
    // Customers
    'customers_title' => 'Clientes', 'customers_sub' => 'Todos que deixaram seus dados em um pedido. Construa fidelidade, identifique os habituais e envie mensagens a quem concordou.',
    'search_customers' => 'Buscar nome, e-mail ou telefone', 'filter_all' => 'Todos', 'filter_opted' => 'Aceitaram marketing', 'filter_repeat' => 'Habituais (2+ pedidos)',
    'sort_recent' => 'Último pedido', 'sort_orders' => 'Mais pedidos', 'sort_spent' => 'Maiores gastos', 'sort_name' => 'Nome',
    'col_customer' => 'Cliente', 'col_orders' => 'Pedidos', 'col_spent' => 'Gasto', 'col_last' => 'Último pedido', 'col_marketing' => 'Marketing',
    'stat_all' => 'Clientes', 'stat_opted' => 'Alcançáveis por e-mail', 'stat_repeat' => 'Habituais',
    'consent_yes' => 'Aceitou', 'consent_no' => 'Não', 'consent_unsub' => 'Cancelou a inscrição', 'anonymous' => 'Convidado', 'export' => 'Exportar CSV',
    'customers_empty' => 'Nenhum cliente ainda', 'customers_empty_text' => 'Os clientes aparecem aqui quando deixam nome, telefone ou e-mail em um pedido.',
    'customer_since' => 'Cliente desde :date', 'agreed_on' => 'Aceitou marketing em :date', 'unsubscribed_on' => 'Cancelou a inscrição em :date', 'no_consent' => 'Não aceitou receber e-mails de marketing.',
    'notes' => 'Anotações privadas', 'notes_help' => 'Só a sua equipe vê isto. Alergias, preferências, qualquer coisa que valha lembrar.', 'order_history' => 'Pedidos', 'no_orders' => 'Nenhum pedido.',
    'delete_customer' => 'Excluir este cliente', 'delete_help' => 'Remove o registro e apaga nome, telefone, e-mail e endereço dos pedidos dele. Os números de vendas permanecem. Esta ação não pode ser desfeita.', 'delete_confirm' => 'Excluir este cliente e apagar seus dados pessoais?',
    'customer_deleted' => 'Cliente excluído e dados pessoais apagados.', 'avg_order' => 'Ticket médio',

    // Promo codes
    'promos_title' => 'Códigos promocionais', 'promos_sub' => 'Códigos de desconto que seus clientes digitam no checkout. Não confundir com os cupons de assinatura da plataforma.',
    'new_promo' => 'Novo código', 'edit_promo' => 'Editar código', 'promo_code' => 'Código', 'promo_desc' => 'Descrição', 'promo_desc_help' => 'Só você vê isto.',
    'promo_type' => 'Tipo de desconto', 'type_percent' => 'Porcentagem de desconto', 'type_fixed' => 'Valor de desconto', 'promo_value' => 'Valor', 'promo_min' => 'Pedido mínimo', 'promo_max_uses' => 'Máximo de usos', 'promo_max_help' => 'Deixe vazio para ilimitado.',
    'promo_starts' => 'Início', 'promo_ends' => 'Término', 'promo_active' => 'Ativo', 'promo_uses' => 'Usado', 'promo_status' => 'Status',
    'status_active' => 'Ativo', 'status_off' => 'Desativado', 'status_expired' => 'Expirado', 'status_scheduled' => 'Agendado', 'status_used_up' => 'Esgotado', 'reward_badge' => 'Recompensa de fidelidade', 'reward_for' => 'Para :name',
    'promo_saved' => 'Código promocional salvo.', 'promo_deleted' => 'Código promocional excluído.', 'promos_empty' => 'Nenhum código promocional ainda', 'promos_empty_text' => 'Crie um código como BEMVINDO10 e compartilhe nas redes sociais ou nas suas campanhas.',
    'turn_off' => 'Desativar', 'turn_on' => 'Ativar', 'delete_promo_confirm' => 'Excluir este código promocional?', 'min_order_label' => 'a partir de :amount',
    'reward_percent' => ':value% de desconto', 'reward_fixed' => ':amount de desconto',

    // Guest checkout
    'promo_field' => 'Código promocional', 'promo_apply' => 'Aplicar', 'promo_remove' => 'Remover', 'promo_applied' => 'Código :code aplicado: :amount de desconto.', 'promo_discount' => 'Desconto',
    'opt_in' => 'Quero receber ofertas e novidades de :name por e-mail. Posso cancelar a inscrição a qualquer momento.',

    // Reviews
    'reviews_title' => 'Avaliações', 'reviews_sub' => 'O que os clientes disseram depois da refeição.', 'reviews_empty' => 'Nenhuma avaliação ainda', 'reviews_empty_text' => 'Os clientes são convidados a avaliar o pedido depois que ele é concluído.',
    'average' => 'Nota média', 'review_count' => '{1} 1 avaliação|[2,*] :count avaliações', 'filter_low' => 'Notas baixas', 'filter_unanswered' => 'Sem resposta',
    'low_alert' => '{1} 1 nota baixa ainda precisa de resposta.|[2,*] :count notas baixas ainda precisam de resposta.', 'low_flag' => 'Requer atenção',
    'reply' => 'Sua resposta', 'reply_save' => 'Salvar resposta', 'reply_help' => 'Exibida ao cliente na página do pedido dele.', 'hide' => 'Ocultar do cardápio', 'show' => 'Mostrar no cardápio', 'hidden' => 'Oculta', 'order_ref' => 'Pedido :number',
    'public_note' => 'Visível no seu cardápio', 'stars' => '{1} :count estrela|[2,*] :count de 5 estrelas',

    // Guest review form
    'rate_title' => 'Como foi?', 'rate_sub' => 'Avalie o seu pedido. Leva dez segundos.', 'rate_comment' => 'Quer acrescentar algo? (opcional)', 'rate_public' => 'Mostrar minha avaliação (com meu primeiro nome) no cardápio',
    'rate_send' => 'Enviar minha avaliação', 'rate_thanks' => 'Obrigado pelo seu feedback!', 'rate_yours' => 'Sua avaliação', 'rate_reply' => 'Resposta do restaurante',
    'review_error_closed' => 'As avaliações não estão abertas para este pedido.', 'review_error_done' => 'Você já avaliou este pedido.', 'review_error_rating' => 'Escolha de 1 a 5 estrelas.',
    'rating_on_menu' => ':average (:count)',

    // Loyalty & settings
    'loyalty_title' => 'Fidelidade e avaliações', 'loyalty_sub' => 'Recompense os clientes habituais automaticamente e pergunte como foi a experiência.',
    'loyalty_section' => 'Recompensa de fidelidade', 'loyalty_enabled' => 'Recompensar clientes habituais', 'loyalty_enabled_help' => 'A cada N pedidos concluídos, o cliente ganha um código promocional de uso único que só ele pode usar. Ele aparece na página do pedido e em um e-mail.',
    'loyalty_every' => 'Recompensar a cada … pedidos concluídos', 'loyalty_type' => 'Recompensa', 'loyalty_value' => 'Valor', 'loyalty_percent_hint' => 'Porcentagem do pedido', 'loyalty_fixed_hint' => 'Valor na sua moeda', 'loyalty_valid' => 'Válido por (dias)',
    'reviews_section' => 'Avaliações', 'reviews_enabled' => 'Pedir aos clientes que avaliem os pedidos concluídos', 'review_email' => 'Pedir também por e-mail quando o cliente deixou um endereço', 'show_rating' => 'Mostrar a nota média no cardápio público',
    'messages_section' => 'Mensagens de texto', 'calling_code' => 'Código telefônico do país', 'calling_code_help' => 'Usado para enviar SMS e WhatsApp a clientes que digitaram um número local, por exemplo 55 para o Brasil. Deixe vazio para enviar mensagens apenas a números que começam com +.',
    'email_section' => 'Envio de e-mails', 'daily_cap' => 'E-mails de campanha por dia', 'daily_cap_help' => 'A plataforma permite no máximo :max por restaurante por dia. Isso mantém a sua conta de e-mail em boa situação.',
    'reward_earned' => 'Você ganhou uma recompensa!', 'reward_earned_text' => 'Obrigado por pedir sempre. Use este código no seu próximo pedido:', 'reward_valid_until' => 'Válido até :date, uma vez, somente para você.',
    'there' => 'você',

    // Campaigns
    'campaigns_title' => 'Campanhas', 'campaigns_sub' => 'Envie e-mails aos clientes que aceitaram receber novidades suas. Toda mensagem tem um link para cancelar a inscrição.',
    'new_campaign' => 'Nova campanha', 'edit_campaign' => 'Editar campanha', 'campaign_name' => 'Nome', 'campaign_name_help' => 'Só você vê isto.', 'campaign_subject' => 'Assunto', 'campaign_body' => 'Mensagem',
    'campaign_body_help' => 'Texto simples com formatação básica (**negrito**, [link](https://…)). Use {{name}} e {{restaurant}} para personalizar.', 'campaign_audience' => 'Quem recebe', 'min_orders' => 'Somente clientes com pelo menos … pedidos', 'min_orders_help' => '0 significa todos que aceitaram.',
    'audience_now' => '{0} Ninguém pode receber isto ainda.|{1} 1 cliente receberá isto.|[2,*] :count clientes receberão isto.', 'audience_note' => 'Somente clientes que marcaram a caixa de e-mail no checkout, têm um endereço e não cancelaram a inscrição.',
    'campaign_saved' => 'Campanha salva.', 'campaign_deleted' => 'Campanha excluída.', 'campaign_sending' => 'Sua campanha está a caminho. Pode levar alguns minutos.',
    'campaign_error_sent' => 'Esta campanha já foi enviada.', 'campaign_error_empty' => 'Ninguém pode receber esta campanha ainda.',
    'status_draft' => 'Rascunho', 'status_sending' => 'Enviando', 'status_sent' => 'Enviada', 'campaigns_empty' => 'Nenhuma campanha ainda', 'campaigns_empty_text' => 'Conte aos seus clientes habituais sobre um novo prato ou uma oferta para dias de pouco movimento.',
    'sent_count' => 'Enviados', 'skipped_count' => 'Não enviados', 'recipients' => 'Destinatários', 'send_test' => 'Enviar um teste para mim', 'send_now' => 'Enviar campanha', 'send_confirm' => 'Enviar esta campanha agora? Não é possível desfazer.',
    'test_sent' => 'E-mail de teste enviado para :email.', 'today_room' => 'Ainda podem sair :count e-mails hoje (limite de :cap).', 'cap_note' => 'Clientes além do limite de hoje são ignorados. Envie novamente amanhã com uma nova campanha.', 'sent_on' => 'Enviada em :date',
    'preview' => 'Pré-visualização', 'unsub_footer' => 'Você recebe isto porque aceitou receber ofertas de :name.', 'unsub_link' => 'Cancelar inscrição',

    // Unsubscribe page
    'unsub_title' => 'Cancelar inscrição', 'unsub_ask' => 'Parar de receber e-mails de :name?', 'unsub_ask_text' => 'Você não receberá mais ofertas e novidades. As atualizações dos pedidos continuam chegando.', 'unsub_button' => 'Sim, cancelar minha inscrição',
    'unsub_done' => 'Inscrição cancelada', 'unsub_done_text' => 'Você não receberá mais e-mails de marketing de :name.', 'unsub_already' => 'Você já cancelou a inscrição de :name.',

    // Banners and pop-ups
    'banners_title' => 'Banners e pop-ups', 'banners_sub' => 'Mostre um prato novo, uma oferta ou um aviso no topo do seu cardápio.', 'new_banner' => 'Novo banner', 'edit_banner' => 'Editar banner',
    'banners_empty' => 'Nenhum banner ainda', 'banners_empty_text' => 'Adicione um banner para contar aos clientes sobre uma oferta, um prato novo ou o horário de funcionamento.', 'banner_saved' => 'Banner salvo.', 'banner_deleted' => 'Banner excluído.',
    'banner_title' => 'Título', 'banner_text' => 'Texto curto', 'banner_link' => 'Link', 'banner_link_hint' => 'Um endereço https:// ou #cat-12 para ir a uma seção do cardápio. Opcional.', 'banner_button' => 'Texto do botão',
    'banner_starts' => 'Exibir a partir de', 'banner_ends' => 'Exibir até', 'banner_popup' => 'Exibir como pop-up (uma vez por visita) em vez de banner', 'popup' => 'Pop-up', 'status_off' => 'Desativado',

    'review_url' => 'Link de avaliação pública', 'review_url_hint' => 'Por exemplo, o link das suas avaliações no Google. Clientes satisfeitos são convidados a compartilhar a nota por lá.', 'review_min' => 'Oferecer a partir de (estrelas)', 'review_public_invite' => 'Que bom que você gostou! Pode compartilhar isso também no Google?', 'review_public_button' => 'Escrever uma avaliação pública',

    'ai_assistant' => 'Assistente do cardápio para os clientes (IA)', 'ai_assistant_hint' => 'Os clientes podem perguntar sobre pratos, dietas e alérgenos. Ele responde apenas com base no seu cardápio, e cada resposta usa um crédito de IA.',

    // Growth: channels, segments, happy hour, gift cards, NPS, pixels, link page, flyer, widget.
    'campaign_channel' => 'Canal', 'channel_email' => 'E-mail', 'channel_sms' => 'SMS', 'channel_whatsapp' => 'WhatsApp', 'channel_push' => 'Notificação push',
    'channel_push_help' => 'Enviada a navegadores cujos clientes tocaram em "Receber ofertas" no seu cardápio. O nome da campanha é o título e o texto é a mensagem (no máximo 180 caracteres). Os segmentos não se aplicam.', 'push_offer' => 'Receber ofertas', 'push_on' => 'Você receberá nossas ofertas.', 'push_denied' => 'As notificações estão bloqueadas ou não são compatíveis com este navegador.',
    'channel_help' => 'Enviada a clientes que aceitaram marketing e deixaram um telefone. Requer um provedor de SMS/WhatsApp em Configurações e usa sua franquia mensal de mensagens. Máximo de 600 caracteres; use {{name}} para o nome do cliente.',
    'campaign_segment' => 'Público', 'segment_all' => 'Todos', 'segment_new' => 'Clientes novos (0-1 pedido)', 'segment_regulars' => 'Habituais (nível prata ou superior)', 'segment_vip' => 'VIP (nível ouro)', 'segment_lapsed' => 'Clientes inativos',
    'sms_stop' => 'Parar mensagens: :url',
    'growth_section' => 'Níveis, pesquisas e piloto automático', 'tier_silver' => 'Nível prata a partir de (pedidos)', 'tier_gold' => 'Nível ouro a partir de (pedidos)', 'tier_help' => 'Os níveis agrupam clientes para campanhas. São contados a partir dos pedidos concluídos.',
    'nps_enabled' => 'Fazer a pergunta de 0 a 10 "você nos recomendaria" junto com a avaliação',
    'autopilot_winback' => 'Piloto automático: reconquistar clientes inativos', 'autopilot_help' => 'Uma vez por dia, clientes que aceitaram marketing e estão ausentes recebem por e-mail um código pessoal de uso único (no máximo uma vez a cada 90 dias).',
    'autopilot_days' => 'Ausente há (dias)', 'autopilot_percent' => 'Desconto (%)',
    'pixels_section' => 'Rastreamento de anúncios', 'pixels_help' => 'Cole apenas o ID. Os scripts são carregados no cardápio dos clientes e ignorados quando o cliente envia Do Not Track. Mencione-os na sua política de privacidade.',
    'pixel_meta' => 'ID do pixel da Meta (Facebook)', 'pixel_ga' => 'ID da tag do Google', 'pixel_tiktok' => 'ID do pixel do TikTok',
    'links_section' => 'Página de links, panfleto e botão para o site', 'links_help' => 'Sua página de links para perfis de redes sociais: :url',
    'link_phone' => 'Telefone', 'link_whatsapp' => 'Número do WhatsApp', 'link_whatsapp_hint' => 'Dígitos com código do país, ex.: 5511987654321', 'link_instagram' => 'URL do Instagram', 'link_facebook' => 'URL do Facebook', 'link_website' => 'URL do site',
    'link_label_menu' => 'Ver o cardápio e pedir', 'link_label_whatsapp' => 'Conversar no WhatsApp', 'link_label_phone' => 'Ligue para nós', 'link_label_instagram' => 'Instagram', 'link_label_facebook' => 'Facebook', 'link_label_website' => 'Nosso site', 'link_label_review' => 'Deixar uma avaliação',
    'flyer_open' => 'Panfleto para impressão', 'flyer_print' => 'Imprimir', 'flyer_scan' => 'Escaneie para ver nosso cardápio e pedir', 'widget_open' => 'Botão para o site', 'widget_button' => 'Peça online',
    'widget_title' => 'Botão para o site', 'widget_sub' => 'Permita que os visitantes do seu próprio site abram o cardápio sem sair da página.', 'widget_button_title' => 'Botão flutuante "Peça online"', 'widget_button_help' => 'Cole antes da tag de fechamento do body do seu site.',
    'widget_iframe_title' => 'Incorporar o cardápio em uma página', 'widget_iframe_help' => 'Ou exiba o cardápio inteiro dentro de uma página.',
    'nps_question' => 'Qual a probabilidade de você nos recomendar a um amigo?', 'nps_low' => 'Pouco provável', 'nps_high' => 'Muito provável',
    'nps_score' => 'Net Promoter Score', 'nps_breakdown' => ':p promotores, :n neutros, :d detratores',
    'templates' => 'Começar de um modelo', 'template_first_order' => 'Boas-vindas 10%', 'template_weekend' => 'Fim de semana 15%', 'template_big_basket' => 'Bônus para pedido grande', 'template_flash' => 'Oferta relâmpago 20%',
    'pricing_title' => 'Happy hour', 'pricing_sub' => 'Descontos automáticos para certos dias e horários. São aplicados pelo servidor ao calcular o carrinho; vale a melhor regra e as regras nunca se acumulam.',
    'rule_name' => 'Nome', 'rule_name_ph' => 'Bebidas no happy hour', 'rule_percent' => 'Porcentagem de desconto', 'rule_from' => 'De', 'rule_to' => 'Até', 'rule_scope' => 'Aplica-se a', 'rule_all_menu' => 'Todo o cardápio',
    'rule_days' => 'Dias', 'rule_days_help' => 'Deixe todos desmarcados para todos os dias.', 'rule_add' => 'Adicionar regra', 'rule_saved' => 'Regra salva.', 'rule_deleted' => 'Regra excluída.', 'every_day' => 'Todos os dias',
    'rules_empty' => 'Nenhum happy hour ainda', 'rules_empty_text' => 'Adicione uma regra como 20% de desconto em bebidas nos dias úteis das 16:00 às 18:00.', 'delete_rule_confirm' => 'Excluir esta regra?',
    'day_0' => 'Dom', 'day_1' => 'Seg', 'day_2' => 'Ter', 'day_3' => 'Qua', 'day_4' => 'Qui', 'day_5' => 'Sex', 'day_6' => 'Sáb',
    'gifts_title' => 'Vales-presente', 'gifts_sub' => 'Venda ou dê vales-presente. Os clientes digitam o código no checkout e o saldo vai sendo consumido.',
    'gift_amount' => 'Valor', 'gift_valid_days' => 'Válido por (dias)', 'gift_valid_help' => 'Vazio = nunca expira.', 'gift_email' => 'Enviar para (e-mail)', 'gift_email_help' => 'Opcional. O destinatário recebe o código por e-mail.', 'gift_note' => 'Mensagem',
    'gift_issue' => 'Emitir vale-presente', 'gift_issued' => 'Vale-presente :code criado.', 'gifts_empty' => 'Nenhum vale-presente ainda', 'gifts_empty_text' => 'Emita um acima e entregue o código ao comprador.',
    'gift_balance' => 'Saldo de :balance de :initial', 'gift_expires' => 'expira em :date',
    'weekly_digest' => 'Enviar por e-mail um resumo da semana passada toda segunda-feira',
    'privacy_section' => 'Privacidade', 'retention_months' => 'Apagar dados pessoais dos clientes após (meses)',
    'retention_help' => '0 mantém tudo. Caso contrário, clientes que não pedem há esse tempo são excluídos e seu nome, telefone, e-mail e endereço são removidos dos pedidos antigos. Os pedidos em si (valores, pratos) permanecem para a sua contabilidade.',
    'segment_birthday' => 'Aniversário neste mês', 'autopilot_birthday' => 'Piloto automático: mimo de aniversário', 'birthday_percent' => 'Desconto de aniversário (%)',
    'segments_title' => 'Segmentos', 'segments_sub' => 'Salve um público uma vez e escolha-o em qualquer campanha. Um cliente deve atender a todas as regras que você preencher.',
    'segment_name' => 'Nome', 'segment_name_ph' => 'Habituais que sumiram', 'segment_rules_help' => 'Deixe uma regra vazia para ignorá-la. Só são contatados clientes que aceitaram marketing.', 'segment_save' => 'Salvar segmento',
    'rule_min_orders' => 'Pelo menos esta quantidade de pedidos', 'rule_max_orders' => 'No máximo esta quantidade de pedidos', 'rule_min_spent' => 'Gastou pelo menos', 'rule_active_within' => 'Pediu nos últimos (dias)', 'rule_inactive_for' => 'Sem pedir há (dias)', 'rule_joined_within' => 'Novo nos últimos (dias)',
    'rule_birthday' => 'Aniversário', 'rule_birthday_this' => 'Neste mês', 'rule_birthday_next' => 'No próximo mês', 'rule_tier' => 'Nível',
    'rule_value_this_month' => 'neste mês', 'rule_value_next_month' => 'no próximo mês', 'rule_value_bronze' => 'bronze', 'rule_value_silver' => 'prata', 'rule_value_gold' => 'ouro',
    'rule_label_min_orders' => ':value+ pedidos', 'rule_label_max_orders' => 'até :value pedidos', 'rule_label_min_spent' => 'gastou :value+', 'rule_label_active_within_days' => 'pediu nos últimos :value dias', 'rule_label_inactive_for_days' => 'inativo há :value+ dias',
    'rule_label_joined_within_days' => 'novo nos últimos :value dias', 'rule_label_birthday' => 'aniversário :value', 'rule_label_tier' => 'nível :value',
    'segment_everyone' => 'Todos', 'segment_reach' => '{0} ninguém pode ser alcançado|{1} :count cliente pode ser alcançado|[2,*] :count clientes podem ser alcançados', 'segment_saved' => 'Segmento salvo.', 'segment_deleted' => 'Segmento excluído.', 'segment_delete_confirm' => 'Excluir este segmento?', 'segments_empty' => 'Nenhum segmento salvo', 'segments_empty_text' => 'Crie um acima e depois escolha-o ao escrever uma campanha.',
];
