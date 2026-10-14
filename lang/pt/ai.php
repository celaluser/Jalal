<?php

return [
    'error_not_configured' => 'A IA ainda não está configurada. Peça ao administrador da plataforma para adicionar uma chave de IA.',
    'error_no_credits' => 'Você usou todos os créditos de IA do seu plano neste mês. Eles são renovados no dia 1º, ou você pode fazer upgrade do plano.',
    'error_provider_error' => 'O serviço de IA não respondeu. Tente novamente em instantes.',
    'error_rate_limited' => 'O serviço de IA está ocupado no momento. Tente novamente em um minuto.',
    'error_bad_response' => 'A IA não conseguiu gerar uma resposta utilizável desta vez. Tente novamente ou acrescente mais detalhes.',
    'error_limit_categories' => 'Importar este cardápio ultrapassaria o limite de categorias do seu plano.',
    'error_limit_products' => 'Importar este cardápio ultrapassaria o limite de produtos do seu plano.',

    'write' => 'Escrever com IA', 'writing' => 'Escrevendo…', 'hints' => 'Ingredientes ou observações (opcional)', 'tone' => 'Estilo',
    'tone_appetizing' => 'Apetitoso', 'tone_short' => 'Curto e simples', 'tone_elegant' => 'Elegante', 'tone_playful' => 'Descontraído',
    'translate' => 'Traduzir com IA', 'translating' => 'Traduzindo…', 'translate_hint' => 'Preenche os campos vazios dos outros idiomas a partir do seu idioma principal.',
    'suggest_tags' => 'Sugerir alérgenos com IA', 'suggesting' => 'Verificando…',
    'tags_notice' => 'Sugerido pela IA. Confira você mesmo cada alérgeno: os clientes dependem dessa informação.', 'tags_none' => 'Nenhum alérgeno ou etiqueta de dieta foi sugerido.',
    'tags_applied' => 'Marcados: :list',
    'credits_left' => ':count créditos restantes neste mês', 'credits_unlimited' => 'Os créditos de IA não são limitados no seu plano', 'credits_cost' => 'Custa :count crédito(s)',
    'review_note' => 'Confira o texto antes de salvar. A IA pode cometer erros.',

    'import_title' => 'Importar um cardápio com IA', 'import_sub' => 'Cole o seu cardápio de um PDF, de um site ou de um documento. A IA o organiza em categorias e pratos, e você revisa tudo antes de adicionar.',
    'import_paste' => 'Cole o texto do seu cardápio', 'import_placeholder' => "ENTRADAS\nBruschetta - 7,50\nSopa do dia - 6\n\nPRATOS PRINCIPAIS\nPizza margherita, tomate e manjericão - 12", 'import_chars' => ':count de :max caracteres',
    'import_read' => 'Ler meu cardápio', 'import_cost' => 'Custa :count créditos', 'import_review' => 'Revisar', 'import_found' => ':count pratos encontrados',
    'import_no_price' => 'Nenhum preço encontrado: estes pratos são adicionados ocultos até você definir um preço.', 'import_add' => 'Adicionar ao meu cardápio', 'import_adding' => 'Adicionando…',
    'import_done' => 'Adicionadas :categories categorias e :products pratos. :hidden estão ocultos até terem um preço.', 'import_remove' => 'Remover', 'import_again' => 'Recomeçar',
    'import_not_configured_title' => 'A IA ainda não está disponível', 'import_how' => 'Como funciona',
    'import_step1' => 'Cole o texto do seu cardápio.', 'import_step2' => 'A IA o lê e o agrupa. Edite nomes e preços se necessário.', 'import_step3' => 'Adicione ao seu cardápio com um clique.',
    'category' => 'Categoria', 'dish' => 'Nome do prato', 'price' => 'Preço', 'description' => 'Descrição',
    'button_import' => 'Importar com IA',
    'need_name' => 'Digite o nome primeiro.',

    'import_photo' => 'Ou uma foto do cardápio', 'import_photo_cost' => 'Uma foto custa :count créditos de IA (cada página, até 5, é contada). Funciona com cardápios impressos e capturas de tela.', 'import_pdf' => 'Ou um cardápio em PDF', 'import_pdf_hint' => 'Somente PDFs com texto real. Para um PDF digitalizado, tire uma foto da página.',
    'error_bad_file' => 'Não foi possível ler esse arquivo. Tente um JPG, PNG ou PDF nítido.', 'error_pdf_no_text' => 'Este PDF não tem texto legível (provavelmente é uma digitalização). Use a importação por foto.',
    'bulk_title' => 'Traduzir o cardápio inteiro', 'bulk_text' => 'Preenche todas as traduções que faltam de pratos e categorias, em segundo plano. Os idiomas que você bloqueou nunca são alterados. Cada item usa créditos de tradução.', 'bulk_start' => 'Traduzir tudo o que falta',
    'bulk_running' => 'Traduzindo…', 'bulk_done' => 'Concluído: :n textos traduzidos.', 'bulk_stopped' => 'Interrompido antes do fim (sem créditos ou o serviço de IA não está disponível). O que foi feito foi mantido.',
    'draft_reply' => 'Rascunhar resposta com IA', 'draft_note' => 'Leia e edite o rascunho antes de enviar.', 'insights_title' => 'Ideias a partir dos seus números', 'insights_button' => 'Obter ideias', 'insights_note' => 'Escrito por uma IA a partir dos números acima. Trate como sugestões.',
    'assistant_name' => 'Assistente do cardápio', 'assistant_ask' => 'Pergunte sobre o cardápio', 'assistant_placeholder' => 'Tem algo vegetariano? O que combina com o hambúrguer?', 'assistant_send' => 'Enviar', 'assistant_hint' => 'As respostas vêm apenas deste cardápio. Peça à equipe para confirmar alergias.', 'assistant_error' => 'O assistente não está disponível no momento.',
];
