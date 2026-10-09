<?php

return [
    // Customers
    'customers_title' => 'Customers', 'customers_sub' => 'Everyone who left their details with an order. Build loyalty, spot regulars, and message those who agreed.',
    'search_customers' => 'Search name, e-mail or phone', 'filter_all' => 'All', 'filter_opted' => 'Agreed to marketing', 'filter_repeat' => 'Regulars (2+ orders)',
    'sort_recent' => 'Latest order', 'sort_orders' => 'Most orders', 'sort_spent' => 'Top spenders', 'sort_name' => 'Name',
    'col_customer' => 'Customer', 'col_orders' => 'Orders', 'col_spent' => 'Spent', 'col_last' => 'Last order', 'col_marketing' => 'Marketing',
    'stat_all' => 'Customers', 'stat_opted' => 'Reachable by e-mail', 'stat_repeat' => 'Regulars',
    'consent_yes' => 'Agreed', 'consent_no' => 'No', 'consent_unsub' => 'Unsubscribed', 'anonymous' => 'Guest', 'export' => 'Export CSV',
    'customers_empty' => 'No customers yet', 'customers_empty_text' => 'Guests appear here when they leave a name, phone or e-mail with an order.',
    'customer_since' => 'Customer since :date', 'agreed_on' => 'Agreed to marketing on :date', 'unsubscribed_on' => 'Unsubscribed on :date', 'no_consent' => 'Has not agreed to marketing e-mails.',
    'notes' => 'Private notes', 'notes_help' => 'Only your team sees this. Allergies, preferences, anything worth remembering.', 'order_history' => 'Orders', 'no_orders' => 'No orders.',
    'delete_customer' => 'Delete this customer', 'delete_help' => 'Removes the record and erases the name, phone, e-mail and address from their orders. Sales figures stay. This cannot be undone.', 'delete_confirm' => 'Delete this customer and erase their personal details?',
    'customer_deleted' => 'Customer deleted and personal details erased.', 'avg_order' => 'Average order',

    // Promo codes
    'promos_title' => 'Promo codes', 'promos_sub' => 'Discount codes your guests enter at checkout. Not to be confused with subscription coupons from the platform.',
    'new_promo' => 'New code', 'edit_promo' => 'Edit code', 'promo_code' => 'Code', 'promo_desc' => 'Description', 'promo_desc_help' => 'Only you see this.',
    'promo_type' => 'Discount type', 'type_percent' => 'Percent off', 'type_fixed' => 'Amount off', 'promo_value' => 'Value', 'promo_min' => 'Minimum order', 'promo_max_uses' => 'Maximum uses', 'promo_max_help' => 'Leave empty for unlimited.',
    'promo_starts' => 'Starts', 'promo_ends' => 'Ends', 'promo_active' => 'Active', 'promo_uses' => 'Used', 'promo_status' => 'Status',
    'status_active' => 'Active', 'status_off' => 'Switched off', 'status_expired' => 'Expired', 'status_scheduled' => 'Scheduled', 'status_used_up' => 'Used up', 'reward_badge' => 'Loyalty reward', 'reward_for' => 'For :name',
    'promo_saved' => 'Promo code saved.', 'promo_deleted' => 'Promo code deleted.', 'promos_empty' => 'No promo codes yet', 'promos_empty_text' => 'Create a code like WELCOME10 and share it on social media or in your campaigns.',
    'turn_off' => 'Switch off', 'turn_on' => 'Switch on', 'delete_promo_confirm' => 'Delete this promo code?', 'min_order_label' => 'from :amount',
    'reward_percent' => ':value% off', 'reward_fixed' => ':amount off',

    // Guest checkout
    'promo_field' => 'Promo code', 'promo_apply' => 'Apply', 'promo_remove' => 'Remove', 'promo_applied' => 'Code :code applied: :amount off.', 'promo_discount' => 'Discount',
    'opt_in' => 'Send me offers and news from :name by e-mail. I can unsubscribe at any time.',

    // Reviews
    'reviews_title' => 'Reviews', 'reviews_sub' => 'What guests said after their meal.', 'reviews_empty' => 'No reviews yet', 'reviews_empty_text' => 'Guests are invited to rate their order once it is completed.',
    'average' => 'Average rating', 'review_count' => '{1} 1 review|[2,*] :count reviews', 'filter_low' => 'Low ratings', 'filter_unanswered' => 'Unanswered',
    'low_alert' => '{1} 1 low rating still needs an answer.|[2,*] :count low ratings still need an answer.', 'low_flag' => 'Needs attention',
    'reply' => 'Your reply', 'reply_save' => 'Save reply', 'reply_help' => 'Shown to the guest on their order page.', 'hide' => 'Hide from menu', 'show' => 'Show on menu', 'hidden' => 'Hidden', 'order_ref' => 'Order :number',
    'public_note' => 'Visible on your menu', 'stars' => '{1} :count star|[2,*] :count of 5 stars',

    // Guest review form
    'rate_title' => 'How was it?', 'rate_sub' => 'Rate your order. It takes ten seconds.', 'rate_comment' => 'Anything to add? (optional)', 'rate_public' => 'Show my rating (with my first name) on the menu',
    'rate_send' => 'Send my rating', 'rate_thanks' => 'Thank you for your feedback!', 'rate_yours' => 'Your rating', 'rate_reply' => 'Reply from the restaurant',
    'review_error_closed' => 'Ratings are not open for this order.', 'review_error_done' => 'You already rated this order.', 'review_error_rating' => 'Choose between 1 and 5 stars.',
    'rating_on_menu' => ':average (:count)',

    // Loyalty & settings
    'loyalty_title' => 'Loyalty & reviews', 'loyalty_sub' => 'Reward regulars automatically and ask guests how it went.',
    'loyalty_section' => 'Loyalty reward', 'loyalty_enabled' => 'Reward regular guests', 'loyalty_enabled_help' => 'After every Nth completed order the guest earns a single-use promo code that only they can use. It appears on their order page and in an e-mail.',
    'loyalty_every' => 'Reward after every … completed orders', 'loyalty_type' => 'Reward', 'loyalty_value' => 'Value', 'loyalty_percent_hint' => 'Percent of the order', 'loyalty_fixed_hint' => 'Amount in your currency', 'loyalty_valid' => 'Valid for (days)',
    'reviews_section' => 'Reviews', 'reviews_enabled' => 'Ask guests to rate completed orders', 'review_email' => 'Also ask by e-mail when the guest left an address', 'show_rating' => 'Show the average rating on the public menu',
    'messages_section' => 'Text messages', 'calling_code' => 'Country calling code', 'calling_code_help' => 'Used to send SMS and WhatsApp to guests who typed a local number, for example 90 for Turkey. Leave empty to message only numbers that start with +.',
    'email_section' => 'E-mail sending', 'daily_cap' => 'Campaign e-mails per day', 'daily_cap_help' => 'The platform allows at most :max per restaurant per day. Keeps your mail account in good standing.',
    'reward_earned' => 'You earned a reward!', 'reward_earned_text' => 'Thank you for ordering again and again. Use this code on your next order:', 'reward_valid_until' => 'Valid until :date, once, only for you.',
    'there' => 'there',

    // Campaigns
    'campaigns_title' => 'Campaigns', 'campaigns_sub' => 'E-mail the guests who agreed to hear from you. Every message has an unsubscribe link.',
    'new_campaign' => 'New campaign', 'edit_campaign' => 'Edit campaign', 'campaign_name' => 'Name', 'campaign_name_help' => 'Only you see this.', 'campaign_subject' => 'Subject', 'campaign_body' => 'Message',
    'campaign_body_help' => 'Plain text with simple formatting (**bold**, [link](https://…)). Use {{name}} and {{restaurant}} to personalise.', 'campaign_audience' => 'Who receives it', 'min_orders' => 'Only guests with at least … orders', 'min_orders_help' => '0 means everyone who agreed.',
    'audience_now' => '{0} Nobody can receive this yet.|{1} 1 guest will receive this.|[2,*] :count guests will receive this.', 'audience_note' => 'Only guests who ticked the e-mail box at checkout, have an address and have not unsubscribed.',
    'campaign_saved' => 'Campaign saved.', 'campaign_deleted' => 'Campaign deleted.', 'campaign_sending' => 'Your campaign is on its way. It can take a few minutes.',
    'campaign_error_sent' => 'This campaign was already sent.', 'campaign_error_empty' => 'Nobody can receive this campaign yet.',
    'status_draft' => 'Draft', 'status_sending' => 'Sending', 'status_sent' => 'Sent', 'campaigns_empty' => 'No campaigns yet', 'campaigns_empty_text' => 'Tell your regulars about a new dish or a quiet-day offer.',
    'sent_count' => 'Sent', 'skipped_count' => 'Not sent', 'recipients' => 'Recipients', 'send_test' => 'Send me a test', 'send_now' => 'Send campaign', 'send_confirm' => 'Send this campaign now? It cannot be undone.',
    'test_sent' => 'Test e-mail sent to :email.', 'today_room' => ':count more e-mails can go out today (limit :cap).', 'cap_note' => 'Guests beyond today\'s limit are skipped. Send again tomorrow with a new campaign.', 'sent_on' => 'Sent :date',
    'preview' => 'Preview', 'unsub_footer' => 'You get this because you agreed to receive offers from :name.', 'unsub_link' => 'Unsubscribe',

    // Unsubscribe page
    'unsub_title' => 'Unsubscribe', 'unsub_ask' => 'Stop e-mails from :name?', 'unsub_ask_text' => 'You will no longer receive offers and news. Order updates still reach you.', 'unsub_button' => 'Yes, unsubscribe me',
    'unsub_done' => 'You are unsubscribed', 'unsub_done_text' => 'You will not get marketing e-mails from :name any more.', 'unsub_already' => 'You are already unsubscribed from :name.',

    // Banners and pop-ups
    'banners_title' => 'Banners & pop-ups', 'banners_sub' => 'Show a new dish, an offer or a notice at the top of your menu.', 'new_banner' => 'New banner', 'edit_banner' => 'Edit banner',
    'banners_empty' => 'No banners yet', 'banners_empty_text' => 'Add a banner to tell guests about an offer, a new dish or opening hours.', 'banner_saved' => 'Banner saved.', 'banner_deleted' => 'Banner deleted.',
    'banner_title' => 'Headline', 'banner_text' => 'Short text', 'banner_link' => 'Link', 'banner_link_hint' => 'An https:// address, or #cat-12 to jump to a menu section. Optional.', 'banner_button' => 'Button text',
    'banner_starts' => 'Show from', 'banner_ends' => 'Show until', 'banner_popup' => 'Show as a pop-up (once per visit) instead of a banner', 'popup' => 'Pop-up', 'status_off' => 'Off',

    'review_url' => 'Public review link', 'review_url_hint' => 'For example your Google reviews link. Happy guests are invited to share their rating there.', 'review_min' => 'Offer it from (stars)', 'review_public_invite' => 'Glad you liked it! Would you share that on Google too?', 'review_public_button' => 'Write a public review',

    'ai_assistant' => 'Menu assistant on the guest menu (AI)', 'ai_assistant_hint' => 'Guests can ask about dishes, diets and allergens. It answers only from your menu, and every answer uses one AI credit.',

    // Growth: channels, segments, happy hour, gift cards, NPS, pixels, link page, flyer, widget.
    'campaign_channel' => 'Channel', 'channel_email' => 'E-mail', 'channel_sms' => 'SMS', 'channel_whatsapp' => 'WhatsApp',
    'channel_help' => 'Sent to guests who agreed to marketing and left a phone number. Needs an SMS/WhatsApp provider in Settings, and uses your monthly message allowance. Max 600 characters; use {{name}} for the guest name.',
    'campaign_segment' => 'Audience', 'segment_all' => 'Everyone', 'segment_new' => 'New guests (0-1 orders)', 'segment_regulars' => 'Regulars (silver tier and up)', 'segment_vip' => 'VIP (gold tier)', 'segment_lapsed' => 'Lapsed guests',
    'sms_stop' => 'Stop messages: :url',
    'growth_section' => 'Tiers, surveys and autopilot', 'tier_silver' => 'Silver tier from (orders)', 'tier_gold' => 'Gold tier from (orders)', 'tier_help' => 'Tiers group guests for campaigns. They are counted from completed orders.',
    'nps_enabled' => 'Ask the 0-10 "would you recommend us" question with the rating',
    'autopilot_winback' => 'Autopilot: win back lapsed guests', 'autopilot_help' => 'Once a day, guests who agreed to marketing and have been away get a personal single-use code by e-mail (at most once every 90 days).',
    'autopilot_days' => 'Away for (days)', 'autopilot_percent' => 'Discount (%)',
    'pixels_section' => 'Ad tracking', 'pixels_help' => 'Paste only the ID. The scripts load on the guest menu and are skipped when the guest sends Do Not Track. Mention them in your privacy policy.',
    'pixel_meta' => 'Meta (Facebook) pixel ID', 'pixel_ga' => 'Google tag ID', 'pixel_tiktok' => 'TikTok pixel ID',
    'links_section' => 'Link page, flyer and website button', 'links_help' => 'Your link-in-bio page for social profiles: :url',
    'link_phone' => 'Phone', 'link_whatsapp' => 'WhatsApp number', 'link_whatsapp_hint' => 'Digits with country code, e.g. 905321112233', 'link_instagram' => 'Instagram URL', 'link_facebook' => 'Facebook URL', 'link_website' => 'Website URL',
    'link_label_menu' => 'View the menu & order', 'link_label_whatsapp' => 'Chat on WhatsApp', 'link_label_phone' => 'Call us', 'link_label_instagram' => 'Instagram', 'link_label_facebook' => 'Facebook', 'link_label_website' => 'Our website', 'link_label_review' => 'Leave a review',
    'flyer_open' => 'Printable flyer', 'flyer_print' => 'Print', 'flyer_scan' => 'Scan to see our menu and order', 'widget_open' => 'Website button', 'widget_button' => 'Order online',
    'widget_title' => 'Website button', 'widget_sub' => 'Let visitors of your own website open the menu without leaving the page.', 'widget_button_title' => 'Floating "Order online" button', 'widget_button_help' => 'Paste before the closing body tag of your website.',
    'widget_iframe_title' => 'Embed the menu in a page', 'widget_iframe_help' => 'Or show the whole menu inside a page.',
    'nps_question' => 'How likely are you to recommend us to a friend?', 'nps_low' => 'Not likely', 'nps_high' => 'Very likely',
    'nps_score' => 'Net Promoter Score', 'nps_breakdown' => ':p promoters, :n passive, :d detractors',
    'templates' => 'Start from a template', 'template_first_order' => 'Welcome 10%', 'template_weekend' => 'Weekend 15%', 'template_big_basket' => 'Big order bonus', 'template_flash' => 'Flash sale 20%',
    'pricing_title' => 'Happy hour', 'pricing_sub' => 'Automatic discounts for certain days and hours. They are applied by the server when the cart is priced; the best rule wins and rules never stack.',
    'rule_name' => 'Name', 'rule_name_ph' => 'Happy hour drinks', 'rule_percent' => 'Percent off', 'rule_from' => 'From', 'rule_to' => 'Until', 'rule_scope' => 'Applies to', 'rule_all_menu' => 'Whole menu',
    'rule_days' => 'Days', 'rule_days_help' => 'Leave all unchecked for every day.', 'rule_add' => 'Add rule', 'rule_saved' => 'Rule saved.', 'rule_deleted' => 'Rule deleted.', 'every_day' => 'Every day',
    'rules_empty' => 'No happy hour yet', 'rules_empty_text' => 'Add a rule such as 20% off drinks on weekdays from 16:00 to 18:00.', 'delete_rule_confirm' => 'Delete this rule?',
    'day_0' => 'Sun', 'day_1' => 'Mon', 'day_2' => 'Tue', 'day_3' => 'Wed', 'day_4' => 'Thu', 'day_5' => 'Fri', 'day_6' => 'Sat',
    'gifts_title' => 'Gift cards', 'gifts_sub' => 'Sell or give gift cards. Guests type the code at checkout and the balance is spent down.',
    'gift_amount' => 'Amount', 'gift_valid_days' => 'Valid for (days)', 'gift_valid_help' => 'Empty = never expires.', 'gift_email' => 'Send to (e-mail)', 'gift_email_help' => 'Optional. The recipient gets the code by e-mail.', 'gift_note' => 'Message',
    'gift_issue' => 'Issue gift card', 'gift_issued' => 'Gift card :code created.', 'gifts_empty' => 'No gift cards yet', 'gifts_empty_text' => 'Issue one above and hand the code to the buyer.',
    'gift_balance' => 'Balance :balance of :initial', 'gift_expires' => 'expires :date',
    'weekly_digest' => 'E-mail me a summary of last week every Monday',
    'privacy_section' => 'Privacy', 'retention_months' => 'Erase guest personal data after (months)',
    'retention_help' => '0 keeps everything. Otherwise, guests who have not ordered for this long are deleted and their name, phone, e-mail and address are removed from old orders. The orders themselves (amounts, dishes) stay for your books.',
    'segment_birthday' => 'Birthday this month', 'autopilot_birthday' => 'Autopilot: birthday treat', 'birthday_percent' => 'Birthday discount (%)',
    'segments_title' => 'Segments', 'segments_sub' => 'Save an audience once and pick it in any campaign. A guest must match every rule you fill in.',
    'segment_name' => 'Name', 'segment_name_ph' => 'Regulars who went quiet', 'segment_rules_help' => 'Leave a rule empty to ignore it. Only guests who agreed to marketing are ever contacted.', 'segment_save' => 'Save segment',
    'rule_min_orders' => 'At least this many orders', 'rule_max_orders' => 'At most this many orders', 'rule_min_spent' => 'Spent at least', 'rule_active_within' => 'Ordered within the last (days)', 'rule_inactive_for' => 'Not ordered for (days)', 'rule_joined_within' => 'New in the last (days)',
    'rule_birthday' => 'Birthday', 'rule_birthday_this' => 'This month', 'rule_birthday_next' => 'Next month', 'rule_tier' => 'Tier',
    'rule_value_this_month' => 'this month', 'rule_value_next_month' => 'next month', 'rule_value_bronze' => 'bronze', 'rule_value_silver' => 'silver', 'rule_value_gold' => 'gold',
    'rule_label_min_orders' => ':value+ orders', 'rule_label_max_orders' => 'up to :value orders', 'rule_label_min_spent' => 'spent :value+', 'rule_label_active_within_days' => 'ordered in the last :value days', 'rule_label_inactive_for_days' => 'quiet for :value+ days',
    'rule_label_joined_within_days' => 'new in the last :value days', 'rule_label_birthday' => 'birthday :value', 'rule_label_tier' => 'tier :value',
    'segment_everyone' => 'Everyone', 'segment_reach' => '{0} nobody can be reached|{1} :count guest can be reached|[2,*] :count guests can be reached', 'segment_saved' => 'Segment saved.', 'segment_deleted' => 'Segment deleted.', 'segment_delete_confirm' => 'Delete this segment?', 'segments_empty' => 'No saved segments', 'segments_empty_text' => 'Create one above, then pick it when you write a campaign.',
];
