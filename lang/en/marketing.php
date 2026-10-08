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
];
