<?php

return [
    'title' => '分店',
    'subtitle' => '用一个账号管理多个门店。每个分店有自己的桌台、员工、营业时间，也可按需设置自己的价格和库存。',
    'add' => '添加分店', 'edit' => '编辑分店', 'name' => '分店名称', 'address' => '地址', 'city' => '城市', 'phone' => '电话',
    'is_active' => '接受订单', 'inactive' => '已关闭', 'hours' => '营业时间', 'hours_help' => '留空则与餐厅营业时间一致。',
    'tables_count' => '{0} 无桌台|{1} 1 张桌台|[2,*] :count 张桌台',
    'created' => '分店已添加。', 'deleted' => '分店已删除，其桌台、员工和订单已保留。',
    'limit_reached' => '您的套餐已达到分店数量上限，请升级后再添加。',
    'empty_title' => '暂无分店', 'empty_text' => '添加您的门店，让每个门店拥有自己的桌台、员工和价格。只有一个门店的餐厅无需设置分店。',
    'usage' => ':used / :max 个分店', 'usage_unlimited' => ':used 个分店',
    'prices_stock' => '价格与库存', 'menu_title' => '价格与库存 · :name',
    'menu_help' => '仅为此分店修改价格、隐藏菜品或设置份数。单元格留空则使用主菜单。',
    'col_dish' => '菜品', 'col_price' => '价格', 'col_available' => '供应状态', 'col_stock' => '份数', 'same' => '与主菜单相同',
    'available' => '可供应', 'sold_out' => '已售罄', 'copy_from' => '从其他分店复制', 'copy' => '复制', 'copied' => '已从 :name 复制价格和库存。',
    'switcher' => '分店', 'all' => '所有分店', 'branch' => '分店', 'no_branch' => '无分店',
    'staff_branch' => '分店', 'staff_branch_help' => '固定员工只能看到此分店。经理请保持“所有分店”。',
    'choose_title' => '选择分店', 'choose_text' => '请选择您要点餐的门店。', 'closed_now' => '当前已打烊',
    'closed_banner' => ':name 当前已打烊，营业时间内将重新接单。',
    'pick_branch_pos' => '下单前请先在顶部栏选择分店。',
];
