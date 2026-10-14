<?php

return [
    'title' => 'Complementos', 'sub' => 'Recursos extras entregues como pacotes assinados. Eles são executados com acesso total ao aplicativo, então instale apenas complementos de fontes confiáveis.',
    'install' => 'Instalar um complemento', 'install_help' => 'Envie o arquivo .zip que você recebeu. A assinatura e cada arquivo são verificados antes. Novos complementos começam desativados.',
    'trust_warning' => 'Um complemento pode ler e alterar tudo, inclusive o seu banco de dados. Faça um backup antes.', 'install_button' => 'Verificar e instalar',
    'installed_title' => 'Complementos instalados', 'installed' => ':name instalado. Ative-o para começar a usar.', 'updated' => 'Complemento atualizado.', 'removed' => 'Complemento removido. Os dados dele permanecem no banco de dados.',
    'on' => 'Ativado', 'off' => 'Desativado', 'turn_on' => 'Ativar', 'turn_off' => 'Desativar', 'remove_confirm' => 'Remover este complemento? Os arquivos dele serão excluídos; as tabelas do banco de dados permanecem.',
    'empty' => 'Nenhum complemento instalado', 'empty_text' => 'Os complementos que você comprar ou criar aparecem aqui.',
    'not_an_addon' => 'Este pacote não é um complemento (todos os arquivos devem ficar em addons/<name>/).', 'invalid_manifest' => 'A descrição do complemento (addon.json) está ausente ou é inválida.',
    'needs_core' => 'Requer a versão :version do aplicativo ou superior.', 'provider_missing' => 'Não foi possível carregar o código do complemento.', 'not_found' => 'Complemento não encontrado.',
];
