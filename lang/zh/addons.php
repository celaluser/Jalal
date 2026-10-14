<?php

return [
    'title' => '插件', 'sub' => '以签名包形式提供的扩展功能。插件拥有应用的完全访问权限，请仅安装来自可信来源的插件。',
    'install' => '安装插件', 'install_help' => '上传您收到的 .zip 文件。系统会先校验签名和每个文件。新插件默认处于关闭状态。',
    'trust_warning' => '插件可以读取和修改一切内容，包括您的数据库。请先备份。', 'install_button' => '检查并安装',
    'installed_title' => '已安装的插件', 'installed' => '已安装 :name。开启后即可使用。', 'updated' => '插件已更新。', 'removed' => '插件已移除。其数据仍保留在数据库中。',
    'on' => '开启', 'off' => '关闭', 'turn_on' => '开启', 'turn_off' => '关闭', 'remove_confirm' => '确定移除此插件？其文件将被删除，数据库表会保留。',
    'empty' => '尚未安装插件', 'empty_text' => '您购买或开发的插件会显示在这里。',
    'not_an_addon' => '此包不是插件（所有文件必须位于 addons/<name>/ 内）。', 'invalid_manifest' => '插件描述文件（addon.json）缺失或无效。',
    'needs_core' => '需要应用版本 :version 或更高。', 'provider_missing' => '无法加载插件代码。', 'not_found' => '未找到插件。',
];
