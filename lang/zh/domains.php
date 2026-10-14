<?php

return [
    'title' => '域名', 'subtitle' => '顾客打开您菜单可使用的网址。',
    'default_address' => '常驻地址', 'default_help' => '无论其他如何设置，此链接始终可用。请将它印在 QR 码上。',
    'subdomain' => '子域名', 'subdomain_help' => '字母、数字和连字符，至少 3 个字符。', 'subdomain_off' => '此平台未启用子域名。', 'subdomain_preview' => '您的菜单将在此打开',
    'custom_domain' => '您自己的域名', 'custom_help' => '例如 menu.yourrestaurant.com。三步即可连接。', 'custom_off' => '此平台未启用自定义域名。',
    'custom_upgrade' => '您的套餐不包含自定义域名。', 'upgrade' => '升级套餐', 'remove_domain' => '清空此字段并保存即可断开连接。',
    'save' => '保存', 'status_verified' => '已连接', 'status_pending' => '等待 DNS', 'status_none' => '未设置',
    'step1' => '在上方输入域名并保存。', 'step2' => '在您的域名服务商处添加此 TXT 记录，以证明域名归您所有：', 'step3' => '点击“检查 DNS”。DNS 变更最长可能需要一小时生效。',
    'record_type' => '类型', 'record_host' => '主机', 'record_value' => '值', 'cname_hint' => '还需通过 CNAME 记录将域名指向 :target（或按下方说明操作）。',
    'platform_instructions' => '平台说明', 'check_dns' => '检查 DNS', 'copy' => '复制', 'copied' => '已复制',
    'verified_now' => '您的域名已连接。', 'not_found_yet' => '暂未找到 DNS 记录。请检查后几分钟再试。', 'verified_manual' => '域名已验证',
    'error_upgrade' => '专属地址属于商店功能，请先在商店中获取。', 'error_unavailable' => '您的餐厅无法使用此功能。', 'error_invalid' => '这不是有效的地址。', 'error_reserved' => '该名称为保留名称，请换一个。',
    'error_taken' => '该地址已被其他餐厅使用。', 'error_platform' => '此处不能使用平台自己的域名。',
];
