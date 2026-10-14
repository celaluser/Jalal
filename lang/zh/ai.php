<?php

return [
    'error_not_configured' => 'AI 尚未设置。请联系平台管理员添加 AI 密钥。',
    'error_no_credits' => '您套餐本月的 AI 额度已用完。额度将于 1 日重置，您也可以升级套餐。',
    'error_provider_error' => 'AI 服务没有响应，请稍后再试。',
    'error_rate_limited' => 'AI 服务当前繁忙，请一分钟后再试。',
    'error_bad_response' => 'AI 这次未能生成可用的结果。请重试，或补充更多细节。',
    'error_limit_categories' => '导入此菜单将超出您套餐的分类数量上限。',
    'error_limit_products' => '导入此菜单将超出您套餐的商品数量上限。',

    'write' => '用 AI 撰写', 'writing' => '撰写中…', 'hints' => '食材或备注（可选）', 'tone' => '风格',
    'tone_appetizing' => '诱人', 'tone_short' => '简短朴实', 'tone_elegant' => '优雅', 'tone_playful' => '俏皮',
    'translate' => '用 AI 翻译', 'translating' => '翻译中…', 'translate_hint' => '根据您的主语言填充其他语言的空白字段。',
    'suggest_tags' => '用 AI 推荐过敏原', 'suggesting' => '检查中…',
    'tags_notice' => '由 AI 推荐。请自行核对每一项过敏原：顾客会依赖这些信息。', 'tags_none' => '未推荐任何过敏原或饮食标签。',
    'tags_applied' => '已勾选：:list',
    'credits_left' => '本月还剩 :count 额度', 'credits_unlimited' => '您的套餐不限 AI 额度', 'credits_cost' => '消耗 :count 额度',
    'review_note' => '保存前请检查文本，AI 可能出错。',

    'import_title' => '用 AI 导入菜单', 'import_sub' => '粘贴来自 PDF、网站或文档的菜单。AI 会将其整理为分类和菜品，添加前您可以检查全部内容。',
    'import_paste' => '粘贴菜单文本', 'import_placeholder' => "前菜\n意式烤面包 - 7.50\n每日例汤 - 6\n\n主菜\n玛格丽特披萨，番茄和罗勒 - 12", 'import_chars' => ':count / :max 个字符',
    'import_read' => '识别我的菜单', 'import_cost' => '消耗 :count 额度', 'import_review' => '检查', 'import_found' => '找到 :count 道菜品',
    'import_no_price' => '未找到价格：这些菜品将以隐藏状态添加，设置价格后才会显示。', 'import_add' => '添加到我的菜单', 'import_adding' => '添加中…',
    'import_done' => '已添加 :categories 个分类和 :products 道菜品，其中 :hidden 道在设置价格前保持隐藏。', 'import_remove' => '移除', 'import_again' => '重新开始',
    'import_not_configured_title' => 'AI 暂不可用', 'import_how' => '使用方法',
    'import_step1' => '粘贴菜单文本。', 'import_step2' => 'AI 读取并分组，必要时可修改名称和价格。', 'import_step3' => '一键添加到您的菜单。',
    'category' => '分类', 'dish' => '菜品名称', 'price' => '价格', 'description' => '描述',
    'button_import' => '用 AI 导入',
    'need_name' => '请先输入名称。',

    'import_photo' => '或上传菜单照片', 'import_photo_cost' => '一张照片消耗 :count AI 额度（每页最多 5 张，按页计算）。适用于印刷菜单和截图。', 'import_pdf' => '或上传 PDF 菜单', 'import_pdf_hint' => '仅支持含真实文字的 PDF。扫描版 PDF 请改为拍摄页面照片。',
    'error_bad_file' => '无法读取该文件。请尝试清晰的 JPG、PNG 或 PDF。', 'error_pdf_no_text' => '此 PDF 没有可读取的文字（可能是扫描件），请改用照片导入。',
    'bulk_title' => '翻译整份菜单', 'bulk_text' => '在后台补全菜品和分类所有缺失的翻译。已锁定的语言不会被改动。每一项都会消耗翻译额度。', 'bulk_start' => '翻译所有缺失内容',
    'bulk_running' => '翻译中…', 'bulk_done' => '完成：已翻译 :n 条文本。', 'bulk_stopped' => '已提前停止（额度用完或 AI 服务不可用），已完成的部分会保留。',
    'draft_reply' => '用 AI 起草回复', 'draft_note' => '发送前请阅读并修改草稿。', 'insights_title' => '来自您数据的建议', 'insights_button' => '获取建议', 'insights_note' => '由 AI 根据上方数据撰写，仅供参考。',
    'assistant_name' => '菜单助手', 'assistant_ask' => '询问菜单', 'assistant_placeholder' => '有素食吗？汉堡配什么好？', 'assistant_send' => '发送', 'assistant_hint' => '回答仅基于本菜单。过敏情况请向店员确认。', 'assistant_error' => '助手暂时不可用。',
];
