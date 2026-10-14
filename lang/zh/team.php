<?php

return [
    'title' => '团队', 'subtitle' => '在这家餐厅与您共事的人，以及他们各自的权限。',
    'invite' => '邀请成员', 'invite_title' => '邀请团队成员', 'invite_sub' => '对方会收到邮件，自行设置密码。',
    'name' => '姓名', 'email' => '邮箱地址', 'role' => '角色', 'status' => '状态', 'send_invite' => '发送邀请',
    'owner' => '店主', 'you' => '您', 'status_active' => '正常', 'status_invited' => '已邀请', 'status_disabled' => '已停用',
    'usage' => ':used / :max 位团队成员', 'usage_unlimited' => ':used 位团队成员',
    'limit_note' => '您的套餐已达到团队人数上限。', 'upgrade' => '升级套餐',
    'edit_member' => '编辑团队成员', 'save' => '保存角色',
    'disable' => '停用', 'enable' => '启用', 'disable_help' => '被停用的人无法登录，其历史记录会保留。', 'resend' => '重新发送邀请', 'remove' => '移除', 'remove_confirm' => '将此人从团队中移除？',
    'invited' => '邀请已发送至 :email。', 'resent' => '邀请已再次发送至 :email。', 'removed' => '团队成员已移除。', 'disabled_done' => '账号已停用。', 'enabled_done' => '账号已启用。',
    'error_limit' => '您的套餐已达到团队人数上限，请升级后再邀请更多人。',
    'error_owner' => '店主账号无法在此修改。', 'error_self' => '您不能修改自己的权限。', 'error_role' => '请选择可用的角色之一。',
    'empty_title' => '目前只有您一人', 'empty_text' => '邀请经理、服务员、后厨和收银人员，让每个人都有自己的登录账号。',

    'roles' => '角色', 'roles_title' => '角色', 'roles_sub' => '内置角色可满足大多数餐厅。需要不同权限时可创建自己的角色。',
    'built_in' => '内置角色', 'custom_roles' => '您的角色', 'new_role' => '新建角色', 'edit_role' => '编辑角色', 'role_name' => '角色名称', 'role_permissions' => '该角色可以做什么',
    'role_created' => '角色已创建。', 'role_deleted' => '角色已删除。', 'role_in_use' => '仍有人使用此角色，请先为他们分配其他角色。',
    'role_users' => '{0} 无人使用此角色|{1} 1 人|[2,*] :count 人', 'no_custom' => '暂无自定义角色。',
    'role_manager' => '经理', 'role_waiter' => '服务员', 'role_kitchen' => '后厨', 'role_cashier' => '收银员', 'role_bar' => '吧台', 'role_delivery' => '配送员',
    'role_manager_text' => '管理菜单、桌台、订单和报表。', 'role_waiter_text' => '查看订单和桌台。', 'role_kitchen_text' => '制作订单：标记为制作中和已就绪。', 'role_cashier_text' => '收款并管理订单。', 'role_bar_text' => '制作饮品：在后厨屏幕上查看吧台工位。', 'role_delivery_text' => '查看并配送分配给自己的订单。',
    'perm_group' => ['menu' => '菜单', 'orders' => '订单', 'tables' => '桌台', 'staff' => '团队', 'roles' => '角色', 'reports' => '报表', 'customers' => '顾客', 'marketing' => '营销', 'settings' => '设置', 'payments' => '支付', 'kitchen' => '后厨', 'billing' => '订阅', 'support' => '支持', 'activity' => '操作日志', 'delivery' => '配送'],
];
