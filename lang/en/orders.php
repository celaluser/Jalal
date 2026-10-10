<?php

return [
    // Errors shown to guests and staff
    'error_closed' => 'We are not taking online orders right now. Please order with the staff.',
    'error_type_unavailable' => 'This kind of order is not available.',
    'error_table_required' => 'Please choose your table.',
    'error_phone_required' => 'Please enter a phone number we can reach you on.',
    'error_name_required' => 'Please enter your name.',
    'error_address_required' => 'Please enter the delivery address.',
    'error_payment_unavailable' => 'Please choose how you will pay.',
    'error_cart_invalid' => 'Some items in your order are no longer available. Please check your order.',
    'error_below_minimum' => 'Your order is below the minimum for delivery.',
    'error_email_invalid' => 'Please enter a valid e-mail address, or leave it empty.',
    'error_promo_invalid' => 'This code is not valid.', 'error_promo_expired' => 'This code has expired or has not started yet.', 'error_promo_used' => 'This code has already been used up.', 'error_promo_min' => 'Your order is below the minimum for this code.',
    'error_out_of_stock' => 'Sorry, one of the dishes just ran out. Please check your order.',
    'error_branch_required' => 'Please choose a branch.', 'error_branch_closed' => 'This branch is closed right now.',
    'error_busy' => 'We are very busy right now. Please try again in a moment.',
    'error_invalid_transition' => 'This order cannot move to that step.',
    'error_cannot_pay' => 'This order cannot be marked as paid.',
    'error_cannot_cancel' => 'This order can no longer be cancelled.',
    'cancelled_by_guest' => 'Cancelled by the guest',
    'updated' => 'Order updated.',

    // Types, statuses, payment
    'type_dine_in' => 'Dine in', 'type_takeaway' => 'Takeaway', 'type_delivery' => 'Delivery',
    'status_new' => 'New', 'status_accepted' => 'Accepted', 'status_preparing' => 'Preparing', 'status_ready' => 'Ready', 'status_completed' => 'Completed', 'status_cancelled' => 'Cancelled',
    'pay_cash' => 'Cash', 'pay_card' => 'Card', 'paid' => 'Paid', 'unpaid' => 'Unpaid',

    // Staff board
    'board_title' => 'Live orders', 'board_sub' => 'New orders appear here by themselves and ring when they arrive.',
    'col_new' => 'New', 'col_kitchen' => 'In the kitchen', 'col_ready' => 'Ready', 'col_done' => 'Done',
    'empty_col' => 'Nothing here', 'open_count' => ':count open',
    'sound_on' => 'Sound on', 'sound_off' => 'Sound off', 'notify' => 'Show desktop alerts',
    'accepting' => 'Accepting orders', 'paused' => 'Orders paused', 'pause' => 'Pause orders', 'resume' => 'Resume orders',
    'paused_banner' => 'Online ordering is paused. Guests can browse but cannot order.',
    'new_order' => 'New order :number',
    'offline' => 'Connection lost. Retrying…',
    'action_accepted' => 'Accept', 'action_preparing' => 'Start preparing', 'action_ready' => 'Mark ready',
    'action_completed_dine_in' => 'Served', 'action_completed_takeaway' => 'Picked up', 'action_completed_delivery' => 'Delivered',
    'cancel' => 'Cancel order', 'cancel_reason' => 'Reason (optional)', 'cancel_confirm' => 'Cancel this order?',
    'print' => 'Print ticket', 'details' => 'Details', 'mark_paid' => 'Mark paid', 'late' => 'Late',
    'minutes_ago' => ':count min', 'just_now' => 'now',
    'note' => 'Note', 'table' => 'Table :name', 'guest' => 'Guest',
    'history' => 'History', 'items' => 'Items', 'total' => 'Total', 'subtotal' => 'Subtotal', 'service' => 'Service charge', 'delivery_fee' => 'Delivery fee', 'tax' => 'Tax', 'tax_included' => 'incl. tax',
    'placed_by_guest' => 'Placed by the guest', 'event_placed' => 'Order placed', 'event_payment' => 'Paid with :note',
    'customer' => 'Customer', 'phone' => 'Phone', 'address' => 'Address', 'source_qr' => 'QR menu', 'source_staff' => 'Staff', 'source_kiosk' => 'Kiosk', 'source_api' => 'API',
    'order_number' => 'Order :number', 'back' => 'Back to orders',

    // Settings
    'settings_title' => 'Ordering', 'settings_sub' => 'Choose what guests can order and how it is priced.',
    'sec_open' => 'Availability', 'sec_types' => 'Order types', 'sec_pricing' => 'Tax and fees', 'sec_payment' => 'Payment', 'sec_flow' => 'Order flow',
    'enabled' => 'Accept online orders', 'paused_message' => 'Message when paused (optional)',
    'dine_in' => 'Dine in (order from the table)', 'takeaway' => 'Takeaway (guest picks up)', 'delivery' => 'Delivery (to an address)',
    'dine_in_pick_table' => 'Let guests choose their table when they did not scan a table code', 'require_name' => 'Ask dine-in guests for their name',
    'tax_rate' => 'Tax rate (%)', 'prices_include_tax' => 'Menu prices already include tax', 'service_rate' => 'Service charge for dine-in (%)',
    'delivery_fee' => 'Delivery fee', 'delivery_min' => 'Minimum order for delivery',
    'setting_pay_cash' => 'Cash (at the counter or to the courier)', 'setting_pay_card' => 'Card (terminal at the counter or with the courier)',
    'payment_help' => 'Guests pay on the spot. Online card payment for orders arrives with a later update.',
    'auto_accept' => 'Accept new orders automatically', 'prep_minutes' => 'Typical preparation time (minutes)', 'allow_cancel' => 'Let guests cancel while the order is still new',
    'need_type' => 'Turn on at least one order type.', 'need_payment' => 'Turn on at least one way to pay.',

    // Guest checkout and tracking
    'checkout' => 'Checkout', 'how' => 'How would you like it?', 'your_details' => 'Your details',
    'name' => 'Your name', 'phone_label' => 'Phone', 'email' => 'E-mail', 'email_label' => 'E-mail (optional)', 'email_help' => 'We e-mail you when your order is received and when it is ready.', 'address_label' => 'Delivery address', 'note_label' => 'Note for the restaurant', 'choose_table' => 'Your table',
    'pay_how' => 'Pay with', 'pay_on_spot_dine_in' => 'You pay at the table or the counter.', 'pay_on_spot_takeaway' => 'You pay when you pick it up.', 'pay_on_spot_delivery' => 'You pay the courier.',
    'minimum_note' => 'Minimum order for delivery: :amount', 'place' => 'Place order', 'placing' => 'Placing your order…',
    'order_placed' => 'Order placed!', 'your_number' => 'Your order number', 'we_got_it' => 'We got your order and will start soon.',
    'track_title' => 'Order :number', 'track_sub' => 'This page updates by itself. Keep it open.',
    'step_new' => 'Received', 'step_accepted' => 'Accepted', 'step_preparing' => 'Being prepared', 'step_ready' => 'Ready', 'step_completed_dine_in' => 'Served', 'step_completed_takeaway' => 'Picked up', 'step_completed_delivery' => 'Delivered',
    'msg_new' => 'Waiting for the restaurant to confirm.', 'msg_accepted' => 'The restaurant accepted your order.', 'msg_preparing' => 'Your food is being prepared.',
    'msg_ready_dine_in' => 'Ready! It is on its way to your table.', 'msg_ready_takeaway' => 'Ready! Come and pick it up.', 'msg_ready_delivery' => 'Ready! The courier is on the way.',
    'msg_completed' => 'Enjoy your meal!', 'msg_cancelled' => 'This order was cancelled.',
    'estimated' => 'Ready in about :count min', 'cancel_mine' => 'Cancel my order', 'cancel_mine_confirm' => 'Cancel your order?', 'order_more' => 'Order something else', 'back_to_menu' => 'Back to the menu',
    'your_items' => 'Your order', 'paid_badge' => 'Paid',

    // Staff order entry
    'pos_title' => 'New order', 'pos_sub' => 'Take an order for a table, a walk-in or a phone call.', 'pos_button' => 'New order',
    'pos_search' => 'Search dishes', 'pos_all' => 'All', 'pos_empty_menu' => 'Nothing on the menu yet.', 'pos_no_results' => 'No dish matches your search.',
    'pos_sold_out' => 'Sold out', 'pos_add' => 'Add', 'pos_choose' => 'Choose options', 'pos_required' => 'Required', 'pos_optional' => 'Optional', 'pos_up_to' => 'Up to :count',
    'pos_add_to_order' => 'Add to order', 'pos_note' => 'Note for the kitchen', 'pos_cart' => 'Order', 'pos_cart_empty' => 'Tap a dish to add it.', 'pos_clear' => 'Clear',
    'pos_table' => 'Table', 'pos_choose_table' => 'Choose a table', 'pos_no_tables' => 'No tables yet. Add tables first, or use takeaway.', 'pos_customer_name' => 'Name (optional)', 'pos_customer_phone' => 'Phone (optional)', 'pos_address' => 'Delivery address',
    'pos_order_note' => 'Order note (optional)', 'pos_paid_now' => 'Paid now', 'pos_pay_with' => 'Paid with', 'pos_send' => 'Send to kitchen', 'pos_sending' => 'Sending…', 'pos_total' => 'Total (before fees)',
    'pos_items' => '{1} 1 item|[2,*] :count items', 'pos_view_order' => 'View order', 'pos_sent' => 'Order :number sent to the kitchen.', 'pos_another' => 'Take another order', 'pos_failed' => 'The order could not be sent. Check your connection and try again.',
    'pos_fees_note' => 'Service charge and tax are added when the order is placed.',

    // New order types, pre-orders, group tab, requests, batching, notifications
    'type_curbside' => 'Curbside pickup', 'type_room_service' => 'Room service',
    'action_completed_curbside' => 'Handed to car', 'action_completed_room_service' => 'Delivered to room',
    'pay_on_spot_curbside' => 'Pay when we bring it out to your car.', 'pay_on_spot_room_service' => 'Pay when it arrives at your room.',
    'vehicle' => 'Car', 'vehicle_label' => 'Your car (colour, model or plate)', 'room' => 'Room', 'room_label' => 'Room number',
    'error_vehicle_required' => 'Please tell us which car to bring your order to.', 'error_room_required' => 'Please enter your room number.',
    'error_too_many_items' => 'That is more items than we can take in one order. Please split it into two orders.', 'error_schedule_invalid' => 'Please choose a time that is still possible.',
    'schedule_when' => 'When?', 'schedule_asap' => 'As soon as possible', 'schedule_later' => 'Later', 'scheduled_for' => 'For :time', 'schedule_day_today' => 'Today', 'schedule_day_tomorrow' => 'Tomorrow',
    'packaging' => 'Packaging', 'wait_now' => 'About :minutes min right now', 'max_items_note' => 'Up to :count items per order.', 'max_items_over' => 'Too many items for one order (max :count).',
    'notify_me' => 'Tell me when it is ready', 'notify_none' => 'No message', 'notify_sms' => 'By text message', 'notify_whatsapp' => 'By WhatsApp', 'notify_push' => 'Notification on this device',
    'notify_ready_dine_in' => ':restaurant: your order :number is ready.', 'notify_ready_takeaway' => ':restaurant: your order :number is ready to collect.', 'notify_ready_delivery' => ':restaurant: your order :number is ready and will leave soon.',
    'notify_ready_curbside' => ':restaurant: your order :number is ready, we are bringing it to your car.', 'notify_ready_room_service' => ':restaurant: your order :number is ready and on its way to your room.', 'notify_on_the_way' => ':restaurant: your order :number is on the way!',
    'msg_on_the_way' => 'Your order is on its way to you!', 'on_the_way' => 'On the way', 'dispatch' => 'Out for delivery', 'shared_tab' => 'Shared bill',
    'requests' => 'Guest requests', 'request_waiter' => 'Needs the waiter', 'request_bill' => 'Wants the bill', 'request_water' => 'Wants water', 'request_other' => 'Needs help', 'request_done' => 'Done',
    'request_sent' => 'Sent. Someone will be with you shortly.', 'call_waiter' => 'Call the waiter', 'ask_bill' => 'Ask for the bill', 'ask_water' => 'Water, please',
    'tab_title' => 'Table bill', 'tab_text' => 'Everything ordered at this table that is not paid yet.', 'tab_empty' => 'Nothing to pay yet.', 'tab_total' => 'Total for the table',
    'batch_title' => 'Prep list', 'batch_sub' => 'Everything still to make, added up across orders.', 'batch_summary' => '{0} No open orders|{1} From 1 open order|[2,*] From :count open orders',
    'batch_empty' => 'Nothing to prepare', 'batch_empty_text' => 'New orders show up here by themselves.', 'station' => 'Station', 'all_stations' => 'All stations',
    'order_again' => 'Order again', 'reorder_unavailable' => 'Your last order is in the cart. Check it before you order: dishes that are gone were left out.',
    'enable_push' => 'Notify me on this device', 'push_on' => 'You will get a notification when it is ready.', 'push_denied' => 'Notifications are blocked in your browser.',

    // Settings screen
    'curbside' => 'Curbside pickup (guest waits in the car)', 'room_service' => 'Room service (hotel guests order to their room)',
    'sec_packaging' => 'Packaging', 'packaging_help' => 'Added to takeaway, delivery and curbside orders.', 'packaging_fee' => 'Fee per order', 'packaging_per_item' => 'Fee per item',
    'wait_per_order' => 'Extra minutes per open order', 'wait_per_order_hint' => 'Makes the waiting time grow when the kitchen is busy. 0 keeps it fixed.',
    'max_items' => 'Most items in one order', 'max_items_hint' => '0 means no limit. Staff are not limited.', 'stations' => 'Preparation stations', 'stations_hint' => 'For example: Kitchen, Bar, Dessert. Each dish can be assigned to one.',
    'sec_schedule' => 'Pre-orders', 'schedule_help' => 'Let guests order for later today or in the next few days.', 'schedule_orders' => 'Allow ordering for a later time', 'schedule_lead' => 'Earliest, in minutes from now', 'schedule_days' => 'How many days ahead',
    'sec_notify' => 'Tell guests when it is ready', 'notify_help' => 'Guests choose at checkout. Text messages and WhatsApp use your messaging provider and your plan’s monthly allowance.',
    'notify_push_setting' => 'Browser notification on the guest’s phone', 'notify_sms_setting' => 'Text message (SMS)', 'notify_whatsapp_setting' => 'WhatsApp',

    // Paying online, tips, split bills, refunds
    'pay_online' => 'Pay online now', 'pay_on_spot_online' => 'You will be taken to a secure page to pay.', 'pay_now' => 'Pay now', 'pay_what' => 'What to pay', 'pay_full' => 'The whole bill', 'pay_tab' => 'The whole table', 'pay_split' => 'My share, split equally between', 'pay_people' => 'People',
    'pay_custom' => 'Other amount', 'pay_with' => 'Pay with', 'pay_amount' => 'Amount', 'pay_button' => 'Pay', 'tip' => 'Tip', 'no_tip' => 'No tip', 'pay_failed' => 'Could not start the payment. Please try again or pay at the counter.',
    'pay_thanks' => 'Thank you, your payment was received.', 'pay_pending' => 'We are waiting for your bank to confirm the payment. This page updates by itself.',
    'error_payment_failed' => 'The payment page could not be opened. Please try again or pay at the counter.', 'error_invalid_amount' => 'That amount cannot be paid.', 'error_refund_failed' => 'The payment provider refused the refund. Refund it in their dashboard instead.',

    'payments' => 'Payments', 'paid_so_far' => 'Paid so far', 'refunded' => 'Refunded', 'still_owed' => 'Still to pay', 'payment_paid' => 'Paid', 'payment_pending' => 'Waiting', 'payment_failed' => 'Failed',
    'take_payment' => 'Take payment', 'take_payment_help' => 'Leave the amount empty to take everything that is left. Enter less for a split or a part payment.', 'refund' => 'Refund', 'refund_amount' => 'Amount to refund', 'refund_reason' => 'Reason', 'refund_confirm' => 'Give this money back?',
    'event_refund' => 'Refund :note', 'refunded_via_gateway' => 'Refunded through the payment provider.', 'refunded_manually' => 'Refund recorded. Hand the money back, or refund it in your payment provider’s dashboard.',

    'gateways_title' => 'Online payments', 'gateways_sub' => 'Connect your own payment account so guests can pay from their phone. The money goes straight to you.',
    'gateways_locked' => 'Your plan does not include online payments.', 'gateways_commission' => 'The platform keeps :percent% of every online payment (not of tips). It is added to your next subscription invoice.',
    'gateways_wallets' => 'Apple Pay, Google Pay and local wallets appear on the payment page when your gateway account has them switched on.', 'gateway_no_currency' => 'Does not support :currency', 'gateway_saved_incomplete' => ':name was saved but is not ready: fill in all required fields.',

    'receipt' => 'Receipt', 'receipt_thanks' => 'Thank you for your visit!', 'receipt_scan' => 'Scan for your digital receipt',

    'zone_label' => 'Delivery area', 'zone_choose' => 'Choose your area', 'zone_min' => 'min.', 'error_zone_required' => 'Please choose your delivery area.',

    'zones_title' => 'Delivery areas', 'zones_sub' => 'Give each area its own fee and minimum order. Guests pick their area at checkout. With no areas, the single fee from Ordering settings is used.', 'zone_add' => 'Add area', 'zone_name' => 'Area name',
    'zones_empty' => 'No areas yet', 'zones_empty_text' => 'Add areas such as “Old town” or “Suburbs” to charge different fees.', 'zone_saved' => 'Delivery area saved.', 'zone_deleted' => 'Delivery area deleted.', 'zone_eta' => 'Extra minutes', 'zone_active' => 'On',
    'courier_title' => 'My deliveries', 'courier_sub' => 'Orders ready to go out, and the ones you picked.', 'courier_mine' => 'On my list', 'courier_none' => 'Nothing on your list right now.', 'courier_coming' => '{1} 1 more is still being prepared.|[2,*] :count more are still being prepared.',
    'courier_open' => 'Ready, nobody has them yet', 'courier_nothing_open' => 'No unassigned deliveries.', 'courier_take' => 'Take it', 'courier_map' => 'Map', 'courier_collect' => 'Collect :amount', 'courier_cash' => 'Delivered · cash received', 'courier_card' => 'Delivered · paid by card', 'courier_delivered' => 'Delivered',
    'delivered_done' => 'Delivered. Thank you!', 'error_courier_invalid' => 'That person cannot deliver.', 'courier' => 'Courier', 'courier_assign' => 'Assign courier', 'courier_unassigned' => 'Nobody yet',

    'discount' => 'Discount', 'discount_help' => 'A discount from you, on top of any promo code. Enter 0 to remove it.', 'discount_reason' => 'Reason (optional)', 'discount_apply' => 'Apply', 'discount_current' => 'Current staff discount: :amount',
    'event_discount' => 'Discount :note', 'error_cannot_discount' => 'This order is already settled, so it cannot be discounted any more.',
    'close_table' => 'Close the table', 'close_table_help' => 'Pays every open order of this table in one go.', 'table_closed' => '{0} Nothing left to pay at this table.|{1} Table closed: 1 order paid.|[2,*] Table closed: :count orders paid.',

    'map_add_order' => 'Add an order',

    'history_title' => 'Order history', 'history_sub' => 'Every order, with filters. Export what you see to a spreadsheet.', 'history_search' => 'Number, name, phone or table', 'history_from' => 'From', 'history_to' => 'To',
    'history_status' => 'Status', 'history_type' => 'Type', 'history_paid' => 'Payment', 'history_source' => 'Taken by', 'history_filter' => 'Filter', 'history_when' => 'When',
    'history_summary' => '{0} No orders match.|{1} 1 order · :sum (tips :tips)|[2,*] :count orders · :sum (tips :tips)', 'history_empty' => 'No orders found', 'history_empty_text' => 'Try other dates or clear the filters.',

    'shifts_title' => 'Cash shifts', 'shifts_sub' => 'Count the cash drawer when you start and when you finish, and see if it adds up.', 'shift_start' => 'Start your shift', 'shift_start_help' => 'Count the cash in the drawer and enter it.', 'shift_opening' => 'Cash in the drawer at the start', 'shift_open' => 'Open the shift',
    'shift_running' => 'Shift running since :time', 'shift_cash_refunds' => 'Cash refunds', 'shift_expected' => 'Cash that should be there', 'shift_counted' => 'Cash you counted', 'shift_note' => 'Note (optional)', 'shift_close' => 'Close the shift',
    'shift_already_open' => 'You already have a shift open.', 'shift_opened' => 'Shift opened.', 'shift_closed' => 'Shift closed.', 'shifts_open_others' => 'Open now', 'shifts_history' => 'Closed shifts', 'shifts_none' => 'No closed shifts yet.', 'shift_who' => 'Cashier', 'shift_difference' => 'Difference',

    'unaccepted_alert' => ':count new order(s) waiting for more than :minutes minutes. Someone please accept them!',
    'kds_title' => 'Kitchen display', 'kds_all' => 'All stations', 'kds_start' => 'Start', 'kds_ready' => 'Ready', 'kds_station_done' => 'Done: :station', 'kds_col_new' => 'To start', 'kds_col_cooking' => 'Cooking', 'kds_col_ready' => 'Ready', 'kds_fullscreen' => 'Full screen', 'kds_empty' => 'All clear',

    'sec_alerts' => 'Orders nobody picks up', 'alerts_help' => 'The board rings again and shows a red warning, and you can be e-mailed.', 'alert_unaccepted' => 'Warn on the board after (minutes)', 'escalate_minutes' => 'E-mail the owner after (minutes)', 'escalate_hint' => '0 switches the e-mail off.',
    'sec_print' => 'Thermal printer', 'print_help' => 'Tickets are queued; a small bridge program next to the printer collects them. See docs/tools/print-bridge.php.', 'print_auto_kitchen' => 'Print a kitchen ticket for every new order', 'print_auto_receipt' => 'Print a receipt when an order is paid',
    'print_width' => 'Paper width', 'print_codepage' => 'Character set', 'print_url' => 'Bridge address (keep it secret)', 'print_bridge_help' => 'Run the bridge on a computer in the restaurant and give it this address and your printer.', 'print_token_renew' => 'Make a new secret address', 'print_token_confirm' => 'The current bridge stops working until you give it the new address. Continue?',
    'print_token_renewed' => 'New address created. Update your bridge.', 'print_queued' => 'Sent to the printer queue.', 'print_kitchen' => 'Kitchen ticket', 'print_receipt' => 'Receipt',

    'staff_app' => 'Staff',
    'error_plan_limit' => 'We cannot take more online orders this month. Please order with the staff.',
];
