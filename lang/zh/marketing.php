<?php

return [
    // Customers
    'customers_title' => '顾客', 'customers_sub' => '所有在下单时留下信息的人。培养忠诚度、识别常客，并向同意接收的人发送消息。',
    'search_customers' => '搜索姓名、邮箱或电话', 'filter_all' => '全部', 'filter_opted' => '同意营销', 'filter_repeat' => '常客（2 单以上）',
    'sort_recent' => '最近订单', 'sort_orders' => '订单最多', 'sort_spent' => '消费最高', 'sort_name' => '姓名',
    'col_customer' => '顾客', 'col_orders' => '订单', 'col_spent' => '消费', 'col_last' => '最近订单', 'col_marketing' => '营销',
    'stat_all' => '顾客', 'stat_opted' => '可通过邮件联系', 'stat_repeat' => '常客',
    'consent_yes' => '已同意', 'consent_no' => '否', 'consent_unsub' => '已退订', 'anonymous' => '访客', 'export' => '导出 CSV',
    'customers_empty' => '暂无顾客', 'customers_empty_text' => '顾客在下单时留下姓名、电话或邮箱后会显示在这里。',
    'customer_since' => '自 :date 起成为顾客', 'agreed_on' => ':date 同意接收营销信息', 'unsubscribed_on' => ':date 退订', 'no_consent' => '尚未同意接收营销邮件。',
    'notes' => '私人备注', 'notes_help' => '仅您的团队可见。过敏、偏好，任何值得记住的内容。', 'order_history' => '订单', 'no_orders' => '暂无订单。',
    'delete_customer' => '删除此顾客', 'delete_help' => '删除该记录，并从其订单中清除姓名、电话、邮箱和地址。销售数据保留。此操作无法撤销。', 'delete_confirm' => '删除此顾客并清除其个人信息？',
    'customer_deleted' => '顾客已删除，个人信息已清除。', 'avg_order' => '平均订单额',

    // Promo codes
    'promos_title' => '优惠码', 'promos_sub' => '顾客在结账时输入的折扣码。请勿与平台的订阅优惠券混淆。',
    'new_promo' => '新建优惠码', 'edit_promo' => '编辑优惠码', 'promo_code' => '优惠码', 'promo_desc' => '描述', 'promo_desc_help' => '仅您可见。',
    'promo_type' => '折扣类型', 'type_percent' => '按百分比减', 'type_fixed' => '按金额减', 'promo_value' => '数值', 'promo_min' => '最低订单额', 'promo_max_uses' => '最大使用次数', 'promo_max_help' => '留空表示不限。',
    'promo_starts' => '开始', 'promo_ends' => '结束', 'promo_active' => '启用', 'promo_uses' => '已使用', 'promo_status' => '状态',
    'status_active' => '生效中', 'status_off' => '已关闭', 'status_expired' => '已过期', 'status_scheduled' => '未开始', 'status_used_up' => '已用完', 'reward_badge' => '忠诚奖励', 'reward_for' => '适用于 :name',
    'promo_saved' => '优惠码已保存。', 'promo_deleted' => '优惠码已删除。', 'promos_empty' => '暂无优惠码', 'promos_empty_text' => '创建像 WELCOME10 这样的优惠码，并在社交媒体或营销活动中分享。',
    'turn_off' => '关闭', 'turn_on' => '开启', 'delete_promo_confirm' => '删除此优惠码？', 'min_order_label' => '满 :amount',
    'reward_percent' => '立减 :value%', 'reward_fixed' => '立减 :amount',

    // Guest checkout
    'promo_field' => '优惠码', 'promo_apply' => '应用', 'promo_remove' => '移除', 'promo_applied' => '优惠码 :code 已应用：减 :amount。', 'promo_discount' => '折扣',
    'opt_in' => '通过邮件向我发送 :name 的优惠和资讯。我可以随时退订。',

    // Reviews
    'reviews_title' => '评价', 'reviews_sub' => '顾客用餐后的反馈。', 'reviews_empty' => '暂无评价', 'reviews_empty_text' => '订单完成后，系统会邀请顾客评分。',
    'average' => '平均评分', 'review_count' => '{1} 1 条评价|[2,*] :count 条评价', 'filter_low' => '低分', 'filter_unanswered' => '未回复',
    'low_alert' => '{1} 有 1 条低分评价尚未回复。|[2,*] 有 :count 条低分评价尚未回复。', 'low_flag' => '需要关注',
    'reply' => '您的回复', 'reply_save' => '保存回复', 'reply_help' => '显示在顾客的订单页面上。', 'hide' => '从菜单隐藏', 'show' => '在菜单显示', 'hidden' => '已隐藏', 'order_ref' => '订单 :number',
    'public_note' => '在您的菜单上可见', 'stars' => '{1} :count 星|[2,*] :count / 5 星',

    // Guest review form
    'rate_title' => '体验如何？', 'rate_sub' => '为您的订单评分，只需十秒。', 'rate_comment' => '还有什么要补充的吗？（可选）', 'rate_public' => '在菜单上显示我的评分（含名字）',
    'rate_send' => '提交评分', 'rate_thanks' => '感谢您的反馈！', 'rate_yours' => '您的评分', 'rate_reply' => '餐厅的回复',
    'review_error_closed' => '此订单未开放评分。', 'review_error_done' => '您已为此订单评过分。', 'review_error_rating' => '请选择 1 到 5 星。',
    'rating_on_menu' => ':average（:count）',

    // Loyalty & settings
    'loyalty_title' => '会员与评价', 'loyalty_sub' => '自动奖励常客，并询问顾客的用餐体验。',
    'loyalty_section' => '忠诚奖励', 'loyalty_enabled' => '奖励常客', 'loyalty_enabled_help' => '每完成第 N 单后，顾客会获得一个仅限本人使用的一次性优惠码，显示在其订单页面并通过邮件发送。',
    'loyalty_every' => '每完成多少单后奖励', 'loyalty_type' => '奖励', 'loyalty_value' => '数值', 'loyalty_percent_hint' => '订单金额的百分比', 'loyalty_fixed_hint' => '以您的货币计的金额', 'loyalty_valid' => '有效期（天）',
    'reviews_section' => '评价', 'reviews_enabled' => '邀请顾客为已完成的订单评分', 'review_email' => '顾客留有邮箱时也通过邮件邀请', 'show_rating' => '在公开菜单上显示平均评分',
    'messages_section' => '短信', 'calling_code' => '国家/地区区号', 'calling_code_help' => '用于向输入本地号码的顾客发送 SMS 和 WhatsApp，例如土耳其为 90。留空则只向以 + 开头的号码发送。',
    'email_section' => '邮件发送', 'daily_cap' => '每日营销邮件数', 'daily_cap_help' => '平台允许每家餐厅每天最多 :max 封，以保护您的邮件账号信誉。',
    'reward_earned' => '您获得了一份奖励！', 'reward_earned_text' => '感谢您一次又一次的光顾。下次下单时请使用此优惠码：', 'reward_valid_until' => '有效期至 :date，仅限一次、仅限您使用。',
    'there' => '您好',

    // Campaigns
    'campaigns_title' => '营销活动', 'campaigns_sub' => '向同意接收消息的顾客发送邮件。每封邮件都带有退订链接。',
    'new_campaign' => '新建活动', 'edit_campaign' => '编辑活动', 'campaign_name' => '名称', 'campaign_name_help' => '仅您可见。', 'campaign_subject' => '主题', 'campaign_body' => '内容',
    'campaign_body_help' => '纯文本，支持简单格式（**粗体**、[链接](https://…)）。使用 {{name}} 和 {{restaurant}} 进行个性化。', 'campaign_audience' => '发送对象', 'min_orders' => '仅限订单数至少为…的顾客', 'min_orders_help' => '0 表示所有同意的顾客。',
    'audience_now' => '{0} 暂无人可接收。|{1} 1 位顾客将收到。|[2,*] :count 位顾客将收到。', 'audience_note' => '仅限在结账时勾选邮件选项、留有邮箱且未退订的顾客。',
    'campaign_saved' => '活动已保存。', 'campaign_deleted' => '活动已删除。', 'campaign_sending' => '您的活动正在发送，可能需要几分钟。',
    'campaign_error_sent' => '此活动已发送过。', 'campaign_error_empty' => '暂无人可接收此活动。',
    'status_draft' => '草稿', 'status_sending' => '发送中', 'status_sent' => '已发送', 'campaigns_empty' => '暂无活动', 'campaigns_empty_text' => '向常客介绍新菜品，或淡日优惠。',
    'sent_count' => '已发送', 'skipped_count' => '未发送', 'recipients' => '收件人', 'send_test' => '给我发测试', 'send_now' => '发送活动', 'send_confirm' => '现在发送此活动？发送后无法撤销。',
    'test_sent' => '测试邮件已发送至 :email。', 'today_room' => '今天还可再发 :count 封邮件（上限 :cap）。', 'cap_note' => '超出今日上限的顾客会被跳过，请明天另建活动再次发送。', 'sent_on' => '发送于 :date',
    'preview' => '预览', 'unsub_footer' => '您收到此邮件，是因为您同意接收 :name 的优惠。', 'unsub_link' => '退订',

    // Unsubscribe page
    'unsub_title' => '退订', 'unsub_ask' => '不再接收 :name 的邮件？', 'unsub_ask_text' => '您将不再收到优惠和资讯，订单通知仍会送达。', 'unsub_button' => '是的，退订',
    'unsub_done' => '您已退订', 'unsub_done_text' => '您将不再收到 :name 的营销邮件。', 'unsub_already' => '您已退订 :name。',

    // Banners and pop-ups
    'banners_title' => '横幅与弹窗', 'banners_sub' => '在菜单顶部展示新菜品、优惠或通知。', 'new_banner' => '新建横幅', 'edit_banner' => '编辑横幅',
    'banners_empty' => '暂无横幅', 'banners_empty_text' => '添加横幅，告知顾客优惠、新菜品或营业时间。', 'banner_saved' => '横幅已保存。', 'banner_deleted' => '横幅已删除。',
    'banner_title' => '标题', 'banner_text' => '简短文字', 'banner_link' => '链接', 'banner_link_hint' => 'https:// 地址，或用 #cat-12 跳转到菜单的某个分区。可选。', 'banner_button' => '按钮文字',
    'banner_starts' => '开始显示', 'banner_ends' => '结束显示', 'banner_popup' => '以弹窗形式显示（每次访问一次），而非横幅', 'popup' => '弹窗', 'status_off' => '关',

    'review_url' => '公开评价链接', 'review_url_hint' => '例如您的 Google 评价链接。满意的顾客会被邀请在那里分享评分。', 'review_min' => '从多少星开始邀请', 'review_public_invite' => '很高兴您喜欢！愿意也在 Google 上分享吗？', 'review_public_button' => '撰写公开评价',

    'ai_assistant' => '顾客菜单上的菜单助手（AI）', 'ai_assistant_hint' => '顾客可以询问菜品、饮食和过敏原。它只根据您的菜单回答，每次回答消耗 1 个 AI 额度。',

    // Growth: channels, segments, happy hour, gift cards, NPS, pixels, link page, flyer, widget.
    'campaign_channel' => '渠道', 'channel_email' => '邮件', 'channel_sms' => 'SMS', 'channel_whatsapp' => 'WhatsApp', 'channel_push' => '推送通知',
    'channel_push_help' => '发送给在您菜单上点击“获取优惠”的顾客的浏览器。活动名称为标题，文字为消息内容（最多 180 个字符）。客群设置不适用。', 'push_offer' => '获取优惠', 'push_on' => '您将收到我们的优惠。', 'push_denied' => '此浏览器已屏蔽或不支持通知。',
    'channel_help' => '发送给同意营销并留有手机号的顾客。需要在设置中配置 SMS/WhatsApp 服务商，并占用您每月的消息额度。最多 600 个字符；用 {{name}} 代表顾客姓名。',
    'campaign_segment' => '受众', 'segment_all' => '所有人', 'segment_new' => '新顾客（0-1 单）', 'segment_regulars' => '常客（银卡及以上）', 'segment_vip' => 'VIP（金卡）', 'segment_lapsed' => '流失顾客',
    'sms_stop' => '退订消息：:url',
    'growth_section' => '等级、调查与自动化', 'tier_silver' => '银卡起始订单数', 'tier_gold' => '金卡起始订单数', 'tier_help' => '等级用于为营销活动分组，按已完成订单统计。',
    'nps_enabled' => '评分时同时询问 0-10 分的“您会推荐我们吗”',
    'autopilot_winback' => '自动化：挽回流失顾客', 'autopilot_help' => '每天一次，向同意营销且已久未光顾的顾客通过邮件发送专属一次性优惠码（每 90 天最多一次）。',
    'autopilot_days' => '未光顾天数', 'autopilot_percent' => '折扣（%）',
    'pixels_section' => '广告追踪', 'pixels_help' => '只需粘贴 ID。脚本会在顾客菜单上加载，顾客发送“请勿追踪”时会跳过。请在隐私政策中说明。',
    'pixel_meta' => 'Meta（Facebook）像素 ID', 'pixel_ga' => 'Google 标签 ID', 'pixel_tiktok' => 'TikTok 像素 ID',
    'links_section' => '链接页、传单和网站按钮', 'links_help' => '用于社交主页的简介链接页：:url',
    'link_phone' => '电话', 'link_whatsapp' => 'WhatsApp 号码', 'link_whatsapp_hint' => '含国家区号的数字，如 905321112233', 'link_instagram' => 'Instagram 链接', 'link_facebook' => 'Facebook 链接', 'link_website' => '网站链接',
    'link_label_menu' => '查看菜单并点单', 'link_label_whatsapp' => '在 WhatsApp 上聊天', 'link_label_phone' => '致电我们', 'link_label_instagram' => 'Instagram', 'link_label_facebook' => 'Facebook', 'link_label_website' => '我们的网站', 'link_label_review' => '留下评价',
    'flyer_open' => '可打印传单', 'flyer_print' => '打印', 'flyer_scan' => '扫码查看菜单并点单', 'widget_open' => '网站按钮', 'widget_button' => '在线点单',
    'widget_title' => '网站按钮', 'widget_sub' => '让您自己网站的访客无需离开页面即可打开菜单。', 'widget_button_title' => '悬浮“在线点单”按钮', 'widget_button_help' => '粘贴到您网站的 body 结束标签之前。',
    'widget_iframe_title' => '在页面中嵌入菜单', 'widget_iframe_help' => '或在页面内展示整份菜单。',
    'nps_question' => '您有多大可能向朋友推荐我们？', 'nps_low' => '不太可能', 'nps_high' => '非常可能',
    'nps_score' => '净推荐值', 'nps_breakdown' => ':p 位推荐者，:n 位中立者，:d 位贬损者',
    'templates' => '从模板开始', 'template_first_order' => '新人 9 折', 'template_weekend' => '周末 85 折', 'template_big_basket' => '大单奖励', 'template_flash' => '限时抢购 8 折',
    'pricing_title' => '欢乐时光', 'pricing_sub' => '针对特定日期和时段的自动折扣。购物车计价时由服务器应用；取最优规则，规则不叠加。',
    'rule_name' => '名称', 'rule_name_ph' => '欢乐时光饮品', 'rule_percent' => '折扣百分比', 'rule_from' => '从', 'rule_to' => '到', 'rule_scope' => '适用于', 'rule_all_menu' => '整份菜单',
    'rule_days' => '日期', 'rule_days_help' => '全部不勾选表示每天。', 'rule_add' => '添加规则', 'rule_saved' => '规则已保存。', 'rule_deleted' => '规则已删除。', 'every_day' => '每天',
    'rules_empty' => '暂无欢乐时光', 'rules_empty_text' => '添加规则，例如工作日 16:00 至 18:00 饮品 8 折。', 'delete_rule_confirm' => '删除此规则？',
    'day_0' => '周日', 'day_1' => '周一', 'day_2' => '周二', 'day_3' => '周三', 'day_4' => '周四', 'day_5' => '周五', 'day_6' => '周六',
    'gifts_title' => '礼品卡', 'gifts_sub' => '销售或赠送礼品卡。顾客在结账时输入卡码，余额会被逐步扣减。',
    'gift_amount' => '金额', 'gift_valid_days' => '有效期（天）', 'gift_valid_help' => '留空 = 永不过期。', 'gift_email' => '发送至（邮箱）', 'gift_email_help' => '可选。收件人会通过邮件收到卡码。', 'gift_note' => '留言',
    'gift_issue' => '发行礼品卡', 'gift_issued' => '礼品卡 :code 已创建。', 'gifts_empty' => '暂无礼品卡', 'gifts_empty_text' => '在上方发行一张，并把卡码交给购买者。',
    'gift_balance' => '余额 :balance / :initial', 'gift_expires' => '到期 :date',
    'weekly_digest' => '每周一用邮件向我发送上周摘要',
    'privacy_section' => '隐私', 'retention_months' => '多少个月后清除顾客个人数据',
    'retention_help' => '0 表示全部保留。否则，超过该时长未下单的顾客将被删除，其姓名、电话、邮箱和地址也会从旧订单中清除。订单本身（金额、菜品）会保留以便记账。',
    'segment_birthday' => '本月生日', 'autopilot_birthday' => '自动化：生日礼遇', 'birthday_percent' => '生日折扣（%）',
    'segments_title' => '客群', 'segments_sub' => '保存一次受众，即可在任意营销活动中选用。顾客必须符合您填写的所有规则。',
    'segment_name' => '名称', 'segment_name_ph' => '沉寂的常客', 'segment_rules_help' => '规则留空表示忽略。只会联系同意营销的顾客。', 'segment_save' => '保存客群',
    'rule_min_orders' => '订单数至少', 'rule_max_orders' => '订单数至多', 'rule_min_spent' => '消费至少', 'rule_active_within' => '最近多少天内下过单', 'rule_inactive_for' => '多少天未下单', 'rule_joined_within' => '最近多少天内新加入',
    'rule_birthday' => '生日', 'rule_birthday_this' => '本月', 'rule_birthday_next' => '下月', 'rule_tier' => '等级',
    'rule_value_this_month' => '本月', 'rule_value_next_month' => '下月', 'rule_value_bronze' => '铜卡', 'rule_value_silver' => '银卡', 'rule_value_gold' => '金卡',
    'rule_label_min_orders' => ':value 单以上', 'rule_label_max_orders' => '最多 :value 单', 'rule_label_min_spent' => '消费 :value 以上', 'rule_label_active_within_days' => '最近 :value 天内下过单', 'rule_label_inactive_for_days' => '沉寂 :value 天以上',
    'rule_label_joined_within_days' => '最近 :value 天内新加入', 'rule_label_birthday' => '生日 :value', 'rule_label_tier' => '等级 :value',
    'segment_everyone' => '所有人', 'segment_reach' => '{0} 无人可联系|{1} 可联系 :count 位顾客|[2,*] 可联系 :count 位顾客', 'segment_saved' => '客群已保存。', 'segment_deleted' => '客群已删除。', 'segment_delete_confirm' => '删除此客群？', 'segments_empty' => '暂无已保存的客群', 'segments_empty_text' => '在上方创建一个，撰写活动时即可选用。',
];
